<?php
namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Models\EmailTemplate;
use App\Services\EmailParserService;
use App\Support\CampaignReminderSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DispatchCampaignReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $campaignId;
    protected string $reminderType;
    protected string $reminderDate = '';

    public function __construct(
        int $campaignId,
        string $reminderType,
        ?string $reminderDate = null
    ) {
        $this->campaignId   = $campaignId;
        $this->reminderType = $reminderType;
        $this->reminderDate = $reminderDate ?? '';
    }

    /**
     * Build the tenant's claim URL using config(), which remains available in
     * queued jobs after configuration is cached.
     */
    private function claimUrl(Campaign $campaign, ?string $claimToken): ?string
    {
        if (! $claimToken) {
            return null;
        }

        $base = rtrim((string) config('app.storefront_url'), '/');
        $base = preg_replace('#/marketplace/?$#i', '', $base);
        $slug = $campaign->company?->alias;

        if (! $base || ! $slug) {
            return null;
        }

        return "{$base}/marketplace/{$slug}/claim?token={$claimToken}";
    }

    private function getCompatibleTemplate(
        Campaign $campaign,
        ?int $templateId
    ): ?EmailTemplate {
        if (! $templateId) {
            return null;
        }

        $template = EmailTemplate::find($templateId);
        if (! $template
            || ! $template->is_active
            || ! $template->html_body
            || ($template->company_id !== null
                && (int) $template->company_id !== (int) $campaign->company_id)
            || $template->reward_type !== $campaign->reward_type) {
            return null;
        }

        return $template;
    }

    public function handle(): void
    {
        if (! in_array($this->reminderType, ['initial', 'expiry'], true)) {
            return;
        }

        $campaign = Campaign::with('company')->findOrFail($this->campaignId);
        if ($campaign->status !== 'active'
            || $campaign->distribution_type !== 'online'
            || ! in_array($campaign->reward_type, ['code', 'link'], true)) {
            return;
        }

        $config     = $campaign->config_json ?? [];
        $enabledKey = $this->reminderType === 'initial'
            ? 'send_initial_reminder'
            : 'send_expiry_reminder';
        $templateKey = $this->reminderType === 'initial'
            ? 'reminder_initial_template_id'
            : 'reminder_expiry_template_id';
        $markerColumn = $this->reminderType === 'initial'
            ? 'initial_reminded_at'
            : 'expiry_reminded_at';

        if (empty($config[$enabledKey])) {
            return;
        }

        $timezone      = config('app.timezone', 'UTC');
        $today         = now($timezone)->startOfDay();
        $latestDueDate = CampaignReminderSchedule::latestDueDate(
            $config,
            $this->reminderType,
            $today,
            $timezone
        );

        if (! $latestDueDate) {
            return;
        }

        // Only the newest due occurrence should send. Older queued jobs are
        // discarded after a newer date becomes due, preventing catch-up spam.
        if ($this->reminderDate && $this->reminderDate !== $latestDueDate) {
            return;
        }
        $reminderDate  = $this->reminderDate ?: $latestDueDate;
        $scheduledDate = Carbon::createFromFormat(
            'Y-m-d',
            $reminderDate,
            $timezone
        )->startOfDay();

        // Do not send a belated claim reminder after the campaign has expired.
        if ($campaign->expires_at && $campaign->expires_at->isPast()) {
            return;
        }

        $templateId = $config[$templateKey] ?? ($config['email_template_id'] ?? null);
        $template   = $this->getCompatibleTemplate($campaign, $templateId);

        // If an optional reminder-specific template was deactivated after
        // launch, fall back to the campaign's selected email template.
        if (! $template && ! empty($config['email_template_id'])) {
            $template = $this->getCompatibleTemplate(
                $campaign,
                (int) $config['email_template_id']
            );
        }

        if (! $template) {
            return;
        }

        $campaign->entitlements()
            ->where('is_claimed', false)
            ->where(function ($query) use ($markerColumn, $reminderDate) {
                $query->whereNull($markerColumn)
                    ->orWhereDate($markerColumn, '<', $reminderDate);
            })
            ->select('id')
            ->chunkById(250, function ($pendingEntitlements) use (
                $campaign,
                $template,
                $markerColumn,
                $scheduledDate
            ) {
                foreach ($pendingEntitlements as $pendingEntitlement) {
                    DB::transaction(function () use (
                        $pendingEntitlement,
                        $campaign,
                        $template,
                        $markerColumn,
                        $scheduledDate
                    ) {
                        // The row lock and per-type last-sent marker allow a
                        // new date while preventing duplicate sends for the
                        // same occurrence across overlapping queue jobs.
                        $entitlement = CampaignEntitlement::with('user.company')
                            ->whereKey($pendingEntitlement->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $entitlement || $entitlement->is_claimed) {
                            return;
                        }

                        $lastSentDate = $entitlement->{$markerColumn}
                            ? Carbon::parse($entitlement->{$markerColumn})->startOfDay()
                            : null;
                        if ($lastSentDate && $lastSentDate->gte($scheduledDate)) {
                            return;
                        }

                        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
                            return;
                        }

                        $user = $entitlement->user;
                        if (! $user || ! $user->email) {
                            return;
                        }

                        if (! $entitlement->claim_token) {
                            $entitlement->update(['claim_token' => Str::random(64)]);
                        }

                        $claimUrl = $this->claimUrl(
                            $campaign,
                            $entitlement->claim_token
                        ) ?? '#';

                        $payload = [
                            'first_name'    => $user->first_name,
                            'last_name'     => $user->last_name,
                            'company_name'  => $user->company?->name ?? $campaign->company?->name ?? 'Our Company',
                            'current_date'  => now()->format('F j, Y'),
                            'campaign_name' => $campaign->name,
                            'reward_value'  => $entitlement->reward_value,
                            'claim_link'    => $claimUrl,
                            'claim_code'    => $entitlement->claim_code ?? '',
                        ];

                        $htmlBody = EmailParserService::parse(
                            $template->html_body,
                            $payload
                        );
                        $baseSubject = EmailParserService::parse(
                            $template->subject,
                            $payload
                        );
                        $finalSubject = $this->reminderType === 'expiry'
                            ? "Final Reminder: {$baseSubject}"
                            : "Reminder: {$baseSubject}";

                        Mail::html($htmlBody, function ($message) use ($user, $finalSubject) {
                            $message->to($user->email)->subject($finalSubject);
                        });

                        $entitlement->update([
                            $markerColumn => now(),
                            'reminded_at' => now(),
                        ]);
                    });
                }
            });
    }
}

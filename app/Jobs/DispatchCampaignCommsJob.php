<?php
namespace App\Jobs;

use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Services\EmailParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DispatchCampaignCommsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
    }

    /**
     * Claim links must be built with config() — env() returns null inside
     * queued jobs once the config is cached — and must include the tenant
     * slug segment, because the storefront router is /{slug}/claim (deployed
     * under /marketplace/{slug}/claim). The old link ({base}/claim?token=…)
     * 404'd in every reward email.
     */
    private function claimUrl(Campaign $campaign, ?string $claimToken): ?string
    {
        if (! $claimToken) {
            return null;
        }

        $base = rtrim((string) config('app.storefront_url'), '/');

        // Guard against a STOREFRONT_URL that already ends in "/marketplace"
        // (a common .env mistake): appending our own "/marketplace/{slug}"
        // produced ".../marketplace/marketplace/{slug}/..." in every email.
        $base = preg_replace('#/marketplace/?$#i', '', $base);

        $slug = $campaign->company?->alias;

        if (! $base || ! $slug) {
            return null;
        }

        return "{$base}/marketplace/{$slug}/claim?token={$claimToken}";
    }

    public function handle(): void
    {
        $campaign = Campaign::with('entitlements.user.company', 'company')->findOrFail($this->campaignId);

        $config     = $campaign->config_json;
        $templateId = $config['email_template_id'] ?? null;

        if (! $templateId) {
            return;
        }

        $template = EmailTemplate::find($templateId);
        if (! $template
            || ! $template->is_active
            || ! $template->html_body
            || $template->reward_type !== $campaign->reward_type) {
            return;
        }

        $entitlements = $campaign->entitlements()
            ->when($campaign->reward_type === 'points', function ($query) {
                return $query->where('is_claimed', true);
            }, function ($query) {
                return $query->where('is_claimed', false);
            })
            ->get();

        foreach ($entitlements as $entitlement) {
            $user = $entitlement->user;
            if (! $user || ! $user->email) {
                continue;
            }

            // Backfill older points/code entitlements which pre-date landing
            // pages, so reminder/new campaign mails never contain a '#' CTA.
            if (! $entitlement->claim_token) {
                $entitlement->update(['claim_token' => Str::random(64)]);
            }

            $claimUrl = $this->claimUrl($campaign, $entitlement->claim_token);

            $payload = [
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'company_name'   => $user->company->name ?? 'Our Company',
                'current_date'   => now()->format('F j, Y'),
                'campaign_name'  => $campaign->name,
                'reward_value'   => $entitlement->reward_value,
                'points_awarded' => $entitlement->reward_value,
                'claim_link'     => $claimUrl ?? '#',
                'claim_code'     => $entitlement->claim_code ?? '',
            ];

            $htmlBody     = EmailParserService::parse($template->html_body, $payload);
            $finalSubject = EmailParserService::parse($template->subject, $payload);

            Mail::html($htmlBody, function ($message) use ($user, $finalSubject) {
                $message->to($user->email)
                    ->subject($finalSubject);
            });
        }
    }
}

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

class DispatchCampaignCommsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle(): void
    {
        $campaign = Campaign::with('entitlements.user.company')->findOrFail($this->campaignId);

        $config     = $campaign->config_json;
        $templateId = $config['email_template_id'] ?? null;

        if (! $templateId) {
            return;
        }

        $template = EmailTemplate::find($templateId);
        if (! $template || ! $template->html_body) {
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

            $claimUrl = null;
            if ($campaign->reward_type === 'link') {
                $claimUrl = rtrim(env('STOREFRONT_URL'), '/') . '/claim?token=' . $entitlement->claim_token;
            }

            $payload = [
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'company_name'  => $user->company->name ?? 'Our Company',
                'current_date'  => now()->format('F j, Y'),
                'campaign_name' => $campaign->name,
                'reward_value'  => $entitlement->reward_value,
                'claim_link'    => $claimUrl ?? '#',
                'claim_code'    => $entitlement->claim_code ?? '',
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

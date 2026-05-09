<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\EmailTemplate;
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
        $campaign = Campaign::with('entitlements.user')->findOrFail($this->campaignId);
        
        // Fetch the selected email template from config_json
        $config = $campaign->config_json;
        $templateId = $config['email_template_id'] ?? null;

        if (!$templateId) return;

        $template = EmailTemplate::find($templateId);
        if (!$template) return;

        $entitlements = $campaign->entitlements()->where('is_claimed', false)->get();

        foreach ($entitlements as $entitlement) {
            $user = $entitlement->user;
            if (!$user || !$user->email) continue;

            // Generate the dynamic link or code
            $claimUrl = null;
            if ($campaign->reward_type === 'link') {
                $claimUrl = config('app.frontend_url') . '/claim?token=' . $entitlement->claim_token;
            }

            // Replace dynamic variables in the HTML body
            $htmlBody = $template->html_body;
            $htmlBody = str_replace('{{ first_name }}', $user->first_name, $htmlBody);
            $htmlBody = str_replace('{{ reward_value }}', $entitlement->reward_value, $htmlBody);
            $htmlBody = str_replace('{{ claim_link }}', $claimUrl ?? '#', $htmlBody);
            $htmlBody = str_replace('{{ claim_code }}', $entitlement->claim_code ?? 'N/A', $htmlBody);

            // Send via your mail provider (SES/SMTP)
            // Mail::html($htmlBody, function ($message) use ($user, $template) {
            //     $message->to($user->email)
            //             ->subject($template->subject);
            // });
        }
    }
}
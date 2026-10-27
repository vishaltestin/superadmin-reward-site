<?php
namespace App\Console\Commands;

use App\Jobs\DispatchCampaignReminderJob;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Support\CampaignReminderSchedule;
use Illuminate\Console\Command;

class SendCampaignReminders extends Command
{
    protected $signature   = 'campaigns:send-reminders';
    protected $description = 'Dispatches due reminder emails for active code and link campaigns.';

    public function handle(): int
    {
        $this->info('Scanning for campaign reminders...');

        $timezone            = config('app.timezone', 'UTC');
        $today               = now($timezone)->startOfDay();
        $remindersDispatched = 0;

        Campaign::where('status', 'active')
            ->where('distribution_type', 'online')
            ->whereIn('reward_type', ['code', 'link'])
            ->chunkById(100, function ($campaigns) use (
                $timezone,
                $today,
                &$remindersDispatched
            ) {
                foreach ($campaigns as $campaign) {
                    $config = $campaign->config_json ?? [];

                    foreach ([
                        'initial' => [
                            'enabled'  => 'send_initial_reminder',
                            'template' => 'reminder_initial_template_id',
                            'marker'   => 'initial_reminded_at',
                        ],
                        'expiry'  => [
                            'enabled'  => 'send_expiry_reminder',
                            'template' => 'reminder_expiry_template_id',
                            'marker'   => 'expiry_reminded_at',
                        ],
                    ] as $type => $keys) {
                        if (empty($config[$keys['enabled']])) {
                            continue;
                        }

                        // New campaigns support multiple dates; old singular
                        // values are normalized by CampaignReminderSchedule.
                        $dueDate = CampaignReminderSchedule::latestDueDate(
                            $config,
                            $type,
                            $today,
                            $timezone
                        );
                        if (! $dueDate) {
                            continue;
                        }

                        $templateIds = array_filter([
                            $config[$keys['template']] ?? null,
                            $config['email_template_id'] ?? null,
                        ]);
                        $hasActiveTemplate = false;
                        foreach ($templateIds as $templateId) {
                            if (EmailTemplate::whereKey($templateId)
                                ->where('is_active', true)
                                ->exists()) {
                                $hasActiveTemplate = true;
                                break;
                            }
                        }

                        if (! $hasActiveTemplate) {
                            continue;
                        }

                        $hasPendingRecipients = $campaign->entitlements()
                            ->where('is_claimed', false)
                            ->where(function ($query) use ($keys, $dueDate) {
                                $query->whereNull($keys['marker'])
                                    ->orWhereDate($keys['marker'], '<', $dueDate);
                            })
                            ->exists();

                        if (! $hasPendingRecipients) {
                            continue;
                        }

                        DispatchCampaignReminderJob::dispatch(
                            $campaign->id,
                            $type,
                            $dueDate
                        );
                        $remindersDispatched++;
                        $this->info("Dispatched {$type} reminder ({$dueDate}) for Campaign ID: {$campaign->id}");
                    }
                }
            });

        $this->info("Finished. Dispatched {$remindersDispatched} reminder job(s).");

        return self::SUCCESS;
    }
}

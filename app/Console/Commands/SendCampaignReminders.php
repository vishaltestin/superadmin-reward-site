<?php
namespace App\Console\Commands;

use App\Jobs\DispatchCampaignReminderJob;
use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendCampaignReminders extends Command
{
    protected $signature   = 'campaigns:send-reminders';
    protected $description = 'Scans active campaigns and sends automated reminders based on their config_json schedules.';

    public function handle()
    {
        $this->info('Scanning for campaign reminders...');

        $campaigns = Campaign::where('status', 'active')
            ->where('distribution_type', 'online')
            ->get();

        $remindersDispatched = 0;

        foreach ($campaigns as $campaign) {
            $config           = $campaign->config_json ?? [];
            $dispatchReminder = false;
            $reminderType     = null;

            if (! empty($config['send_initial_reminder']) && ! empty($config['reminder_initial_date'])) {
                if (Carbon::parse($config['reminder_initial_date'])->isToday()) {
                    $dispatchReminder = true;
                    $reminderType     = 'initial';
                }
            }

            if (! empty($config['send_expiry_reminder']) && ! empty($config['reminder_expiry_date'])) {
                if (Carbon::parse($config['reminder_expiry_date'])->isToday()) {
                    $dispatchReminder = true;
                    $reminderType     = 'expiry';
                }
            }

            if ($dispatchReminder) {
                DispatchCampaignReminderJob::dispatch($campaign->id, $reminderType);
                $remindersDispatched++;
                $this->info("Dispatched {$reminderType} reminder for Campaign ID: {$campaign->id}");
            }
        }

        $this->info("Finished. Dispatched reminders for {$remindersDispatched} campaigns.");

        return self::SUCCESS;
    }
}

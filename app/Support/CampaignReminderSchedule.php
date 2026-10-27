<?php
namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

class CampaignReminderSchedule
{
    /**
     * Return unique configured dates for a reminder type. New campaigns use
     * the plural arrays; the singular fallback keeps older campaigns working.
     *
     * @return array<int, string>
     */
    public static function dates(array $config, string $type): array
    {
        $pluralKey = $type === 'initial'
            ? 'reminder_initial_dates'
            : 'reminder_expiry_dates';
        $legacyKey = $type === 'initial'
            ? 'reminder_initial_date'
            : 'reminder_expiry_date';

        if (array_key_exists($pluralKey, $config) && is_array($config[$pluralKey])) {
            $dates = $config[$pluralKey];
        } else {
            $dates = ! empty($config[$legacyKey]) ? [$config[$legacyKey]] : [];
        }

        $dates = array_filter($dates, function ($date): bool {
            return is_string($date)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
        });

        return array_values(array_unique($dates));
    }

    /**
     * Return only the latest due date. If a scheduler was unavailable for
     * several dates, recipients get one current reminder, not a catch-up burst.
     */
    public static function latestDueDate(
        array $config,
        string $type,
        Carbon $today,
        string $timezone
    ): ?string {
        $dueDates = [];

        foreach (self::dates($config, $type) as $date) {
            try {
                $scheduledDate = Carbon::createFromFormat(
                    'Y-m-d',
                    $date,
                    $timezone
                )->startOfDay();

                if ($scheduledDate->lte($today)) {
                    $dueDates[] = $date;
                }
            } catch (Throwable) {
                // Ignore malformed legacy config rather than stopping the
                // entire reminder scan for every other campaign.
                continue;
            }
        }

        if (empty($dueDates)) {
            return null;
        }

        sort($dueDates);

        return end($dueDates) ?: null;
    }
}

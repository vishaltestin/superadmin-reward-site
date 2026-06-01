<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function getCalendarEvents(Request $request)
    {
        $admin                 = $request->user();
        $year                  = (int) $request->query('year', date('Y'));
        $month                 = (int) $request->query('month', date('m'));
        $requestedVerticalSlug = $request->query('vertical_slug');

        $allowedVerticals = collect();
        if ($admin->user_type === 'sub_admin') {
            $allowedVerticals = $admin->managedVerticals()->where('is_active', true)->get();
        } else {
            $allowedVerticals = $admin->company->verticals()->where('is_active', true)->get();
        }

        if ($requestedVerticalSlug) {
            $allowedVerticals = $allowedVerticals->filter(function ($v) use ($requestedVerticalSlug) {
                return $v->slug === $requestedVerticalSlug;
            });
        }

        $allowedVerticalIds = $allowedVerticals->pluck('id')->toArray();
        $verticalSlugMap    = $allowedVerticals->pluck('slug', 'id')->toArray();

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        $rewardees = User::where('company_id', $admin->company_id)
            ->where('user_type', 'rewardee')
            ->where('is_active', true)
            ->whereHas('rewardeeProfile', function ($query) use ($allowedVerticalIds) {
                $query->whereIn('vertical_id', $allowedVerticalIds);
            })
            ->with('rewardeeProfile')
            ->get();

        $dbEvents    = Event::whereIn('vertical_id', $allowedVerticalIds)->get();
        $eventLookup = [];

        foreach ($dbEvents as $dbEvent) {
            $eventLookup[$dbEvent->vertical_id][$dbEvent->title] = $dbEvent->id;
        }

        $dateFieldMappings = [
            'birth_date'                  => ['type' => 'birthday', 'db_titles' => ['Client Birthday', 'Channel Partner Birthday']],
            'anniversary_date'            => ['type' => 'anniversary', 'db_titles' => ['Employee Anniversary', 'Client Anniversary', 'Anniversary']],
            'date_joining'                => ['type' => 'joining', 'db_titles' => ['New Joinee']],
            'date_onboarding'             => ['type' => 'onboarding', 'db_titles' => ['New Channel Partner onboarding success']],
            'retirement_day'              => ['type' => 'retirement', 'db_titles' => ['Employee Retirement / Last day at work']],
            'date_of_purchase'            => ['type' => 'purchase', 'db_titles' => ['Congrats on New purchase']],
            'site_visit'                  => ['type' => 'site-visit', 'db_titles' => ['Site visit Booking', 'Site visit Success']],
            'test_drive_date'             => ['type' => 'test-drive', 'db_titles' => ['Test Drive Success']],
            'service_due_date'            => ['type' => 'service', 'db_titles' => ['Service Due']],
            'insurance_due_date'          => ['type' => 'insurance', 'db_titles' => ['Insurance Due']],
            'any_specific_milestone_date' => ['type' => 'milestone', 'db_titles' => []],
            'registry_due_date'           => ['type' => 'registry', 'db_titles' => []],
            'possession_due_date'         => ['type' => 'possession', 'db_titles' => []],
        ];

        $recurringEvents = ['birthday', 'anniversary', 'joining'];

        $events = [];

        foreach ($rewardees as $user) {
            $profile = $user->rewardeeProfile;
            if (! $profile || empty($profile->vertical_data)) {
                continue;
            }

            $verticalData = $profile->vertical_data;
            $verticalId   = $profile->vertical_id;
            $verticalSlug = $verticalSlugMap[$verticalId] ?? 'unknown';

            foreach ($dateFieldMappings as $jsonKey => $mapping) {
                $eventType = $mapping['type'];
                $dbTitles  = $mapping['db_titles'];

                if (! empty($verticalData[$jsonKey])) {
                    try {
                        $originalDate = Carbon::parse($verticalData[$jsonKey]);
                        $eventDate    = $originalDate->copy();

                        if (in_array($eventType, $recurringEvents)) {
                            if ($year >= $originalDate->year) {
                                $eventDate->year($year);
                            } else {
                                continue;
                            }
                        }

                        if ($eventDate->year == $year && $eventDate->month == $month) {

                            $originalEventId = null;
                            foreach ($dbTitles as $title) {
                                if (isset($eventLookup[$verticalId][$title])) {
                                    $originalEventId = $eventLookup[$verticalId][$title];
                                    break;
                                }
                            }

                            $yearsSince  = $year - $originalDate->year;
                            $titlePrefix = ($yearsSince > 0 && in_array($eventType, $recurringEvents))
                                ? " (Year {$yearsSince})"
                                : "";

                            $events[] = [
                                'id' => "{$user->id}-{$eventType}",
                                'title' => "{$user->first_name} {$user->last_name}'s " . ucfirst(str_replace('-', ' ', $eventType)) . $titlePrefix,
                                'date'          => $eventDate->format('Y-m-d'),
                                'type'          => $eventType,
                                'vertical_slug' => $verticalSlug,
                                'vertical_id'   => $verticalId,
                                'event_id'      => $originalEventId,
                                'user_id'       => $user->id,
                                'first_name'    => $user->first_name,
                                'last_name'     => $user->last_name,
                            ];
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        usort($events, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return response()->json(['data' => $events]);
    }
}

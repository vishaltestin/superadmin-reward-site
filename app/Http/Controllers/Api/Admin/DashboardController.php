<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event; 
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function getCalendarEvents(Request $request)
    {
        $admin = $request->user();
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('m'));
        $requestedVerticalSlug = $request->query('vertical_slug');

        // 1. RBAC: Determine which verticals this admin is allowed to see
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
        $verticalSlugMap = $allowedVerticals->pluck('slug', 'id')->toArray();

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        // 2. Fetch Rewardees
        $rewardees = User::where('company_id', $admin->company_id)
            ->where('user_type', 'rewardee')
            ->where('is_active', true)
            ->whereHas('rewardeeProfile', function ($query) use ($allowedVerticalIds) {
                $query->whereIn('vertical_id', $allowedVerticalIds);
            })
            ->with('rewardeeProfile')
            ->get();

        // 3. Pre-fetch actual Database Events for these verticals
        $dbEvents = Event::whereIn('vertical_id', $allowedVerticalIds)->get();
        $eventLookup = [];
        
        // Fast lookup grouping: $eventLookup[vertical_id]['Exact DB Title'] = event_id
        foreach ($dbEvents as $dbEvent) {
            $eventLookup[$dbEvent->vertical_id][$dbEvent->title] = $dbEvent->id;
        }

        // 4. Advanced Mapping: JSON Key -> Frontend Type & DB Titles
        $dateFieldMappings = [
            'birth_date' => ['type' => 'birthday', 'db_titles' => ['Client Birthday', 'Channel Partner Birthday']],
            'anniversary_date' => ['type' => 'anniversary', 'db_titles' => ['Employee Anniversary', 'Client Anniversary', 'Anniversary']],
            'date_joining' => ['type' => 'joining', 'db_titles' => ['New Joinee']],
            'date_onboarding' => ['type' => 'onboarding', 'db_titles' => ['New Channel Partner onboarding success']],
            'retirement_day' => ['type' => 'retirement', 'db_titles' => ['Employee Retirement / Last day at work']],
            'date_of_purchase' => ['type' => 'purchase', 'db_titles' => ['Congrats on New purchase']],
            'site_visit' => ['type' => 'site-visit', 'db_titles' => ['Site visit Booking', 'Site visit Success']],
            'test_drive_date' => ['type' => 'test-drive', 'db_titles' => ['Test Drive Success']],
            'service_due_date' => ['type' => 'service', 'db_titles' => ['Service Due']],
            'insurance_due_date' => ['type' => 'insurance', 'db_titles' => ['Insurance Due']],
            'any_specific_milestone_date' => ['type' => 'milestone', 'db_titles' => []],
            'registry_due_date' => ['type' => 'registry', 'db_titles' => []],
            'possession_due_date' => ['type' => 'possession', 'db_titles' => []],
        ];

        // FIX: Explicitly define which events happen every year.
        // Joining is added here so "Work Anniversaries" show up every year just like birthdays.
        $recurringEvents = ['birthday', 'anniversary', 'joining'];

        $events = [];

        // 5. Flatten the JSON data into Calendar Events
        foreach ($rewardees as $user) {
            $profile = $user->rewardeeProfile;
            if (!$profile || empty($profile->vertical_data)) continue;

            $verticalData = $profile->vertical_data;
            $verticalId = $profile->vertical_id; 
            $verticalSlug = $verticalSlugMap[$verticalId] ?? 'unknown';

            foreach ($dateFieldMappings as $jsonKey => $mapping) {
                $eventType = $mapping['type'];
                $dbTitles = $mapping['db_titles'];

                if (!empty($verticalData[$jsonKey])) {
                    try {
                        // Keep a copy of the original date so we can check the original year later
                        $originalDate = Carbon::parse($verticalData[$jsonKey]);
                        $eventDate = $originalDate->copy();
                        
                        // FIX: Handle Recurring Events (Birthdays, Anniversaries, Work Anniversaries)
                        if (in_array($eventType, $recurringEvents)) {
                            // Logic Check: You cannot celebrate an anniversary BEFORE the year you joined.
                            // If they joined in 2026, we should NOT show their anniversary if the admin is looking at the 2025 calendar.
                            if ($year >= $originalDate->year) {
                                // Shift the event's year to the year the admin is currently viewing on the calendar
                                $eventDate->year($year);
                            } else {
                                // Skip this event, it hasn't happened yet in history
                                continue; 
                            }
                        }

                        // Finally, check if this event falls into the exact Month and Year the UI is requesting
                        if ($eventDate->year == $year && $eventDate->month == $month) {
                            
                            // Find the original DB Event ID by checking our known titles
                            $originalEventId = null;
                            foreach ($dbTitles as $title) {
                                if (isset($eventLookup[$verticalId][$title])) {
                                    $originalEventId = $eventLookup[$verticalId][$title];
                                    break;
                                }
                            }

                            // Optional: Calculate which anniversary number this is (e.g., "3rd Anniversary")
                            $yearsSince = $year - $originalDate->year;
                            $titlePrefix = ($yearsSince > 0 && in_array($eventType, $recurringEvents)) 
                                ? " (Year {$yearsSince})" 
                                : "";

                            $events[] = [
                                'id' => "{$user->id}-{$eventType}",
                                'title' => "{$user->first_name} {$user->last_name}'s " . ucfirst(str_replace('-', ' ', $eventType)) . $titlePrefix,
                                'date' => $eventDate->format('Y-m-d'),
                                'type' => $eventType,
                                'vertical_slug' => $verticalSlug,
                                'vertical_id' => $verticalId, 
                                'event_id' => $originalEventId, 
                                'user_id' => $user->id,
                                'first_name' => $user->first_name,
                                'last_name' => $user->last_name,
                            ];
                        }
                    } catch (\Exception $e) {
                        continue; // Skip silently if the date format in JSON is corrupted
                    }
                }
            }
        }

        // Sort events chronologically so they appear in order on the frontend list view
        usort($events, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return response()->json(['data' => $events]);
    }
}
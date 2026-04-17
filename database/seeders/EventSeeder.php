<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Your exact data structure mapped to your Vertical IDs (1 through 5)
        $eventData = [
            // 1. Internal Employees (Direct events, no groups)
            1 => [
                'New Joinee', 'Employee Anniversary', 'Employee Retirement / Last day at work',
                'Employee Referrals', 'Employee Survey', 'Employee Testimonial',
                'Star Employee of Month', 'Festive Gifting to employees', 'Special Days',
                'Annual Sports Day', 'Shifting to New Office', 'Building Sales Pipeline',
                'On Target Completion', 'Course Completion Reward', 'Good job'
            ],
            
            // 2. External Client (Grouped by Marketing and Sales)
            2 => [
                'Marketing' => [
                    'Digital Campaign - Lead Gen', 'Annual Event', 'Sponsorship Event / Exhibition', 
                    'Customer Roundtable Guests', 'Webinar Attendees', 'Podcast Guests', 
                    'Client Survey', 'Client Testimonial'
                ],
                'Sales' => [
                    'Client Birthday', 'Client Anniversary', 'Improve CX / CLS Score', 
                    'Client Survey', 'Client Testimonial', 'Contract Sign up Success', 
                    'Client Referrals', 'Festive Gifting to clients'
                ]
            ],
            
            // 3. Channel Partners (Direct events, no groups)
            3 => [
                'Channel Partner Birthday', 'Anniversary', 'New Channel Partner onboarding success',
                'Channel Partner Meetup Event', 'Client Won', 'Festive Gifting to Channel Partner'
            ],
            
            // 4. Auto - Dealers (Grouped)
            4 => [
                'Sales' => [
                    'Showroom Visit', 'Test Drive Success', 'Congrats on New purchase', 
                    'Service Due', 'Insurance Due', 'Client Birthday', 'Client Anniversary', 
                    'Client Referrals', 'Festive Gifting to clients'
                ],
                'Marketing' => [
                    'Digital Campaign - Lead Gen', 'Test Drive Camp', 'New Model Launch Event', 
                    'Annual Event', 'Sponsorship Event / Exhibition', 'Customer Roundtable Guests', 
                    'Webinar Attendees', 'Podcast Guests', 'Client Survey', 'Client Testimonial'
                ]
            ],
            
            // 5. Real Estate (Grouped)
            5 => [
                'Sales' => [
                    'Site visit Booking', 'Site visit Success', 'Congrats on New purchase', 
                    'Client Referrals', 'Client Birthday', 'Client Anniversary', 'Festive Gifting to clients'
                ],
                'Marketing' => [
                    'Digital Campaign - Lead Gen', 'Open House Event', 'Property Launch Event', 
                    'Annual Event', 'Sponsorship Event / Exhibition', 'Customer Roundtable Guests', 
                    'Webinar Attendees', 'Podcast Guests', 'Client Survey', 'Client Testimonial'
                ]
            ]
        ];

        DB::transaction(function () use ($eventData) {
            foreach ($eventData as $verticalId => $items) {
                foreach ($items as $key => $value) {
                    
                    // If the key is a string (like 'Marketing' or 'Sales'), it means it has children
                    if (is_string($key)) {
                        // 1. Create the Parent Event (Group)
                        $parent = Event::create([
                            'vertical_id' => $verticalId,
                            'title' => $key,
                            'is_active' => true,
                        ]);

                        // 2. Create all the children under this Parent
                        foreach ($value as $childTitle) {
                            Event::create([
                                'vertical_id' => $verticalId,
                                'parent_id' => $parent->id,
                                'title' => $childTitle,
                                'is_active' => true,
                            ]);
                        }
                    } else {
                        // If it's just a regular event with no parent (like 'New Joinee')
                        Event::create([
                            'vertical_id' => $verticalId,
                            'title' => $value, // $value is the title here
                            'is_active' => true,
                        ]);
                    }
                }
            }
        });
    }
}
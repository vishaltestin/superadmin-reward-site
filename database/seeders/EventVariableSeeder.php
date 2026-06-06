<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventVariable;
use App\Models\Event;

class EventVariableSeeder extends Seeder
{
    public function run(): void
    {
        $variables = [
            [
                'name'  => 'First Name',
                'value' => '{{ first_name }}',
            ],
            [
                'name'  => 'Last Name',
                'value' => '{{ last_name }}',
            ],
            [
                'name'  => 'Company Name',
                'value' => '{{ company_name }}',
            ],
            [
                'name'  => 'Current Date',
                'value' => '{{ current_date }}',
            ],
            [
                'name'  => 'Campaign Name',
                'value' => '{{ campaign_name }}',
            ],
            [
                'name'  => 'Reward Value',
                'value' => '{{ reward_value }}',
            ],
            [
                'name'  => 'Claim Link',
                'value' => '{{ claim_link }}',
            ],
            [
                'name'  => 'Claim Code',
                'value' => '{{ claim_code }}',
            ],
        ];

        foreach ($variables as $variable) {
            EventVariable::create($variable);
        }

        // Optional: Event-specific variables
        $newJoineeEvent = Event::where('title', 'New Joinee')->first();

        if ($newJoineeEvent) {
            EventVariable::create([
                'event_id' => $newJoineeEvent->id,
                'name' => 'Login URL',
                'value' => '{{ login_url }}',
            ]);

            EventVariable::create([
                'event_id' => $newJoineeEvent->id,
                'name' => 'Default Password',
                'value' => '{{ password }}',
            ]);
        }
    }
}
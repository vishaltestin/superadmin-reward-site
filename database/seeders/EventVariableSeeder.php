<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventVariable;
use App\Models\Event; // Assuming you have an Event model

class EventVariableSeeder extends Seeder
{
    public function run(): void
    {
        $globalVars = [
            ['name' => 'First Name', 'value' => '{{ first_name }}'],
            ['name' => 'Last Name', 'value' => '{{ last_name }}'],
            ['name' => 'Company Name', 'value' => '{{ company_name }}'],
            ['name' => 'Current Date', 'value' => '{{ current_date }}'],
        ];

        foreach ($globalVars as $var) {
            EventVariable::create($var);
        }

        // 2. Create Specific Variables (Example: Attach to "New Joinee" event)
        // Find the "New Joinee" event from your database (Adjust the title to match yours)
        $newJoineeEvent = Event::where('title', 'New Joinee')->first();

        if ($newJoineeEvent) {
            EventVariable::create([
                'event_id' => $newJoineeEvent->id,
                'name' => 'Login URL',
                'value' => '{{ login_url }}'
            ]);
            
            EventVariable::create([
                'event_id' => $newJoineeEvent->id,
                'name' => 'Default Password',
                'value' => '{{ password }}'
            ]);
        }
    }
}
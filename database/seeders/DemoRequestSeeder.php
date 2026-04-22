<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DemoRequest;

class DemoRequestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. A brand new request
        DemoRequest::firstOrCreate(
            ['email' => 'clark.kent@dailyplanet.com'],
            [
                'first_name' => 'Clark',
                'last_name' => 'Kent',
                'company_name' => 'Daily Planet',
                'mobile' => '+15550199283',
                'message' => 'We are looking to implement a new rewarding system for our journalists. Can we get a walkthrough of the platform this week?',
                'status' => 'new',
            ]
        );

        // 2. A request that sales has reached out to
        DemoRequest::firstOrCreate(
            ['email' => 'diana.prince@themyscira.gov'],
            [
                'first_name' => 'Diana',
                'last_name' => 'Prince',
                'company_name' => 'Themyscira Antiquities',
                'mobile' => '+18887774444',
                'message' => 'Interested in seeing how your exception engine works for custom B2B pricing.',
                'status' => 'contacted',
            ]
        );

        // 3. A request with a scheduled call
        DemoRequest::firstOrCreate(
            ['email' => 'peter.parker@dailybugle.net'],
            [
                'first_name' => 'Peter',
                'last_name' => 'Parker',
                'company_name' => 'Daily Bugle',
                'mobile' => '+12125550198',
                'message' => 'J. Jonah Jameson told me to find a cheaper way to reward photographers. Show me what you got.',
                'status' => 'demo_scheduled',
            ]
        );

        // 4. A closed/won request
        DemoRequest::firstOrCreate(
            ['email' => 'lex.luthor@lexcorp.com'],
            [
                'first_name' => 'Lex',
                'last_name' => 'Luthor',
                'company_name' => 'LexCorp',
                'mobile' => '+18005550199',
                'message' => 'Need an enterprise-grade API for our internal HR tools.',
                'status' => 'closed',
            ]
        );
    }
}
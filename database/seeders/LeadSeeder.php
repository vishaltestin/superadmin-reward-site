<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        // 1. A Standard Pending Lead (Ready to be approved)
        Lead::firstOrCreate(
            ['email' => 'tony.stark@starkindustries.com'],
            [
                'first_name' => 'Tony',
                'last_name' => 'Stark',
                'mobile' => '+19876543210',
                'company_name' => 'Stark Industries',
                'number_of_employee' => '1001+',
                'designation' => 'CEO',
                'department' => 'Executive',
                'status' => 'pending',
            ]
        );

        // 2. Another Pending Lead (To test the table list view)
        Lead::firstOrCreate(
            ['email' => 'pam.beesly@dundermifflin.com'],
            [
                'first_name' => 'Pam',
                'last_name' => 'Beesly',
                'mobile' => '+15550192837',
                'company_name' => 'Dunder Mifflin',
                'number_of_employee' => '51-200',
                'designation' => 'Office Administrator',
                'department' => 'Operations',
                'status' => 'pending',
            ]
        );

        // 3. A Rejected Lead (To test your filters and badge colors)
        Lead::firstOrCreate(
            ['email' => 'spammy.mcspam@freemail.xyz'],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'mobile' => '0000000000',
                'company_name' => 'Fake Company LLC',
                'number_of_employee' => '0-50',
                'designation' => 'N/A',
                'department' => 'N/A',
                'status' => 'rejected',
            ]
        );

        // 4. An Already Approved Lead (Historical Data)
        Lead::firstOrCreate(
            ['email' => 'bruce.wayne@wayneenterprises.com'],
            [
                'first_name' => 'Bruce',
                'last_name' => 'Wayne',
                'mobile' => '+18887776666',
                'company_name' => 'Wayne Enterprises',
                'number_of_employee' => '1001+',
                'designation' => 'Owner',
                'department' => 'Management',
                'status' => 'approved',
            ]
        );
    }
}
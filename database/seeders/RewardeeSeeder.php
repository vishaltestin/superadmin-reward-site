<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\RewardeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class RewardeeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // 1. Let's fix the existing data first
        // If Samsung exists, let's approve it so we can use it
        $samsung = Company::where('alias', 'samsung')->first();
        if ($samsung) {
            $samsung->update(['is_approved' => 1]);
        }

        // Let's create a second company that DOES have the Internal Employees vertical (ID 1) 
        // so we have a good mix of data.
        $apple = Company::firstOrCreate(
            ['alias' => 'apple'],
            [
                'name' => 'Apple', 
                'is_active' => 1, 
                'is_approved' => 1,
                'industry' => 'IT & Software',
            ]
        );
        // Give Apple access to Internal Employees (1) and External Client (2)
        $apple->verticals()->syncWithoutDetaching([1, 2]);

        // 2. Fetch ONLY approved companies that have at least one vertical assigned
        $approvedCompanies = Company::where('is_approved', 1)
            ->has('verticals')
            ->with('verticals') // Eager load the pivot data
            ->get();

        if ($approvedCompanies->isEmpty()) {
            $this->command->error('No approved companies with assigned verticals found!');
            return;
        }

        DB::transaction(function () use ($faker, $approvedCompanies) {
            // Generate 50 dummy rewardees
            for ($i = 0; $i < 50; $i++) {
                
                // 3. SMART SELECTION: Pick a random approved company
                $company = $approvedCompanies->random();
                
                // 4. SMART SELECTION: Pick a random vertical THAT THIS COMPANY ACTUALLY OWNS
                $verticalId = $company->verticals->random()->id;

                $firstName = $faker->firstName;
                $lastName = $faker->lastName;

                // Create the Base User
                $user = User::create([
                    'company_id' => $company->id,
                    'user_type' => 'rewardee',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => $firstName . ' ' . $lastName,
                    'email' => $faker->unique()->safeEmail,
                    'mobile' => $faker->phoneNumber,
                    'password' => Hash::make('password'), 
                    'is_active' => true,
                ]);

                // Generate Vertical-Specific JSON Data based on the smartly selected vertical
                $verticalData = [];
                
                switch ($verticalId) {
                    case 1: // Internal Employees
                        $verticalData = [
                            'department' => $faker->randomElement(['Engineering', 'Sales', 'HR', 'Marketing', 'Finance']),
                            'job_title' => $faker->jobTitle,
                            'date_joining' => $faker->date(),
                            'workforce_type' => $faker->randomElement(['Full-time', 'Contractor', 'Intern']),
                        ];
                        break;
                    case 2: // External Client
                        $verticalData = [
                            'companyName' => $faker->company,
                            'industry' => $faker->randomElement(['Technology', 'Healthcare', 'Manufacturing', 'Retail']),
                            'relation_intensity' => $faker->randomElement(['High', 'Neutral', 'Low']),
                            'customer_status' => $faker->randomElement(['Customer', 'Prospect']),
                        ];
                        break;
                    case 3: // Channel Partners
                        $verticalData = [
                            'channel_partner_name' => $faker->company,
                            'partner_status' => $faker->randomElement(['Onboarded', 'To be Onboarded']),
                            'date_onboarding' => $faker->date(),
                            'segment' => $faker->randomElement(['Top N', 'NextN', 'Coverage']),
                        ];
                        break;
                    case 4: // Auto Dealers
                        $verticalData = [
                            'preferred_vehicle' => $faker->randomElement(['SUV', 'Sedan', 'Hatchback', 'EV']),
                            'testDrive' => $faker->randomElement(['Done', 'Pending']),
                            'purchase' => $faker->randomElement(['Done', 'Pending']),
                            'service_due_date' => $faker->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
                        ];
                        break;
                    case 5: // Real Estate
                        $verticalData = [
                            'property_type' => $faker->randomElement(['Residential', 'Commercial']),
                            'site_visit' => $faker->randomElement(['Done', 'Pending']),
                            'annual_income_level' => $faker->randomElement(['Below 5L', '5L-10L', '10L-20L', '20L-50L', 'Above 50L']),
                            'requirement' => $faker->randomElement(['Immediately', 'Within 3 months', 'After 3 months']),
                        ];
                        break;
                }

                // Save the dynamic profile
                RewardeeProfile::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'vertical_id' => $verticalId,
                    'vertical_data' => $verticalData,
                ]);
            }
        });
    }
}
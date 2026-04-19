<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\LandingPageTemplate;
use Illuminate\Database\Seeder;

class LandingPageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Find the event we want to attach this landing page to
        $event = Event::where('title', 'New Joinee')->first();

        if (! $event) {
            $this->command->warn('Event "New Joinee" not found. Skipping Landing Page Seeder.');
            return;
        }

        // 2. Define the React Page Schema (The Modular Blocks)
        $pageSchema = [
            [
                'id' => 'sec_hero_01',
                'type' => 'hero',
                'name' => 'Welcome Hero Banner',
                'isVisible' => true,
                'properties' => [
                    ['key' => 'title', 'label' => 'Main Title', 'type' => 'text', 'value' => 'Welcome to the Team!'],
                    ['key' => 'titleColor', 'label' => 'Title Color', 'type' => 'color', 'value' => '#ffffff'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'value' => 'We are thrilled to have you here. Claim your welcome bonus below.'],
                    ['key' => 'backgroundColor', 'label' => 'Background Color', 'type' => 'color', 'value' => '#4f46e5'],
                    ['key' => 'buttonText', 'label' => 'CTA Text', 'type' => 'text', 'value' => 'Get Started'],
                ]
            ],
            [
                'id' => 'sec_claim_01',
                'type' => 'claim_ui',
                'name' => 'Points Claim Box',
                'isVisible' => true,
                'properties' => [
                    ['key' => 'designVariant', 'label' => 'Design Variant', 'type' => 'select', 'value' => 'confetti_card'],
                    ['key' => 'headingText', 'label' => 'Heading', 'type' => 'text', 'value' => 'Your Welcome Gift'],
                    ['key' => 'pointsValue', 'label' => 'Points to Display', 'type' => 'text', 'value' => '{{ points_awarded }}'], // Dynamic merge tag!
                    ['key' => 'claimButtonColor', 'label' => 'Button Color', 'type' => 'color', 'value' => '#10b981'],
                ]
            ],
            [
                'id' => 'sec_social_01',
                'type' => 'social_footer',
                'name' => 'Social Links Footer',
                'isVisible' => true,
                'properties' => [
                    ['key' => 'linkedinUrl', 'label' => 'LinkedIn', 'type' => 'text', 'value' => 'https://linkedin.com/company/yourcompany'],
                    ['key' => 'footerText', 'label' => 'Footer Text', 'type' => 'text', 'value' => '© 2024 Your SaaS Platform'],
                ]
            ]
        ];

        // 3. Insert the Template into the Database
        LandingPageTemplate::firstOrCreate(
            [
                'event_id' => $event->id,
                'company_id' => null, // NULL means this is the Super Admin Global Master
            ],
            [
                'name' => 'Global Employee Welcome Page',
                'title' => 'Claim Your Welcome Bonus!',
                'status' => 'published',
                'is_active' => true,
                
                // The JSON Columns
                'global_theme_tokens' => [
                    'primaryColor' => '#4f46e5',
                    'fontFamily' => 'Inter, sans-serif',
                ],
                'seo_meta' => [
                    'title' => 'Welcome to the Team!',
                    'description' => 'You have a new welcome bonus waiting for you.',
                    'og_image' => null,
                ],
                'page_schema' => $pageSchema,
            ]
        );
    }
}
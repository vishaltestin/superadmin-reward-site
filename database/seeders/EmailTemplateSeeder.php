<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'event_title' => 'New Joinee',
                'name' => 'Global Employee Welcome',
                'subject' => 'Welcome to the team, {{ first_name }}!',
                'html_body' => $this->getWelcomeHtml(),
            ],
            [
                'event_title' => 'Employee Anniversary',
                'name' => 'Global Work Anniversary',
                'subject' => 'Happy Work Anniversary, {{ first_name }}!',
                'html_body' => $this->getAnniversaryHtml(),
            ],
            [
                'event_title' => 'Client Birthday',
                'name' => 'Global Client Birthday',
                'subject' => 'Wishing you a very Happy Birthday!',
                'html_body' => $this->getBirthdayHtml(),
            ]
        ];

        foreach ($templates as $data) {
            // Find the event by title so we can attach the foreign ID
            $event = Event::where('title', $data['event_title'])->first();

            if ($event) {
                EmailTemplate::firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'company_id' => null, // NULL means this is your Super Admin Master template!
                    ],
                    [
                        'name' => $data['name'],
                        'subject' => $data['subject'],
                        'html_body' => $data['html_body'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    // ---------------------------------------------------------
    // HTML Template Generators (Clean, responsive table-based HTML)
    // ---------------------------------------------------------

    private function getWelcomeHtml(): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <div style="background-color: #4f46e5; padding: 30px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0;">Welcome to the Team!</h1>
            </div>
            <div style="padding: 30px; background-color: #ffffff; color: #334155;">
                <p style="font-size: 16px;">Hi {{ first_name }},</p>
                <p style="font-size: 16px; line-height: 1.5;">We are thrilled to have you onboard. To celebrate your arrival, we have credited your account with your welcome bonus!</p>
                
                <div style="background-color: #f1f5f9; padding: 20px; text-align: center; border-radius: 8px; margin: 25px 0;">
                    <p style="margin: 0; font-size: 14px; color: #64748b;">You have received</p>
                    <h2 style="margin: 5px 0 0 0; color: #0f172a; font-size: 28px;">{{ points_awarded }} Points</h2>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="{{ login_url }}" style="background-color: #4f46e5; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">Login to Redeem</a>
                </div>
            </div>
        </div>
        HTML;
    }

    private function getAnniversaryHtml(): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <div style="background-color: #10b981; padding: 30px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0;">Happy Work Anniversary! 🎉</h1>
            </div>
            <div style="padding: 30px; background-color: #ffffff; color: #334155;">
                <p style="font-size: 16px;">Hi {{ first_name }},</p>
                <p style="font-size: 16px; line-height: 1.5;">Thank you for your continued dedication and hard work. As a token of our appreciation, we have sent you a gift.</p>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="{{ login_url }}" style="background-color: #10b981; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">Claim Your Points</a>
                </div>
            </div>
        </div>
        HTML;
    }

    private function getBirthdayHtml(): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <div style="background-color: #f59e0b; padding: 30px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0;">Happy Birthday! 🎂</h1>
            </div>
            <div style="padding: 30px; background-color: #ffffff; color: #334155;">
                <p style="font-size: 16px;">Dear {{ first_name }},</p>
                <p style="font-size: 16px; line-height: 1.5;">Wishing you a fantastic birthday filled with joy and success! Enjoy your special day.</p>
            </div>
        </div>
        HTML;
    }
}
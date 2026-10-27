<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\LandingPageTemplate;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Optional demo data for manually testing the points/code/link template flow.
 *
 * Run: php artisan db:seed --class=DemoRewardTemplateSeeder
 * Optional: DEMO_TEMPLATE_EVENT_ID=12 php artisan db:seed --class=DemoRewardTemplateSeeder
 */
class DemoRewardTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $requestedEventId = env('DEMO_TEMPLATE_EVENT_ID');

        $event = $requestedEventId
            ? Event::find($requestedEventId)
            : Event::where('is_active', true)->orderBy('id')->first();

        if (! $event && ! $requestedEventId) {
            $event = Event::orderBy('id')->first();
        }

        if (! $event) {
            throw new RuntimeException(
                'No event found. Create an event first, or set DEMO_TEMPLATE_EVENT_ID to a valid event ID.'
            );
        }

        foreach ($this->emailTemplates() as $template) {
            EmailTemplate::updateOrCreate(
                [
                    'event_id'   => $event->id,
                    'company_id' => null,
                    'name'       => $template['name'],
                ],
                [
                    'reward_type' => $template['reward_type'],
                    'subject'     => $template['subject'],
                    'html_body'   => $template['html_body'],
                    'is_active'   => true,
                ]
            );
        }

        foreach ($this->landingTemplates() as $template) {
            LandingPageTemplate::updateOrCreate(
                [
                    'event_id'   => $event->id,
                    'company_id' => null,
                    'name'       => $template['name'],
                ],
                [
                    'reward_type'         => $template['reward_type'],
                    'title'               => $template['title'],
                    'status'              => 'published',
                    'is_active'           => true,
                    'global_theme_tokens' => $template['global_theme_tokens'],
                    'seo_meta'            => $template['seo_meta'],
                    'page_schema'         => $template['page_schema'],
                ]
            );
        }

        if ($this->command) {
            $this->command->info(
                "Created/updated 6 demo templates for event #{$event->id}: {$event->title}"
            );
            $this->command->info(
                'Use this same event when creating Points, Code, and Link test campaigns.'
            );
        }
    }

    private function emailTemplates(): array
    {
        return [
            [
                'reward_type' => 'points',
                'name'        => '[DEMO] Points Reward Email',
                'subject'     => '{{ first_name }}, your points have arrived!',
                'html_body'   => $this->emailHtml(
                    '#4f46e5',
                    'POINTS REWARD',
                    'Your points are ready, {{ first_name }}!',
                    '<p style="margin:0 0 16px;font-size:16px;line-height:26px;">Your reward from <strong>{{ campaign_name }}</strong> has been added to your rewards wallet.</p>',
                    '{{ points_awarded }} POINTS',
                    'View my points reward'
                ),
            ],
            [
                'reward_type' => 'code',
                'name'        => '[DEMO] Code Reward Email',
                'subject'     => '{{ first_name }}, your reward code is inside',
                'html_body'   => $this->emailHtml(
                    '#059669',
                    'REWARD CODE',
                    'Your reward code is ready!',
                    '<p style="margin:0 0 16px;font-size:16px;line-height:26px;">Use the code below when redeeming your reward from <strong>{{ campaign_name }}</strong>. You can also open your personalised reward page.</p>',
                    'CODE: {{ claim_code }}',
                    'Open my reward page'
                ),
            ],
            [
                'reward_type' => 'link',
                'name'        => '[DEMO] Link Reward Email',
                'subject'     => '{{ first_name }}, choose your reward',
                'html_body'   => $this->emailHtml(
                    '#2563eb',
                    'CHOOSE YOUR REWARD',
                    'A reward is waiting for you!',
                    '<p style="margin:0 0 16px;font-size:16px;line-height:26px;">Open your private reward link and choose a product from the catalogue prepared for <strong>{{ campaign_name }}</strong>.</p>',
                    'REWARD VALUE: ₹{{ reward_value }}',
                    'Choose my reward'
                ),
            ],
        ];
    }

    private function landingTemplates(): array
    {
        return [
            [
                'reward_type' => 'points',
                'name'        => '[DEMO] Points Congratulations Page',
                'title'       => 'Your Points Reward',
                'global_theme_tokens' => [
                    'primaryColor' => '#4f46e5',
                    'textColor'    => '#111827',
                    'fontFamily'   => 'Inter, sans-serif',
                ],
                'seo_meta' => [
                    'title'       => 'Your Points Reward',
                    'description' => 'Your points reward is ready.',
                    'og_image'    => null,
                ],
                'page_schema' => [
                    $this->heroBlock(
                        'demo_points_hero',
                        'Congratulations, {{ first_name }}!',
                        'You have received {{ points_awarded }} points from {{ company_name }}. They are ready to use in your rewards wallet.',
                        '',
                        '',
                        '#312e81',
                        '#ffffff'
                    ),
                    $this->ctaBlock(
                        'demo_points_cta',
                        '{{ points_awarded }} points. One happy reward. Enjoy!',
                        '#eef2ff',
                        '#312e81'
                    ),
                ],
            ],
            [
                'reward_type' => 'code',
                'name'        => '[DEMO] Code Reward Page',
                'title'       => 'Your Reward Code',
                'global_theme_tokens' => [
                    'primaryColor' => '#059669',
                    'textColor'    => '#111827',
                    'fontFamily'   => 'Inter, sans-serif',
                ],
                'seo_meta' => [
                    'title'       => 'Your Reward Code',
                    'description' => 'Your personalised reward code is ready.',
                    'og_image'    => null,
                ],
                'page_schema' => [
                    $this->heroBlock(
                        'demo_code_hero',
                        'Your reward code is ready, {{ first_name }}!',
                        'Use this code when you redeem your reward from {{ campaign_name }}.',
                        '',
                        '',
                        '#064e3b',
                        '#34d399'
                    ),
                    $this->ctaBlock(
                        'demo_code_cta',
                        'YOUR CODE: {{ claim_code }}',
                        '#d1fae5',
                        '#065f46'
                    ),
                ],
            ],
            [
                'reward_type' => 'link',
                'name'        => '[DEMO] Link Product Claim Page',
                'title'       => 'Choose Your Reward',
                'global_theme_tokens' => [
                    'primaryColor' => '#2563eb',
                    'textColor'    => '#111827',
                    'fontFamily'   => 'Inter, sans-serif',
                ],
                'seo_meta' => [
                    'title'       => 'Choose Your Reward',
                    'description' => 'Choose a product with your reward.',
                    'og_image'    => null,
                ],
                'page_schema' => [
                    $this->heroBlock(
                        'demo_link_hero',
                        'Choose your reward, {{ first_name }}!',
                        'You have a reward worth ₹{{ reward_value }}. Select a product below and continue to checkout.',
                        'Choose your reward',
                        '#rewards',
                        '#172554',
                        '#60a5fa'
                    ),
                    [
                        'id'        => 'demo_link_selector',
                        'type'      => 'RewardSelector',
                        'isVisible' => true,
                        'variant'   => 'grid',
                        'content'   => [
                            'heading' => 'Choose one product',
                            'subtext' => 'Your reward value is applied automatically at checkout.',
                        ],
                        'styles'    => [
                            'backgroundColor'     => '#ffffff',
                            'headingColor'        => '#172554',
                            'cardBackgroundColor' => '#eff6ff',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function emailHtml(
        string $accent,
        string $kicker,
        string $heading,
        string $body,
        string $highlight,
        string $buttonLabel
    ): string {
        return <<<HTML
<div style="margin:0;padding:24px 12px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:34px 32px;background:{$accent};text-align:center;">
                <div style="font-size:11px;letter-spacing:2px;font-weight:bold;color:#ffffff;opacity:0.85;">{$kicker}</div>
                <h1 style="margin:12px 0 0;font-size:28px;line-height:36px;color:#ffffff;">{$heading}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:32px;">
                <p style="margin:0 0 16px;font-size:16px;line-height:26px;">Hi {{ first_name }},</p>
                {$body}
                <div style="margin:24px 0;padding:20px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;text-align:center;font-size:22px;font-weight:bold;color:{$accent};">
                    {$highlight}
                </div>
                <div style="text-align:center;padding:8px 0 24px;">
                    <a href="{{ claim_link }}" style="display:inline-block;padding:14px 26px;border-radius:8px;background:{$accent};color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;">
                        {$buttonLabel}
                    </a>
                </div>
                <p style="margin:0;font-size:12px;line-height:20px;color:#64748b;">
                    If the button does not work, copy this link into your browser:<br>
                    <a href="{{ claim_link }}" style="color:{$accent};word-break:break-all;">{{ claim_link }}</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 32px;background:#f8fafc;text-align:center;font-size:12px;line-height:20px;color:#64748b;">
                Sent by {{ company_name }} · {{ campaign_name }}
            </td>
        </tr>
    </table>
</div>
HTML;
    }

    private function heroBlock(
        string $id,
        string $heading,
        string $subtext,
        string $buttonText,
        string $buttonLink,
        string $backgroundColor,
        string $accentColor
    ): array {
        return [
            'id'        => $id,
            'type'      => 'HeroBanner',
            'isVisible' => true,
            'variant'   => 'centered',
            'content'   => [
                'heading'    => $heading,
                'subtext'    => $subtext,
                'buttonText' => $buttonText,
                'buttonLink' => $buttonLink,
            ],
            'styles'    => [
                'backgroundImage' => '',
                'overlayOpacity'  => 0,
                'backgroundColor' => $backgroundColor,
                'headingColor'    => '#ffffff',
                'subtextColor'    => '#e2e8f0',
                'buttonBgColor'   => $accentColor,
                'buttonTextColor' => '#0f172a',
            ],
        ];
    }

    private function ctaBlock(
        string $id,
        string $heading,
        string $backgroundColor,
        string $headingColor
    ): array {
        return [
            'id'        => $id,
            'type'      => 'CallToAction',
            'isVisible' => true,
            'variant'   => 'card',
            'content'   => [
                'heading'    => $heading,
                'buttonText' => '',
                'buttonLink' => '',
            ],
            'styles'    => [
                'backgroundColor' => $backgroundColor,
                'headingColor'    => $headingColor,
                'buttonBgColor'   => $headingColor,
                'buttonTextColor' => '#ffffff',
            ],
        ];
    }
}
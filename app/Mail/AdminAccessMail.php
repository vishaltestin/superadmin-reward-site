<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AdminAccessMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $rawPassword,
        public string $loginUrl,
        public string $ctaLink
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Using the exact subject provided
            subject: 'Congratulations — You’re Now the Super Admin of Your RewardsApp Account',
        );
    }

    // public function headers(): Headers
    // {
    //     return new Headers(
    //         text: [
    //             'X-Sib-Sandbox' => 'drop', 
    //         ],
    //     );
    // }

    // public function content(): Content
    // {
    //     return new Content(
    //         // markdown: 'emails.admins.access',
    //     );
    // }
    public function content(): Content
{
    return new Content(
        view: 'emails.admins.access',
    );
}
}
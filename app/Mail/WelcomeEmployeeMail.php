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

class WelcomeEmployeeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $rawPassword,
        public string $storefrontUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . $this->user->company->name . ' Rewards!',
        );
    }

    // 2. Inject the Brevo Sandbox header
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
    //         markdown: 'emails.employees.welcome',
    //     );
    // }
    public function content(): Content
    {
        return new Content(
            view: 'emails.employees.welcome',
        );
    }
}

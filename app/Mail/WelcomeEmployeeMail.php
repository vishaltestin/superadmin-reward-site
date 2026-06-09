<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmployeeMail extends Mailable
{
    use SerializesModels;

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

    public function content(): Content
    {
        return new Content(
            view: 'emails.employees.welcome',
        );
    }
}

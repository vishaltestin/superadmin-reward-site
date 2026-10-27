<?php
namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoRequestThankYouMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public DemoRequest $demoRequest,
    ) {}

    public function envelope(): Envelope
    {
        $ccEmail = config('rewardsapp.lead_notification_cc');
        $cc      = is_string($ccEmail) && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)
            ? [new Address($ccEmail)]
            : [];

        return new Envelope(
            subject: 'We received your RewardsApp demo request',
            cc: $cc,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.demo-requests.thank-you',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OrganizationMemberAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $memberUser,
        public readonly Organization $organization,
        public readonly string $role
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your My Digital Diary workspace account'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-member-account-created'
        );
    }
}

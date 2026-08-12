<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganizationInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public OrganizationMember $member, public Organization $organization)
    {
    }

    public function build()
    {
        return $this->subject("You've been invited to join {$this->organization->name}")
            ->markdown('emails.organization-invite');
    }
}

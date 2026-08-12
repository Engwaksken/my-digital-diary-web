<?php

namespace App\Mail;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MeetingNotesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public string $shareText,
        public string $senderName,
    ) {
    }

    public function build()
    {
        return $this->subject('Meeting Notes: ' . $this->meeting->title)
            ->view('emails.meeting-notes');
    }
}

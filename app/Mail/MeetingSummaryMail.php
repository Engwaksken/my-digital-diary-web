<?php

namespace App\Mail;

use App\Models\MeetingRecording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MeetingSummaryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MeetingRecording $recording, public string $senderName)
    {
    }

    public function build()
    {
        return $this->subject('Meeting summary: ' . $this->recording->meeting->title)
            ->markdown('emails.meeting-summary');
    }
}

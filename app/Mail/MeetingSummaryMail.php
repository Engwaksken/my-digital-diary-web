<?php

namespace App\Mail;

use App\Models\MeetingRecording;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingSummaryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public MeetingRecording $recording;

    public string $senderName;

    public bool $includeNotes;

    public bool $includeTranscript;

    public bool $includeSummary;

    public ?string $personalMessage;

    public function __construct(
        MeetingRecording $recording,
        string $senderName,
        bool $includeNotes = true,
        bool $includeTranscript = true,
        bool $includeSummary = true,
        ?string $personalMessage = null
    ) {
        $this->recording =
            $recording->loadMissing(
                'meeting'
            );

        $this->senderName =
            $senderName;

        $this->includeNotes =
            $includeNotes;

        $this->includeTranscript =
            $includeTranscript;

        $this->includeSummary =
            $includeSummary;

        $this->personalMessage =
            filled($personalMessage)
                ? trim($personalMessage)
                : null;
    }

    public function envelope(): Envelope
    {
        $meetingTitle =
            $this->recording
                ->meeting
                ?->title
            ?: 'Meeting';

        return new Envelope(
            subject:
                'Meeting Notes & Summary: '
                . $meetingTitle
        );
    }

    public function content(): Content
    {
        return new Content(
            view:
                'emails.meeting-summary'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
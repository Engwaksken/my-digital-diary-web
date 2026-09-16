<?php

namespace App\Mail;

use App\Models\EnterpriseInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EnterpriseInquiryReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public EnterpriseInquiry $inquiry,
        public string $replySubject,
        public string $replyBody,
    ) {
    }

    public function build(): Mailable
    {
        return $this->subject($this->replySubject)
            ->markdown('emails.enterprise-inquiry-reply');
    }
}

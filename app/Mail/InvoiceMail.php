<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function build()
    {
        $pdf = Pdf::loadView('subscription.invoice-pdf', ['invoice' => $this->invoice])->setPaper('a4');

        return $this->subject(($this->invoice->isQuote() ? 'Quotation ' : 'Invoice ') . $this->invoice->invoice_number)
            ->markdown('emails.invoice')
            ->attachData($pdf->output(), 'invoice-' . $this->invoice->invoice_number . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\PurchaseRfq;
use App\Models\PurchaseRfqVendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseRfqMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRfq $rfq,
        public PurchaseRfqVendor $rfqVendor
    ) {}

    public function build()
    {
        return $this->subject('RFQ ' . $this->rfq->code)
            ->view('emails.purchase_rfqs.rfq')
            ->with([
                'rfq'    => $this->rfq,
                'vendor' => $this->rfqVendor,
            ]);
    }
}

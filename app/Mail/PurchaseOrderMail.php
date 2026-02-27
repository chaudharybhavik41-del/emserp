<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseOrder $order
    ) {}

    public function build()
    {
        return $this->subject('Purchase Order ' . $this->order->code)
            ->view('emails.purchase_orders.po')
            ->with([
                'order' => $this->order,
            ]);
    }
}

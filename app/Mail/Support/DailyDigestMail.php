<?php

namespace App\Mail\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $digestData
     */
    public function __construct(
        public array $digestData
    ) {}

    public function build()
    {
        $date = $this->digestData['date'] ?? '';

        return $this->subject('Daily ERP Digest - ' . $date)
            ->view('emails.support.daily_digest')
            ->with([
                'digest' => $this->digestData,
            ]);
    }
}

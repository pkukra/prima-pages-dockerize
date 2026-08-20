<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectText;
    public string $messageText;
    public ?string $actionUrl;

    public function __construct(string $subjectText, string $messageText, ?string $actionUrl = null)
    {
        $this->subjectText = $subjectText;
        $this->messageText = $messageText;
        $this->actionUrl = $actionUrl;
    }

    public function build()
    {
        return $this->subject($this->subjectText)
            ->view('emails.incoming_mail_notif')
            ->with([
                'subjectText' => $this->subjectText,
                'messageText' => $this->messageText,
                'actionUrl' => $this->actionUrl,
            ]);
    }
}

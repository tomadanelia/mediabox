<?php

namespace App\Mail;
use Illuminate\Bus\Queueable; 
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
//this class uses ShouldQueue to queue the email sending process we should run the queue worker to process the queued with artisan or supervisor
class VerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    public function __construct(public string $code, public string $username) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verification Code'
        );
    }

    public function content(): Content
    {
        return new Content(
            textString: "Hello $this->username, your verification code is: $this->code"
        );
    }
}

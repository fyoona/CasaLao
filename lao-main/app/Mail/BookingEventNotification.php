<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingEventNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $event,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking ' . $this->event . ': ' . $this->booking->room->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.event-notification',
            text: 'emails.bookings.event-notification-text',
        );
    }
}

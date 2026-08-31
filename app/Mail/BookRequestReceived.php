<?php

namespace App\Mail;

use App\Enums\UserRole;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Models\BookRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The note that lands in the shop's inbox when a reader asks us for a book.
 *
 * Sent to the shop, not to the reader: it replaces the mailto the book page
 * used to carry, so it has to read like the message that reader would have
 * written. Their address is the reply-to, so hitting reply answers them.
 */
class BookRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BookRequest $bookRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('book_requests.mail.received.subject', ['title' => $this->bookRequest->title]),
            replyTo: [$this->bookRequest->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.book-requests.received',
            with: [
                /* Named explicitly: this is sent from the shop and from the
                   client panel, and an unqualified URL is resolved against
                   whichever panel happens to be current. */
                'url' => BookRequestResource::getUrl(
                    'edit',
                    ['record' => $this->bookRequest],
                    panel: UserRole::Admin->panelId(),
                ),
            ],
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}

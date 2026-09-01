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
 * A reader has called a request off from their own pages.
 *
 * Worth an email rather than only a change of status on a screen nobody is
 * watching: the shop may already have ordered the book, and stopping that is
 * time-critical.
 */
class BookRequestWithdrawn extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BookRequest $bookRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [$this->bookRequest->user->email],
            subject: __('book_requests.mail.withdrawn.subject', ['title' => $this->bookRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.book-requests.withdrawn',
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

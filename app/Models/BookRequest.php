<?php

namespace App\Models;

use App\Enums\BookRequestStatus;
use Database\Factories\BookRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A book a reader has asked us for: either one we do not stock at all, or one
 * on the shelf that has run out. The catalogue is not touched by any of this --
 * a request is a note for the bookseller, not a record of a book.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $book_id
 * @property string $title
 * @property string|null $author
 * @property string|null $publisher
 * @property string|null $isbn
 * @property string|null $notes
 * @property BookRequestStatus $status
 * @property string|null $admin_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Book|null $book
 */
#[Fillable([
    'user_id', 'book_id',
    'title', 'author', 'publisher', 'isbn', 'notes',
    'status', 'admin_notes',
])]
class BookRequest extends Model
{
    /** @use HasFactory<BookRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookRequestStatus::class,
        ];
    }

    /**
     * Who asked. Only a signed-in reader can, so there is always one, and their
     * name, address and telephone are read off the account rather than copied
     * on to the request -- correcting a typo in an address has to fix every
     * request that reader has open, not just the next one.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The catalogued book this was asked about, when the reader came from its
     * page. Kept nullable on purpose: the point of the form is books we do not
     * have, and a book that is later withdrawn must not take the request away.
     *
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Everything still waiting on a bookseller.
     *
     * @param  Builder<BookRequest>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', BookRequestStatus::open());
    }

    /**
     * The book as the reader described it, on one line, for a subject or a table.
     */
    /**
     * Whether a bookseller could still be acting on this, which is what makes
     * it worth a reader's while to call it off.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, BookRequestStatus::open(), true);
    }

    public function reference(): string
    {
        return collect([$this->title, $this->author, $this->publisher])
            ->filter()
            ->implode(' · ');
    }
}

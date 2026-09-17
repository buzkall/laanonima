<?php

namespace App\Models;

use App\Enums\ContributorRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a book's title page: a person, and what they did.
 *
 * A row rather than a pivot so the panel's repeater can edit it directly, and
 * so the same person can be filed twice on one book under two roles.
 *
 * @property int $id
 * @property int $book_id
 * @property int $author_id
 * @property ContributorRole $role
 * @property int $position
 * @property-read Book $book
 * @property-read Author $author
 */
#[Fillable(['book_id', 'author_id', 'role', 'position'])]
#[WithoutTimestamps]
class BookContributor extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role'     => ContributorRole::class,
            'position' => 'integer',
        ];
    }

    /**
     * The authors line and the search text on the book are denormalizations
     * of these rows, so both are rewritten whenever one is filed, moved or
     * removed. The search text also carries translators, who never reach the
     * authors line.
     */
    protected static function booted(): void
    {
        $sync = function(self $contributor): void {
            $contributor->book->syncAuthorsLine();
            $contributor->book->syncSearchText();
        };

        static::saved($sync);
        static::deleted($sync);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A person on a title page -- author, translator, illustrator -- with the
 * biography the shop writes about them. The role is not theirs: it belongs
 * to each contribution, so one person can write one book and translate another.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $bio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'bio'])]
#[RouteKey('slug')]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function(self $author): void {
            if (blank($author->slug)) {
                $author->slug = Str::slug($author->name);
            }
        });

        /*
         | The database would drop the contributions on its own, but silently:
         | going through the models lets each one rewrite its book's authors
         | line on the way out.
         */
        static::deleting(function(self $author): void {
            $author->contributions()->get()->each->delete();
        });

        /* The authors line on every book quotes the name, so a rename follows. */
        static::saved(function(self $author): void {
            if (! $author->wasChanged('name')) {
                return;
            }

            $author->contributions()->with('book')->get()
                ->pluck('book')
                ->unique('id')
                ->each(fn(Book $book) => $book->syncAuthorsLine());
        });
    }

    /**
     * The same person, keyed by slug, so two books by them share one record.
     */
    public static function named(string $name): self
    {
        $name = trim($name);

        return self::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }

    /**
     * The biography as plain words, for a listing or a meta description: the
     * rich editor stores HTML, and neither place can show markup.
     */
    public function bioExcerpt(int $limit = 155): ?string
    {
        /* A block closing tag becomes a space, or two paragraphs run into one
           another as "...posguerra.Murió en 2021." once the markup is gone.
           Inline tags are left alone so a full stop stays against its word. */
        $spaced = preg_replace('/<(?:br\s*\/?|\/(?:p|div|li|blockquote|h[1-6]))>/i', ' ', (string)$this->bio);

        $text = Str::squish(html_entity_decode(strip_tags((string)$spaced)));

        return $text === '' ? null : Str::limit($text, $limit);
    }

    /**
     * @return HasMany<BookContributor, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(BookContributor::class);
    }

    /**
     * Every book this person had a hand in, whatever the role.
     *
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_contributors')->distinct();
    }
}

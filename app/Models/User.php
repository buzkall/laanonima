<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BookRequest> $bookRequests
 */
#[Fillable(['name', 'email', 'phone', 'role', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
        ];
    }

    /**
     * Each role gets its own panel: administrators the admin panel, clients the
     * client panel. Any other panel is denied.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === $this->role->panelId();
    }

    /**
     * Everyone holding one given role.
     *
     * A user has exactly one role, so this is a plain equality on the column
     * rather than a lookup through a pivot.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function hasRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role);
    }

    /**
     * A bookseller is whoever runs the shop. Everything the shop keeps to
     * itself -- the requests readers send in, a book still being catalogued --
     * is theirs to see, and nobody else's.
     */
    public function isBookseller(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * The books this reader has asked us for.
     *
     * A request belongs to whoever sent it and to nobody else, so the rows go
     * with the account: `book_requests.user_id` cascades on delete, and closing
     * an account takes its requests away with it.
     *
     * @return HasMany<BookRequest, $this>
     */
    public function bookRequests(): HasMany
    {
        return $this->hasMany(BookRequest::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1) . Str::substr($initials, -1)
            : $initials;
    }
}

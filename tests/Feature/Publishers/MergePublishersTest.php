<?php

use App\Actions\Publishers\MergePublishers;
use App\Filament\Resources\Publishers\Pages\ListPublishers;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

it('moves the books of the absorbed publishers and deletes them', function(): void {
    $survivor = Publisher::factory()->create(['name' => 'Editorial Anagrama']);
    $absorbed = Publisher::factory()->create(['name' => 'Editorial Anagrama S.A.']);
    $other = Publisher::factory()->create();

    $moved = Book::factory()->count(2)->for($absorbed)->create();
    $untouched = Book::factory()->for($other)->create();

    $reassigned = app(MergePublishers::class)($survivor, [$absorbed]);

    expect($reassigned)->toBe(2)
        ->and(Publisher::find($absorbed->id))->toBeNull()
        ->and($moved->each->refresh()->pluck('publisher_id')->unique()->all())->toBe([$survivor->id])
        ->and($untouched->refresh()->publisher_id)->toBe($other->id);
});

it('absorbs several publishers at once', function(): void {
    $survivor = Publisher::factory()->create();
    $absorbed = Publisher::factory()->count(2)->create();

    $absorbed->each(fn(Publisher $publisher) => Book::factory()->for($publisher)->create());

    $reassigned = app(MergePublishers::class)($survivor, $absorbed);

    expect($reassigned)->toBe(2)
        ->and(Publisher::query()->count())->toBe(1)
        ->and($survivor->books()->count())->toBe(2);
});

/*
 | `publisher_id` is `nullOnDelete`, so a merge that deleted the row before
 | moving the books would leave a shelf of them with no publisher at all -- and
 | quietly, since nothing fails.
 */
it('never leaves a book without a publisher', function(): void {
    $survivor = Publisher::factory()->create();
    $absorbed = Publisher::factory()->create();
    $book = Book::factory()->for($absorbed)->create();

    app(MergePublishers::class)($survivor, [$absorbed]);

    expect($book->refresh()->publisher_id)->toBe($survivor->id);
});

it('refuses to absorb a publisher into itself', function(): void {
    $publisher = Publisher::factory()->create();
    $book = Book::factory()->for($publisher)->create();

    $reassigned = app(MergePublishers::class)($publisher, [$publisher]);

    expect($reassigned)->toBe(0)
        ->and(Publisher::find($publisher->id))->not->toBeNull()
        ->and($book->refresh()->publisher_id)->toBe($publisher->id);
});

/*
 | The survivor is the name a bookseller chose to keep, so the merge is not an
 | edit of it -- but whatever only the duplicate knew would otherwise be thrown
 | away with the row.
 */
it('keeps what the survivor already had and takes what it was missing', function(): void {
    $survivor = Publisher::factory()->create([
        'website'     => 'https://anagrama-editorial.com',
        'description' => null,
    ]);
    $absorbed = Publisher::factory()->create([
        'website'     => 'https://duplicada.example',
        'description' => 'Fundada en 1969.',
    ]);

    app(MergePublishers::class)($survivor, [$absorbed]);

    expect($survivor->refresh()->website)->toBe('https://anagrama-editorial.com')
        ->and($survivor->description)->toBe('Fundada en 1969.');
});

it('takes the logotype of the absorbed publisher when it has none of its own', function(): void {
    $survivor = Publisher::factory()->create();
    $absorbed = Publisher::factory()->create();
    $absorbed->addMediaFromString(fakeCover(400, 400))
        ->usingFileName('logo.jpg')
        ->toMediaCollection(Publisher::LOGO_COLLECTION);

    app(MergePublishers::class)($survivor, [$absorbed]);

    $logo = $survivor->refresh()->getFirstMedia(Publisher::LOGO_COLLECTION);

    expect($logo)->not->toBeNull()
        ->and($logo->file_name)->toBe('logo.jpg');

    Storage::disk('public')->assertExists($logo->getPathRelativeToRoot());
});

it('leaves the logotype the survivor already had', function(): void {
    $survivor = Publisher::factory()->create();
    $survivor->addMediaFromString(fakeCover(400, 400))
        ->usingFileName('propio.jpg')
        ->toMediaCollection(Publisher::LOGO_COLLECTION);

    $absorbed = Publisher::factory()->create();
    $absorbed->addMediaFromString(fakeCover(400, 400))
        ->usingFileName('duplicado.jpg')
        ->toMediaCollection(Publisher::LOGO_COLLECTION);

    app(MergePublishers::class)($survivor, [$absorbed]);

    expect($survivor->refresh()->getMedia(Publisher::LOGO_COLLECTION))->toHaveCount(1)
        ->and($survivor->getFirstMedia(Publisher::LOGO_COLLECTION)->file_name)->toBe('propio.jpg');
});

it('merges from the listing, keeping the row the action was called on', function(): void {
    $survivor = Publisher::factory()->create(['name' => 'Caja Negra']);
    $absorbed = Publisher::factory()->create(['name' => 'Caja Negra Editorial']);
    $book = Book::factory()->for($absorbed)->create();

    livewire(ListPublishers::class)
        ->callAction(TestAction::make('merge')->table($survivor), ['absorbed' => [$absorbed->id]])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($book->refresh()->publisher_id)->toBe($survivor->id)
        ->and(Publisher::find($absorbed->id))->toBeNull();
});

it('asks which publishers to absorb', function(): void {
    $survivor = Publisher::factory()->create();

    livewire(ListPublishers::class)
        ->callAction(TestAction::make('merge')->table($survivor))
        ->assertHasActionErrors(['absorbed' => 'required']);

    expect(Publisher::query()->count())->toBe(1);
});

/*
 | A merge deletes the absorbed rows but loses nothing with them, so it has its
 | own ability and the demo's catch on `delete` does not reach it -- see DemoMode.
 */
it('merges while the demo is open', function(): void {
    config()->set('site.demo_mode', true);

    $survivor = Publisher::factory()->create();
    $absorbed = Publisher::factory()->create();

    livewire(ListPublishers::class)
        ->assertActionVisible(TestAction::make('merge')->table($survivor))
        ->callAction(TestAction::make('merge')->table($survivor), ['absorbed' => [$absorbed->id]])
        ->assertHasNoActionErrors();

    expect(Publisher::find($absorbed->id))->toBeNull();
});

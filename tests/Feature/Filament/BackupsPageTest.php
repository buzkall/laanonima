<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

beforeEach(function(): void {
    $this->admin = User::factory()->admin()->create();

    $this->actingAs($this->admin);
});

it('shows the backups page to an administrator', function(): void {
    Livewire::test(Backups::class)->assertOk();
});

it('lets an administrator make, download and delete backups', function(string $ability): void {
    expect($this->admin->can($ability))->toBeTrue();
})->with(['create-backup', 'download-backup', 'delete-backup']);

it('refuses a reader every backup ability', function(string $ability): void {
    expect(User::factory()->client()->create()->can($ability))->toBeFalse();
})->with(['create-backup', 'download-backup', 'delete-backup']);

it('refuses every backup ability while the demo is on, to an administrator too', function(string $ability): void {
    config()->set('site.demo_mode', true);

    expect($this->admin->can($ability))->toBeFalse();
})->with(['create-backup', 'download-backup', 'delete-backup']);

it('queues the backup rather than running it inside the request', function(): void {
    Queue::fake();

    Livewire::test(Backups::class)->call('create', BackupType::ONLY_DATABASE->value);

    Queue::assertPushed(CreateBackupJob::class, fn(CreateBackupJob $job): bool => $job->timeout === 80);
});

it('keeps the page from anyone who does not run the shop', function(): void {
    $this->actingAs(User::factory()->client()->create());

    expect(Backups::canAccess())->toBeFalse();
});

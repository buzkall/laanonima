<?php

use App\Support\WorkTrail;
use Illuminate\Support\Facades\Log;

/**
 * The trail exists for the failures a `catch` never sees, so what is worth
 * testing is exactly the two things a normal test never produces: a step
 * written before the work rather than after it, and a report filed from the
 * shutdown handler when the process ran out of time or heap.
 */
it('reports a fatal that ended the process, with what was being worked on', function(): void {
    Log::spy();

    $trail = WorkTrail::start('enrich', ['isbn13' => '9788433922069']);

    $trail->reportFatal([
        'type'    => E_ERROR,
        'message' => 'Maximum execution time of 120 seconds exceeded',
        'file'    => '/app/Actions/Books/EnrichImportedBook.php',
        'line'    => 84,
    ]);

    Log::shouldHaveReceived('error')->withArgs(
        fn(string $message, array $context): bool => $message === '[enrich] died'
            && $context['isbn13'] === '9788433922069'
            && str_contains($context['error'], 'Maximum execution time'),
    )->once();
});

it('says nothing about a trail that finished before the process ended', function(): void {
    $trail = WorkTrail::start('enrich', ['isbn13' => '9788433922069']);
    $trail->finish();

    Log::spy();

    $trail->reportFatal(['type' => E_ERROR, 'message' => 'something else', 'file' => 'x.php', 'line' => 1]);

    Log::shouldNotHaveReceived('error');
});

/* A warning or a notice is the ordinary end of a request. Reporting one would
   put a "died" line under every trail that did not happen to close last. */
it('ignores an error that is not a fatal', function(): void {
    $trail = WorkTrail::start('enrich', ['isbn13' => '9788433922069']);

    Log::spy();

    $trail->reportFatal(['type' => E_WARNING, 'message' => 'undefined array key', 'file' => 'x.php', 'line' => 1]);

    Log::shouldNotHaveReceived('error');
});

it('records the limits the process is actually running under', function(): void {
    Log::spy();

    WorkTrail::start('enrich', ['isbn13' => '9788433922069']);

    /* Read at the start and never computed again: a trail that stops at thirty
       seconds when `set_time_limit()` asked for a hundred and twenty says the
       ceiling belongs to the FPM pool and not to PHP, which is not something
       the application can find out any other way. */
    Log::shouldHaveReceived('log')->withArgs(
        fn(string $level, string $message, array $context): bool => $message === '[enrich] started'
            && array_key_exists('time_limit', $context)
            && array_key_exists('memory_limit', $context)
            && array_key_exists('pid', $context),
    )->once();
});

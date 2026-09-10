<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A breadcrumb trail through work that may not survive to describe itself.
 *
 * Everything the Cupida import defers runs in a terminating callback, and the
 * two things that actually kill it there are not exceptions: `max_execution_time`
 * exceeded and `memory_limit` exhausted are fatals that no `catch (Throwable)`
 * ever sees, and a child FPM terminates on `request_terminate_timeout` writes
 * nothing at all. So the existing warnings are silent at exactly the moment
 * something goes wrong, and the log shows a book filed with no cover and no
 * explanation beside it -- which is what a bookseller is looking at.
 *
 * Hence a line before every step rather than a summary after the last one: the
 * last breadcrumb written is where the process died, and that reads the same
 * whether it threw, timed out or was killed outright. `finish()` closes the
 * trail; a fatal instead of it is reported by the shutdown handler, which is
 * the only place PHP will still tell us "Maximum execution time of 120 seconds
 * exceeded" with the ISBN it happened on.
 *
 * The limits are recorded at the start on purpose. Knowing a trail stopped at
 * thirty seconds when `set_time_limit()` had asked for a hundred and twenty
 * says the ceiling is the pool's, not PHP's, and that is not something the
 * application can read any other way.
 */
class WorkTrail
{
    private float $startedAt;
    private float $lastStepAt;
    private bool $finished = false;

    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        private string $work,
        private array $context,
    ) {
        $this->startedAt = microtime(true);
        $this->lastStepAt = $this->startedAt;
    }

    /**
     * Open a trail and say what the process is working with.
     *
     * @param  array<string, mixed>  $context  what identifies this run -- an ISBN, an id
     */
    public static function start(string $work, array $context = []): self
    {
        $trail = new self($work, $context);

        if (! $trail->enabled()) {
            return $trail;
        }

        $trail->write('started', [
            'pid'          => getmypid(),
            'time_limit'   => (int)ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            /* How long this process had already been running when the deferred
               work began. A request that spent twenty seconds on a model starts
               its enrichment with twenty seconds of somebody else's clock on it,
               and that is invisible from inside the callback. */
            'request_age_ms' => defined('LARAVEL_START')
                ? (int)round((microtime(true) - LARAVEL_START) * 1000)
                : null,
        ]);

        register_shutdown_function($trail->reportFatal(...));

        return $trail;
    }

    /**
     * Write the breadcrumb before doing the thing, never after it.
     *
     * @param  array<string, mixed>  $context
     */
    public function step(string $what, array $context = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $now = microtime(true);

        $this->write($what, $context + ['since_last_ms' => (int)round(($now - $this->lastStepAt) * 1000)]);

        $this->lastStepAt = $now;
    }

    /**
     * Close the trail, so a fatal after this point is somebody else's.
     *
     * @param  array<string, mixed>  $context
     */
    public function finish(array $context = []): void
    {
        $this->finished = true;

        if (! $this->enabled()) {
            return;
        }

        $this->write('finished', $context + [
            'peak_memory' => $this->readableBytes(memory_get_peak_usage(true)),
        ]);
    }

    /**
     * The only report a timeout or an exhausted heap ever files.
     *
     * Nothing is said about a trail that merely ended without `finish()`: a
     * process killed outright never reaches here at all, and saying "unfinished"
     * for every other shutdown would bury the one line worth reading.
     *
     * Public and taking the error rather than only reading it, because a
     * shutdown handler cannot be reached from a test any other way -- and a
     * handler nobody has ever seen fire is a handler that quietly does nothing
     * on the one afternoon it is the whole point.
     *
     * @param  array{type: int, message: string, file: string, line: int}|null  $error  defaults to whatever ended the process
     */
    public function reportFatal(?array $error = null): void
    {
        if ($this->finished) {
            return;
        }

        $error ??= error_get_last();

        if ($error === null || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }

        try {
            Log::error("[{$this->work}] died", $this->context + [
                'error'       => $error['message'],
                'file'        => "{$error['file']}:{$error['line']}",
                'elapsed_ms'  => $this->elapsedMs(),
                'peak_memory' => $this->readableBytes(memory_get_peak_usage(true)),
            ]);
        } catch (Throwable) {
            /* A shutdown handler that throws replaces the fatal we are trying
               to report with one of its own. */
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $what, array $context): void
    {
        Log::log(
            (string)config('books.metadata.trace_level', 'info'),
            "[{$this->work}] {$what}",
            $this->context + $context + ['elapsed_ms' => $this->elapsedMs()],
        );
    }

    private function elapsedMs(): int
    {
        return (int)round((microtime(true) - $this->startedAt) * 1000);
    }

    private function readableBytes(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 1) . 'MB';
    }

    /**
     * Left on by default. This is a bookshop's catalog filling up a handful of
     * books at a time, not a hot path, and a trail nobody switched on is a trail
     * that is off on the afternoon it was needed.
     */
    private function enabled(): bool
    {
        return (bool)config('books.metadata.trace', true);
    }
}

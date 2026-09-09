<?php

namespace App\Support\Cupida;

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

/**
 * What one written recommendation cost the shop.
 *
 * The provider answers with token counts and no price, so a figure only exists
 * because `cupida.prices` keeps the rates and this multiplies them out. The
 * arithmetic is not the fragile part -- the rate list is. When the column stops
 * matching the invoice, that is where to look.
 *
 * A model with no rate on file is left with its tokens and no cost, because an
 * empty column says "we do not know what this cost" and a zero would say it was
 * free. Same reason nothing here throws: a price the shop cannot read is not
 * worth losing a reader's book over.
 *
 * "No rate on file" is rarer than it looks, though: the id that answers is the
 * dated one the alias resolved to, so `rates()` falls back to the longest
 * matching prefix rather than requiring the list to name every release.
 *
 * The model is read off the response rather than off `cupida.model`, because
 * the config says what was asked for and the response says what answered. They
 * are the same thing today and would stop being it the moment anything picks a
 * model another way -- an attribute, a per-request override, a provider that
 * substitutes one -- and a row that names the wrong model is priced wrong too.
 */
final readonly class PromptCost
{
    public function __construct(
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public ?float $usd,
    ) {}

    /**
     * Cache tokens are billed at their own rates and counted as input, which is
     * what they are: nothing prompts with a cache today, so both are zero, and
     * a prompt that starts caching is priced rather than quietly mispriced.
     */
    public static function of(Usage $usage, Meta $meta): self
    {
        /* A provider that names no model has still answered and still costs
           something, so fall back to what was asked for rather than throw a
           recommendation away over a missing label. */
        $model = $meta->model ?? (string)config('cupida.model');

        $inputTokens = $usage->promptTokens
            + $usage->cacheWriteInputTokens
            + $usage->cacheReadInputTokens;

        return new self(
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $usage->completionTokens,
            usd: self::usd($usage, $model),
        );
    }

    private static function usd(Usage $usage, string $model): ?float
    {
        $prices = self::rates($model);

        if ($prices === null) {
            return null;
        }

        $dollars = $usage->promptTokens * (float)($prices['input'] ?? 0)
            + $usage->completionTokens * (float)($prices['output'] ?? 0)
            + $usage->cacheWriteInputTokens * (float)($prices['cache_write'] ?? 0)
            + $usage->cacheReadInputTokens * (float)($prices['cache_read'] ?? 0);

        return round($dollars / 1_000_000, precision: 6);
    }

    /**
     * The rates for whatever answered.
     *
     * `cupida.model` is an alias -- "claude-haiku-4-5" -- and the API answers
     * with the version it resolved to: "claude-haiku-4-5-20251001". Looking the
     * response up by name alone therefore misses every real call and prices
     * nothing, while the tests, which fake the alias back, pass. So an exact
     * key wins and a prefix is the fallback, longest first, which is what keeps
     * a dated id off a rate meant for a different model.
     *
     * @return array<string, float|int>|null
     */
    private static function rates(string $model): ?array
    {
        /** @var array<string, array<string, float|int>> $prices */
        $prices = config('cupida.prices', []);

        if (is_array($prices[$model] ?? null)) {
            return $prices[$model];
        }

        $keys = array_keys($prices);

        usort($keys, fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($keys as $key) {
            if (str_starts_with($model, $key)) {
                return $prices[$key];
            }
        }

        return null;
    }
}

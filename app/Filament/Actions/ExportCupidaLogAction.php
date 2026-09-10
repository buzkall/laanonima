<?php

namespace App\Filament\Actions;

use App\Models\CupidaRecommendation;
use App\Support\Cupida\CupidaLog;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The log as a file, for the question the panel cannot answer.
 *
 * A card at a time says what a reader was handed. Whether the recommendations
 * are any good is a question about all of them at once, and the way the shop
 * asks it is by handing the run to a model -- so this downloads it in the shape
 * something else can read, prompt and shortlist included. See CupidaLog.
 *
 * It exports what is on screen, not the table. The filters above the cards are
 * how the question gets narrowed -- only the written ones, only the ones that
 * went past the shortlist, only this month once a date filter exists -- and an
 * export that ignored them would make every one of those a thing you had to do
 * again in the file.
 */
class ExportCupidaLogAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'exportCupidaLog';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hiddenLabel()
            ->icon(Heroicon::OutlinedArrowDownTray)
            /* Gray, like the credit button beside it: this is something the
               shop reaches for, not something the page is asking of it. */
            ->color('gray')
            ->action(function(): StreamedResponse {
                /* Resolved here and not inside the callback: the callback runs
                   while the response is being sent, and the query is a question
                   about the page as it was when the button was pressed. */
                $query = $this->exportedQuery();

                return response()->streamDownload(
                    function() use ($query): void {
                        app(CupidaLog::class)->stream($query);
                    },
                    'la-cupida-' . now()->format('Y-m-d-His') . '.json',
                    ['Content-Type' => 'application/json'],
                );
            });
    }

    /**
     * The rows the filters have left on screen.
     *
     * Filament builds that query on the page rather than on the action, and
     * hands back null before the table has been resolved -- which a header
     * action cannot reach anyway, but the whole log is the honest answer if it
     * ever does.
     *
     * @return Builder<CupidaRecommendation>
     */
    private function exportedQuery(): Builder
    {
        $livewire = $this->getLivewire();

        $query = $livewire instanceof HasTable
            ? $livewire->getFilteredSortedTableQuery()
            : null;

        return $query ?? CupidaRecommendation::query();
    }
}

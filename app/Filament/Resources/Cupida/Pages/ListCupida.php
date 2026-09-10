<?php

namespace App\Filament\Resources\Cupida\Pages;

use App\Filament\Actions\EditCupidaCreditAction;
use App\Filament\Actions\EditCupidaPromptAction;
use App\Filament\Actions\ExportCupidaLogAction;
use App\Filament\Resources\Cupida\CupidaResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCupida extends ListRecords
{
    protected static string $resource = CupidaResource::class;

    /**
     * The heading names the page, not the rows in it: see
     * CupidaResource::getBreadcrumb().
     */
    public function getTitle(): string|Htmlable
    {
        return __('cupida.admin.resource.title');
    }

    /**
     * No CreateAction: nothing here is authored. What the header offers instead
     * are the two things about La Cupida a bookseller owns -- what it is told
     * to say, and what is left on the account paying for it -- and the log
     * itself, for the question neither of them answers: whether any of this is
     * working.
     */
    protected function getHeaderActions(): array
    {
        return [
            EditCupidaCreditAction::make(),
            EditCupidaPromptAction::make(),
            ExportCupidaLogAction::make(),
        ];
    }
}

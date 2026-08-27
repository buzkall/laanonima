<?php

namespace App\Filament\Pages;

use App\Support\Qr\QrGenerator;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property-read Schema $form
 */
class QrCodeGenerator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;
    protected string $view = 'filament.pages.qr-code-generator';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['url' => config('app.url')]);
    }

    public static function getNavigationLabel(): string
    {
        return __('qr.navigation_label');
    }

    public function getTitle(): string
    {
        return __('qr.title');
    }

    public function getSubheading(): ?string
    {
        return __('qr.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('url')
                    ->label(__('qr.fields.url'))
                    ->url()
                    ->required()
                    ->maxLength(500)
                    ->live(debounce: 400)
                    ->helperText(__('qr.helpers.url')),
            ]);
    }

    /**
     * The preview redraws on every keystroke, so a half-typed URL must render
     * the placeholder rather than blow up the page.
     */
    public function getPreviewSvg(): ?string
    {
        $url = $this->data['url'] ?? null;

        if (! is_string($url) || blank($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return app(QrGenerator::class)->svg($url);
    }

    public function downloadSvgAction(): Action
    {
        return Action::make('downloadSvg')
            ->label(__('qr.actions.download_svg'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn(): StreamedResponse => $this->download(
                'svg',
                'image/svg+xml',
                fn(string $url): string => app(QrGenerator::class)->svg($url, 1024),
            ));
    }

    public function downloadThermalPngAction(): Action
    {
        return Action::make('downloadThermalPng')
            ->label(__('qr.actions.download_thermal'))
            ->icon(Heroicon::OutlinedPrinter)
            ->action(fn(): StreamedResponse => $this->download(
                'png',
                'image/png',
                fn(string $url): string => app(QrGenerator::class)->thermalPng($url),
            ));
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->downloadSvgAction(),
            $this->downloadThermalPngAction(),
        ];
    }

    private function download(string $extension, string $mimeType, Closure $generate): StreamedResponse
    {
        // getState() validates first, so an invalid URL surfaces as an inline
        // form error instead of a broken file download.
        $url = (string)$this->form->getState()['url'];
        $contents = $generate($url);

        return response()->streamDownload(
            fn(): int => print $contents,
            'qr-' . $this->fileSlug($url) . '.' . $extension,
            ['Content-Type' => $mimeType],
        );
    }

    /**
     * Names the file after the host and path so a batch of downloads for
     * different landing pages does not collapse into "file (1)", "file (2)".
     */
    private function fileSlug(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        $slug = Str::slug(str_replace(
            ['.', '/'],
            ['-', ' '],
            (is_string($host) ? $host : 'anonima') . ' ' . trim(is_string($path) ? $path : '', '/'),
        ));

        return Str::limit($slug, 60, '');
    }
}

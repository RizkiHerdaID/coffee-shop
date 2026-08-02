<?php

namespace App\Filament\Pages;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Pages\Page;

class QrCodes extends Page
{
    protected string $view = 'filament.pages.qr-codes';

    public static function getNavigationLabel(): string
    {
        return __('qr.nav_label');
    }

    public function getTitle(): string
    {
        return __('qr.admin_title');
    }

    protected function getViewData(): array
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(256, 4), new SvgImageBackEnd));

        $qrCodes = [];

        for ($table = 1; $table <= config('shop.tables'); $table++) {
            $qrCodes[$table] = 'data:image/svg+xml;base64,'.base64_encode(
                $writer->writeString(route('qr.menu', ['table' => $table]))
            );
        }

        return ['qrCodes' => $qrCodes];
    }
}

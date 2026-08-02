<?php

namespace App\Filament\Resources\Promos\Schemas;

use App\Exceptions\MissingAiKeyException;
use App\Services\AiCopyService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

class PromoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('promos.fields.title'))
                    ->required(),
                TextInput::make('subtitle')
                    ->label(__('promos.fields.subtitle'))
                    ->suffixAction(
                        Action::make('generateAiSubtitle')
                            ->label(__('promos.ai.generate_label'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->requiresConfirmation()
                            ->visible(fn (): bool => filled(config('services.deepseek.api_key')))
                            ->action(function (Get $get, Set $set): void {
                                $title = $get('title');

                                if (blank($title)) {
                                    Notification::make()
                                        ->title(__('promos.ai.notification.title_required'))
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                try {
                                    $subtitle = app(AiCopyService::class)->generatePromoSubtitle($title);

                                    $set('subtitle', $subtitle);

                                    Notification::make()
                                        ->title(__('promos.ai.notification.generated'))
                                        ->success()
                                        ->send();
                                } catch (MissingAiKeyException) {
                                    Notification::make()
                                        ->title(__('promos.ai.notification.no_key_title'))
                                        ->body(__('promos.ai.notification.no_key_body'))
                                        ->danger()
                                        ->send();
                                } catch (RuntimeException $exception) {
                                    Notification::make()
                                        ->title(__('promos.ai.notification.failed_title'))
                                        ->body(__('promos.ai.notification.failed_body', [
                                            'reason' => $exception->getMessage(),
                                        ]))
                                        ->danger()
                                        ->send();
                                }
                            })
                    ),
                TextInput::make('badge')
                    ->label(__('promos.fields.badge')),
                TextInput::make('cta_text')
                    ->label(__('promos.fields.cta_text')),
                TextInput::make('cta_url')
                    ->label(__('promos.fields.cta_url'))
                    ->url()
                    ->placeholder('https://…'),
                DateTimePicker::make('starts_at')
                    ->label(__('promos.fields.starts_at'))
                    ->default(now())
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->label(__('promos.fields.ends_at')),
                Toggle::make('active')
                    ->label(__('promos.fields.active'))
                    ->default(true),
                TextInput::make('sort_order')
                    ->label(__('promos.fields.sort_order'))
                    ->numeric()
                    ->default(0),
            ]);
    }
}

<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Exceptions\MissingAiKeyException;
use App\Services\AiCopyService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use RuntimeException;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('menu-items.fields.name'))
                    ->required(),
                TextInput::make('price')
                    ->label(__('menu-items.fields.price'))
                    ->required()
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(function ($state) {
                        if (blank($state)) {
                            return $state;
                        }

                        if (str_contains((string) $state, '.')) {
                            return $state;
                        }

                        return number_format((int) $state, 0, ',', '.');
                    })
                    ->dehydrateStateUsing(function ($state) {
                        $cleaned = str_replace('.', '', (string) $state);

                        return $cleaned === '' ? null : (int) $cleaned;
                    }),
                TextInput::make('note')
                    ->label(__('menu-items.fields.note'))
                    ->suffixAction(
                        Action::make('generateAiNote')
                            ->label(__('ai-copy.form.generate_label'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->requiresConfirmation()
                            ->visible(fn (): bool => filled(config('services.deepseek.api_key')))
                            ->action(function (Get $get, Set $set): void {
                                $name = $get('name');

                                if (blank($name)) {
                                    Notification::make()
                                        ->title(__('ai-copy.notification.name_required'))
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                try {
                                    $price = $get('price');

                                    $note = app(AiCopyService::class)->generateDescription(
                                        $name,
                                        filled($price) ? (int) str_replace('.', '', (string) $price) : null,
                                    );

                                    $set('note', $note);

                                    Notification::make()
                                        ->title(__('ai-copy.notification.generated'))
                                        ->success()
                                        ->send();
                                } catch (MissingAiKeyException) {
                                    Notification::make()
                                        ->title(__('ai-copy.notification.no_key_title'))
                                        ->body(__('ai-copy.notification.no_key_body'))
                                        ->danger()
                                        ->send();
                                } catch (RuntimeException $exception) {
                                    Notification::make()
                                        ->title(__('ai-copy.notification.failed_title'))
                                        ->body(__('ai-copy.notification.failed_body', [
                                            'reason' => $exception->getMessage(),
                                        ]))
                                        ->danger()
                                        ->send();
                                }
                            })
                    ),
                FileUpload::make('photo')
                    ->label(__('menu-items.fields.photo'))
                    ->disk('public')
                    ->image(),
                Select::make('category')
                    ->label(__('menu-items.fields.category'))
                    ->options([
                        'coffee' => __('menu.categories.coffee'),
                        'non-coffee' => __('menu.categories.non-coffee'),
                        'snack' => __('menu.categories.snack'),
                        'food' => __('menu.categories.food'),
                    ]),
                Toggle::make('available')
                    ->label(__('menu-items.fields.available'))
                    ->default(true),
                CheckboxList::make('badges')
                    ->label(__('menu.badges.label'))
                    ->options([
                        'vegan' => __('menu.badges.vegan'),
                        'spicy' => __('menu.badges.spicy'),
                        'gluten_free' => __('menu.badges.gluten_free'),
                        'halal' => __('menu.badges.halal'),
                    ])
                    ->columns(2),
                TextInput::make('sort_order')
                    ->label(__('menu-items.fields.sort_order'))
                    ->numeric()
                    ->default(0),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('testimonials.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('rating')
                    ->label(__('testimonials.fields.rating'))
                    ->options(fn (): array => array_combine(range(1, 5), range(1, 5)))
                    ->default(5)
                    ->required(),
                Textarea::make('text')
                    ->label(__('testimonials.fields.text'))
                    ->required()
                    ->rows(4),
                Toggle::make('visible')
                    ->label(__('testimonials.fields.visible'))
                    ->default(true),
                TextInput::make('sort_order')
                    ->label(__('testimonials.fields.sort_order'))
                    ->numeric()
                    ->default(0),
            ]);
    }
}

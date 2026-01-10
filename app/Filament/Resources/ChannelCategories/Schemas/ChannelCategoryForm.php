<?php

namespace App\Filament\Resources\ChannelCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ChannelCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_ka')
                    ->required(),
                TextInput::make('name_en')
                    ->required(),
                Textarea::make('description_en')
                    ->columnSpanFull(),
                Textarea::make('description_ka')
                    ->columnSpanFull(),
                TextInput::make('icon_url')
                    ->url(),
            ]);
    }
}

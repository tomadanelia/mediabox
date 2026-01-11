<?php

namespace App\Filament\Resources\ChannelCategories;

use App\Filament\Resources\ChannelCategories\Pages\CreateChannelCategory;
use App\Filament\Resources\ChannelCategories\Pages\EditChannelCategory;
use App\Filament\Resources\ChannelCategories\Pages\ListChannelCategories;
use App\Models\ChannelCategory;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChannelCategoryResource extends Resource
{
    protected static ?string $model = ChannelCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name_en';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name_ka')->required()->label('Name (KA)'),
                TextInput::make('name_en')->required()->label('Name (EN)'),
                TextInput::make('external_id')->label('External ID'),
                TextInput::make('icon_url')->label('Icon URL'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ka')->searchable()->sortable(),
                TextColumn::make('name_en')->searchable(),
            ])
            ->filters([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannelCategories::route('/'),
            'create' => CreateChannelCategory::route('/create'),
            'edit' => EditChannelCategory::route('/{record}/edit'),
        ];
    }
}
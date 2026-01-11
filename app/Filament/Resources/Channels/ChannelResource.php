<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelResource\Pages;
use App\Models\Channel;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Layout\Section;
use Filament\Forms\Components\Layout\Grid;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|BackedEnum|null $navigationIcon =  Heroicon::RectangleStack;
   public static function schema(Schema $schema): Schema
    {
        return $schema
            ->columns([

                // Section 1: Basic Info
                /** @phpstan-ignore-next-line */
                Section::make('Display Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name_ka')
                                ->label('Name (Georgian)')
                                ->required(),
                            TextInput::make('name_en')
                                ->label('Name (English)')
                                ->required(),
                        ]),
                        Textarea::make('description_ka')
                            ->label('Description (KA)'),
                        Textarea::make('description_en')
                            ->label('Description (EN)'),
                    ]),

                // Section 2: Settings & Categorization
                Section::make('Configuration')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('category_id')
                                ->relationship('category', 'name_ka')
                                ->required()
                                ->searchable()
                                ->preload(),
                            
                            TextInput::make('number')
                                ->numeric()
                                ->label('Channel Number')
                                ->required(),
                        ]),
                        
                            Grid::make(3)->schema([
                            Toggle::make('is_active')
                         ->label('Active')
                          ->default(true)
                          ->inline(),

                          Toggle::make('is_vip_only')
                          ->label('VIP Only')
                          ->default(false)
                          ->inline(),
                        ]),
                    ]),

                // Section 3: Technical / Sync Data (Read Only recommended)
                Section::make('Sync Data')
                    ->description('Data synced from MediaBox API. Avoid changing manually.')
                    ->collapsed()
                    ->schema([
                        TextInput::make('external_id')
                            ->label('UID (External)')
                            ->readOnly(),
                        
                        TextInput::make('epg_id')
                            ->label('EPG ID')
                            ->readOnly(),

                        TextInput::make('icon_url')
                            ->label('Icon URL')
                            ->suffixIcon('heroicon-m-globe-alt'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->sortable()
                    ->label('#'),

                ImageColumn::make('icon_url')
                    ->label('Logo')
                    ->circular(),

                TextColumn::make('name_ka')
                    ->searchable()
                    ->sortable()
                    ->label('Name'),

                TextColumn::make('category.name_ka')
                    ->label('Category')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                IconColumn::make('is_vip_only')
                    ->boolean()
                    ->label('VIP'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('number', 'asc')
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name_ka'),
                
                TernaryFilter::make('is_active'),
                TernaryFilter::make('is_vip_only'),
            ]);
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
            'index' => Pages\ListChannels::route('/'),
            'create' => Pages\CreateChannel::route('/create'),
            'edit' => Pages\EditChannel::route('/{record}/edit'),
        ];
    }
}
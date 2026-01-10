<?php

namespace App\Filament\Resources\ChannelCategories\Pages;

use App\Filament\Resources\ChannelCategories\ChannelCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChannelCategories extends ListRecords
{
    protected static string $resource = ChannelCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

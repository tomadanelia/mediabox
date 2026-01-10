<?php

namespace App\Filament\Resources\ChannelCategories\Pages;

use App\Filament\Resources\ChannelCategories\ChannelCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChannelCategory extends EditRecord
{
    protected static string $resource = ChannelCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

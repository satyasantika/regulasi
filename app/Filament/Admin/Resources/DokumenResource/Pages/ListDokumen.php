<?php

namespace App\Filament\Admin\Resources\DokumenResource\Pages;

use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDokumen extends ListRecords
{
    protected static string $resource = DokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Tab status publikasi (F4.2).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tab = ['semua' => Tab::make('Semua')];

        foreach (StatusPublikasi::cases() as $status) {
            $tab[$status->value] = Tab::make($status->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status_publikasi', $status->value));
        }

        return $tab;
    }
}

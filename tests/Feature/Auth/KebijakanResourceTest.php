<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

test('setiap resource filament memakai policy', function () {
    $tanpaPolicy = [];

    foreach (Filament::getPanels() as $panel) {
        foreach ($panel->getResources() as $resource) {
            if (Gate::getPolicyFor($resource::getModel()) === null) {
                $tanpaPolicy[] = $resource;
            }
        }
    }

    expect($tanpaPolicy)->toBe([]);
});

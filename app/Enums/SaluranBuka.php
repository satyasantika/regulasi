<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Saluran pembukaan tautan (03-SKEMA §2). Pratinjau sematan Drive tidak tercatat (dimuat langsung dari Google).
 */
enum SaluranBuka: string implements HasLabel
{
    case Web = 'web';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Api => 'API',
        };
    }
}

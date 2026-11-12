<?php

namespace App\Helpers;

use App\Support\LiveEngineConfig;

class SystemSetting
{
    /**
     * Determine whether market rates, sessions, and odds are enabled.
     */
    public static function isMarketRatesEnabled(): bool
    {
        return LiveEngineConfig::isMarketRatesEnabled();
    }
}


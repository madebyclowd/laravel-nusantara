<?php

namespace MadeByClowd\Nusantara\Facades;

use Illuminate\Support\Facades\Facade;

class Nusantara extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'nusantara';
    }
}

<?php

namespace REAZON\PWdocx\Facades;

use Illuminate\Support\Facades\Facade as BaseFacade;

class GoDrive extends BaseFacade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'godrive';
    }
}

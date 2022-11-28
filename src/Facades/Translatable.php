<?php

namespace Hellotess\Translatable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hellotess\Translatable\Translatable
 */
class Translatable extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'translatable';
    }
}

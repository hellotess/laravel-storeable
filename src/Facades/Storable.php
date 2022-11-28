<?php

namespace Hellotess\Storable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hellotess\Storable\Storable
 */
class Storable extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'storable';
    }
}

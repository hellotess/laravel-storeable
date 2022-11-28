<?php

namespace Hellotess\Storable\Exceptions;

use Exception;

class AttributeIsNotStorable extends Exception
{
    public static function make(string $key, $model): static
    {
        $storableAttributes = implode(', ', $model->getStorableAttributes());

        return new static("Cannot translate attribute `{$key}` as it's not one of the storable attributes: `$storableAttributes`");
    }
}

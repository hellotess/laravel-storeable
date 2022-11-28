<?php

namespace Hellotess\Storable\Events;

class StorevalueHasBeenSetEvent
{
    public function __construct(
        public mixed $model,
        public string $key,
        public string $store,
        public mixed $oldValue,
        public mixed $newValue,
    ) {
        //
    }
}

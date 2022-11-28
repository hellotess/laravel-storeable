<?php

namespace Hellotess\Storable;

use Closure;

class Storable
{
    /*
     * If a translation has not been set for a given locale, use this locale instead.
     */
    public ?string $fallbackStore;

    /*
     * If a translation has not been set for a given locale and the fallback locale,
     * any other locale will be chosen instead.
     */
    public bool $fallbackAny = false;

    public ?Closure $missingKeyCallback = null;

    public function fallback(
        ?string $fallbackStore = null,
        ?bool $fallbackAny = false,
        $missingKeyCallback = null
    ): self {
        $this->fallbackStore = $fallbackStore;
        $this->fallbackAny = $fallbackAny;
        $this->missingKeyCallback = $missingKeyCallback;

        return $this;
    }
}

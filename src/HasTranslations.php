<?php

namespace Hellotess\Storable;

use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Hellotess\Storable\Events\StorevalueHasBeenSetEvent;
use Hellotess\Storable\Exceptions\AttributeIsNotStorable;

trait HasTranslations
{
    protected ?string $storevalueStore = null;

    public static function usingLocale(string $store): self
    {
        return (new self())->setLocale($store);
    }

    public function getAttributeValue($key): mixed
    {
        if (! $this->isStorableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getStorevalue($key, $this->getLocale());
    }

    public function setAttribute($key, $value)
    {
        if ($this->isStorableAttribute($key) && is_array($value)) {
            return $this->setStorevalues($key, $value);
        }

        // Pass arrays and unstorable attributes to the parent method.
        if (! $this->isStorableAttribute($key) || is_array($value)) {
            return parent::setAttribute($key, $value);
        }

        // If the attribute is storable and not already translated, set a
        // translation for the current app locale.
        return $this->setStorevalue($key, $this->getLocale(), $value);
    }

    public function translate(string $key, string $store = '', bool $useFallbackStore = true): mixed
    {
        return $this->getStorevalue($key, $store, $useFallbackStore);
    }

    public function getStorevalue(string $key, string $store, bool $useFallbackStore = true): mixed
    {
        $normalizedStore = $this->normalizeStore($key, $store, $useFallbackStore);

        $isKeyMissingFromStore = ($store !== $normalizedStore);

        $storevalues = $this->getStorevalues($key);

        $storevalue = $storevalues[$normalizedStore] ?? '';

        $storableConfig = app(Storable::class);

        if ($isKeyMissingFromStore && $storableConfig->missingKeyCallback) {
            try {
                $callbackReturnValue = (app(Storable::class)->missingKeyCallback)($this, $key, $store, $storevalue, $normalizedStore);
                if (is_string($callbackReturnValue)) {
                    $storevalue = $callbackReturnValue;
                }
            } catch (Exception) {
                //prevent the fallback to crash
            }
        }

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $storevalue);
        }

        return $storevalue;
    }

    public function getStorevalueWithFallback(string $key, string $store): mixed
    {
        return $this->getStorevalue($key, $store, true);
    }

    public function getStorevalueWithoutFallback(string $key, string $store): mixed
    {
        return $this->getStorevalue($key, $store, false);
    }

    public function getStorevalues(string $key = null, array $allowedStores = null): array
    {
        if ($key !== null) {
            $this->guardAgainstNonStorableAttribute($key);

            return array_filter(
                json_decode($this->getAttributes()[$key] ?? '' ?: '{}', true) ?: [],
                fn ($value, $store) => $this->filterStorevalues($value, $store, $allowedStores),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        return array_reduce($this->getStorableAttributes(), function ($result, $item) use ($allowedStores) {
            $result[$item] = $this->getStorevalues($item, $allowedStores);

            return $result;
        });
    }

    public function setStorevalue(string $key, string $store, $value): self
    {
        $this->guardAgainstNonStorableAttribute($key);

        $storevalues = $this->getStorevalues($key);

        $oldValue = $storevalues[$store] ?? '';

        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            $this->{$method}($value, $store);

            $value = $this->attributes[$key];
        }

        $storevalues[$store] = $value;

        $this->attributes[$key] = $this->asJson($storevalues);

        event(new StorevalueHasBeenSetEvent($this, $key, $store, $oldValue, $value));

        return $this;
    }

    public function setStorevalues(string $key, array $storevalues): self
    {
        $this->guardAgainstNonStorableAttribute($key);

        if (! empty($storevalues)) {
            foreach ($storevalues as $store => $storevalue) {
                $this->setStorevalue($key, $store, $storevalue);
            }
        } else {
            $this->attributes[$key] = $this->asJson([]);
        }

        return $this;
    }

    public function forgetStorevalue(string $key, string $store): self
    {
        $storevalues = $this->getStorevalues($key);

        unset(
            $storevalues[$store],
            $this->$key
        );

        $this->setStorevalues($key, $storevalues);

        return $this;
    }

    public function forgetStorevalues(string $key, bool $asNull = false): self
    {
        $this->guardAgainstNonStorableAttribute($key);

        collect($this->getValuedStores($key))->each(function (string $store) use ($key) {
            $this->forgetStorevalue($key, $store);
        });

        if ($asNull) {
            $this->attributes[$key] = null;
        }

        return $this;
    }

    public function forgetAllTranslations(string $store): self
    {
        collect($this->getStorableAttributes())->each(function (string $attribute) use ($store) {
            $this->forgetStorevalue($attribute, $store);
        });

        return $this;
    }

    public function getValuedStores(string $key): array
    {
        return array_keys($this->getStorevalues($key));
    }

    public function isStorableAttribute(string $key): bool
    {
        return in_array($key, $this->getStorableAttributes());
    }

    public function hasTranslation(string $key, string $store = null): bool
    {
        $store = $store ?: $this->getLocale();

        return isset($this->getStorevalues($key)[$store]);
    }

    public function replaceTranslations(string $key, array $storevalues): self
    {
        foreach ($this->getValuedStores($key) as $store) {
            $this->forgetStorevalue($key, $store);
        }

        $this->setStorevalues($key, $storevalues);

        return $this;
    }

    protected function guardAgainstNonStorableAttribute(string $key): void
    {
        if (! $this->isStorableAttribute($key)) {
            throw AttributeIsNotStorable::make($key, $this);
        }
    }

    protected function normalizeStore(string $key, string $store, bool $useFallbackStore): string
    {
        $valuedStores = $this->getValuedStores($key);

        if (in_array($store, $valuedStores)) {
            return $store;
        }

        if (! $useFallbackStore) {
            return $store;
        }

        $fallbackConfig = app(Storable::class);

        $fallbackStore = $fallbackConfig->fallbackStore ?? config('app.fallback_locale');

        if (! is_null($fallbackStore) && in_array($fallbackStore, $valuedStores)) {
            return $fallbackStore;
        }

        if (! empty($valuedStores) && $fallbackConfig->fallbackAny) {
            return $valuedStores[0];
        }

        return $store;
    }

    protected function filterStorevalues(mixed $value = null, string $store = null, array $allowedStores = null): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value === '') {
            return false;
        }

        if ($allowedStores === null) {
            return true;
        }

        if (! in_array($store, $allowedStores)) {
            return false;
        }

        return true;
    }

    public function setLocale(string $store): self
    {
        $this->storevalueStore = $store;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->storevalueStore ?: config('app.locale');
    }

    public function getStorableAttributes(): array
    {
        return is_array($this->storable)
            ? $this->storable
            : [];
    }

    public function translations(): Attribute
    {
        return Attribute::get(function () {
            return collect($this->getStorableAttributes())
                ->mapWithKeys(function (string $key) {
                    return [$key => $this->getStorevalues($key)];
                })
                ->toArray();
        });
    }

    public function getCasts(): array
    {
        return array_merge(
            parent::getCasts(),
            array_fill_keys($this->getStorableAttributes(), 'array'),
        );
    }

    public function locales(): array
    {
        return array_unique(
            array_reduce($this->getStorableAttributes(), function ($result, $item) {
                return array_merge($result, $this->getValuedStores($item));
            }, [])
        );
    }
}

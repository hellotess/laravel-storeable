<?php

namespace Hellotess\Storable;

use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Hellotess\Storable\Events\TranslationHasBeenSetEvent;
use Hellotess\Storable\Exceptions\AttributeIsNotStorable;

trait HasTranslations
{
    protected ?string $translationLocale = null;

    public static function usingLocale(string $locale): self
    {
        return (new self())->setLocale($locale);
    }

    public function getAttributeValue($key): mixed
    {
        if (! $this->isStorableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key, $this->getLocale());
    }

    public function setAttribute($key, $value)
    {
        if ($this->isStorableAttribute($key) && is_array($value)) {
            return $this->setTranslations($key, $value);
        }

        // Pass arrays and unstorable attributes to the parent method.
        if (! $this->isStorableAttribute($key) || is_array($value)) {
            return parent::setAttribute($key, $value);
        }

        // If the attribute is storable and not already translated, set a
        // translation for the current app locale.
        return $this->setTranslation($key, $this->getLocale(), $value);
    }

    public function translate(string $key, string $locale = '', bool $useFallbackLocale = true): mixed
    {
        return $this->getTranslation($key, $locale, $useFallbackLocale);
    }

    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed
    {
        $normalizedLocale = $this->normalizeLocale($key, $locale, $useFallbackLocale);

        $isKeyMissingFromLocale = ($locale !== $normalizedLocale);

        $translations = $this->getTranslations($key);

        $translation = $translations[$normalizedLocale] ?? '';

        $storableConfig = app(Storable::class);

        if ($isKeyMissingFromLocale && $storableConfig->missingKeyCallback) {
            try {
                $callbackReturnValue = (app(Storable::class)->missingKeyCallback)($this, $key, $locale, $translation, $normalizedLocale);
                if (is_string($callbackReturnValue)) {
                    $translation = $callbackReturnValue;
                }
            } catch (Exception) {
                //prevent the fallback to crash
            }
        }

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $translation);
        }

        return $translation;
    }

    public function getTranslationWithFallback(string $key, string $locale): mixed
    {
        return $this->getTranslation($key, $locale, true);
    }

    public function getTranslationWithoutFallback(string $key, string $locale): mixed
    {
        return $this->getTranslation($key, $locale, false);
    }

    public function getTranslations(string $key = null, array $allowedLocales = null): array
    {
        if ($key !== null) {
            $this->guardAgainstNonStorableAttribute($key);

            return array_filter(
                json_decode($this->getAttributes()[$key] ?? '' ?: '{}', true) ?: [],
                fn ($value, $locale) => $this->filterTranslations($value, $locale, $allowedLocales),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        return array_reduce($this->getStorableAttributes(), function ($result, $item) use ($allowedLocales) {
            $result[$item] = $this->getTranslations($item, $allowedLocales);

            return $result;
        });
    }

    public function setTranslation(string $key, string $locale, $value): self
    {
        $this->guardAgainstNonStorableAttribute($key);

        $translations = $this->getTranslations($key);

        $oldValue = $translations[$locale] ?? '';

        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            $this->{$method}($value, $locale);

            $value = $this->attributes[$key];
        }

        $translations[$locale] = $value;

        $this->attributes[$key] = $this->asJson($translations);

        event(new TranslationHasBeenSetEvent($this, $key, $locale, $oldValue, $value));

        return $this;
    }

    public function setTranslations(string $key, array $translations): self
    {
        $this->guardAgainstNonStorableAttribute($key);

        if (! empty($translations)) {
            foreach ($translations as $locale => $translation) {
                $this->setTranslation($key, $locale, $translation);
            }
        } else {
            $this->attributes[$key] = $this->asJson([]);
        }

        return $this;
    }

    public function forgetTranslation(string $key, string $locale): self
    {
        $translations = $this->getTranslations($key);

        unset(
            $translations[$locale],
            $this->$key
        );

        $this->setTranslations($key, $translations);

        return $this;
    }

    public function forgetTranslations(string $key, bool $asNull = false): self
    {
        $this->guardAgainstNonStorableAttribute($key);

        collect($this->getTranslatedLocales($key))->each(function (string $locale) use ($key) {
            $this->forgetTranslation($key, $locale);
        });

        if ($asNull) {
            $this->attributes[$key] = null;
        }

        return $this;
    }

    public function forgetAllTranslations(string $locale): self
    {
        collect($this->getStorableAttributes())->each(function (string $attribute) use ($locale) {
            $this->forgetTranslation($attribute, $locale);
        });

        return $this;
    }

    public function getTranslatedLocales(string $key): array
    {
        return array_keys($this->getTranslations($key));
    }

    public function isStorableAttribute(string $key): bool
    {
        return in_array($key, $this->getStorableAttributes());
    }

    public function hasTranslation(string $key, string $locale = null): bool
    {
        $locale = $locale ?: $this->getLocale();

        return isset($this->getTranslations($key)[$locale]);
    }

    public function replaceTranslations(string $key, array $translations): self
    {
        foreach ($this->getTranslatedLocales($key) as $locale) {
            $this->forgetTranslation($key, $locale);
        }

        $this->setTranslations($key, $translations);

        return $this;
    }

    protected function guardAgainstNonStorableAttribute(string $key): void
    {
        if (! $this->isStorableAttribute($key)) {
            throw AttributeIsNotStorable::make($key, $this);
        }
    }

    protected function normalizeLocale(string $key, string $locale, bool $useFallbackLocale): string
    {
        $translatedLocales = $this->getTranslatedLocales($key);

        if (in_array($locale, $translatedLocales)) {
            return $locale;
        }

        if (! $useFallbackLocale) {
            return $locale;
        }

        $fallbackConfig = app(Storable::class);

        $fallbackLocale = $fallbackConfig->fallbackLocale ?? config('app.fallback_locale');

        if (! is_null($fallbackLocale) && in_array($fallbackLocale, $translatedLocales)) {
            return $fallbackLocale;
        }

        if (! empty($translatedLocales) && $fallbackConfig->fallbackAny) {
            return $translatedLocales[0];
        }

        return $locale;
    }

    protected function filterTranslations(mixed $value = null, string $locale = null, array $allowedLocales = null): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value === '') {
            return false;
        }

        if ($allowedLocales === null) {
            return true;
        }

        if (! in_array($locale, $allowedLocales)) {
            return false;
        }

        return true;
    }

    public function setLocale(string $locale): self
    {
        $this->translationLocale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->translationLocale ?: config('app.locale');
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
                    return [$key => $this->getTranslations($key)];
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
                return array_merge($result, $this->getTranslatedLocales($item));
            }, [])
        );
    }
}

<?php

use Illuminate\Support\Facades\Storage;
use Hellotess\Storable\Exceptions\AttributeIsNotStorable;
use Hellotess\Storable\Facades\Storable;
use Hellotess\Storable\Test\TestSupport\TestModel;

beforeEach(function () {
    $this->testModel = new TestModel();
});

it('will return package fallback locale translation when getting an unknown locale', function () {
    config()->set('app.fallback_locale', 'nl');
    Storable::fallback(
        fallbackStore: 'en',
    );

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'fr'))->toBe('testValue_en');
});

it('will return default fallback locale translation when getting an unknown locale', function () {
    config()->set('app.fallback_locale', 'en');

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'fr'))->toBe('testValue_en');
});

it('provides a flog to not return fallback locale translation when getting an unknown locale', function () {
    config()->set('app.fallback_locale', 'en');

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'fr', false))->toBe('');
});

it('will return fallback locale translation when getting an unknown locale and fallback is true', function () {
    config()->set('app.fallback_locale', 'en');

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalueWithFallback('name', 'fr'))->toBe('testValue_en');
});

it('will execute callback fallback when getting an unknown locale and fallback callback is enabled', function () {
    Storage::fake();

    Storable::fallback(missingKeyCallback: function ($model, string $storevalueKey, string $store) {
        //something assertable outside the closure
        Storage::put("test.txt", "test");
    });

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalueWithFallback('name', 'fr'))->toBe('testValue_en');

    Storage::assertExists("test.txt");
});

it('will use callback fallback return value as translation', function () {
    Storable::fallback(missingKeyCallback: function ($model, string $storevalueKey, string $store) {
        return "testValue_fallback_callback";
    });

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalueWithFallback('name', 'fr'))->toBe('testValue_fallback_callback');
});

it('wont use callback fallback return value as translation if it is not a string', function () {
    Storable::fallback(missingKeyCallback: function ($model, string $storevalueKey, string $store) {
        return 123456;
    });

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalueWithFallback('name', 'fr'))->toBe('testValue_en');
});

it('wont execute callback fallback when getting an existing translation', function () {
    Storage::fake();

    Storable::fallback(missingKeyCallback: function ($model, string $storevalueKey, string $store) {
        //something assertable outside the closure
        Storage::put("test.txt", "test");
    });

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalueWithFallback('name', 'en'))->toBe('testValue_en');

    Storage::assertMissing("test.txt");
});

it('wont fail if callback fallback throw exception', function () {
    Storable::fallback(missingKeyCallback: function ($model, string $storevalueKey, string $store) {
        throw new \Exception();
    });

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalueWithFallback('name', 'fr'))->toBe('testValue_en');
});

it('will return an empty string when getting an unknown locale and fallback is not set', function () {
    config()->set('app.fallback_locale', '');

    Storable::fallback(
        fallbackStore: '',
    );

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalueWithoutFallback('name', 'fr'))->toBe('');
});

it('will return an empty string when getting an unknown locale and fallback is empty', function () {
    config()->set('app.fallback_locale', '');

    Storable::fallback(
        fallbackStore: '',
    );

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'fr'))->toBe('');
});

it('can save a translated attribute', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->name)->toBe('testValue_en');
});

it('can set translated values when creating a model', function () {
    $model = TestModel::create([
        'name' => ['en' => 'testValue_en'],
    ]);

    expect($model->name)->toBe('testValue_en');
});

it('can save multiple translations', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    expect($this->testModel->name)->toBe('testValue_en');
    expect($this->testModel->getStorevalue('name', 'fr'))->toBe('testValue_fr');
});

it('will return the value of the current locale when using the property', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    app()->setLocale('fr');

    expect($this->testModel->name)->toBe('testValue_fr');
});

it('can get all translations in one go', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('name'));
});

it('can get specified translations in one go', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'en' => 'testValue_en',
    ], $this->testModel->getStorevalues('name', ['en']));
});

it('can get all translations for all storable attributes in one go', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');

    $this->testModel->setStorevalue('other_field', 'en', 'testValue_en');
    $this->testModel->setStorevalue('other_field', 'fr', 'testValue_fr');

    $this->testModel->setStorevalue('field_with_mutator', 'en', 'testValue_en');
    $this->testModel->setStorevalue('field_with_mutator', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'name' => [
            'en' => 'testValue_en',
            'fr' => 'testValue_fr',
        ],
        'other_field' => [
            'en' => 'testValue_en',
            'fr' => 'testValue_fr',
        ],
        'field_with_mutator' => [
            'en' => 'testValue_en',
            'fr' => 'testValue_fr',
        ],
    ], $this->testModel->getStorevalues());
});

it('can get specified translations for all storable attributes in one go', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');

    $this->testModel->setStorevalue('other_field', 'en', 'testValue_en');
    $this->testModel->setStorevalue('other_field', 'fr', 'testValue_fr');

    $this->testModel->setStorevalue('field_with_mutator', 'en', 'testValue_en');
    $this->testModel->setStorevalue('field_with_mutator', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'name' => ['en' => 'testValue_en'],
        'other_field' => ['en' => 'testValue_en'],
        'field_with_mutator' => ['en' => 'testValue_en'],
    ], $this->testModel->getStorevalues(null, ['en']));
});

it('can get the locales which have a translation', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    expect($this->testModel->getValuedStores('name'))->toBe(['en', 'fr']);
});

it('can forget a translation', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('name'));

    $this->testModel->forgetStorevalue('name', 'en');

    $this->assertSame([
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('name'));
});

it('can forget all translations of field', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('name'));

    $this->testModel->forgetStorevalues('name');

    expect($this->testModel->getAttributes()['name'])->toBe('[]');
    expect($this->testModel->getStorevalues('name'))->toBe([]);

    $this->testModel->save();

    expect($this->testModel->fresh()->getAttributes()['name'])->toBe('[]');
    expect($this->testModel->fresh()->getStorevalues('name'))->toBe([]);
});

it('can forget all translations of field and make field null', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('name'));

    $this->testModel->forgetStorevalues('name', true);

    expect($this->testModel->getAttributes()['name'])->toBeNull();
    expect($this->testModel->getStorevalues('name'))->toBe([]);

    $this->testModel->save();

    expect($this->testModel->fresh()->getAttributes()['name'])->toBeNull();
    expect($this->testModel->fresh()->getStorevalues('name'))->toBe([]);
});

it('can forget a field with mutator translation', function () {
    $this->testModel->setStorevalue('field_with_mutator', 'en', 'testValue_en');
    $this->testModel->setStorevalue('field_with_mutator', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('field_with_mutator'));

    $this->testModel->forgetStorevalue('field_with_mutator', 'en');

    $this->assertSame([
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('field_with_mutator'));
});

it('can forget all translations', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');

    $this->testModel->setStorevalue('other_field', 'en', 'testValue_en');
    $this->testModel->setStorevalue('other_field', 'fr', 'testValue_fr');

    $this->testModel->setStorevalue('field_with_mutator', 'en', 'testValue_en');
    $this->testModel->setStorevalue('field_with_mutator', 'fr', 'testValue_fr');
    $this->testModel->save();

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('name'));

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('other_field'));

    $this->assertSame([
        'en' => 'testValue_en',
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('field_with_mutator'));

    $this->testModel->forgetAllTranslations('en');

    $this->assertSame([
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('name'));

    $this->assertSame([
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('other_field'));

    $this->assertSame([
        'fr' => 'testValue_fr',
    ], $this->testModel->getStorevalues('field_with_mutator'));
});

it('will throw an exception when trying to translate an unstorable attribute', function () {
    $this->expectException(AttributeIsNotStorable::class);

    $this->testModel->setStorevalue('untranslated', 'en', 'value');
});

it('is compatible with accessors on non storable attributes', function () {
    $testModel = new class () extends TestModel {
        public function getOtherFieldAttribute(): string
        {
            return 'accessorName';
        }
    };

    expect('accessorName')->toEqual((new $testModel())->otherField);
});

it('can use accessors on translated attributes', function () {
    $testModel = new class () extends TestModel {
        public function getNameAttribute($value): string
        {
            return "I just accessed {$value}";
        }
    };

    $testModel->setStorevalue('name', 'en', 'testValue_en');

    expect('I just accessed testValue_en')->toEqual($testModel->name);
});

it('can use mutators on translated attributes', function () {
    $testModel = new class () extends TestModel {
        public function setNameAttribute($value)
        {
            $this->attributes['name'] = "I just mutated {$value}";
        }
    };

    $testModel->setStorevalue('name', 'en', 'testValue_en');

    expect('I just mutated testValue_en')->toEqual($testModel->name);
});

it('can set translations for default language', function () {
    $model = TestModel::create([
        'name' => [
            'en' => 'testValue_en',
            'fr' => 'testValue_fr',
        ],
    ]);

    app()->setLocale('en');

    $model->name = 'updated_en';
    expect($model->name)->toEqual('updated_en');
    expect($model->getStorevalue('name', 'fr'))->toEqual('testValue_fr');

    app()->setLocale('fr');
    $model->name = 'updated_fr';
    expect($model->name)->toEqual('updated_fr');
    expect($model->getStorevalue('name', 'en'))->toEqual('updated_en');
});

it('can set multiple translations at once', function () {
    $storevalues = ['nl' => 'hallo', 'en' => 'hello', 'kh' => 'សួរស្តី'];

    $this->testModel->setStorevalues('name', $storevalues);
    $this->testModel->save();

    expect($this->testModel->getStorevalues('name'))->toEqual($storevalues);
});

it('can check if an attribute is storable', function () {
    expect($this->testModel->isStorableAttribute('name'))->toBeTrue();

    expect($this->testModel->isStorableAttribute('other'))->toBeFalse();
});

it('can check if an attribute has translation', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'nl', null);
    $this->testModel->save();

    expect($this->testModel->hasTranslation('name', 'en'))->toBeTrue();

    expect($this->testModel->hasTranslation('name', 'pt'))->toBeFalse();
});

it('can correctly set a field when a mutator is defined', function () {
    $testModel = (new class () extends TestModel {
        public function setNameAttribute($value)
        {
            $this->attributes['name'] = "I just mutated {$value}";
        }
    });

    $testModel->name = 'hello';

    $expected = ['en' => 'I just mutated hello'];
    expect($testModel->getStorevalues('name'))->toEqual($expected);
});

it('can set multiple translations when a mutator is defined', function () {
    $testModel = (new class () extends TestModel {
        public function setNameAttribute($value)
        {
            $this->attributes['name'] = "I just mutated {$value}";
        }
    });

    $storevalues = [
        'nl' => 'hallo',
        'en' => 'hello',
        'kh' => 'សួរស្តី',
    ];

    $testModel->setStorevalues('name', $storevalues);

    $testModel->save();

    $expected = [
        'nl' => 'I just mutated hallo',
        'en' => 'I just mutated hello',
        'kh' => 'I just mutated សួរស្តី',
    ];

    expect($testModel->getStorevalues('name'))->toEqual($expected);
});

it('can set multiple translations on field when a mutator is defined', function () {
    $storevalues = [
        'nl' => 'hallo',
        'en' => 'hello',
    ];

    $testModel = $this->testModel;
    $testModel->field_with_mutator = $storevalues;
    $testModel->save();

    expect($testModel->getStorevalues('field_with_mutator'))->toEqual($storevalues);
});

it('can translate a field based on the translations of another one', function () {
    $testModel = (new class () extends TestModel {
        public function setOtherFieldAttribute($value, $store = 'en')
        {
            $this->attributes['other_field'] = $value . ' ' . $this->getStorevalue('name', $store);
        }
    });

    $testModel->setStorevalues('name', [
        'nl' => 'wereld',
        'en' => 'world',
    ]);

    $testModel->setStorevalues('other_field', [
        'nl' => 'hallo',
        'en' => 'hello',
    ]);

    $testModel->save();

    $expected = [
        'nl' => 'hallo wereld',
        'en' => 'hello world',
    ];

    expect($testModel->getStorevalues('other_field'))->toEqual($expected);
});

it('handle null value from database', function () {
    $testModel = (new class () extends TestModel {
        public function setAttributesExternally(array $attributes)
        {
            $this->attributes = $attributes;
        }
    });

    $testModel->setAttributesExternally(['name' => json_encode(null), 'other_field' => null]);

    expect($testModel->name)->toEqual('');
    expect($testModel->other_field)->toEqual('');
});

it('can get all translations', function () {
    $storevalues = ['nl' => 'hallo', 'en' => 'hello'];

    $this->testModel->setStorevalues('name', $storevalues);
    $this->testModel->setStorevalues('field_with_mutator', $storevalues);
    $this->testModel->save();

    $this->assertEquals([
        'name' => ['nl' => 'hallo', 'en' => 'hello'],
        'other_field' => [],
        'field_with_mutator' => ['nl' => 'hallo', 'en' => 'hello'],
    ], $this->testModel->translations);
});

it('will return fallback locale translation when getting an empty translation from the locale', function () {
    config()->set('app.fallback_locale', 'en');

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'nl', null);
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'nl'))->toBe('testValue_en');
});

it('will return correct translation value if value is set to zero', function () {
    $this->testModel->setStorevalue('name', 'nl', '0');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'nl'))->toBe('0');
});

it('will not return fallback value if value is set to zero', function () {
    config()->set('app.fallback_locale', 'en');

    $this->testModel->setStorevalue('name', 'en', '1');
    $this->testModel->setStorevalue('name', 'nl', '0');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'nl'))->toBe('0');
});

it('will not remove zero value of other locale in database', function () {
    config()->set('app.fallback_locale', 'en');

    $this->testModel->setStorevalue('name', 'nl', '0');
    $this->testModel->setStorevalue('name', 'en', '1');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'nl'))->toBe('0');
});

it('can be translated based on given locale', function () {
    $value = 'World';

    $this->testModel = TestModel::usingLocale('en')->fill([
        'name' => $value,
    ]);
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'en'))->toBe($value);
});

it('can set and fetch attributes based on set locale', function () {
    $en = 'World';
    $fr = 'Monde';

    $this->testModel->setLocale('en');
    $this->testModel->name = $en;
    $this->testModel->setLocale('fr');
    $this->testModel->name = $fr;

    $this->testModel->save();

    $this->testModel->setLocale('en');
    expect($this->testModel->name)->toBe($en);
    $this->testModel->setLocale('fr');
    expect($this->testModel->name)->toBe($fr);
});

it('can replace translations', function () {
    $storevalues = ['nl' => 'hallo', 'en' => 'hello', 'kh' => 'សួរស្តី'];

    $this->testModel->setStorevalues('name', $storevalues);
    $this->testModel->save();

    $newTranslations = ['es' => 'hola'];
    $this->testModel->replaceTranslations('name', $newTranslations);

    expect($this->testModel->getStorevalues('name'))->toEqual($newTranslations);
});

it('can use any locale if given locale not set', function () {
    config()->set('app.fallback_locale', 'en');

    Storable::fallback(
        fallbackAny: true,
    );

    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->setStorevalue('name', 'de', 'testValue_de');
    $this->testModel->save();

    $this->testModel->setLocale('it');
    expect($this->testModel->name)->toBe('testValue_fr');
});

it('will return set translation when fallback any set', function () {
    config()->set('app.fallback_locale', 'en');

    Storable::fallback(
        fallbackAny: true,
    );

    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->setStorevalue('name', 'de', 'testValue_de');
    $this->testModel->save();

    $this->testModel->setLocale('de');
    expect($this->testModel->name)->toBe('testValue_de');
});

it('will return fallback translation when fallback any set', function () {
    config()->set('app.fallback_locale', 'en');

    Storable::fallback(
        fallbackAny: true,
    );

    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    $this->testModel->setLocale('de');
    expect($this->testModel->name)->toBe('testValue_en');
});

it('provides a flog to not return any translation when getting an unknown locale', function () {
    config()->set('app.fallback_locale', 'en');

    Storable::fallback(
        fallbackAny: true,
    );

    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->setStorevalue('name', 'de', 'testValue_de');
    $this->testModel->save();

    $this->testModel->setLocale('it');
    expect($this->testModel->getStorevalue('name', 'it', false))->toBe('');
});

it('will return default fallback locale translation when getting an unknown locale with fallback any', function () {
    config()->set('app.fallback_locale', 'en');

    Storable::fallback(
        fallbackAny: true,
    );

    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->save();

    expect($this->testModel->getStorevalue('name', 'fr'))->toBe('testValue_en');
});

it('will return all locales when getting all translations', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');
    $this->testModel->setStorevalue('name', 'fr', 'testValue_fr');
    $this->testModel->setStorevalue('name', 'tr', 'testValue_tr');
    $this->testModel->save();

    expect($this->testModel->locales())->toEqual([
        'en',
        'fr',
        'tr',
    ]);
});

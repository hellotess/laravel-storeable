<?php

use Illuminate\Support\Facades\Event;
use Hellotess\Translatable\Events\TranslationHasBeenSetEvent;
use Hellotess\Translatable\Test\TestSupport\TestModel;

beforeEach(function () {
    Event::fake();

    $this->testModel = new TestModel();
});

it('will fire an event when a translation has been set', function () {
    $this->testModel->setTranslation('name', 'en', 'testValue_en');

    Event::assertDispatched(TranslationHasBeenSetEvent::class);
});

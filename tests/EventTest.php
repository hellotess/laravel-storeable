<?php

use Illuminate\Support\Facades\Event;
use Hellotess\Storable\Events\StorevalueHasBeenSetEvent;
use Hellotess\Storable\Test\TestSupport\TestModel;

beforeEach(function () {
    Event::fake();

    $this->testModel = new TestModel();
});

it('will fire an event when a translation has been set', function () {
    $this->testModel->setStorevalue('name', 'en', 'testValue_en');

    Event::assertDispatched(StorevalueHasBeenSetEvent::class);
});

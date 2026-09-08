<?php

namespace Tests;

use App\Services\Push\PushNotifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakePushNotifier;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test debe tocar la red de Expo: siempre usamos el fake.
        $this->app->instance(PushNotifier::class, new FakePushNotifier);
    }

    protected function pushNotifier(): FakePushNotifier
    {
        return $this->app->make(PushNotifier::class);
    }
}

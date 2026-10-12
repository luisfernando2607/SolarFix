<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La configuración se guarda en memoria por petición; cada test parte de la base limpia.
        Setting::flushCache();
    }
}

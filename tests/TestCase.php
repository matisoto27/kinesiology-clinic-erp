<?php

namespace Tests;

use Database\Seeders\ActividadSeeder;
use Database\Seeders\TipoActividadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->usaRefreshDatabase()) {
            $this->seed([
                TipoActividadSeeder::class,
                ActividadSeeder::class,
            ]);
        }
    }

    private function usaRefreshDatabase(): bool
    {
        return in_array(RefreshDatabase::class, class_uses_recursive(static::class));
    }
}

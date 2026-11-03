<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

/**
 * Migra la base de datos en memoria antes de cada prueba.
 *
 * Se niega a ejecutarse si la conexión no es SQLite en memoria: RefreshDatabase
 * borra todas las tablas y nunca debe tocar la base de desarrollo.
 */
trait RefreshesInMemoryDatabase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');

        if (
            config("database.connections.{$connection}.driver") !== 'sqlite' ||
            config("database.connections.{$connection}.database") !== ':memory:'
        ) {
            throw new RuntimeException(
                'Las pruebas solo pueden usar SQLite en memoria (ver phpunit.xml).'
            );
        }
    }
}

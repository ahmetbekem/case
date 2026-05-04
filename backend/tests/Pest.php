<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
|
| Tests in `Feature/` and `Unit/Application/` extend Tests\TestCase to get
| the Laravel application container. Tests in `Unit/Domain/` are intentionally
| pure-PHP — they run without booting Laravel for maximum speed and to
| enforce Domain layer's framework independence (CODING_STANDARDS §19.4).
|
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit/Application');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeUuid', function () {
    return $this->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

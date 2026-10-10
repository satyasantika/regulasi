<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Uji Feature memakai TestCase Laravel. RefreshDatabase dipasang per berkas uji yang menyentuh basis data.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

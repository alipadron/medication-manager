<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest Test Case
|--------------------------------------------------------------------------
|
| Bind the application's TestCase to every test in each suite so Pest tests
| get the Laravel container, assertions, and helpers.
|
| Feature tests that touch the database opt in to RefreshDatabase per file.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

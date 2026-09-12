<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * Exercise a fake, temporary public/index.html so the SPA fallback route can
 * be asserted without depending on a pre-built frontend on disk, and restore
 * the real file (if any) afterwards.
 */
function with_spa_index(callable $assert): void
{
    $path = public_path('index.html');
    $hadOriginal = File::exists($path);
    $original = $hadOriginal ? File::get($path) : null;

    File::put($path, '<!doctype html><html><body>Medication Manager SPA</body></html>');

    try {
        $assert();
    } finally {
        if ($hadOriginal) {
            File::put($path, $original);
        } else {
            File::delete($path);
        }
    }
}

it('serves the single-origin SPA at the root and for deep links', function () {
    with_spa_index(function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('Medication Manager SPA', false);

        $this->get('/medications/1/schedule')
            ->assertOk()
            ->assertSee('Medication Manager SPA', false);
    });
});

it('keeps /api routes above the SPA fallback', function () {
    with_spa_index(function () {
        $this->getJson('/api/v1/people')
            ->assertOk()
            ->assertJson(['data' => []]);
    });
});

it('does not shadow the /up health probe with the SPA', function () {
    with_spa_index(function () {
        $this->get('/up')
            ->assertStatus(200)
            ->assertDontSee('Medication Manager SPA', false);
    });
});

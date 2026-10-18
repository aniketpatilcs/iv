<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert on Inertia props, not compiled assets, so they don't need `npm run build`.
        $this->withoutVite();
    }
}

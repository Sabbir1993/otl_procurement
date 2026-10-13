<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** Sign in as the seeded user of a prototype role (exec, sup, mkt, qa, fin, ed, imp, admin, audit). */
    protected function asRole(string $role): static
    {
        return $this->actingAs(User::where('role', $role)->firstOrFail());
    }
}

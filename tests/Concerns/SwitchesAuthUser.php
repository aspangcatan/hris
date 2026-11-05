<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Mocks the Auth facade once and lets a test switch the acting user any number of times.
 * (Calling Auth::shouldReceive() again only adds a second expectation; Mockery keeps
 * answering with the first one, so the "switched" user silently stays the first user.)
 */
trait SwitchesAuthUser
{
    private ?object $actingAuthUser = null;
    private bool $authMocked = false;

    protected function actAsUser(object $user): void
    {
        $this->actingAuthUser = $user;

        if (!$this->authMocked) {
            Auth::shouldReceive('id')->andReturnUsing(fn() => $this->actingAuthUser->id ?? null);
            Auth::shouldReceive('user')->andReturnUsing(fn() => $this->actingAuthUser);
            Auth::shouldReceive('check')->andReturnUsing(fn() => $this->actingAuthUser !== null);
            $this->authMocked = true;
        }
    }
}

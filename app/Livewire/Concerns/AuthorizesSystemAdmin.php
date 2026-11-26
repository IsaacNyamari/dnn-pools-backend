<?php

namespace App\Livewire\Concerns;

trait AuthorizesSystemAdmin
{
    /**
     * Runs on every request to the component, on top of the route middleware.
     */
    public function bootAuthorizesSystemAdmin(): void
    {
        abort_unless(auth()->user()?->is_system_admin === true, 403);
    }
}

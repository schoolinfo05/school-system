<?php

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\Request;

trait AuthorizesPortal
{
    private function requireAnyRole(Request $request, array $roles): void
    {
        $user = $request->user();

        if (!$user || (
            !in_array($user->role, $roles, true)
            && !$user->hasAnyRole($roles)
        )) {
            abort(403);
        }
    }
}

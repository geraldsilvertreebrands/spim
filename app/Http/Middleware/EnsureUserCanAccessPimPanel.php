<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessPimPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        \Log::info('PIM Middleware Check', [
            'user' => $user?->email,
            'roles' => $user?->roles->pluck('name')->toArray(),
            'hasAnyRole' => $user?->hasAnyRole(['admin', 'pim-editor']),
        ]);

        if (! $user) {
            return redirect()->route('filament.pim.auth.login');
        }

        // Admin and PIM editors can access
        if ($user->hasAnyRole(['admin', 'pim-editor'])) {
            return $next($request);
        }

        abort(403, 'You do not have access to the PIM panel.');
    }
}

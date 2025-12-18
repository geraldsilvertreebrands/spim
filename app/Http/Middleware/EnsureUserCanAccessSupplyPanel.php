<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessSupplyPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('filament.supply.auth.login');
        }

        // Admin and suppliers can access
        if ($user->hasAnyRole(['admin', 'supplier-basic', 'supplier-premium'])) {
            return $next($request);
        }

        abort(403, 'You do not have access to the Supplier Portal.');
    }
}

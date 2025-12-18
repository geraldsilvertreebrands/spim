<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessPricingPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('filament.pricing.auth.login');
        }

        // Admin and pricing analysts can access
        if ($user->hasAnyRole(['admin', 'pricing-analyst'])) {
            return $next($request);
        }

        abort(403, 'You do not have access to the Pricing tool.');
    }
}

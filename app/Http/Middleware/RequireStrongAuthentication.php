<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privileged roles must enrol two-factor authentication or a passkey before
 * they can use administrative pages.
 */
class RequireStrongAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresStrongAuthentication() && ! $user->hasStrongAuthentication()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Your role requires two-factor authentication or a passkey. Please set one up to continue.'),
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            is_file(storage_path('framework/app-maintenance'))
            && $request->user()?->role !== UserRole::Admin
            && ! $request->routeIs(
                'login',
                'login.submit',
                'auth.google',
                'auth.google.callback',
                'logout',
            )
        ) {
            return response()->view('maintenance.index', status: 503)
                ->header('Retry-After', '300');
        }

        return $next($request);
    }
}

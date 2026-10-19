<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? Locale::tryFrom($request->session()->get('locale', 'en'))
            ?? Locale::default();

        App::setLocale($locale->value);

        return $next($request);
    }
}

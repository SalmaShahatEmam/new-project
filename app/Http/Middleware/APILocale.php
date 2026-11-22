<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class APILocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $rawLocale = Request()->server('HTTP_ACCEPT_LANGUAGE');
        if ($rawLocale) {
            $primaryLocale = strtolower(substr((string) $rawLocale, 0, 2));
            if (in_array($primaryLocale, ['ar', 'en'], true)) {
                App::setLocale($primaryLocale);
            } else {
                App::setLocale(config('app.locale', 'ar'));
            }
        } else {
            App::setLocale(config('app.locale', 'ar'));
        }
        return $next($request);
    }
}

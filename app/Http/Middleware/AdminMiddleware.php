<?php

namespace App\Http\Middleware;

use App\Enum\UserTypeEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        abort_unless($user !== null, 401, 'Unauthenticated.');

        $hasAdminRole = $user->roles()
            ->where(function ($query) {
                $query->where('slug', 'admin')
                    ->orWhere('name->en', 'admin');
            })
            ->exists();

        $isAdmin = $user->type === UserTypeEnum::ADMIN || $hasAdminRole;
        abort_unless($isAdmin, 401, 'Unauthorized.');

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\MenuRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        MenuRegistry::syncNewMenusToRoles();

        $routeName = $request->route()?->getName();
        $menuKey = MenuRegistry::menuKeyForRoute($routeName);

        // Route tanpa mapping menu (mis. profile) diloloskan.
        if ($menuKey === null) {
            return $next($request);
        }

        if (MenuRegistry::userCan($user, $menuKey)) {
            return $next($request);
        }

        $home = $user->homeRouteName();
        if ($home === $routeName) {
            abort(403, 'Akun tidak punya akses menu.');
        }

        return redirect()->route($home);
    }
}

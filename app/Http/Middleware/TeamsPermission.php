<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Support\Utils;

class TeamsPermission
{
    public function handle(Request $request, Closure $next)
    {
        if (Utils::isTenancyEnabled() && ($team = Filament::getTenant())) {
            setPermissionsTeamId($team->id);
        }
        return $next($request);
    }
}

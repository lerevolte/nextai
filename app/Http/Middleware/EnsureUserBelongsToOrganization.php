<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Organization;

class EnsureUserBelongsToOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $organization = $request->route('organization');
        
        // Если передан slug, получаем модель
        if (is_string($organization)) {
            $organization = Organization::where('slug', $organization)->firstOrFail();
            // Заменяем параметр на модель
            $request->route()->setParameter('organization', $organization);
        }
        
        if (!$organization) {
            return $next($request);
        }

        if ($request->user()->organization_id !== $organization->id) {
            abort(403, 'У вас нет доступа к этой организации');
        }

        return $next($request);
    }
}
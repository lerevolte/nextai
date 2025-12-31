<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    public function settings()
    {
        $organization = auth()->user()->organization;
        return view('organization.settings', compact('organization'));
    }

    public function update(Request $request)
    {
        $organization = auth()->user()->organization;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $organization->update($validated);

        return redirect()->route('organization.settings')
            ->with('success', 'Настройки обновлены');
    }

    public function regenerateApiKey(Request $request)
    {
        $organization = auth()->user()->organization;
        // Проверяем права доступа
        // if ($request->user()->role !== 'admin') {
        //     return response()->json(['error' => 'Access denied'], 403);
        // }
        
        // Генерируем новый ключ
        $newApiKey = 'org_' . Str::random(32);
        $organization->update(['api_key' => $newApiKey]);
        
        // Логируем изменение
        Log::info('API key regenerated', [
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
        ]);
        
        return response()->json([
            'success' => true,
            'api_key' => $newApiKey,
        ]);
    }
}
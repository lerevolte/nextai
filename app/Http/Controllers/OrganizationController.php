<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
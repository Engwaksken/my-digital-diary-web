<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use Illuminate\Http\Request;

/**
 * Lets a user register their own AI provider API key(s) and choose which
 * one is "active" (used by AiPlannerService). Deliberately NOT a
 * CrudController subclass: keys are never shown again in full after
 * saving, and "activate" needs to deactivate all of the user's other keys.
 */
class ApiCredentialController extends Controller
{
    public function index(Request $request)
    {
        $credentials = $request->user()
            ->apiCredentials()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $settings = \App\Models\SiteSetting::current();
        $hasOwnActiveKey = $request->user()->apiCredentials()->where('is_active', true)->exists();
        $sharedUsedThisMonth = \App\Models\AiPlan::where('user_id', $request->user()->id)
            ->where('used_shared_key', true)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $providers = \App\Models\AiProvider::where('is_enabled', true)->orderBy('sort_order')->get();

        return view('api-credentials.index', compact('credentials', 'settings', 'hasOwnActiveKey', 'sharedUsedThisMonth', 'providers'));
    }

    public function create()
    {
        $providers = \App\Models\AiProvider::where('is_enabled', true)->orderBy('sort_order')->get();

        return view('api-credentials.form', compact('providers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'exists:ai_providers,key'],
            'api_key' => ['required', 'string', 'max:1000'],
        ]);

        $credential = $request->user()->apiCredentials()->create($data);

        // First key a user adds becomes active automatically.
        if ($request->user()->apiCredentials()->count() === 1) {
            $credential->update(['is_active' => true]);
        }

        return redirect()->route('api-credentials.index')->with('success', 'API key added.');
    }

    public function activate(Request $request, int $apiCredential)
    {
        $credential = $request->user()->apiCredentials()->findOrFail($apiCredential);

        $request->user()->apiCredentials()->update(['is_active' => false]);
        $credential->update(['is_active' => true]);

        return back()->with('success', "\"{$credential->label}\" is now the active API key.");
    }

    public function destroy(Request $request, int $apiCredential)
    {
        $credential = $request->user()->apiCredentials()->findOrFail($apiCredential);
        $wasActive = $credential->is_active;
        $credential->delete();

        // If we just deleted the active key, promote the next most recent one.
        if ($wasActive) {
            $request->user()->apiCredentials()->latest()->first()?->update(['is_active' => true]);
        }

        return back()->with('success', 'API key removed.');
    }
}

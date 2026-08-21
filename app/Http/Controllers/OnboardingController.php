<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    private const FOCUSES = ['money','day','goals','health','work','growth','everything'];

    public function show(Request $request): View
    {
        return view('onboarding.show', ['user' => $request->user()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'onboarding_focuses' => ['required','array','min:1','max:7'],
            'onboarding_focuses.*' => ['string', Rule::in(self::FOCUSES)],
        ]);

        $request->user()->update([
            'onboarding_focuses' => array_values(array_unique($data['onboarding_focuses'])),
            'onboarding_completed_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('status', 'Your diary is now personalised around what matters most to you.');
    }
}

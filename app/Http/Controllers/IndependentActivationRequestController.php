<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\IndependentActivationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class IndependentActivationRequestController extends Controller
{
    public function create(Request $request): View
    {
        return view('account.request-independent-activation', [
            'pendingRequest' => IndependentActivationRequest::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->latest()
                ->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable','string','max:2000'],
        ]);

        $exists = IndependentActivationRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return back()->with('info', 'Your request is already pending.');
        }

        IndependentActivationRequest::create([
            'user_id' => $request->user()->id,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Your request has been sent to the administrator.');
    }
}

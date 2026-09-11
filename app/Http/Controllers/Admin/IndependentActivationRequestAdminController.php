<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IndependentActivationRequest;
use App\Services\OrganizationMembershipLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

final class IndependentActivationRequestAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.independent-activation-requests.index', [
            'requests' => IndependentActivationRequest::with(['user','reviewer'])
                ->latest()->paginate(25),
        ]);
    }

    public function approve(
        Request $request,
        IndependentActivationRequest $activationRequest,
        OrganizationMembershipLifecycleService $lifecycle
    ): RedirectResponse {
        $lifecycle->activateIndependent($activationRequest->user);

        $activationRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'admin_notes' => $request->input('admin_notes'),
            'reviewed_at' => now(),
        ]);

        Artisan::call('subscriptions:repair-monthly-trials');

        return back()->with('success', 'User activated as an independent Monthly Trial user.');
    }

    public function reject(Request $request, IndependentActivationRequest $activationRequest): RedirectResponse
    {
        $activationRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'admin_notes' => $request->input('admin_notes'),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Request rejected.');
    }
}

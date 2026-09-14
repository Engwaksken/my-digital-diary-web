<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserExtraRequest;
use App\Services\ApplyExtraRequestService;
use Illuminate\Http\Request;

class ExtraRequestController extends Controller
{
    public function __construct(
        private readonly ApplyExtraRequestService $applyService
    ) {}

    public function index(Request $request)
    {
        $requests = UserExtraRequest::with('user', 'iotecTransaction')->latest()->paginate(20);
        return view('extra-requests.admin-index', compact('requests'));
    }

    public function show(UserExtraRequest $extraRequest)
    {
        return view('extra-requests.show', compact('extraRequest'));
    }

    public function approve(UserExtraRequest $extraRequest)
    {
        $extraRequest->update(['status' => 'approved']);
        return back()->with('success', 'Extra request approved.');
    }

    public function reject(UserExtraRequest $extraRequest)
    {
        $extraRequest->update(['status' => 'rejected']);
        return back()->with('success', 'Extra request rejected.');
    }

    public function apply(UserExtraRequest $extraRequest)
    {
        $this->applyService->applyQuota($extraRequest);
        return back()->with('success', 'Quota applied to user successfully.');
    }
}

<?php

namespace App\Http\Controllers;

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
        $requests = $request->user()->extraRequests()->latest()->paginate(15);
        return view('extra-requests.index', compact('requests'));
    }

    public function create()
    {
        return view('extra-requests.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'quota_amount' => ['required', 'integer', 'min:10', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
        ]);

        $extraRequest = $this->applyService->createRequest($request->user(), $data);

        return redirect()->route('extra-requests.show', $extraRequest)
            ->with('success', 'Extra recording quota request created successfully. Please complete payment.');
    }

    public function show(UserExtraRequest $extraRequest)
    {
        if ($extraRequest->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403);
        }
        return view('extra-requests.show', compact('extraRequest'));
    }

    public function pay(Request $request, UserExtraRequest $extraRequest)
    {
        if ($extraRequest->user_id !== auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'payment_channel' => ['required', 'string', 'in:mobile_money,card,mtn,airtel'],
            'payer' => ['required', 'string', 'max:50'],
        ]);

        $result = $this->applyService->initiatePayment($extraRequest, $data);

        if (! empty($result['card_redirect_url'])) {
            return redirect()->away($result['card_redirect_url']);
        }

        return redirect()->route('extra-requests.show', $extraRequest)
            ->with('success', 'Payment initiated successfully. Please check your phone for prompt.');
    }
}

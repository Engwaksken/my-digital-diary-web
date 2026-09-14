<?php

namespace App\Http\Controllers\Api;

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
        $requests = $request->user()->extraRequests()->latest()->get();
        return response()->json(['success' => true, 'data' => $requests]);
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

        return response()->json(['success' => true, 'data' => $extraRequest], 201);
    }

    public function show(Request $request, UserExtraRequest $extraRequest)
    {
        if ($extraRequest->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $extraRequest->load('iotecTransaction')]);
    }

    public function pay(Request $request, UserExtraRequest $extraRequest)
    {
        if ($extraRequest->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'payment_channel' => ['required', 'string', 'in:mobile_money,card,mtn,airtel'],
            'payer' => ['required', 'string', 'max:50'],
        ]);

        $result = $this->applyService->initiatePayment($extraRequest, $data);

        return response()->json(['success' => true, 'data' => $result]);
    }
}

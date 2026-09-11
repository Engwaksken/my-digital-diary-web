<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Services\DebtReminderService;
use Illuminate\Http\Request;

class DebtReminderController extends Controller
{
    public function send(Request $request, Debt $debt, DebtReminderService $service)
    {
        abort_unless((int) $debt->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'channel'=>['required','in:email,sms,both'],
            'recipient_scope'=>['required','in:counterparty,me,both'],
            'message'=>['nullable','string','max:1200'],
        ]);

        return response()->json([
            'data' => $service->send(
                $debt,
                $request->user(),
                $data['channel'],
                $data['recipient_scope'],
                $data['message'] ?? null
            ),
        ]);
    }

    public function history(Request $request, Debt $debt)
    {
        abort_unless((int) $debt->user_id === (int) $request->user()->id, 403);
        return response()->json(['data' => $debt->reminderLogs()->latest()->paginate(50)]);
    }
}

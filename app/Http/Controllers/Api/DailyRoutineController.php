<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DailyRoutineService;
use Illuminate\Http\Request;

class DailyRoutineController extends Controller
{
    public function start(Request $request, DailyRoutineService $service)
    {
        return response()->json(['data' => $service->start($request->user())]);
    }

    public function end(Request $request, DailyRoutineService $service)
    {
        return response()->json(['data' => $service->end($request->user())]);
    }
}

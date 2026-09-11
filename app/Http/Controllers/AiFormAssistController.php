<?php

namespace App\Http\Controllers;

use App\Services\Ai\FormAssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AiFormAssistController extends Controller
{
    public function generate(Request $request, FormAssistService $service): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'in:spiritual-practices,social-media-planner,relationships,network-contacts'],
            'topic' => ['required', 'string', 'max:500'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            $fields = $service->generate(
                $data['module'],
                $data['topic'],
                $data['context'] ?? []
            );

            return response()->json([
                'ok' => true,
                'data' => $fields,
                'message' => 'AI draft generated. Review and edit it before saving.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'AI could not prepare a draft right now. Your form has not been changed.',
            ], 422);
        }
    }
}

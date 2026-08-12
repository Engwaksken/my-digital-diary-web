<?php

namespace App\Http\Controllers\Api;

use App\Models\Feedback;

class FeedbackController extends ApiCrudController
{
    protected string $model = Feedback::class;

    protected array $rules = [
        'category' => 'required|in:bug,feature_request,general,complaint,compliment',
        'subject' => 'required|string|max:255',
        'message' => 'required|string',
        'rating' => 'nullable|integer|min:1|max:5',
    ];
}

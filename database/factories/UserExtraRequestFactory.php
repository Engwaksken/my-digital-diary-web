<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserExtraRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserExtraRequest>
 */
class UserExtraRequestFactory extends Factory
{
    protected $model = UserExtraRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'request_type' => 'extra_recording_quota',
            'description' => 'Extra 60 minutes recording quota',
            'status' => 'pending',
            'amount' => 5000.00,
            'currency' => 'UGX',
            'quota_amount' => 60,
            'quota_used' => 0,
            'expires_at' => now()->addDays(30),
        ];
    }
}

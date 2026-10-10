<?php

namespace Database\Factories;

use App\Models\Request;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestLog>
 */
class RequestLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'logged_on' => fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'content' => fake()->realText(80),
            'recorded_by' => User::factory(),
        ];
    }
}

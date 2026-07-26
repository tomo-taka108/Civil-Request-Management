<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Request>
 *
 * 案件のテストデータ生成。全 NOT NULL 項目を満たす。
 * 採番系（reception_year/seq/number）も直接生成する場合に備えて埋める。
 */
class RequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) now()->format('Y');
        $seq = fake()->unique()->numberBetween(1, 9999);

        return [
            'office_id' => Office::factory(),
            'reception_number' => sprintf('%d-%04d', $year, $seq),
            'reception_year' => $year,
            'reception_seq' => $seq,
            'reception_date' => fake()->date(),
            'reception_time' => fake()->time('H:i:s'),
            'reception_method' => fake()->randomElement(['window', 'phone', 'email', 'letter', 'fax', 'patrol', 'other']),
            'reception_method_other' => null,
            'registered_by' => User::factory(),
            'requester_category' => fake()->randomElement(['individual', 'neighborhood_association', 'municipality', 'council_member', 'anonymous', 'staff_patrol', 'other']),
            'requester_name' => fake()->optional()->name(),
            'department' => fake()->randomElement(['road', 'river', 'sabo']),
            'content' => fake()->realText(100),
            'request_type' => fake()->randomElement(['complaint', 'request', 'anomaly']),
            'latitude' => null,
            'longitude' => null,
            'address' => fake()->optional()->address(),
            'response_necessity' => fake()->randomElement(['yes', 'no', 'unknown']),
            'urgency' => fake()->randomElement(['high', 'medium', 'low']),
            'response_policy' => fake()->optional()->realText(50),
            'response_status' => 'not_started',
            'response_completed_date' => null,
        ];
    }
}

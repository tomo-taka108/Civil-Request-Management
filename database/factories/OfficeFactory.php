<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // テスト用の架空の事務所名（実在名は使わない。CLAUDE.md 6章）
            'name' => 'テスト'.fake()->unique()->numberBetween(1, 9999).'土木事務所',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Request;
use App\Models\RequestPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestPhoto>
 */
class RequestPhotoFactory extends Factory
{
    protected $model = RequestPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'request_log_id' => null,
            'path' => 'request-photos/test/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'size' => 1024,
            'uploaded_by' => User::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instructor_id' => User::factory(),
            'title'         => $this->faker->sentence(4),
            'description'   => $this->faker->paragraph(),
            'published'     => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['published' => false]);
    }
}

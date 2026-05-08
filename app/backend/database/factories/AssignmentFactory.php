<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id'   => Course::factory(),
            'title'       => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'deadline'    => now()->addDays(7),
        ];
    }

    public function pastDeadline(): static
    {
        return $this->state(['deadline' => now()->subDay()]);
    }
}

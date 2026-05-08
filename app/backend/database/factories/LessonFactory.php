<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title'     => $this->faker->sentence(4),
            'content'   => $this->faker->paragraphs(3, true),
            'order'     => $this->faker->numberBetween(1, 20),
            'published' => true,
        ];
    }
}

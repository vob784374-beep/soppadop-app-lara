<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'student_id'    => User::factory(),
            'content'       => $this->faker->paragraphs(2, true),
            'grade'         => null,
            'submitted_at'  => now(),
        ];
    }

    public function graded(int $grade = 85): static
    {
        return $this->state(['grade' => $grade]);
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'       => rtrim(fake()->sentence(4), '.'),
            'description' => fake()->optional(0.8)->paragraph(),
        ];
    }
}

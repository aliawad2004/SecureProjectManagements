<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 
            'content' => $this->faker->sentence, 
            'commentable_id' => Project::factory(),
            'commentable_type' => Project::class,
        ];
    }

    public function forTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_id' => Task::factory(),
            'commentable_type' => Task::class,
        ]);
    }
}

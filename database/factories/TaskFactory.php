<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(), 
            'name' => $this->faker->unique()->words(3, true) . ' Task',
            'description' => $this->faker->sentence,
            'status' => $this->faker->randomElement(['open', 'in_progress', 'completed', 'cancelled']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'due_date' => Carbon::now()->addDays(rand(1, 14)),
            'assigned_to_user_id' => \App\Models\User::factory(), 
        ];
    }
}

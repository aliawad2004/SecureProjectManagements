<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => \App\Models\Team::factory(), // <--- Ensure this is here
            'name' => $this->faker->unique()->words(3, true) . ' Project',
            'description' => $this->faker->sentence,
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed']),
            'due_date' => Carbon::now()->addDays(rand(7, 30)),
            'created_by_user_id' => \App\Models\User::factory(), // <--- Ensure this is here
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str; // For UUID

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(), 
            'type' => $this->faker->randomElement([
                'App\\Notifications\\GenericNotification',
                'App\\Notifications\\TaskAssignedNotification',
                'App\\Notifications\\NewCommentNotification',
                'App\\Notifications\\TaskCompletedNotification',
               
            ]),
            'data' => ['message' => $this->faker->sentence],
            'user_id' => \App\Models\User::factory(), 
            
            'read_at' => null, 
        ];
    }
}

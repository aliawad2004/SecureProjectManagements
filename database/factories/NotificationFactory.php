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
            'id' => Str::uuid(), // <--- Ensure UUID is generated
            'type' => $this->faker->randomElement([
                'App\\Notifications\\GenericNotification',
                'App\\Notifications\\TaskAssignedNotification',
                'App\\Notifications\\NewCommentNotification',
                'App\\Notifications\\TaskCompletedNotification',
                // Add other notification types if you have them
            ]),
            'data' => ['message' => $this->faker->sentence],
            'user_id' => \App\Models\User::factory(), // <--- Ensure a User is created for the notification
            // 'notifiable_type' => \App\Models\User::class, // Use if your migration uses notifiable_id/type
            // 'notifiable_id' => \App\Models\User::factory(), // Use if your migration uses notifiable_id/type
            'read_at' => null, // Default to unread
        ];
    }
}
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Create a new user with specified details.
     *
     * @param array $userData Array containing 'name', 'email', 'password', 'role'.
     * @return \App\Models\User
     */
    public function createUser(array $userData): User
    {
        Log::info("AuthService: Attempting to create user with email: {$userData['email']}");

        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'role' => $userData['role'] ?? 'member',
        ]);

        Log::info("AuthService: User {$user->email} created successfully with ID: {$user->id}");

        return $user;
    }

   
}

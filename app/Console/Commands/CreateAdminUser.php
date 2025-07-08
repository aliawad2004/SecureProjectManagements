<?php
// php artisan app:create-admin "Super Admin" admin@example.com password123 --role=admin
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Log;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin
                            {name : The name of the admin user}
                            {email : The email of the admin user}
                            {password : The password for the admin user}
                            {--role=admin : The role for the user (admin, project_manager, member)}'; 

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin (or any specified role) user.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');
        $role = $this->option('role');

        // Optional: Basic validation for console arguments
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,project_manager,member'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return Command::FAILURE;
        }

        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role,
            ]);

            $this->info("User '{$user->name}' with role '{$user->role}' and ID: {$user->id} created successfully!");
            Log::info("User '{$user->name}' with role '{$user->role}' and ID: {$user->id} created successfully via console command.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to create user: " . $e->getMessage());
            Log::error("Failed to create user via console command: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
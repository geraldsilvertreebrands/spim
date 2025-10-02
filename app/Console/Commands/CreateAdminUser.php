<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin {email} {name?} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name') ?? explode('@', $email)[0];
        $password = $this->option('password') ?? $this->secret('Password');

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");
            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        // Assign admin role
        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $this->error('Admin role not found. Please run: php artisan db:seed --class=RoleSeeder');
            $user->delete();
            return self::FAILURE;
        }

        $user->assignRole($adminRole);

        $this->info("Admin user created successfully!");
        $this->info("Email: {$email}");
        $this->info("Name: {$name}");

        return self::SUCCESS;
    }
}


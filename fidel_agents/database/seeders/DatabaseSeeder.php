<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $studentRole = Role::create([
            'name' => 'student',
        ]);

        Role::create(['name' => 'parent']);
        Role::create(['name' => 'organization']);
        Role::create(['name' => 'instructor']);

        User::factory()->create([
            'role_id' => $studentRole->id,
            'created_at' => now(),
        ]);
    }
}

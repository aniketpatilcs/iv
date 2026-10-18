<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a local demo account. Never run this seeder in production.
     */
    public function run(): void
    {
        User::updateOrCreate(['username' => 'demo'], [
            'name' => 'Ralph Murillo',
            'email' => 'demo@example.com',
            'insurance_company' => 'Carrier account',
            'is_active' => true,
            'password' => 'password',
        ]);
    }
}

<?php

namespace Database\Seeders;

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
        $this->call([
            AreaPositionSeeder::class,
            CommercialAreaSeeder::class,
            PermissionSeeder::class,
            ContractorSeeder::class,
            ResidenteSeeder::class,
        ]);
    }
}

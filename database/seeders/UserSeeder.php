<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $area = Area::where('name', 'Tecnología')->first();
        $position = Position::where('name', 'Desarrollador Fullstack')->first();

        User::updateOrCreate(
            ['email' => 'samuel.pineda@elite.com'],
            [
                'name' => 'Samuel Pineda',
                'first_name' => 'Samuel',
                'last_name' => 'Pineda',
                'document' => null,
                'area_id' => $area?->id,
                'position_id' => $position?->id,
                'password' => Hash::make('TkNAZ1X.'),
            ]
        );
    }
}

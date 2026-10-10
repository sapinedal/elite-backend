<?php

namespace Database\Seeders;

use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Documental\Models\DocumentAreaPermission;
use Illuminate\Database\Seeder;

class DocumentalPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $areas = Area::all();

        foreach ($areas as $area) {
            $areaName = strtolower($area->name);

            // Regla global por defecto para cada área
            $isManagement = str_contains($areaName, 'gerencia') ||
                            str_contains($areaName, 'administrativ') ||
                            str_contains($areaName, 'direcci');

            DocumentAreaPermission::updateOrCreate(
                [
                    'area_id' => $area->id,
                    'folder_path' => '*',
                ],
                [
                    'can_read' => true,
                    'can_upload' => $isManagement,
                    'can_create_folder' => $isManagement,
                    'can_delete' => $isManagement,
                ]
            );

            // Si es Comercial, le otorgamos permisos de subida en carpetas de ventas
            if (str_contains($areaName, 'comercial')) {
                DocumentAreaPermission::updateOrCreate(
                    [
                        'area_id' => $area->id,
                        'folder_path' => 'migracion-google-drive/Sala de ventas',
                    ],
                    [
                        'can_read' => true,
                        'can_upload' => true,
                        'can_create_folder' => true,
                        'can_delete' => false,
                    ]
                );

                DocumentAreaPermission::updateOrCreate(
                    [
                        'area_id' => $area->id,
                        'folder_path' => 'migracion-google-drive/Informe para comercial',
                    ],
                    [
                        'can_read' => true,
                        'can_upload' => true,
                        'can_create_folder' => true,
                        'can_delete' => false,
                    ]
                );
            }

            // Si es Operaciones o Técnica, permisos de subida en Avances de obra y Formatos
            if (str_contains($areaName, 'operaciones') || str_contains($areaName, 'técnica')) {
                DocumentAreaPermission::updateOrCreate(
                    [
                        'area_id' => $area->id,
                        'folder_path' => 'migracion-google-drive/Avances de obra',
                    ],
                    [
                        'can_read' => true,
                        'can_upload' => true,
                        'can_create_folder' => true,
                        'can_delete' => false,
                    ]
                );
            }
        }
    }
}

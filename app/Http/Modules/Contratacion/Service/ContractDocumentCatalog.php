<?php

namespace App\Http\Modules\Contratacion\Service;

use App\Http\Modules\Contratacion\Models\ContratacionContract;

/**
 * Estructura de carpetas de cada contrato en S3 y adjuntos obligatorios.
 * Es la única fuente de verdad: el frontend la recibe en /contratacion/catalogs.
 */
class ContractDocumentCatalog
{
    public const FOLDER_LEGAL = 'legal';

    public const FOLDERS = [
        'legal' => '01 DOCUMENTACIÓN LEGAL',
        'pliego' => '02 PLIEGO DE CONDICIONES',
        'contrato' => '03 CONTRATO',
        'acta_inicio' => '04 ACTA DE INICIO',
        'cortes' => '05 CORTES',
        'liquidacion' => '06 LIQUIDACIÓN',
        'otros' => '07 OTROS',
    ];

    // Documentos que deben existir dentro de 01 DOCUMENTACIÓN LEGAL
    public const LEGAL_DOCUMENTS = [
        'cc' => 'Cédula (CC)',
        'rut' => 'RUT',
        'camara_comercio' => 'Cámara de Comercio',
    ];

    // Carpetas que deben tener al menos un archivo siempre (cortes y otros son opcionales)
    public const REQUIRED_FOLDERS = ['pliego', 'contrato', 'acta_inicio'];

    public static function folderKeys(): array
    {
        return array_keys(self::FOLDERS);
    }

    /**
     * Adjuntos faltantes del contrato. Requiere `documents` cargado (eager loading) para evitar N+1.
     *
     * @return array<int, array{key: string, folder: string, label: string}>
     */
    public static function missingFor(ContratacionContract $contract): array
    {
        $documents = $contract->documents;
        $missing = [];

        foreach (self::LEGAL_DOCUMENTS as $type => $label) {
            $exists = $documents->contains(fn ($doc) => $doc->folder === self::FOLDER_LEGAL && $doc->document_type === $type);
            if (!$exists) {
                $missing[] = ['key' => "legal.{$type}", 'folder' => self::FOLDER_LEGAL, 'label' => $label];
            }
        }

        $requiredFolders = self::REQUIRED_FOLDERS;
        // La liquidación solo se exige cuando el contrato ya está liquidado
        if ($contract->status === ContratacionContract::STATUS_LIQUIDADO) {
            $requiredFolders[] = 'liquidacion';
        }

        foreach ($requiredFolders as $folder) {
            if (!$documents->contains('folder', $folder)) {
                $missing[] = ['key' => $folder, 'folder' => $folder, 'label' => self::FOLDERS[$folder]];
            }
        }

        return $missing;
    }

    public static function toArray(): array
    {
        return [
            'folders' => collect(self::FOLDERS)->map(fn ($name, $key) => ['key' => $key, 'name' => $name])->values(),
            'legal_documents' => collect(self::LEGAL_DOCUMENTS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'required_folders' => self::REQUIRED_FOLDERS,
        ];
    }
}

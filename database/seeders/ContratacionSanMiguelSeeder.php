<?php

namespace Database\Seeders;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionDocument;
use App\Http\Modules\Contratacion\Service\ContractDocumentCatalog;
use App\Http\Modules\Juridica\Models\ContractType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Contratos de obra existentes de Ciudadela San Miguel (módulo Contratación).
 *
 * Seguro para producción:
 * - Idempotente: un contrato se identifica por frente + tipo + consecutivo + contratista,
 *   así que volver a ejecutarlo actualiza los existentes en lugar de duplicarlos.
 * - Solo LEE de S3: vincula cada contrato con su carpeta existente (s3_path) y registra sus archivos en
 *   contratacion_documents para verlos en la aplicación. No mueve, copia ni crea nada en el bucket.
 *   Los contratos sin carpeta conocida la crean al cargar su primer adjunto.
 * - Crea los frentes y tipos de contrato que falten, reutilizando los existentes sin importar
 *   mayúsculas ni tildes.
 *
 * Ejecutar: php artisan db:seed --class=ContratacionSanMiguelSeeder
 */
class ContratacionSanMiguelSeeder extends Seeder
{
    protected const PROJECT_CODE = 'vis';

    // Estados del archivo de origen -> estados del módulo (ContratacionContract::STATUSES)
    protected const STATUS_MAP = [
        'activo' => ContratacionContract::STATUS_EN_EJECUCION,
        'finalizado-pendiente por liquidacion' => ContratacionContract::STATUS_TERMINADO,
        'en legalizacion' => ContratacionContract::STATUS_POR_INICIAR,
        // Sin estado en el origen
        '' => ContratacionContract::STATUS_POR_INICIAR,
    ];

    // Abreviatura del tipo para el ID del contrato
    protected const TYPE_CODES = [
        'Mano de Obra' => 'MO',
        'Suministro' => 'SUM',
        'Transporte' => 'TR',
        'Suministro e Instalación' => 'SI',
        'Órdenes de Servicio' => 'ODS',
    ];

    // Abreviatura del frente para el ID del contrato
    protected const TOWER_CODES = [
        'TORRE 2' => 'T2',
        'URBANISMO' => 'URB',
    ];

    /**
     * [frente, tipo, consecutivo, contratista, objeto, fecha firma (d/m/Y), estado, carpeta existente en S3]
     *
     * La carpeta es relativa a tecnica/<carpeta del proyecto> (Contratos San Miguel). null = no se encontró:
     * se crea al cargar el primer adjunto desde la aplicación.
     * Si dos contratos comparten carpeta (suministro y transporte de concreto), los archivos se registran
     * solo en el primero para no duplicarlos (eliminar un adjunto en uno lo borraría del otro).
     */
    protected array $contracts = [
        ['TORRE 2', 'Mano de Obra', '01', 'Emelect Group SAS', 'M.O Fundaciones', '30/09/2024', 'Finalizado-Pendiente por liquidación', 'TORRE 2/7. CONTRATOS MANO DE OBRA/01. EMELECT - M.O Fundación'],
        ['TORRE 2', 'Mano de Obra', '02', 'Perforación y voladura demoliciones SAS', 'M.O Voladura de roca', '01/10/2024', 'Finalizado-Pendiente por liquidación', 'TORRE 2/7. CONTRATOS MANO DE OBRA/02. PYV DEMOLICIONES - M.O voladura de roca'],
        ['TORRE 2', 'Mano de Obra', '03', 'Emelect Group SAS', 'M.O Estructura', '12/03/2026', 'Activo', 'TORRE 2/7. CONTRATOS MANO DE OBRA/03. EMELECT - M.O estructura'],
        ['TORRE 2', 'Mano de Obra', '04', 'Yancely Astrid Monsalve Yepes Y&M SAS', 'M.O Enchape', '08/05/2026', 'Activo', 'TORRE 2/7. CONTRATOS MANO DE OBRA/04. YANCELLY MONSALVE - M.O enchape'],
        ['TORRE 2', 'Mano de Obra', '05', 'Yancely Astrid Monsalve Yepes Y&M SAS', 'M.O Mampostería', '08/05/2026', 'Activo', 'TORRE 2/7. CONTRATOS MANO DE OBRA/05. YANCELLY MONSALVE - M.O mamposteria'],
        ['TORRE 2', 'Mano de Obra', '06', 'Tecnoizajes SAS', 'M.O Izaje malla de seguridad', '04/07/2026', 'Finalizado-Pendiente por liquidación', 'TORRE 2/7. CONTRATOS MANO DE OBRA/06. TECNOIZAJES-M.O malla de seguridad'],
        ['TORRE 2', 'Mano de Obra', '08', 'Construaplicamos J Y J SAS', 'Resanes Fachada', '19/08/2026', 'Activo', 'TORRE 2/7. CONTRATOS MANO DE OBRA/08. CONSTRUAPLICAMOS J&J S.A.S'],
        ['TORRE 2', 'Suministro', '01', 'Vías y Triturados de Antioquia', 'Suministro de Concreto', null, 'Activo', 'TORRE 2/8. CONTRATOS SUMINISTRO/01. VIAS Y TRITURADOS DE ANTIOQUIA - Sum y transp. de concreto (CIMENTACIONES)'],
        ['TORRE 2', 'Transporte', '01', 'Vías y Triturados de Antioquia', 'Transporte de Concreto', null, 'Activo', 'TORRE 2/8. CONTRATOS SUMINISTRO/01. VIAS Y TRITURADOS DE ANTIOQUIA - Sum y transp. de concreto (CIMENTACIONES)'],
        ['TORRE 2', 'Suministro', '04', 'Vías y Triturados de Antioquia', 'Suministro de Concreto', null, 'Activo', 'TORRE 2/8. CONTRATOS SUMINISTRO/04. VIAS Y TRITURADOS DE ANTIOQUIA-Sum y trans. de concreto (ESTRUCTURA)'],
        ['TORRE 2', 'Transporte', '04', 'Vías y Triturados de Antioquia', 'Transporte de Concreto', null, 'Activo', 'TORRE 2/8. CONTRATOS SUMINISTRO/04. VIAS Y TRITURADOS DE ANTIOQUIA-Sum y trans. de concreto (ESTRUCTURA)'],
        ['TORRE 2', 'Suministro e Instalación', '01', 'AS Ingeniería', 'Movimiento de Tierras', null, 'Activo', 'TORRE 2/2. CONTRATOS DE URBANISMO/1. As Ingenieria y Transporte. Movimiento de tierra'],
        ['URBANISMO', 'Órdenes de Servicio', '00', 'AS Ingeniería', 'ODS', null, 'Activo', 'URBANISMO/05. ORDENES DE SERVICIO/AS INGENIERIA - Movimiento tierra tanque'],
        ['TORRE 2', 'Suministro e Instalación', '01', 'IHC', 'Redes Hidrosanitarias', null, 'Activo', 'TORRE 2/10. CONTRATOS DE SUMINISTRO E INSTALACIÓN/01. IHC - Sum e inst redes hidrosanitarias'],
        ['URBANISMO', 'Mano de Obra', '01', 'Emelect Group SAS', 'Fundaciones Torre 2', null, 'Activo', 'URBANISMO/07. MANO DE OBRA/01. EMELECT - M.O excavación fundaciones tanque'],
        ['URBANISMO', 'Mano de Obra', '03', 'Emelect Group SAS', 'Estructura Torre 2', null, 'Activo', 'URBANISMO/07. MANO DE OBRA/03. EMELECT - M.O fundaciones muro de contención'],
        ['URBANISMO', 'Transporte', '01', 'AS Ingeniería', 'Movimiento de Tierra', null, '', null],
        ['URBANISMO', 'Mano de Obra', '01', 'Construcciones y Servicios Arias', 'Muro de Contención', null, 'Activo', 'URBANISMO/07. MANO DE OBRA/04. CONSTRUCCIONES Y SERVICIOS ARIAS - M.O Estructura Muro Contención'],
        ['TORRE 2', 'Órdenes de Servicio', '01', 'Caspete', 'Alimentación', null, 'Activo', 'INDIRECTOS/CASPETE'],
        ['TORRE 2', 'Órdenes de Servicio', '01', 'Hidrodinámica y Estructuras', 'ODS 1', null, 'Activo', 'ORDENES DE SERVICIO (PAGO DIRECTO SIN CONTRATO)/Ordenes de servicio 21. HIDRODINAMICA Y ESTRUCTURAS'],
        ['TORRE 2', 'Mano de Obra', '09', 'VYM Arquitectonicos', 'Instalación Ventaneria', null, 'Activo', 'TORRE 2/7. CONTRATOS MANO DE OBRA/09.V&M  m.o instalacion ventaneria'],
        ['URBANISMO', 'Órdenes de Servicio', '03', 'Trans-Excava SAS', 'Retiro De Material', null, 'Activo', null],
        ['TORRE 2', 'Mano de Obra', '01', 'IHC', 'INSTALACIÓN (M.O)', null, 'Activo', null],
        ['TORRE 2', 'Suministro e Instalación', '01', 'Aplitekpro', 'Impermeabilización Cubierta', null, 'En legalización', null],
    ];

    public function run(): void
    {
        $project = Project::where('code', self::PROJECT_CODE)->first();
        if (!$project) {
            throw new RuntimeException('No existe el proyecto Ciudadela San Miguel (code "' . self::PROJECT_CODE . '"). Ejecuta primero ProjectSeeder.');
        }

        $created = 0;
        $updated = 0;

        $linked = [];

        DB::transaction(function () use ($project, &$created, &$updated, &$linked) {
            $towers = [];
            $types = [];

            foreach ($this->contracts as [$towerName, $typeName, $consecutive, $contractor, $object, $signedAt, $status, $folder]) {
                $tower = $towers[$towerName] ??= $this->resolveTower($project, $towerName);
                $type = $types[$typeName] ??= $this->resolveType($typeName);

                $attributes = [
                    'contract_code' => $this->contractCode($towerName, $typeName, $consecutive, $contractor),
                    'object' => $object,
                    'signed_at' => $signedAt ? Carbon::createFromFormat('d/m/Y', $signedAt)->toDateString() : null,
                    'status' => $this->mapStatus($status),
                ];
                // Sin carpeta conocida no se toca s3_path: así no se pierde la que haya creado la aplicación
                if ($folder) {
                    $attributes['s3_path'] = $this->projectPath($project) . '/' . $folder;
                }

                $contract = ContratacionContract::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'tower_id' => $tower->id,
                        'contract_type_id' => $type->id,
                        'consecutive' => $consecutive,
                        'contractor_name' => $contractor,
                    ],
                    $attributes
                );

                $contract->wasRecentlyCreated ? $created++ : $updated++;

                if ($folder) {
                    $linked[] = $contract;
                }
            }
        });

        // Los archivos se leen fuera de la transacción: listar S3 puede tardar y no debe bloquear la BD
        $seenPaths = [];
        $files = 0;
        foreach ($linked as $contract) {
            if (isset($seenPaths[$contract->s3_path])) {
                $this->command?->warn("  {$contract->contract_code}: comparte carpeta con {$seenPaths[$contract->s3_path]}, sus archivos quedan registrados allá.");
                continue;
            }
            $seenPaths[$contract->s3_path] = $contract->contract_code;
            $files += $this->syncDocuments($contract);
        }

        $this->command?->info("Contratos San Miguel: {$created} creados, {$updated} actualizados, " . count($seenPaths) . " carpetas vinculadas, {$files} archivos registrados.");
    }

    /**
     * Frente de obra del proyecto; lo crea en mayúsculas si no existe.
     */
    protected function resolveTower(Project $project, string $name): Tower
    {
        $tower = Tower::where('project_id', $project->id)
            ->get()
            ->first(fn (Tower $t) => $this->comparable($t->name) === $this->comparable($name));

        return $tower ?? Tower::create([
            'project_id' => $project->id,
            'name' => $name,
            'code' => self::TOWER_CODES[$name] ?? null,
        ]);
    }

    /**
     * Tipo de contrato; reutiliza el existente (reactivándolo si estaba desactivado) o lo crea.
     */
    protected function resolveType(string $name): ContractType
    {
        $type = ContractType::all()->first(fn (ContractType $t) => $this->comparable($t->name) === $this->comparable($name));

        if ($type) {
            if (!$type->is_active) {
                $type->update(['is_active' => true]);
            }

            return $type;
        }

        return ContractType::create(['name' => $name, 'code' => self::TYPE_CODES[$name] ?? null, 'is_active' => true]);
    }

    /**
     * ID del contrato: <frente>-<tipo>-<consecutivo>-<contratista>, ej. T2-MO-03-EMELECT.
     * Incluye el contratista porque en el origen hay consecutivos repetidos dentro del mismo frente y tipo.
     */
    protected function contractCode(string $tower, string $type, string $consecutive, string $contractor): string
    {
        $contractorCode = Str::upper(Str::ascii(Str::before(trim($contractor), ' ')));

        return implode('-', [self::TOWER_CODES[$tower] ?? Str::upper($tower), self::TYPE_CODES[$type], $consecutive, $contractorCode]);
    }

    protected function mapStatus(string $status): string
    {
        $key = $this->comparable($status);

        if (!array_key_exists($key, self::STATUS_MAP)) {
            throw new RuntimeException("Estado de contrato no reconocido: \"{$status}\".");
        }

        return self::STATUS_MAP[$key];
    }

    protected function projectPath(Project $project): string
    {
        return trim(config('contratacion.s3_root'), '/') . '/' . ($project->contracts_folder ?: 'Contratos ' . $project->name);
    }

    /**
     * Registra en contratacion_documents los archivos que hay en la carpeta del contrato y quita los
     * registros de archivos que ya no están (solo en la BD: nunca borra nada del bucket).
     * Devuelve cuántos archivos quedaron registrados.
     */
    protected function syncDocuments(ContratacionContract $contract): int
    {
        $disk = Storage::disk('s3');

        // Se lista en lugar de usar directoryExists(): en DigitalOcean Spaces devuelve false para
        // carpetas sin objeto marcador aunque tengan archivos
        $files = [];
        foreach ($disk->listContents($contract->s3_path, true) as $item) {
            if ($item->isFile() && basename($item->path()) !== '.keep') {
                $files[$item->path()] = $item;
            }
        }

        if (!$files) {
            $this->command?->warn("  {$contract->contract_code}: la carpeta {$contract->s3_path} no existe o está vacía.");
        }

        $contract->documents()->whereNotIn('path', array_keys($files) ?: [''])->delete();
        $existing = $contract->documents()->pluck('path')->flip();

        foreach ($files as $path => $item) {
            if ($existing->has($path)) {
                continue;
            }

            $relative = ltrim(Str::after($path, $contract->s3_path), '/');
            $folder = $this->folderFor($relative);

            ContratacionDocument::create([
                'contract_id' => $contract->id,
                'folder' => $folder,
                'document_type' => $folder === ContractDocumentCatalog::FOLDER_LEGAL ? $this->legalTypeFor(basename($path)) : null,
                'name' => basename($path),
                'path' => $path,
                'size' => (int) ($item->fileSize() ?? 0),
                'mime_type' => $item->mimeType(),
                'uploaded_by' => null,
            ]);
        }

        return count($files);
    }

    /**
     * Carpeta del catálogo según la subcarpeta del archivo (ej. "05 CORTES/..." o "CORTE 01/..." -> cortes).
     * Lo que no encaja (anexos, archivos sueltos, ODS 09...) va a "otros".
     */
    protected function folderFor(string $relativePath): string
    {
        if (!str_contains($relativePath, '/')) {
            return 'otros';
        }

        $first = $this->comparable(Str::before($relativePath, '/'));

        return match (true) {
            str_contains($first, 'legal') => 'legal',
            str_contains($first, 'pliego') => 'pliego',
            str_contains($first, 'acta') => 'acta_inicio',
            str_contains($first, 'corte') => 'cortes',
            str_contains($first, 'liquidacion') => 'liquidacion',
            str_contains($first, 'contrato') => 'contrato',
            default => 'otros',
        };
    }

    /**
     * Tipo de documento legal según el nombre (ej. "1.2 RUT EMELECT.pdf"). null si no se reconoce
     * (certificación bancaria, etc.): el contrato seguirá mostrando la alerta de ese documento.
     */
    protected function legalTypeFor(string $filename): ?string
    {
        $name = ' ' . preg_replace('/[^a-z0-9]+/', ' ', $this->comparable($filename)) . ' ';

        return match (true) {
            str_contains($name, ' rut ') => 'rut',
            str_contains($name, ' camara ') => 'camara_comercio',
            str_contains($name, ' cedula ') || str_contains($name, ' cc ') => 'cc',
            default => null,
        };
    }

    protected function comparable(string $value): string
    {
        return trim(Str::lower(Str::ascii($value)));
    }
}

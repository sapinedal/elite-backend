<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Tasks\Models\TaskObservation;
use App\Http\Modules\Tasks\Models\TaskAuditLog;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Configuracion\Models\Area;
use Carbon\Carbon;

class ImportTasksSheetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:import-sheet 
                            {--test : Importa únicamente la tarea de prueba (Fila 3)} 
                            {--row= : Importa una fila específica del Excel} 
                            {--dry-run : Simula la importación sin guardar en la BD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa tareas y su histórico de observaciones (Dailys/Comités) desde el Excel a la base de datos';

    /**
     * Mapeos conocidos de alias de usuarios
     */
    protected array $userAliases = [
        'SARA' => 'sara.moreno@elite.com',
        'SARA MORENO' => 'sara.moreno@elite.com',
        'SARA ELENA MORENO OROZCO' => 'sara.moreno@elite.com',
        'INGRID' => 'ingrid.ospicio@elite.com',
        'INGRID OSPINO' => 'ingrid.ospicio@elite.com',
        'INGRID PAOLA OSPICIO PACHECO' => 'ingrid.ospicio@elite.com',
        'PAOLA ANDREA ARENAS GAVIRIA' => 'paola.arenas@elite.com',
        'NATALIA ANDREA POSADA RAVE' => 'natalia.posada@elite.com',
        'SAMUEL PINEDA' => 'samuel.pineda@elite.com',
        'LÍDER TRÁMITES Y ESCRITURACIÓN' => 'tramites.lider@elite.com',
        'TRÁMITES CIUDADELA SAN MIGUEL' => 'tramites.lider@elite.com',
        'TRAMITES CIUDADELA SAN MIGUEL' => 'tramites.lider@elite.com',
        'JUAN CARLOS ESQUIVEL HOYOS' => 'almacen@inverconstruccion.com',
        'SANTIAGO PRIETO PINTO' => 'santiago.prieto@elite.com',
        'JORGE ELIAS PEMBERTY ZAPATA' => 'dirobrasanmiguel@inverconstruccion.com',
        'OBRA SAN MIGUEL' => 'dirobrasanmiguel@inverconstruccion.com',
        'JULIÁN POSADA' => 'residenteacabados@inverconstruccion.com',
        'JULIAN POSADA' => 'residenteacabados@inverconstruccion.com',
        'JULIAN ANDRES POSADA MORALES' => 'residenteacabados@inverconstruccion.com',
        'SOFIA YEPES PEÑA' => 'contadora@inverconstruccion.com',
        'SOFIA YEPES' => 'contadora@inverconstruccion.com',
        'ISABEL CRISTINA GARCIA MARIN' => 'analistacontable2@inverconstruccion.com',
        'CLAUDIA PATRICIA JIMENEZ CARVAJAL' => 'asistentedetesoreria@inverconstruccion.com',
        'SHARON JOLAINE VELANDIA TELLEZ' => 'facturacion@inverconstruccion.com',
        'VANESSA CALLE VALDERRAMA' => 'analistacontable@inverconstruccion.com',
        'ANALISTACONTABLE@INVERCONSTRUCCION.COM' => 'analistacontable@inverconstruccion.com',
        'GINNA MARCELA QUINTANA LEON' => 'juridica@inverconstruccion.com',
        'MARCELA QUINTANA' => 'juridica@inverconstruccion.com',
        'MANUELA MARIA MEJIA GOMEZ' => 'mmejia@cybproject.com',
        'MANUELA MEJIA' => 'mmejia@cybproject.com',
        'MMEJIA@CYBPROJECT.COM' => 'mmejia@cybproject.com',
        'SANTIAGO SANCHEZ VILLA' => 'ssanchez@cybproject.com',
        'SANTIAGO SANCHEZ' => 'ssanchez@cybproject.com',
        'SSANCHEZ@CYBPROJECT.COM' => 'ssanchez@cybproject.com',
    ];

    /**
     * Mapeos de estados
     */
    protected array $statusMap = [
        'por hacer' => 'Por hacer',
        'en espera' => 'En espera',
        'en progreso' => 'En progreso',
        'en proceso' => 'En progreso',
        'completada' => 'Completada',
        'completado' => 'Completada',
        'finalizada' => 'Completada',
        'finalizado' => 'Completada',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isTest = $this->option('test');
        $rowOption = $this->option('row');
        $isDryRun = $this->option('dry-run');

        $this->info('===========================================================');
        $this->info('  MIGRACIÓN / IMPORTACIÓN DE TAREAS DESDE GOOGLE SHEETS');
        $this->info('===========================================================');

        // 1. Obtener usuario administrador por defecto para requested_by_id
        $adminUser = User::where('email', 'samuel.pineda@elite.com')->first() ?? User::first();
        if (!$adminUser) {
            $this->error('No se encontró ningún usuario administrador en la base de datos.');
            return 1;
        }
        $this->info("✓ Usuario administrador por defecto: {$adminUser->name} (ID: {$adminUser->id})");

        // 2. Cargar caché de usuarios
        $users = User::all();
        $userMap = [];
        foreach ($users as $user) {
            $email = strtolower(trim($user->email));
            $name = $this->normalizeString($user->name);
            $userMap[$email] = $user->id;
            $userMap[$name] = $user->id;
        }

        // Agregar alias
        foreach ($this->userAliases as $alias => $email) {
            $emailLower = strtolower(trim($email));
            if (isset($userMap[$emailLower])) {
                $userMap[$this->normalizeString($alias)] = $userMap[$emailLower];
            }
        }

        // 3. Cargar caché de áreas
        $areas = Area::all();
        $areaMap = [];
        foreach ($areas as $area) {
            $areaMap[$this->normalizeString($area->name)] = $area->id;
        }

        // 4. Ejecutar extractor Python para leer el Excel
        $pythonScript = base_path('scripts/extract_tasks_json.py');
        $cmd = "python3 " . escapeshellarg($pythonScript);
        if ($isTest) {
            $cmd .= " --test";
        } elseif ($rowOption) {
            $cmd .= " --row " . intval($rowOption);
        }

        $this->line("Extrayendo datos de Excel con script auxiliar...");
        $output = shell_exec($cmd);
        if (!$output) {
            $this->error('Error ejecutando el extractor Python o archivo Excel inaccesible.');
            return 1;
        }

        $tasksData = json_decode($output, true);
        if (!is_array($tasksData)) {
            $this->error('Formato de salida JSON no válido recibido del extractor.');
            $this->line(substr($output, 0, 500));
            return 1;
        }

        $totalTasks = count($tasksData);
        $this->info("✓ Se procesarán {$totalTasks} tarea(s).");

        if ($isDryRun) {
            $this->warn('[MODO DRY-RUN ACTIVADO] No se guardarán cambios en la BD.');
        }

        $importedTasksCount = 0;
        $importedObsCount = 0;

        DB::beginTransaction();

        try {
            foreach ($tasksData as $taskItem) {
                $rowNum = $taskItem['row'];
                $title = trim($taskItem['title']);
                $priorityRaw = strtoupper(trim($taskItem['priority'] ?? 'P2'));
                $priority = in_array($priorityRaw, ['P0', 'P1', 'P2', 'P3']) ? $priorityRaw : 'P2';

                $estadoRaw = strtolower(trim($taskItem['estado'] ?? ''));
                $status = $this->statusMap[$estadoRaw] ?? 'Por hacer';

                // Resolver responsable
                $respRaw = trim($taskItem['responsable'] ?? '');
                $responsibleId = null;
                if ($respRaw !== '') {
                    $respEmailKey = strtolower($respRaw);
                    $respNameKey = $this->normalizeString($respRaw);
                    if (isset($userMap[$respEmailKey])) {
                        $responsibleId = $userMap[$respEmailKey];
                    } elseif (isset($userMap[$respNameKey])) {
                        $responsibleId = $userMap[$respNameKey];
                    } else {
                        // Búsqueda parcial
                        foreach ($userMap as $mapKey => $uid) {
                            if (strlen($mapKey) > 4 && (str_contains($mapKey, $respNameKey) || str_contains($respNameKey, $mapKey))) {
                                $responsibleId = $uid;
                                break;
                            }
                        }
                    }
                }

                // Resolver área
                $areaRaw = trim($taskItem['area'] ?? '');
                $areaId = null;
                if ($areaRaw !== '') {
                    $areaKey = $this->normalizeString($areaRaw);
                    if (isset($areaMap[$areaKey])) {
                        $areaId = $areaMap[$areaKey];
                    } else {
                        if (!$isDryRun) {
                            $newArea = Area::create(['name' => $areaRaw]);
                            $areaId = $newArea->id;
                            $areaMap[$areaKey] = $areaId;
                            $this->line("  [Área creada] {$areaRaw} (ID: {$areaId})");
                        } else {
                            $areaId = 999;
                        }
                    }
                }

                $startDate = !empty($taskItem['start_date']) ? Carbon::parse($taskItem['start_date'])->toDateString() : null;
                $schedEndDate = !empty($taskItem['sched_end_date']) ? Carbon::parse($taskItem['sched_end_date'])->toDateString() : null;
                $actualEndDate = !empty($taskItem['actual_end_date']) ? Carbon::parse($taskItem['actual_end_date'])->toDateString() : null;

                if ($status === 'Completada' && empty($actualEndDate)) {
                    $actualEndDate = $schedEndDate;
                }

                $this->line("-----------------------------------------------------------");
                $this->line("Fila {$rowNum}: {$title}");
                $this->line("  Prioridad:   {$priority}");
                $this->line("  Estado:      {$status}");
                $this->line("  Responsable: " . ($respRaw ?: 'Ninguno') . ($responsibleId ? " → ID {$responsibleId}" : " → [Sin asignar / null]"));
                $this->line("  Área:        " . ($areaRaw ?: 'Ninguna') . " (ID: " . ($areaId ?? 'null') . ")");
                $this->line("  Fechas:      Inicio: {$startDate} | Fin Estimado: {$schedEndDate} | Fin Real: {$actualEndDate}");
                $this->line("  Dailys/Obs:  " . count($taskItem['observations']) . " notas históricas");

                if (!$isDryRun) {
                    $task = Task::create([
                        'title' => $title,
                        'priority' => $priority,
                        'status' => $status,
                        'requested_by_id' => $adminUser->id,
                        'responsible_id' => $responsibleId,
                        'area_id' => $areaId,
                        'start_date' => $startDate,
                        'scheduled_end_date' => $schedEndDate,
                        'actual_end_date' => $actualEndDate,
                    ]);

                    // Audit Log
                    TaskAuditLog::create([
                        'task_id' => $task->id,
                        'user_id' => $adminUser->id,
                        'action' => 'created',
                        'changes' => [
                            'title' => ['new' => $task->title],
                            'priority' => ['new' => $task->priority],
                            'status' => ['new' => $task->status],
                            'responsible_id' => ['new' => $task->responsible_id],
                            'area_id' => ['new' => $task->area_id]
                        ]
                    ]);

                    // Observaciones
                    $observerUserId = $responsibleId ?: $adminUser->id;
                    foreach ($taskItem['observations'] as $obs) {
                        $obsDate = !empty($obs['date']) ? Carbon::parse($obs['date'])->setTime(12, 0, 0) : Carbon::now();
                        
                        TaskObservation::create([
                            'task_id' => $task->id,
                            'user_id' => $observerUserId,
                            'observation' => $obs['text'],
                            'created_at' => $obsDate,
                            'updated_at' => $obsDate,
                        ]);
                        $importedObsCount++;
                    }

                    $importedTasksCount++;
                    $this->info("  ✓ Tarea creada exitosamente con ID: {$task->id}");
                } else {
                    $importedTasksCount++;
                    $importedObsCount += count($taskItem['observations']);
                }
            }

            if (!$isDryRun) {
                DB::commit();
                $this->info("\n===========================================================");
                $this->info("✓ MIGRACIÓN FINALIZADA CON ÉXITO");
                $this->info("  Tareas importadas:        {$importedTasksCount}");
                $this->info("  Observaciones importadas: {$importedObsCount}");
                $this->info("===========================================================");
            } else {
                DB::rollBack();
                $this->info("\n===========================================================");
                $this->info("✓ SIMULACIÓN DRY-RUN COMPLETADA");
                $this->info("  Tareas proyectadas:        {$importedTasksCount}");
                $this->info("  Observaciones proyectadas: {$importedObsCount}");
                $this->info("===========================================================");
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error importando tareas: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }

    private function normalizeString(?string $str): string
    {
        if (!$str) {
            return '';
        }
        $str = mb_strtoupper(trim($str), 'UTF-8');
        $str = preg_replace('/\s+/', ' ', $str);
        // Quitar acentos para match tolerante
        $unaccented = strtr(
            $str,
            ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']
        );
        return $unaccented;
    }
}

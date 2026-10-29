<?php

namespace App\Http\Modules\Contratacion\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Mail\PolicyExpiringMail;
use App\Http\Modules\Contratacion\Models\ContratacionPolicy;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifica por correo las pólizas que llegan a cada umbral antes de vencer (40, 30 y 20 días por defecto,
 * config contratacion.policy_alert_thresholds). Cada umbral se avisa una sola vez (last_alert_days); una póliza
 * que entra tarde (ej. registrada con 25 días) recibe el aviso del umbral en que está. Las vencidas reciben el
 * último aviso si no lo tenían. Si cambia la vigencia se vuelve a empezar (ContractService limpia los avisos).
 */
class PolicyAlertService
{
    // Quien lo tenga recibe los avisos de pólizas, además de contratacion.alert_emails
    public const PERMISSION_RECIPIENT = 'contratacion.alertas_polizas';

    /**
     * @return array{projects: int, policies: int, skipped: int}
     */
    public function sendPendingAlerts(): array
    {
        $limit = now()->startOfDay()->addDays(config('contratacion.policy_alert_days'));

        $policies = ContratacionPolicy::query()
            ->whereDate('expires_at', '<=', $limit)
            ->where(fn ($q) => $q->whereNull('last_alert_days')->orWhere('last_alert_days', '>', min(config('contratacion.policy_alert_thresholds'))))
            ->whereHas('contract') // excluye contratos eliminados (soft delete)
            ->with(['contract.project:id,name', 'contract.tower:id,name'])
            ->orderBy('expires_at')
            ->get()
            ->filter(fn (ContratacionPolicy $policy) => $policy->needsAlert());

        $summary = ['projects' => 0, 'policies' => 0, 'skipped' => 0];

        foreach ($policies->groupBy('contract.project_id') as $projectPolicies) {
            $project = $projectPolicies->first()->contract->project;
            $recipients = $this->recipientsFor($project);

            if ($recipients->isEmpty()) {
                Log::warning("Contratación: no hay destinatarios para las alertas de pólizas del proyecto {$project->name}.");
                $summary['skipped'] += $projectPolicies->count();
                continue;
            }

            try {
                Mail::to($recipients->all())->send(new PolicyExpiringMail($project, $projectPolicies));
            } catch (Throwable $e) {
                // Se reintenta en la próxima ejecución porque last_alert_days no cambia
                report($e);
                $summary['skipped'] += $projectPolicies->count();
                continue;
            }

            foreach ($projectPolicies->groupBy(fn (ContratacionPolicy $p) => $p->alertThreshold()) as $threshold => $sent) {
                ContratacionPolicy::whereKey($sent->pluck('id'))->update(['alert_sent_at' => now(), 'last_alert_days' => $threshold]);
            }
            $summary['projects']++;
            $summary['policies'] += $projectPolicies->count();
        }

        return $summary;
    }

    /**
     * Destinatarios: los correos de contratacion.alert_emails (Director de Obra y Paula Camacho) más los usuarios
     * con el permiso contratacion.alertas_polizas que tienen acceso al proyecto. Si no hay ninguno, los usuarios
     * con contratacion.editar del proyecto, para que el aviso no se pierda.
     */
    protected function recipientsFor(Project $project): Collection
    {
        $usersWith = fn (string $permission) => User::permission($permission)
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => $user->canAccessProject($project->id))
            ->pluck('email');

        $emails = collect(config('contratacion.alert_emails'))->merge($usersWith(self::PERMISSION_RECIPIENT));
        if ($emails->filter()->isEmpty()) {
            $emails = $usersWith('contratacion.editar');
        }

        return $emails
            ->map(fn ($email) => mb_strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();
    }
}

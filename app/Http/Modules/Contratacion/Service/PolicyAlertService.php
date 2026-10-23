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
 * Notifica por correo las pólizas cuya vigencia vence dentro de la ventana configurada
 * (40 días por defecto). Cada póliza se notifica una sola vez; si cambia su fecha de vigencia
 * se vuelve a notificar (ContractService limpia alert_sent_at).
 */
class PolicyAlertService
{
    /**
     * @return array{projects: int, policies: int, skipped: int}
     */
    public function sendPendingAlerts(): array
    {
        $limit = now()->startOfDay()->addDays(config('contratacion.policy_alert_days'));

        $policies = ContratacionPolicy::query()
            ->whereNull('alert_sent_at')
            ->whereDate('expires_at', '<=', $limit)
            ->whereHas('contract') // excluye contratos eliminados (soft delete)
            ->with(['contract.project:id,name', 'contract.tower:id,name'])
            ->orderBy('expires_at')
            ->get();

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
                // Se reintenta en la próxima ejecución porque alert_sent_at queda vacío
                report($e);
                $summary['skipped'] += $projectPolicies->count();
                continue;
            }

            ContratacionPolicy::whereKey($projectPolicies->pluck('id'))->update(['alert_sent_at' => now()]);
            $summary['projects']++;
            $summary['policies'] += $projectPolicies->count();
        }

        return $summary;
    }

    /**
     * Destinatarios configurados en contratacion.alert_emails; si no hay, los usuarios con permiso
     * contratacion.editar que tienen acceso al proyecto.
     */
    protected function recipientsFor(Project $project): Collection
    {
        $configured = collect(config('contratacion.alert_emails'));

        $emails = $configured->isNotEmpty()
            ? $configured
            : User::permission('contratacion.editar')
                ->whereNotNull('email')
                ->get()
                ->filter(fn (User $user) => $user->canAccessProject($project->id))
                ->pluck('email');

        return $emails
            ->map(fn ($email) => mb_strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();
    }
}

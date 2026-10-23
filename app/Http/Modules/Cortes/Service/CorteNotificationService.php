<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Cortes\Mail\CorteNotificationMail;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos del flujo de cortes. Quién recibe cada aviso:
 *
 * - Radicado / reenviado: el residente (confirmación) y los revisores de Interventor (deben revisar).
 * - Aprobado en una etapa intermedia: los revisores de la siguiente etapa, con copia informativa al residente.
 * - Aprobado en Gerencia: el residente y quienes aprobaron las etapas anteriores.
 * - Devuelto: el residente y quienes ya lo habían aprobado en el ciclo actual
 *   (Interventor -> solo residente; Director -> residente + interventor; Gerencia -> residente + interventor + director).
 *
 * Un fallo del correo nunca revierte la acción: se registra en el log y el flujo continúa.
 */
class CorteNotificationService
{
    public function submitted(Corte $corte, bool $isResubmission): void
    {
        $event = $isResubmission ? CorteNotificationMail::EVENT_RESUBMITTED : CorteNotificationMail::EVENT_SUBMITTED;

        $this->send($corte, $event, $this->stageReviewers($corte, CorteWorkflow::firstStage()), CorteWorkflow::firstStage());
        $this->send($corte, CorteNotificationMail::EVENT_CONFIRMATION, $this->creatorEmail($corte));
    }

    public function approved(Corte $corte, string $stage, ?string $comment): void
    {
        $nextStage = CorteWorkflow::nextStage($stage);

        if ($nextStage) {
            $this->send($corte, CorteNotificationMail::EVENT_PENDING_REVIEW, $this->stageReviewers($corte, $nextStage), $nextStage, $comment);
            $this->send($corte, CorteNotificationMail::EVENT_ADVANCED, $this->creatorEmail($corte), $stage, $comment);

            return;
        }

        $recipients = $this->creatorEmail($corte)->merge($this->previousApprovers($corte, $stage));
        $this->send($corte, CorteNotificationMail::EVENT_APPROVED, $recipients, $stage, $comment);
    }

    public function returned(Corte $corte, string $stage, string $comment): void
    {
        $recipients = $this->creatorEmail($corte)->merge($this->previousApprovers($corte, $stage));
        $this->send($corte, CorteNotificationMail::EVENT_RETURNED, $recipients, $stage, $comment);
    }

    protected function send(Corte $corte, string $event, Collection $recipients, ?string $stage = null, ?string $comment = null): void
    {
        $recipients = $recipients
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            Log::warning("Cortes: no hay destinatarios para el aviso '{$event}' del corte {$corte->code} (etapa: " . ($stage ?? '-') . ').');
            return;
        }

        try {
            Mail::to($recipients->all())->send(new CorteNotificationMail($corte, $event, $stage, $comment));
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function creatorEmail(Corte $corte): Collection
    {
        return collect([$corte->creator?->email]);
    }

    /**
     * Usuarios con el permiso de la etapa y acceso al proyecto, más los correos de config/cortes.php.
     */
    protected function stageReviewers(Corte $corte, string $stage): Collection
    {
        $users = User::permission(CorteWorkflow::STAGES[$stage]['permission'])
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => $user->canAccessProject($corte->project_id))
            ->pluck('email');

        return $users->merge(config("cortes.stage_emails.{$stage}", []));
    }

    /**
     * Correos de quienes aprobaron las etapas anteriores a $stage en el ciclo actual
     * (desde la última radicación o reenvío).
     */
    protected function previousApprovers(Corte $corte, string $stage): Collection
    {
        $previous = CorteWorkflow::previousStages($stage);
        if (!$previous) {
            return collect();
        }

        $cycleStartId = $corte->history()
            ->whereIn('action', [CorteHistory::ACTION_RADICADO, CorteHistory::ACTION_REENVIADO])
            ->max('id');

        return $corte->history()
            ->where('id', '>', $cycleStartId ?? 0)
            ->where('action', CorteHistory::ACTION_APROBADO)
            ->whereIn('stage', $previous)
            ->with('user:id,email')
            ->get()
            ->pluck('user.email');
    }
}

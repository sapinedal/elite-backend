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
 * Correos del flujo de cortes. Quién recibe cada aviso (destinatarios en config/cortes.php):
 *
 * | Evento                       | Para                                                         |
 * |------------------------------|--------------------------------------------------------------|
 * | Radicado / reenviado         | Interventor (debe revisar) · Residentes (confirmación)       |
 * | Aprobado por Interventor     | Director de Obra (debe revisar) · Residentes (avance)        |
 * | Aprobado por Director        | Gerencia (debe revisar) · Residentes (avance)                |
 * | Aprobado por Gerencia        | Residentes + Interventor + Director · Facturación (aparte)   |
 * | Devuelto por Interventor     | Residentes                                                   |
 * | Devuelto por Director        | Residentes + Interventor                                     |
 * | Devuelto por Gerencia        | Residentes + Interventor + Director                          |
 *
 * "Residentes" = quien radicó el corte + cortes.residentes. En TODOS los correos va en copia cortes.monitor.
 * Un fallo del correo nunca revierte la acción: se registra en el log y el flujo continúa.
 */
class CorteNotificationService
{
    public function submitted(Corte $corte, bool $isResubmission, ?string $comment = null): void
    {
        $firstStage = CorteWorkflow::firstStage();
        $event = $isResubmission ? CorteNotificationMail::EVENT_RESUBMITTED : CorteNotificationMail::EVENT_SUBMITTED;

        $this->send($corte, $event, $this->stageRecipients($corte, $firstStage), $firstStage, $comment);
        $this->send($corte, CorteNotificationMail::EVENT_CONFIRMATION, $this->residentes($corte), null, $comment);
    }

    public function approved(Corte $corte, string $stage, ?string $comment): void
    {
        $nextStage = CorteWorkflow::nextStage($stage);

        if ($nextStage) {
            $this->send($corte, CorteNotificationMail::EVENT_PENDING_REVIEW, $this->stageRecipients($corte, $nextStage), $nextStage, $comment);
            $this->send($corte, CorteNotificationMail::EVENT_ADVANCED, $this->residentes($corte), $stage, $comment);

            return;
        }

        // Última etapa: aviso de aprobación a todos los que participaron y correo aparte a Facturación
        $participants = $this->residentes($corte)->merge($this->previousReviewers($corte, $stage));
        $this->send($corte, CorteNotificationMail::EVENT_APPROVED, $participants, $stage, $comment);
        $this->send($corte, CorteNotificationMail::EVENT_BILLING, collect(config('cortes.facturacion', [])), $stage, $comment);
    }

    public function returned(Corte $corte, string $stage, string $comment): void
    {
        $recipients = $this->residentes($corte)->merge($this->previousReviewers($corte, $stage));
        $this->send($corte, CorteNotificationMail::EVENT_RETURNED, $recipients, $stage, $comment);
    }

    /**
     * Envía un correo con copia al monitor. Si no hay destinatarios igual se envía al monitor,
     * para que quede constancia de que el evento ocurrió.
     */
    public function send(Corte $corte, string $event, Collection $recipients, ?string $stage = null, ?string $comment = null): void
    {
        $to = $this->normalize($recipients);
        $cc = $this->normalize(collect(config('cortes.monitor', [])))->diff($to)->values();

        if ($to->isEmpty()) {
            Log::warning("Cortes: no hay destinatarios para el aviso '{$event}' del corte {$corte->code} (etapa: " . ($stage ?? '-') . ').');
            if ($cc->isEmpty()) {
                return;
            }
            [$to, $cc] = [$cc, collect()];
        }

        try {
            Mail::to($to->all())
                ->cc($cc->all())
                ->send(new CorteNotificationMail($corte, $event, $stage, $comment));
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function normalize(Collection $emails): Collection
    {
        return $emails
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    /**
     * Quien radicó el corte más los residentes de obra configurados.
     */
    protected function residentes(Corte $corte): Collection
    {
        return collect([$corte->creator?->email])->merge(config('cortes.residentes', []));
    }

    /**
     * Revisores de una etapa: los correos configurados en cortes.stage_emails; si no hay, los usuarios con
     * el permiso de la etapa que tienen acceso al proyecto.
     */
    protected function stageRecipients(Corte $corte, string $stage): Collection
    {
        $configured = config("cortes.stage_emails.{$stage}", []);
        if ($configured) {
            return collect($configured);
        }

        return User::permission(CorteWorkflow::STAGES[$stage]['permission'])
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => $user->canAccessProject($corte->project_id))
            ->pluck('email');
    }

    /**
     * Revisores de las etapas anteriores a $stage: los configurados y quienes realmente lo aprobaron
     * en el ciclo actual (desde la última radicación o reenvío).
     */
    protected function previousReviewers(Corte $corte, string $stage): Collection
    {
        $previous = CorteWorkflow::previousStages($stage);
        if (!$previous) {
            return collect();
        }

        $configured = collect($previous)->flatMap(fn (string $s) => $this->stageRecipients($corte, $s));

        $cycleStartId = $corte->history()
            ->whereIn('action', [CorteHistory::ACTION_RADICADO, CorteHistory::ACTION_REENVIADO])
            ->max('id');

        $approvers = $corte->history()
            ->where('id', '>', $cycleStartId ?? 0)
            ->where('action', CorteHistory::ACTION_APROBADO)
            ->whereIn('stage', $previous)
            ->with('user:id,email')
            ->get()
            ->pluck('user.email');

        return $configured->merge($approvers);
    }
}

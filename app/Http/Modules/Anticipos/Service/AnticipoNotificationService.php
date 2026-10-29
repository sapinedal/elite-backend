<?php

namespace App\Http\Modules\Anticipos\Service;

use App\Http\Modules\Anticipos\Mail\AnticipoNotificationMail;
use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Anticipos\Models\AnticipoHistory;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos del flujo de anticipos. Quién recibe cada aviso (destinatarios en config/anticipos.php):
 *
 * | Evento                         | Para                                                        |
 * |--------------------------------|-------------------------------------------------------------|
 * | Radicado / reenviado           | Director de Obra (debe aprobar) · Residente (confirma)      |
 * | Aprobado por Director          | Gerencia (debe aprobar) · Residente (avance)                |
 * | Aprobado por Gerencia          | Contabilidad (generar en Multifox) · Residente + Director   |
 * | Devuelto por Director          | Residente                                                   |
 * | Devuelto por Gerencia          | Residente + Director                                        |
 * | Anticipo cargado de Multifox   | Residente + aprobadores (pasó al cuadro de pagos)           |
 *
 * En TODOS los correos va en copia anticipos.monitor. Un fallo del correo nunca revierte la acción.
 */
class AnticipoNotificationService
{
    public function submitted(Anticipo $anticipo, bool $isResubmission, ?string $comment = null): void
    {
        $firstStage = AnticipoWorkflow::firstStage();
        $event = $isResubmission ? AnticipoNotificationMail::EVENT_RESUBMITTED : AnticipoNotificationMail::EVENT_SUBMITTED;

        $this->send($anticipo, $event, $this->stageRecipients($anticipo, $firstStage), $firstStage, $comment);
        $this->send($anticipo, AnticipoNotificationMail::EVENT_CONFIRMATION, $this->creator($anticipo), null, $comment);
    }

    public function approved(Anticipo $anticipo, string $stage, ?string $comment): void
    {
        $nextStage = AnticipoWorkflow::nextStage($stage);

        if ($nextStage) {
            $this->send($anticipo, AnticipoNotificationMail::EVENT_PENDING_REVIEW, $this->stageRecipients($anticipo, $nextStage), $nextStage, $comment);
            $this->send($anticipo, AnticipoNotificationMail::EVENT_ADVANCED, $this->creator($anticipo), $stage, $comment);

            return;
        }

        // Aprobación final: Contabilidad genera el anticipo en Multifox; aviso aparte al residente y aprobadores
        $this->send($anticipo, AnticipoNotificationMail::EVENT_ACCOUNTING, $this->accountingRecipients($anticipo), AnticipoWorkflow::STEP_CONTABILIDAD, $comment);
        $this->send($anticipo, AnticipoNotificationMail::EVENT_APPROVED, $this->creator($anticipo)->merge($this->previousReviewers($anticipo, $stage)), $stage, $comment);
    }

    public function returned(Anticipo $anticipo, string $stage, string $comment): void
    {
        $recipients = $this->creator($anticipo)->merge($this->previousReviewers($anticipo, $stage));
        $this->send($anticipo, AnticipoNotificationMail::EVENT_RETURNED, $recipients, $stage, $comment);
    }

    /**
     * Contabilidad cargó el anticipo de Multifox: el residente y los aprobadores saben que pasó al cuadro de pagos.
     */
    public function completed(Anticipo $anticipo, ?string $comment): void
    {
        $lastStage = array_key_last(AnticipoWorkflow::STAGES);
        $recipients = $this->creator($anticipo)
            ->merge($this->previousReviewers($anticipo, $lastStage))
            ->merge($this->stageRecipients($anticipo, $lastStage));

        $this->send($anticipo, AnticipoNotificationMail::EVENT_COMPLETED, $recipients, AnticipoWorkflow::STEP_CONTABILIDAD, $comment);
    }

    /**
     * Envía un correo con copia al monitor. Si no hay destinatarios igual se envía al monitor,
     * para que quede constancia de que el evento ocurrió.
     */
    public function send(Anticipo $anticipo, string $event, Collection $recipients, ?string $stage = null, ?string $comment = null): void
    {
        $to = $this->normalize($recipients);
        $cc = $this->normalize(collect(config('anticipos.monitor', [])))->diff($to)->values();

        if ($to->isEmpty()) {
            Log::warning("Anticipos: no hay destinatarios para el aviso '{$event}' del anticipo {$anticipo->code} (etapa: " . ($stage ?? '-') . ').');
            if ($cc->isEmpty()) {
                return;
            }
            [$to, $cc] = [$cc, collect()];
        }

        try {
            Mail::to($to->all())
                ->cc($cc->all())
                ->send(new AnticipoNotificationMail($anticipo, $event, $stage, $comment));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Contabilidad: los correos configurados; si no hay, los usuarios con anticipos.contabilizar en el proyecto.
     */
    public function accountingRecipients(Anticipo $anticipo): Collection
    {
        $configured = config('anticipos.contabilidad', []);
        if ($configured) {
            return collect($configured);
        }

        return $this->usersWithPermission(AnticipoWorkflow::PERMISSION_CONTABILIZAR, $anticipo);
    }

    protected function normalize(Collection $emails): Collection
    {
        return $emails
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    protected function creator(Anticipo $anticipo): Collection
    {
        return collect([$anticipo->creator?->email]);
    }

    /**
     * Aprobadores de una etapa: los correos configurados en anticipos.stage_emails; si no hay, los usuarios con
     * el permiso de la etapa que tienen acceso al proyecto.
     */
    protected function stageRecipients(Anticipo $anticipo, string $stage): Collection
    {
        $configured = config("anticipos.stage_emails.{$stage}", []);

        return $configured ? collect($configured) : $this->usersWithPermission(AnticipoWorkflow::STAGES[$stage]['permission'], $anticipo);
    }

    protected function usersWithPermission(string $permission, Anticipo $anticipo): Collection
    {
        return User::permission($permission)
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => $user->canAccessProject($anticipo->project_id))
            ->pluck('email');
    }

    /**
     * Aprobadores de las etapas anteriores a $stage: los configurados y quienes lo aprobaron en el ciclo actual.
     */
    protected function previousReviewers(Anticipo $anticipo, string $stage): Collection
    {
        $previous = AnticipoWorkflow::previousStages($stage);
        if (!$previous) {
            return collect();
        }

        $configured = collect($previous)->flatMap(fn (string $s) => $this->stageRecipients($anticipo, $s));

        $cycleStartId = $anticipo->history()
            ->whereIn('action', [AnticipoHistory::ACTION_RADICADO, AnticipoHistory::ACTION_REENVIADO])
            ->max('id');

        $approvers = $anticipo->history()
            ->where('id', '>', $cycleStartId ?? 0)
            ->where('action', AnticipoHistory::ACTION_APROBADO)
            ->whereIn('stage', $previous)
            ->with('user:id,email')
            ->get()
            ->pluck('user.email');

        return $configured->merge($approvers);
    }
}

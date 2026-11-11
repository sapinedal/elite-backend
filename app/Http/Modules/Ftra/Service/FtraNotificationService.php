<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Ftra\Mail\FtraNotificationMail;
use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Ftra\Models\FtraRecordHistory;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos del flujo FTRA (mismo esquema que Cortes):
 *
 * | Evento                          | Para                                               |
 * |---------------------------------|----------------------------------------------------|
 * | Registrado / reenviado          | Supervisor (debe revisar)                          |
 * | Recibido por el Supervisor      | Director de Obra (debe revisar) · Residente        |
 * | Recibido por el Director (fin)  | Residente + Supervisor                             |
 * | Devuelto por el Supervisor      | Residente                                          |
 * | Devuelto por el Director        | Residente + Supervisor                             |
 *
 * "Residente" = quien registró el FTRA. Revisores = config/ftra.php más los usuarios con el permiso de la
 * etapa asignado directamente y acceso al proyecto. En copia ftra.monitor. Un fallo del correo nunca revierte la acción.
 */
class FtraNotificationService
{
    public function submitted(FtraRecord $record, bool $isResubmission, ?string $comment = null): void
    {
        $event = $isResubmission ? FtraNotificationMail::EVENT_RESUBMITTED : FtraNotificationMail::EVENT_SUBMITTED;
        $this->send($record, $event, $this->stageRecipients($record, 'supervisor'), 'supervisor', $comment);
    }

    public function approved(FtraRecord $record, string $stage, ?string $comment): void
    {
        $next = FtraWorkflow::nextStage($stage);

        if ($next) {
            $this->send($record, FtraNotificationMail::EVENT_PENDING_REVIEW, $this->stageRecipients($record, $next), $next, $comment);
            $this->send($record, FtraNotificationMail::EVENT_ADVANCED, $this->residente($record), $stage, $comment);

            return;
        }

        $participants = $this->residente($record)->merge($this->previousReviewers($record, $stage));
        $this->send($record, FtraNotificationMail::EVENT_APPROVED, $participants, $stage, $comment);
    }

    public function returned(FtraRecord $record, string $stage, string $comment): void
    {
        $recipients = $this->residente($record)->merge($this->previousReviewers($record, $stage));
        $this->send($record, FtraNotificationMail::EVENT_RETURNED, $recipients, $stage, $comment);
    }

    public function send(FtraRecord $record, string $event, Collection $recipients, ?string $stage = null, ?string $comment = null): void
    {
        $to = $this->normalize($recipients);
        $cc = $this->normalize(collect(config('ftra.monitor', [])))->diff($to)->values();

        if ($to->isEmpty()) {
            Log::warning("FTRA: no hay destinatarios para el aviso '{$event}' del registro {$record->id} (etapa: " . ($stage ?? '-') . ').');
            if ($cc->isEmpty()) {
                return;
            }
            [$to, $cc] = [$cc, collect()];
        }

        try {
            Mail::to($to->all())->cc($cc->all())->send(new FtraNotificationMail($record, $event, $stage, $comment));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Revisores de la etapa: correos configurados + usuarios con el permiso asignado directamente
     * (no por rol: los roles Técnica, Gerencia y Admin lo tienen todos) con acceso al proyecto.
     */
    public function stageRecipients(FtraRecord $record, string $stage): Collection
    {
        $users = User::query()
            ->withDirectPermission(FtraWorkflow::STAGES[$stage]['permission'])
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => $user->canAccessProject($record->project_id))
            ->pluck('email');

        return collect(config("ftra.stage_emails.{$stage}", []))->merge($users);
    }

    protected function residente(FtraRecord $record): Collection
    {
        $record->loadMissing('registeredBy:id,email');

        return collect([$record->registeredBy?->email]);
    }

    /** Revisores de las etapas anteriores: los configurados y quien realmente aprobó en el ciclo actual. */
    protected function previousReviewers(FtraRecord $record, string $stage): Collection
    {
        $previous = FtraWorkflow::previousStages($stage);
        if (!$previous) {
            return collect();
        }

        $configured = collect($previous)->flatMap(fn (string $s) => $this->stageRecipients($record, $s));

        $cycleStartId = $record->history()
            ->whereIn('action', [FtraRecordHistory::ACTION_REGISTRADO, FtraRecordHistory::ACTION_REENVIADO])
            ->max('id');

        $approvers = FtraRecordHistory::query()
            ->where('ftra_record_id', $record->id)
            ->where('id', '>', $cycleStartId ?? 0)
            ->where('action', FtraRecordHistory::ACTION_APROBADO)
            ->whereIn('stage', $previous)
            ->with('user:id,email')
            ->get()
            ->pluck('user.email');

        return $configured->merge($approvers);
    }

    protected function normalize(Collection $emails): Collection
    {
        return $emails
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }
}

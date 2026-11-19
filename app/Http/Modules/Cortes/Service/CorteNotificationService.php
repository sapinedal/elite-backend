<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Cortes\Mail\CorteInvoiceMail;
use App\Http\Modules\Cortes\Mail\CorteNotificationMail;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Users\Service\MailRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Correos del flujo de cortes. Cada destinatario es un permiso correos.cortes.* asignado directamente al usuario
 * (con acceso al proyecto), más los buzones externos de config/cortes.php:
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
 * | Factura cargada              | Multifox (registro en Multifox)                              |
 *
 * "Residentes" = quien radicó el corte + correos.cortes.residentes. En TODOS los correos va en copia correos.cortes.copia.
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
        $this->send($corte, CorteNotificationMail::EVENT_BILLING, MailRecipients::for('cortes', 'facturacion', $corte->project_id, config('cortes.facturacion', [])), $stage, $comment);
    }

    public function returned(Corte $corte, string $stage, string $comment): void
    {
        $recipients = $this->residentes($corte)->merge($this->previousReviewers($corte, $stage));
        $this->send($corte, CorteNotificationMail::EVENT_RETURNED, $recipients, $stage, $comment);
    }

    /**
     * Factura cargada a un corte aprobado: aviso para registrarla en Multifox, con copia al monitor.
     */
    public function invoiceUploaded(CorteInvoice $invoice): void
    {
        $projectId = $invoice->corte->project_id;
        $to = $this->normalize($this->multifoxRecipients('correos.cortes.multifox', $projectId));
        $cc = $this->normalize($this->copia($projectId))->diff($to)->values();

        if ($to->isEmpty()) {
            Log::warning("Cortes: nadie tiene el permiso correos.cortes.multifox para la factura {$invoice->invoice_number} (corte {$invoice->corte->code}).");
            if ($cc->isEmpty()) {
                return;
            }
            [$to, $cc] = [$cc, collect()];
        }

        try {
            Mail::to($to->all())->cc($cc->all())->send(new CorteInvoiceMail($invoice));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Usuarios con el permiso de correo de Multifox ($permission) asignado directamente y acceso al proyecto, más
     * los buzones externos de cortes.multifox_emails. Solo se conservan los correos de los dominios permitidos.
     */
    public function multifoxRecipients(string $permission = 'correos.cortes.multifox', ?int $projectId = null): Collection
    {
        $emails = collect(config('cortes.multifox_emails', []))->merge(MailRecipients::emails($permission, $projectId));

        // El aviso lleva la factura adjunta: solo a correos de la empresa (cortes.multifox_allowed_domains)
        $domains = collect(config('cortes.multifox_allowed_domains', []))->map(fn ($d) => mb_strtolower(ltrim(trim($d), '@')));

        return $domains->isEmpty()
            ? $emails
            : $emails->filter(fn ($email) => $domains->contains(mb_strtolower(Str::after((string) $email, '@'))))->values();
    }

    /**
     * Envía un correo con copia al monitor. Si no hay destinatarios igual se envía al monitor,
     * para que quede constancia de que el evento ocurrió.
     */
    public function send(Corte $corte, string $event, Collection $recipients, ?string $stage = null, ?string $comment = null): void
    {
        $to = $this->normalize($recipients);
        $cc = $this->normalize($this->copia($corte->project_id))->diff($to)->values();

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

    /** En copia de todos los correos del flujo: correos.cortes.copia + cortes.monitor. */
    protected function copia(?int $projectId): Collection
    {
        return MailRecipients::for('cortes', 'copia', $projectId, config('cortes.monitor', []));
    }

    /**
     * Quien radicó el corte más los usuarios con correos.cortes.residentes (y cortes.residentes).
     */
    protected function residentes(Corte $corte): Collection
    {
        return collect([$corte->creator?->email])
            ->merge(MailRecipients::for('cortes', 'residentes', $corte->project_id, config('cortes.residentes', [])));
    }

    /**
     * Revisores de una etapa: usuarios con correos.cortes.<etapa> y acceso al proyecto, más cortes.stage_emails.
     */
    protected function stageRecipients(Corte $corte, string $stage): Collection
    {
        return MailRecipients::for('cortes', $stage, $corte->project_id, config("cortes.stage_emails.{$stage}", []));
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

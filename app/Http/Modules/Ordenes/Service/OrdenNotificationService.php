<?php

namespace App\Http\Modules\Ordenes\Service;

use App\Http\Modules\Ordenes\Mail\OrdenNotificationMail;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Models\OrdenHistory;
use App\Http\Modules\Users\Service\MailRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos del flujo de órdenes. Cada destinatario es un permiso correos.ordenes.* asignado directamente al usuario
 * (con acceso al proyecto), más los buzones externos de config/ordenes.php:
 *
 * | Evento                         | Para                                                    |
 * |--------------------------------|---------------------------------------------------------|
 * | Registrada / reenviada         | Director de Obra (debe aprobar) · Residente (confirma)  |
 * | Aprobada por Director          | Gerencia (debe aprobar) · Residente (avance)            |
 * | Aprobada por Gerencia          | Almacén (cargar OC en el ERP) · Residente + Director    |
 * | Devuelta por Director          | Residente                                               |
 * | Devuelta por Gerencia          | Residente + Director                                    |
 * | Orden de compra ERP cargada    | Facturación (cargar factura del proveedor)              |
 * | Factura del proveedor cargada  | Almacén (cargar la entrada)                             |
 * | Entrada cargada                | Residente (orden completada)                            |
 *
 * "Almacén" = usuarios con correos.ordenes.almacen. En TODOS los correos va en copia correos.ordenes.copia.
 * Un fallo del correo nunca revierte la acción: se registra en el log y el flujo continúa.
 */
class OrdenNotificationService
{
    public function submitted(Orden $orden, bool $isResubmission, ?string $comment = null): void
    {
        $firstStage = OrdenWorkflow::firstStage();
        $event = $isResubmission ? OrdenNotificationMail::EVENT_RESUBMITTED : OrdenNotificationMail::EVENT_SUBMITTED;

        $this->send($orden, $event, $this->stageRecipients($orden, $firstStage), $firstStage, $comment);
        $this->send($orden, OrdenNotificationMail::EVENT_CONFIRMATION, $this->creator($orden), null, $comment);
    }

    public function approved(Orden $orden, string $stage, ?string $comment): void
    {
        $nextStage = OrdenWorkflow::nextStage($stage);

        if ($nextStage) {
            $this->send($orden, OrdenNotificationMail::EVENT_PENDING_REVIEW, $this->stageRecipients($orden, $nextStage), $nextStage, $comment);
            $this->send($orden, OrdenNotificationMail::EVENT_ADVANCED, $this->creator($orden), $stage, $comment);

            return;
        }

        // Aprobación final: Almacén debe cargar la orden de compra en el ERP; aviso aparte al residente y aprobadores
        $firstStep = OrdenWorkflow::firstStep();
        $this->send($orden, OrdenNotificationMail::EVENT_STEP_PENDING, $this->stepRecipients($orden, $firstStep), $firstStep, $comment);
        $this->send($orden, OrdenNotificationMail::EVENT_APPROVED, $this->creator($orden)->merge($this->previousReviewers($orden, $stage)), $stage, $comment);
    }

    public function returned(Orden $orden, string $stage, string $comment): void
    {
        $recipients = $this->creator($orden)->merge($this->previousReviewers($orden, $stage));
        $this->send($orden, OrdenNotificationMail::EVENT_RETURNED, $recipients, $stage, $comment);
    }

    /**
     * Se cargó el documento de $step: aviso al responsable del siguiente, o al residente si la orden quedó completada.
     */
    public function documentUploaded(Orden $orden, string $step, ?string $comment): void
    {
        $nextStep = OrdenWorkflow::nextStep($step);

        $nextStep
            ? $this->send($orden, OrdenNotificationMail::EVENT_STEP_PENDING, $this->stepRecipients($orden, $nextStep), $nextStep, $comment)
            : $this->send($orden, OrdenNotificationMail::EVENT_COMPLETED, $this->creator($orden), $step, $comment);
    }

    /**
     * Envía un correo con copia al monitor. Si no hay destinatarios igual se envía al monitor,
     * para que quede constancia de que el evento ocurrió.
     */
    public function send(Orden $orden, string $event, Collection $recipients, ?string $stage = null, ?string $comment = null): void
    {
        $to = $this->normalize($recipients);
        $cc = $this->normalize(MailRecipients::for('ordenes', 'copia', $orden->project_id, config('ordenes.monitor', [])))->diff($to)->values();

        if ($to->isEmpty()) {
            Log::warning("Órdenes: no hay destinatarios para el aviso '{$event}' de la orden {$orden->code} (etapa: " . ($stage ?? '-') . ').');
            if ($cc->isEmpty()) {
                return;
            }
            [$to, $cc] = [$cc, collect()];
        }

        try {
            Mail::to($to->all())
                ->cc($cc->all())
                ->send(new OrdenNotificationMail($orden, $event, $stage, $comment));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Responsables de un documento: Facturación (correos.ordenes.facturacion) carga la factura del proveedor y
     * Almacén (correos.ordenes.almacen) la orden ERP y la entrada.
     */
    public function stepRecipients(Orden $orden, string $step): Collection
    {
        return $step === OrdenWorkflow::STEP_FACTURA
            ? MailRecipients::for('ordenes', 'facturacion', $orden->project_id, config('ordenes.facturacion', []))
            : MailRecipients::for('ordenes', 'almacen', $orden->project_id, config('ordenes.almacen_emails', []));
    }

    protected function normalize(Collection $emails): Collection
    {
        return $emails
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    protected function creator(Orden $orden): Collection
    {
        return collect([$orden->creator?->email]);
    }

    /**
     * Aprobadores de una etapa: usuarios con correos.ordenes.<etapa> y acceso al proyecto, más ordenes.stage_emails.
     */
    protected function stageRecipients(Orden $orden, string $stage): Collection
    {
        return MailRecipients::for('ordenes', $stage, $orden->project_id, config("ordenes.stage_emails.{$stage}", []));
    }

    /**
     * Aprobadores de las etapas anteriores a $stage: los configurados y quienes la aprobaron en el ciclo actual.
     */
    protected function previousReviewers(Orden $orden, string $stage): Collection
    {
        $previous = OrdenWorkflow::previousStages($stage);
        if (!$previous) {
            return collect();
        }

        $configured = collect($previous)->flatMap(fn (string $s) => $this->stageRecipients($orden, $s));

        $cycleStartId = $orden->history()
            ->whereIn('action', [OrdenHistory::ACTION_REGISTRADA, OrdenHistory::ACTION_REENVIADA])
            ->max('id');

        $approvers = $orden->history()
            ->where('id', '>', $cycleStartId ?? 0)
            ->where('action', OrdenHistory::ACTION_APROBADO)
            ->whereIn('stage', $previous)
            ->with('user:id,email')
            ->get()
            ->pluck('user.email');

        return $configured->merge($approvers);
    }
}

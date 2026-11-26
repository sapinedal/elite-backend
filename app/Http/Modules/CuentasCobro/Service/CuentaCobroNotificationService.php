<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Cortes\Service\CorteNotificationService;
use App\Http\Modules\CuentasCobro\Mail\CuentaCobroNotificationMail;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroHistory;
use App\Http\Modules\Users\Service\MailRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos del flujo de cuentas de cobro. Cada destinatario es un permiso correos.cuentas_cobro.* asignado
 * directamente al usuario (con acceso al proyecto), más los buzones externos de config/cuentas_cobro.php:
 *
 * | Evento                          | Para                                                        |
 * |---------------------------------|-------------------------------------------------------------|
 * | Radicada / reenviada            | Director de Obra (debe aprobar) · Residente (confirma)      |
 * | Aprobada por Director           | Gerencia (debe aprobar) · Residente (avance)                |
 * | Aprobada por Gerencia           | Paula (decide Multifox) · Residente + Director              |
 * | Directa: paso pendiente         | Residente (+ cuentas_cobro.cargar_erp en ERP / liquidación) |
 * | Devuelta                        | Residente (+ Director si la devolvió Gerencia)              |
 * | En cuadro de pagos              | Residente + aprobadores                                     |
 *
 * Paula = quien tiene correos.cuentas_cobro.multifox. En TODOS va en copia correos.cuentas_cobro.copia.
 */
class CuentaCobroNotificationService
{
    public function __construct(protected CorteNotificationService $cortes)
    {
    }

    public function submitted(CuentaCobro $cuenta, bool $isResubmission, ?string $comment = null): void
    {
        $firstStage = CuentaCobroWorkflow::firstStage();
        $event = $isResubmission ? CuentaCobroNotificationMail::EVENT_RESUBMITTED : CuentaCobroNotificationMail::EVENT_SUBMITTED;

        $this->send($cuenta, $event, $this->stageRecipients($cuenta, $firstStage), $firstStage, $comment);
        $this->send($cuenta, CuentaCobroNotificationMail::EVENT_CONFIRMATION, $this->creator($cuenta), null, $comment);
    }

    public function approved(CuentaCobro $cuenta, string $stage, ?string $comment): void
    {
        $nextStage = CuentaCobroWorkflow::nextStage($stage);

        if ($nextStage) {
            $this->send($cuenta, CuentaCobroNotificationMail::EVENT_PENDING_REVIEW, $this->stageRecipients($cuenta, $nextStage), $nextStage, $comment);
            $this->send($cuenta, CuentaCobroNotificationMail::EVENT_ADVANCED, $this->creator($cuenta), $stage, $comment);

            return;
        }

        // Directa: sigue el cargue ERP (residente o cuentas_cobro.cargar_erp); General / Indirectos: Paula decide Multifox
        $cuenta->isDirecta()
            ? $this->stepPending($cuenta, $comment)
            : $this->send($cuenta, CuentaCobroNotificationMail::EVENT_MULTIFOX_REVIEW, $this->cortes->multifoxRecipients('correos.cuentas_cobro.multifox', $cuenta->project_id), CuentaCobroWorkflow::STEP_MULTIFOX, $comment);
        $this->send($cuenta, CuentaCobroNotificationMail::EVENT_APPROVED, $this->creator($cuenta)->merge($this->previousReviewers($cuenta, $stage)), $stage, $comment);
    }

    /**
     * Cuenta directa: aviso del documento que sigue. El cargue ERP y la liquidación los hace el residente o quien
     * tenga cuentas_cobro.cargar_erp; la factura la asocia el residente desde Facturas del mes.
     */
    public function stepPending(CuentaCobro $cuenta, ?string $comment): void
    {
        $step = $cuenta->current_step;
        if (!$step) {
            return;
        }

        $recipients = $this->creator($cuenta);
        if ($step !== CuentaCobroWorkflow::STEP_FACTURA) {
            $recipients = $recipients->merge(MailRecipients::emails(CuentaCobroWorkflow::PERMISSION_ERP, $cuenta->project_id));
        }

        $this->send($cuenta, CuentaCobroNotificationMail::EVENT_STEP_PENDING, $recipients, $step, $comment);
    }

    public function returned(CuentaCobro $cuenta, string $stage, string $comment): void
    {
        $this->send($cuenta, CuentaCobroNotificationMail::EVENT_RETURNED, $this->creator($cuenta)->merge($this->previousReviewers($cuenta, $stage)), $stage, $comment);
    }

    /**
     * Pasó al cuadro de pagos (con o sin Multifox).
     */
    public function inPayments(CuentaCobro $cuenta, ?string $comment): void
    {
        $lastStage = array_key_last(CuentaCobroWorkflow::STAGES);
        $recipients = $this->creator($cuenta)
            ->merge($this->previousReviewers($cuenta, $lastStage))
            ->merge($this->stageRecipients($cuenta, $lastStage));

        $this->send($cuenta, CuentaCobroNotificationMail::EVENT_IN_PAYMENTS, $recipients, CuentaCobroWorkflow::STEP_MULTIFOX, $comment);
    }

    public function send(CuentaCobro $cuenta, string $event, Collection $recipients, ?string $stage = null, ?string $comment = null): void
    {
        $to = $this->normalize($recipients);
        $cc = $this->normalize(MailRecipients::for('cuentas_cobro', 'copia', $cuenta->project_id, config('cuentas_cobro.monitor', [])))->diff($to)->values();

        if ($to->isEmpty()) {
            Log::warning("Cuentas de cobro: no hay destinatarios para el aviso '{$event}' de la cuenta {$cuenta->code} (etapa: " . ($stage ?? '-') . ').');
            if ($cc->isEmpty()) {
                return;
            }
            [$to, $cc] = [$cc, collect()];
        }

        try {
            Mail::to($to->all())->cc($cc->all())->send(new CuentaCobroNotificationMail($cuenta, $event, $stage, $comment));
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

    protected function creator(CuentaCobro $cuenta): Collection
    {
        return collect([$cuenta->creator?->email]);
    }

    protected function stageRecipients(CuentaCobro $cuenta, string $stage): Collection
    {
        return MailRecipients::for('cuentas_cobro', $stage, $cuenta->project_id, config("cuentas_cobro.stage_emails.{$stage}", []));
    }

    protected function previousReviewers(CuentaCobro $cuenta, string $stage): Collection
    {
        $previous = CuentaCobroWorkflow::previousStages($stage);
        if (!$previous) {
            return collect();
        }

        $configured = collect($previous)->flatMap(fn (string $s) => $this->stageRecipients($cuenta, $s));

        $cycleStartId = $cuenta->history()
            ->whereIn('action', [CuentaCobroHistory::ACTION_RADICADA, CuentaCobroHistory::ACTION_REENVIADA])
            ->max('id');

        $approvers = $cuenta->history()
            ->where('id', '>', $cycleStartId ?? 0)
            ->where('action', CuentaCobroHistory::ACTION_APROBADO)
            ->whereIn('stage', $previous)
            ->with('user:id,email')
            ->get()
            ->pluck('user.email');

        return $configured->merge($approvers);
    }
}

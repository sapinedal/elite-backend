<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Cortes\Service\CorteNotificationService;
use App\Http\Modules\CuentasCobro\Mail\CuentaCobroNotificationMail;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroHistory;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos del flujo de cuentas de cobro (destinatarios en config/cuentas_cobro.php):
 *
 * | Evento                          | Para                                                        |
 * |---------------------------------|-------------------------------------------------------------|
 * | Radicada / reenviada            | Director de Obra (debe aprobar) · Residente (confirma)      |
 * | Aprobada por Director           | Gerencia (debe aprobar) · Residente (avance)                |
 * | Aprobada por Gerencia           | Paula (decide Multifox) · Residente + Director              |
 * | Devuelta                        | Residente (+ Director si la devolvió Gerencia)              |
 * | En cuadro de pagos              | Residente + aprobadores                                     |
 *
 * Paula = el cargo de Multifox de Cortes (cortes.multifox_positions). En TODOS va en copia cuentas_cobro.monitor.
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

        $this->send($cuenta, CuentaCobroNotificationMail::EVENT_MULTIFOX_REVIEW, $this->cortes->multifoxRecipients(), CuentaCobroWorkflow::STEP_MULTIFOX, $comment);
        $this->send($cuenta, CuentaCobroNotificationMail::EVENT_APPROVED, $this->creator($cuenta)->merge($this->previousReviewers($cuenta, $stage)), $stage, $comment);
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
        $cc = $this->normalize(collect(config('cuentas_cobro.monitor', [])))->diff($to)->values();

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
        $configured = config("cuentas_cobro.stage_emails.{$stage}", []);
        if ($configured) {
            return collect($configured);
        }

        return User::permission(CuentaCobroWorkflow::STAGES[$stage]['permission'])
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => $user->canAccessProject($cuenta->project_id))
            ->pluck('email');
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

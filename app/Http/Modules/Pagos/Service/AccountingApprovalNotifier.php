<?php

namespace App\Http\Modules\Pagos\Service;

use App\Http\Modules\Pagos\Mail\AccountingApprovalMail;
use App\Http\Modules\Users\Service\MailRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos de la aprobación de Contabilidad antes de cuentas por pagar, igual en todos los módulos de registro.
 *
 * | Evento                    | Para                                                                  |
 * |---------------------------|-----------------------------------------------------------------------|
 * | Cargado en Multifox       | correos.<módulo>.aprobacion_contable (Contabilidad debe aprobar)      |
 * | Aprobado por Contabilidad | El flujo: residente y quien cargó en Multifox                         |
 * | Devuelto por Contabilidad | Quien carga en Multifox (cortes.multifox / responsable del módulo)    |
 *
 * En todos va en copia correos.<módulo>.copia. Un fallo del correo nunca revierte la acción.
 */
class AccountingApprovalNotifier
{
    public const RECIPIENT = 'aprobacion_contable';

    /**
     * Contabilidad: quienes tienen correos.<módulo>.aprobacion_contable (asignado directamente) en el proyecto.
     */
    public function accountants(string $module, ?int $projectId): Collection
    {
        return MailRecipients::for($module, self::RECIPIENT, $projectId);
    }

    /**
     * Usuarios con alguno de los permisos asignado directamente (no por rol) y acceso al proyecto.
     *
     * @param list<string> $permissions
     */
    public function usersWith(array $permissions, ?int $projectId): Collection
    {
        return collect($permissions)->flatMap(fn (string $permission) => MailRecipients::emails($permission, $projectId));
    }

    public function pending(string $module, ?int $projectId, array $payload, ?string $comment = null): void
    {
        $this->send($module, $projectId, $this->accountants($module, $projectId), AccountingApprovalMail::EVENT_PENDING, $payload, $comment);
    }

    public function approved(string $module, ?int $projectId, Collection $flow, array $payload, ?string $comment, ?string $actor): void
    {
        $this->send($module, $projectId, $flow, AccountingApprovalMail::EVENT_APPROVED, $payload, $comment, $actor);
    }

    public function returned(string $module, ?int $projectId, Collection $multifox, array $payload, string $comment, ?string $actor): void
    {
        $this->send($module, $projectId, $multifox, AccountingApprovalMail::EVENT_RETURNED, $payload, $comment, $actor);
    }

    protected function send(string $module, ?int $projectId, Collection $recipients, string $event, array $payload, ?string $comment, ?string $actor = null): void
    {
        $to = $this->normalize($recipients);
        $cc = $this->normalize(MailRecipients::for($module, 'copia', $projectId))->diff($to)->values();

        if ($to->isEmpty()) {
            Log::warning("Aprobación contable ({$module}): no hay destinatarios para '{$event}' de {$payload['reference']}.");
            if ($cc->isEmpty()) {
                return;
            }
            [$to, $cc] = [$cc, collect()];
        }

        try {
            Mail::to($to->all())->cc($cc->all())->send(new AccountingApprovalMail($event, $payload, $comment, $actor));
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
}

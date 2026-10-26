<?php

namespace App\Console\Commands;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Mail\PolicyExpiringMail;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionPolicy;
use App\Http\Modules\Cortes\Mail\CorteInvoiceMail;
use App\Http\Modules\Cortes\Mail\CorteNotificationMail as M;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Service\CorteWorkflow as W;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envía un ejemplo de cada correo de notificación (flujo de cortes, facturas para Multifox y alerta de pólizas) a una sola dirección,
 * con datos ficticios en memoria: no lee ni escribe en la base de datos y no notifica a nadie más.
 *
 * Uso: php artisan notificaciones:ejemplos samuel.pineda@sumimedical.com
 */
class PreviewNotificationEmails extends Command
{
    protected $signature = 'notificaciones:ejemplos
        {email : Dirección que recibirá los ejemplos}
        {--solo= : Enviar solo uno: el número del ejemplo (1-10)}';

    protected $description = 'Envía un ejemplo de cada plantilla de correo (cortes, facturas y pólizas) a una dirección';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Correo no válido: {$email}");
            return self::FAILURE;
        }

        $comment = [
            'radicado' => 'Se adjunta el corte No. 5 de estructura con las cantidades medidas en obra.',
            'aprobado' => 'Cantidades verificadas en obra. Sin observaciones.',
            'devuelto' => "Las cantidades del ítem 3.2 (concreto columnas piso 8) no coinciden con el acta de medición.\nPor favor revisar y ajustar.",
            'reenviado' => 'Se corrigieron las cantidades del ítem 3.2 según el acta de medición.',
        ];

        // [evento, etapa, historial (acción, etapa), comentario, para quién va en la realidad]
        $scenarios = [
            [M::EVENT_SUBMITTED, W::STAGE_INTERVENTOR, [['radicado', null]], $comment['radicado'], 'Interventor'],
            [M::EVENT_CONFIRMATION, null, [['radicado', null]], $comment['radicado'], 'Residentes (quien radicó + residentes de obra)'],
            [M::EVENT_PENDING_REVIEW, W::STAGE_DIRECTOR, [['radicado', null], ['aprobado', W::STAGE_INTERVENTOR]], $comment['aprobado'], 'Director de Obra'],
            [M::EVENT_ADVANCED, W::STAGE_INTERVENTOR, [['radicado', null], ['aprobado', W::STAGE_INTERVENTOR]], $comment['aprobado'], 'Residentes'],
            [M::EVENT_RETURNED, W::STAGE_DIRECTOR, [['radicado', null], ['aprobado', W::STAGE_INTERVENTOR], ['devuelto', W::STAGE_DIRECTOR]], $comment['devuelto'], 'Residentes + Interventor'],
            [M::EVENT_RESUBMITTED, W::STAGE_INTERVENTOR, [['radicado', null], ['aprobado', W::STAGE_INTERVENTOR], ['devuelto', W::STAGE_DIRECTOR], ['reenviado', null]], $comment['reenviado'], 'Interventor'],
            [M::EVENT_APPROVED, W::STAGE_GERENCIA, [['radicado', null], ['aprobado', W::STAGE_INTERVENTOR], ['aprobado', W::STAGE_DIRECTOR], ['aprobado', W::STAGE_GERENCIA]], 'Aprobado para pago.', 'Residentes + Interventor + Director'],
            [M::EVENT_BILLING, W::STAGE_GERENCIA, [['radicado', null], ['aprobado', W::STAGE_INTERVENTOR], ['aprobado', W::STAGE_DIRECTOR], ['aprobado', W::STAGE_GERENCIA]], 'Aprobado para pago.', 'Facturación'],
        ];

        $total = count($scenarios) + 2;
        $only = $this->option('solo') ? (int) $this->option('solo') : null;
        $prefix = fn (int $n) => "[EJEMPLO {$n}/{$total}]";
        $rows = [];

        foreach ($scenarios as $i => [$event, $stage, $history, $text, $audience]) {
            if ($only && $only !== $i + 1) {
                continue;
            }
            $mail = new M($this->fakeCorte($history), $event, $stage, $text, $prefix($i + 1));
            Mail::to($email)->send($mail);
            $rows[] = [$i + 1, $mail->envelope()->subject, $audience];
        }

        $n = count($scenarios) + 1;
        if (!$only || $only === $n) {
            Mail::to($email)->send(new PolicyExpiringMail($this->fakeProject(), $this->fakePolicies(), $prefix($n)));
            $rows[] = [$n, 'Pólizas próximas a vencer', 'Destinatarios de pólizas (contratacion.alert_emails)'];
        }

        $n++;
        if (!$only || $only === $n) {
            $mail = new CorteInvoiceMail($this->fakeInvoice(), $prefix($n), attachFile: false);
            Mail::to($email)->send($mail);
            $rows[] = [$n, $mail->envelope()->subject, 'Cargo(s) de Multifox (cortes.multifox_positions)'];
        }

        $this->table(['#', 'Asunto', 'En la realidad se envía a'], $rows);
        $this->info('Se enviaron ' . count($rows) . " correo(s) de ejemplo a {$email}.");

        return self::SUCCESS;
    }

    protected function fakeProject(): Project
    {
        return new Project(['name' => 'Ciudadela San Miguel']);
    }

    /**
     * Corte ficticio con sus relaciones e historial armados en memoria según el escenario.
     *
     * @param array<int, array{0: string, 1: ?string}> $history
     */
    protected function fakeCorte(array $history): Corte
    {
        $people = [
            null => 'Julián Posada',
            W::STAGE_INTERVENTOR => 'Interventoría',
            W::STAGE_DIRECTOR => 'Director de Obra',
            W::STAGE_GERENCIA => 'Laura Gómez',
        ];

        $corte = (new Corte())->forceFill([
            'id' => 0,
            'code' => 'CORTE-T2-005',
            'file_name' => 'Corte 05 - Estructura Torre 2.xlsx',
            'file_size' => 2_516_582,
            'created_at' => now()->subDays(2)->setTime(9, 30),
        ]);

        $entries = collect($history)->values()->map(function (array $item, int $i) use ($people) {
            [$action, $stage] = $item;
            $entry = (new CorteHistory())->forceFill([
                'id' => $i + 1,
                'action' => $action,
                'stage' => $stage,
                'created_at' => now()->subDays(2)->addHours($i * 5),
            ]);

            return $entry->setRelation('user', new User(['name' => $people[$stage] ?? 'Julián Posada']));
        });

        // Estado del corte según la última acción del historial
        [$lastAction, $lastStage] = end($history);
        [$status, $currentStage] = match ($lastAction) {
            'radicado', 'reenviado' => [W::STATUS_PENDIENTE_INTERVENTOR, W::STAGE_INTERVENTOR],
            'devuelto' => [W::STATUS_DEVUELTO, null],
            'aprobado' => ($next = W::nextStage($lastStage)) ? [W::statusFor($next), $next] : [W::STATUS_APROBADO, null],
        };
        $corte->forceFill(['status' => $status, 'current_stage' => $currentStage]);

        return $corte
            ->setRelation('project', $this->fakeProject())
            ->setRelation('tower', new Tower(['name' => 'TORRE 2']))
            ->setRelation('contractor', new FtraContractor(['name' => 'Emelect Group SAS', 'nit' => '901234567-1']))
            ->setRelation('contract', new ContratacionContract(['contract_code' => 'T2-MO-03-EMELECT']))
            ->setRelation('creator', new User(['name' => 'Julián Posada']))
            ->setRelation('history', $entries);
    }

    /**
     * Nota crédito ficticia sobre un corte facturado (el archivo no se adjunta en el ejemplo).
     */
    public function fakeInvoice(): CorteInvoice
    {
        $approved = [['radicado', null], ['aprobado', W::STAGE_INTERVENTOR], ['aprobado', W::STAGE_DIRECTOR], ['aprobado', W::STAGE_GERENCIA]];
        $corte = $this->fakeCorte($approved)->forceFill(['status' => W::STATUS_FACTURADO]);

        return (new CorteInvoice())->forceFill([
            'id' => 0,
            'invoice_number' => 'NC-0077',
            'document_type' => CorteInvoice::TYPE_NOTA_CREDITO,
            'related_document' => 'FE-9001',
            'observation' => 'Se descuentan 12 m³ de concreto del ítem 3.2 que no se ejecutaron en el periodo.',
            'file_name' => 'NC-0077 Emelect Group.pdf',
            'file_size' => 184_320,
            'created_at' => now(),
        ])->setRelation('corte', $corte)->setRelation('uploader', new User(['name' => 'Analista de Facturación']));
    }

    protected function fakePolicies()
    {
        $tower = new Tower(['name' => 'TORRE 2']);
        $policy = function (string $code, string $contractor, string $type, string $number, string $insurer, int $days) use ($tower) {
            $contract = (new ContratacionContract(['contract_code' => $code, 'contractor_name' => $contractor]))->setRelation('tower', $tower);

            return (new ContratacionPolicy([
                'policy_type' => $type,
                'policy_number' => $number,
                'insurer' => $insurer,
                'expires_at' => now()->startOfDay()->addDays($days),
            ]))->setRelation('contract', $contract);
        };

        return collect([
            $policy('T2-MO-03-EMELECT', 'Emelect Group SAS', 'Cumplimiento', '994821', 'Seguros del Estado', -3),
            $policy('T2-MO-04-YANCELY', 'Yancely Astrid Monsalve Yepes Y&M SAS', 'Salarios y prestaciones sociales', '55-44-1010', 'SURA', 9),
            $policy('T2-SUM-04-VIAS', 'Vías y Triturados de Antioquia', 'Calidad y correcto funcionamiento', '320-77', 'Mundial', 32),
        ]);
    }
}

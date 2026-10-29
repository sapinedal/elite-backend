<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Mail\PolicyExpiringMail;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    config(['contratacion.policy_alert_days' => 40, 'contratacion.alert_emails' => ['alertas@example.com']]);
    $this->travelTo(now()->setDate(2026, 10, 29)->setTime(7, 0));
});

function contractFor(Project $project, string $code = 'C-001'): ContratacionContract
{
    return ContratacionContract::create([
        'project_id' => $project->id,
        'contract_code' => $code,
        'consecutive' => '1',
        'contractor_name' => 'Contratista ' . $code,
        'object' => 'Obra',
        'status' => ContratacionContract::STATUS_EN_EJECUCION,
    ]);
}

function policyFor(ContratacionContract $contract, string $expiresAt): ContratacionPolicy
{
    return $contract->policies()->create(['policy_type' => 'Cumplimiento', 'expires_at' => $expiresAt]);
}

test('notifica las pólizas dentro de la ventana y las vencidas, una sola vez', function () {
    $project = Project::create(['code' => 'p1', 'name' => 'Proyecto 1']);
    $contract = contractFor($project);

    $expired = policyFor($contract, '2026-10-01');   // vencida
    $today = policyFor($contract, '2026-10-29');     // vence hoy
    $edge = policyFor($contract, '2026-12-08');      // día 40 exacto
    $outside = policyFor($contract, '2026-12-09');   // día 41: fuera

    $this->artisan('contratacion:policy-alerts')->assertSuccessful();

    Mail::assertSent(PolicyExpiringMail::class, 1);
    Mail::assertSent(PolicyExpiringMail::class, function (PolicyExpiringMail $mail) use ($expired, $today, $edge) {
        return $mail->hasTo('alertas@example.com')
            && $mail->policies->pluck('id')->sort()->values()->all() === collect([$expired->id, $today->id, $edge->id])->sort()->values()->all();
    });

    expect($outside->fresh()->alert_sent_at)->toBeNull()
        ->and($edge->fresh()->alert_sent_at)->not->toBeNull();

    // Segunda ejecución el mismo día: no repite
    $this->artisan('contratacion:policy-alerts')->assertSuccessful();
    Mail::assertSent(PolicyExpiringMail::class, 1);

    // Al día siguiente la de 41 días entra en la ventana
    $this->travel(1)->days();
    $this->artisan('contratacion:policy-alerts')->assertSuccessful();
    Mail::assertSent(PolicyExpiringMail::class, 2);
    expect($outside->fresh()->alert_sent_at)->not->toBeNull();
});

test('agrupa un correo por proyecto y excluye contratos eliminados', function () {
    $p1 = Project::create(['code' => 'p1', 'name' => 'Proyecto 1']);
    $p2 = Project::create(['code' => 'p2', 'name' => 'Proyecto 2']);
    policyFor(contractFor($p1, 'A'), '2026-11-10');
    policyFor(contractFor($p1, 'B'), '2026-11-15');
    policyFor(contractFor($p2, 'C'), '2026-11-20');
    $deleted = contractFor($p2, 'D');
    $deletedPolicy = policyFor($deleted, '2026-11-01');
    $deleted->delete();

    $this->artisan('contratacion:policy-alerts')->assertSuccessful();

    Mail::assertSent(PolicyExpiringMail::class, 2);
    Mail::assertSent(PolicyExpiringMail::class, fn ($m) => $m->project->is($p1) && $m->policies->count() === 2);
    Mail::assertSent(PolicyExpiringMail::class, fn ($m) => $m->project->is($p2) && $m->policies->count() === 1);
    expect($deletedPolicy->fresh()->alert_sent_at)->toBeNull();
});

test('si falla el envío no marca la póliza y se reintenta', function () {
    $project = Project::create(['code' => 'p1', 'name' => 'Proyecto 1']);
    $policy = policyFor(contractFor($project), '2026-11-10');

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP caído'));
    $this->artisan('contratacion:policy-alerts')->assertSuccessful();

    expect($policy->fresh()->alert_sent_at)->toBeNull();
});

test('el correo se renderiza con los datos de la póliza', function () {
    $project = Project::create(['code' => 'p1', 'name' => 'Proyecto 1']);
    $contract = contractFor($project, 'CT-77');
    policyFor($contract, '2026-10-20');
    policyFor($contract, '2026-11-08');

    $mail = new PolicyExpiringMail($project, ContratacionPolicy::with('contract.tower')->get());
    $html = $mail->render();

    expect($html)->toContain('CT-77')
        ->toContain('Vencida')
        ->toContain('10 días')
        ->toContain('08/11/2026');
});

test('la tarea está programada a diario', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('contratacion:policy-alerts')->assertSuccessful();
});

test('avisa a los 40, 30 y 20 días, una vez en cada umbral', function () {
    $project = Project::create(['code' => 'p1', 'name' => 'Proyecto 1']);
    $policy = policyFor(contractFor($project), now()->addDays(45)->toDateString());
    $run = function () {
        $this->artisan('contratacion:policy-alerts')->assertSuccessful();
    };

    $run(); // 45 días: aún no
    Mail::assertNothingSent();

    $this->travel(5)->days(); // 40 días
    $run();
    $run(); // mismo día: no repite
    Mail::assertSent(PolicyExpiringMail::class, 1);
    expect($policy->fresh()->last_alert_days)->toBe(40);

    $this->travel(5)->days(); // 35 días: sin aviso
    $run();
    Mail::assertSent(PolicyExpiringMail::class, 1);

    $this->travel(5)->days(); // 30 días
    $run();
    Mail::assertSent(PolicyExpiringMail::class, 2);
    expect($policy->fresh()->last_alert_days)->toBe(30);

    $this->travel(10)->days(); // 20 días
    $run();
    Mail::assertSent(PolicyExpiringMail::class, 3);
    expect($policy->fresh()->last_alert_days)->toBe(20);

    $this->travel(25)->days(); // ya venció: no hay más avisos
    $run();
    Mail::assertSent(PolicyExpiringMail::class, 3);
});

test('una póliza que entra tarde recibe el aviso del umbral en que está', function () {
    $project = Project::create(['code' => 'p1', 'name' => 'Proyecto 1']);
    $policy = policyFor(contractFor($project), now()->addDays(25)->toDateString()); // ya pasó el de 40

    $this->artisan('contratacion:policy-alerts')->assertSuccessful();
    expect($policy->fresh()->last_alert_days)->toBe(30);

    $this->travel(5)->days(); // 20 días
    $this->artisan('contratacion:policy-alerts')->assertSuccessful();
    Mail::assertSent(PolicyExpiringMail::class, 2);
    expect($policy->fresh()->last_alert_days)->toBe(20);
});

test('reciben el aviso los correos configurados y los usuarios con el permiso de alertas de pólizas', function () {
    $project = Project::create(['code' => 'p1', 'name' => 'Proyecto 1']);
    policyFor(contractFor($project), now()->addDays(10)->toDateString());

    $mariaJose = App\Http\Modules\Users\Models\User::create(['name' => 'María José Ospina', 'email' => 'mjospina@example.com', 'password' => 'secret']);
    $mariaJose->givePermissionTo(['contratacion.alertas_polizas', 'proyectos.ver_todos']);

    $this->artisan('contratacion:policy-alerts')->assertSuccessful();

    Mail::assertSent(PolicyExpiringMail::class, fn ($mail) => $mail->hasTo('alertas@example.com') && $mail->hasTo('mjospina@example.com'));
});

<?php

use App\Http\Modules\Auth\Mail\PasswordResetMail;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    config(['app.frontend_url' => 'https://elite.test']);
    $this->user = User::create(['name' => 'Paula Camacho', 'email' => 'paula@example.com', 'password' => Hash::make('Anterior123')]);
});

function resetLink(): array
{
    $mail = Mail::sent(PasswordResetMail::class)->first();
    parse_str(parse_url($mail->url, PHP_URL_QUERY), $query);

    return [$mail->url, $query['token'], $query['email']];
}

test('envía el enlace al correo registrado (sin distinguir mayúsculas) y no revela si el correo existe', function () {
    $ok = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'PAULA@example.com'])->assertOk()->json('message');
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nadie@example.com'])->assertOk()->json('message');

    expect($ok)->toBe($unknown);
    Mail::assertSent(PasswordResetMail::class, 1);
    Mail::assertSent(PasswordResetMail::class, fn ($m) => $m->hasTo('paula@example.com'));

    [$url, , $email] = resetLink();
    expect($url)->toStartWith('https://elite.test/restablecer-clave?token=')->and($email)->toBe('paula@example.com');
});

test('con el enlace se cambia la contraseña, se cierran las sesiones y el enlace no se puede reutilizar', function () {
    $this->user->createToken('auth_token');
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'paula@example.com'])->assertOk();
    [, $token, $email] = resetLink();

    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $email, 'password' => 'Nueva2026', 'password_confirmation' => 'Nueva2026'])
        ->assertOk();

    expect(Hash::check('Nueva2026', $this->user->fresh()->password))->toBeTrue()
        ->and($this->user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => 'paula@example.com', 'password' => 'Nueva2026'])->assertOk();

    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $email, 'password' => 'Otra2026x', 'password_confirmation' => 'Otra2026x'])
        ->assertStatus(422)->assertJsonPath('message', 'El enlace para restablecer la contraseña no es válido o ya venció. Solicita uno nuevo.');
});

test('rechaza un token inválido, uno vencido y una contraseña débil', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'paula@example.com'])->assertOk();
    [, $token, $email] = resetLink();

    $this->postJson('/api/v1/auth/reset-password', ['token' => 'otro', 'email' => $email, 'password' => 'Nueva2026', 'password_confirmation' => 'Nueva2026'])
        ->assertStatus(422);
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $email, 'password' => 'corta', 'password_confirmation' => 'corta'])
        ->assertStatus(422)->assertJsonValidationErrors('password');
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $email, 'password' => 'Nueva2026', 'password_confirmation' => 'Distinta2026'])
        ->assertStatus(422)->assertJsonValidationErrors('password');

    $this->travel(61)->minutes();
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $email, 'password' => 'Nueva2026', 'password_confirmation' => 'Nueva2026'])
        ->assertStatus(422);

    expect(Hash::check('Anterior123', $this->user->fresh()->password))->toBeTrue();
});

test('el correo se renderiza con el enlace y el vencimiento', function () {
    $html = (new PasswordResetMail($this->user, 'https://elite.test/restablecer-clave?token=abc&email=paula%40example.com'))->render();

    expect($html)->toContain('Paula Camacho')->toContain('60 minutos')->toContain('Crear nueva contraseña')->toContain('restablecer-clave?token=abc');
});

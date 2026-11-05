<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * JWT HS256 para la comunicación con ONLYOFFICE Docs (configuración del editor y callbacks de guardado).
 */
class OnlyOfficeJwt
{
    public function __construct(protected ?string $secret = null)
    {
        $this->secret ??= config('onlyoffice.jwt_secret');
    }

    public function isConfigured(): bool
    {
        return is_string($this->secret) && $this->secret !== '';
    }

    public function encode(array $payload): string
    {
        $this->assertConfigured();

        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
        $segments[] = $this->base64UrlEncode(hash_hmac('sha256', implode('.', $segments), $this->secret, true));

        return implode('.', $segments);
    }

    /**
     * Verifica la firma y devuelve el payload. Lanza InvalidArgumentException si el token no es válido.
     */
    public function decode(string $token): array
    {
        $this->assertConfigured();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token con formato inválido.');
        }

        [$header, $payload, $signature] = $parts;
        $headerData = json_decode($this->base64UrlDecode($header), true);
        if (($headerData['alg'] ?? null) !== 'HS256') {
            throw new InvalidArgumentException('Algoritmo de firma no soportado.');
        }

        $expected = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Firma del token inválida.');
        }

        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Contenido del token inválido.');
        }
        if (isset($data['exp']) && is_numeric($data['exp']) && $data['exp'] < time()) {
            throw new InvalidArgumentException('Token expirado.');
        }

        return $data;
    }

    protected function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET no está configurado.');
        }
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}

<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use InvalidArgumentException;

/**
 * CRUD de tipos de cuenta de cobro. Un tipo con cuentas registradas no se elimina: se desactiva,
 * para que las cuentas existentes conserven su tipo.
 */
class CuentaCobroTipoService
{
    public function list(): array
    {
        return CuentaCobroTipo::query()
            ->withCount('cuentas')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (CuentaCobroTipo $tipo) => $this->present($tipo))
            ->all();
    }

    public function save(array $data, ?CuentaCobroTipo $tipo = null): CuentaCobroTipo
    {
        $attributes = [
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? (trim($data['description']) ?: null) : null,
            'requires_attachment' => (bool) $data['requires_attachment'],
            'attachment_hint' => !empty($data['requires_attachment']) && isset($data['attachment_hint']) ? (trim($data['attachment_hint']) ?: null) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        $tipo ? $tipo->update($attributes) : $tipo = CuentaCobroTipo::create($attributes);

        return $tipo->loadCount('cuentas');
    }

    /**
     * @return bool true si se eliminó; false si se desactivó porque tiene cuentas
     */
    public function delete(CuentaCobroTipo $tipo): bool
    {
        if ($tipo->cuentas()->withTrashed()->exists()) {
            $tipo->update(['is_active' => false]);

            return false;
        }

        $tipo->delete();

        return true;
    }

    public function present(CuentaCobroTipo $tipo): array
    {
        return [
            'id' => $tipo->id,
            'name' => $tipo->name,
            'description' => $tipo->description,
            'requires_attachment' => $tipo->requires_attachment,
            'attachment_hint' => $tipo->attachment_hint,
            'is_active' => $tipo->is_active,
            'cuentas_count' => $tipo->cuentas_count ?? null,
        ];
    }

    public function assertUsable(?CuentaCobroTipo $tipo): CuentaCobroTipo
    {
        if (!$tipo || !$tipo->is_active) {
            throw new InvalidArgumentException('El tipo de cuenta de cobro no existe o está inactivo.');
        }

        return $tipo;
    }
}

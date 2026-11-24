<?php

namespace App\Http\Modules\Contratacion\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Carpeta de S3 vinculada a un tipo de documento del contrato (ContractDocumentCatalog::FOLDERS).
 */
class ContratacionContractFolder extends Model
{
    protected $table = 'contratacion_contract_folders';

    protected $fillable = [
        'contract_id',
        'folder',
        'path',
        'linked_by',
    ];

    public function contract()
    {
        return $this->belongsTo(ContratacionContract::class, 'contract_id');
    }
}

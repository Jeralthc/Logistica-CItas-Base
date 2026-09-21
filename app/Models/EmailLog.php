<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'numero_oc',
        'proveedor',
        'email_destino',
        'vendedor_nombre',
        'tipo_evento',
        'estatus',
        'error_mensaje',
    ];
}

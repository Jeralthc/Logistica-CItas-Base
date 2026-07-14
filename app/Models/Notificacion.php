<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'numero_oc',
        'proveedor',
        'tipo',
        'fecha_oc',
        'fecha_recepcion',
        'status_erp',
        'target_user_id',
        'leida',
    ];

    protected $casts = [
        'leida' => 'boolean',
        'fecha_oc' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];
}

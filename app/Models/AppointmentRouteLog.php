<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentRouteLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'numero_oc',
        'estatus_anterior',
        'estatus_nuevo',
        'user_id',
        'user_name'
    ];
}

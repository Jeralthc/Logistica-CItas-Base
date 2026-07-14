<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Operario extends Model
{
    protected $fillable = [
        'nombre',
        'cedula',
        'tipo',
        'disponible',
        'turno',
    ];

    protected $casts = [
        'disponible' => 'boolean',
    ];
}

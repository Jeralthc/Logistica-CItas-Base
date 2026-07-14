<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaRendimiento extends Model
{
    protected $table = 'categorias_rendimiento';

    protected $fillable = [
        'nombre',
        'tiempo_fijo',
        'velocidad_descarga',
    ];

    protected $casts = [
        'tiempo_fijo' => 'integer',
        'velocidad_descarga' => 'decimal:2',
    ];
}

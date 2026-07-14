<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'module',
        'action',
        'auditable_type',
        'auditable_id',
        'motive',
        'old_values',
        'new_values',
        'ip_address'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    const UPDATED_AT = null;
}

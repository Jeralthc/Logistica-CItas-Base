<?php

namespace App\Services;

use App\Models\SystemAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Registra una acción en la Bitácora Global del Sistema.
     *
     * @param string $module
     * @param string $action
     * @param string|null $motive
     * @param mixed $auditableType
     * @param mixed $auditableId
     * @param array|null $oldValues
     * @param array|null $newValues
     */
    public static function log($module, $action, $motive = null, $auditableType = null, $auditableId = null, $oldValues = null, $newValues = null)
    {
        $user = Auth::user();

        // Aplicar Data Masking
        $oldValues = self::maskSensitiveData($oldValues);
        $newValues = self::maskSensitiveData($newValues);

        SystemAuditLog::create([
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->name : 'Sistema Automático',
            'user_role' => $user ? $user->role : 'Sistema',
            'module' => $module,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'motive' => $motive,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Oculta datos sensibles como contraseñas antes de guardar en la bitácora.
     */
    private static function maskSensitiveData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveKeys = ['password', 'password_base', 'password_confirmation', 'token', 'api_token', 'secret'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = self::maskSensitiveData($value);
            }
        }

        return $data;
    }
}

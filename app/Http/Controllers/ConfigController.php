<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ConfigController extends Controller
{
    /**
     * Get the current ERP configuration from .env
     */
    public function getErpConfig()
    {
        $env = File::get(base_path('.env'));
        
        $config = [
            'mode' => $this->getEnvValue($env, 'ERP_CONNECTION_MODE') ?: 'database',
            'api_url' => $this->getEnvValue($env, 'ERP_API_URL'),
            'api_token' => $this->getEnvValue($env, 'ERP_API_TOKEN'),
            'host' => $this->getEnvValue($env, 'ERP_HOST'),
            'database' => $this->getEnvValue($env, 'ERP_DATABASE'),
            'username' => $this->getEnvValue($env, 'ERP_USERNAME'),
            // Do not send the password back to the frontend for security reasons
        ];

        return response()->json($config);
    }

    /**
     * Update the ERP configuration in .env
     */
    public function updateErpConfig(Request $request)
    {
        // Validar que el usuario sea administrador
        if (!auth('web')->check() || auth('web')->user()->role !== 'admin') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'mode' => 'required|in:database,api',
            'api_url' => 'nullable|string',
            'api_token' => 'nullable|string',
            'host' => 'nullable|string',
            'database' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string', // Optional to update
        ]);

        $envPath = base_path('.env');
        $env = File::get($envPath);

        $env = $this->setEnvValue($env, 'ERP_CONNECTION_MODE', $validated['mode']);
        
        if ($validated['mode'] === 'api') {
            $env = $this->setEnvValue($env, 'ERP_API_URL', $validated['api_url'] ?? '');
            $env = $this->setEnvValue($env, 'ERP_API_TOKEN', $validated['api_token'] ?? '');
        } else {
            $env = $this->setEnvValue($env, 'ERP_HOST', $validated['host'] ?? '');
            $env = $this->setEnvValue($env, 'ERP_DATABASE', $validated['database'] ?? '');
            $env = $this->setEnvValue($env, 'ERP_USERNAME', $validated['username'] ?? '');
            
            if (!empty($validated['password'])) {
                $env = $this->setEnvValue($env, 'ERP_PASSWORD', $validated['password']);
            }
        }

        File::put($envPath, $env);

        // Limpiar la caché de configuración y la conexión actual
        Artisan::call('config:clear');
        \Illuminate\Support\Facades\DB::purge('sqlsrv');

        return response()->json(['message' => 'Configuración actualizada exitosamente']);
    }

    /**
     * Helper to get an environment variable's value
     */
    private function getEnvValue($envContent, $key)
    {
        if (preg_match("/^{$key}=(.*)$/m", $envContent, $matches)) {
            return trim($matches[1], '"');
        }
        return '';
    }

    /**
     * Helper to set an environment variable's value
     */
    private function setEnvValue($envContent, $key, $value)
    {
        $value = '"' . trim($value, '"') . '"'; // Escape with quotes in case of spaces
        
        if (preg_match("/^{$key}=/m", $envContent)) {
            return preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
        } else {
            return $envContent . "\n{$key}={$value}";
        }
    }
}

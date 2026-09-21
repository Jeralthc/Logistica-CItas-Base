<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\ProveedorContacto;
use App\Mail\NotificacionReactivacionOdc;

class NotificarOdcsHabilitadas extends Command
{
    protected $signature = 'odc:notificar-habilitadas {--all : Enviar a todas las habilitadas y no sólo a las de hoy}';
    protected $description = 'Enviar correo profesional de notificación y disculpa a los proveedores de ODCs habilitadas';

    public function handle()
    {
        $this->info("Buscando órdenes de compra habilitadas...");

        $query = DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada');

        if (!$this->option('all')) {
            $query->whereDate('updated_at', '>=', now()->format('Y-m-d'));
        }

        $ordenes = $query->get();

        if ($ordenes->isEmpty()) {
            // Si no hay hoy, buscar todas las habilitadas recientemente
            $this->warn("No se encontraron órdenes habilitadas hoy. Buscando todas las habilitadas...");
            $ordenes = DB::table('erp_ordenes_sync')
                ->where('estatus_habilitacion', 'habilitada')
                ->get();
        }

        if ($ordenes->isEmpty()) {
            $this->error("No hay ninguna orden de compra en estado habilitada.");
            return 0;
        }

        $enviados = 0;
        $errores = 0;

        foreach ($ordenes as $o) {
            $resumen = json_decode($o->resumen_json, true) ?? [];
            $codProv = trim($resumen['Codigo_Proveedor'] ?? '');
            
            // Buscar contacto o usuario asociado
            $limpiarRif = function($val) {
                return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($val ?? '')));
            };
            $rifLimpio = $limpiarRif($codProv);

            $user = User::where('role', 'proveedor')
                ->get()
                ->first(function($u) use ($limpiarRif, $rifLimpio) {
                    return $limpiarRif($u->rif) === $rifLimpio || $limpiarRif($u->username) === $rifLimpio;
                });

            $emailDestino = null;
            $vendedorNombre = null;

            if ($user) {
                $emailDestino = $user->email;
                $vendedorNombre = $user->name;
                $contacto = ProveedorContacto::where('user_id', $user->id)->first();
                if ($contacto && !empty($contacto->email)) {
                    $emailDestino = $contacto->email;
                    if (!empty($contacto->nombre)) {
                        $vendedorNombre = $contacto->nombre;
                    }
                }
            }

            if (!$emailDestino || str_contains($emailDestino, '@proveedor.suraki.net')) {
                // Intentar buscar en el resumen o en MA_PROVEEDORES ERP
                $emailDestino = $resumen['Email_Proveedor'] ?? $resumen['Email'] ?? $resumen['email'] ?? null;
                if ((!$emailDestino || str_contains($emailDestino, '@proveedor.suraki.net')) && $codProv) {
                    try {
                        $provErp = DB::connection('sqlsrv')->selectOne("
                            SELECT 
                                COALESCE(
                                    NULLIF(LTRIM(RTRIM(c_email)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_ven)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_adm)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_vdd)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_fiscal)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_reg)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_depo)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_dep)), '')
                                ) AS c_email
                            FROM MA_PROVEEDORES WITH (NOLOCK) 
                            WHERE c_codproveed = ? OR c_rif LIKE ?
                        ", [$codProv, "%{$rifLimpio}%"]);
                        if ($provErp && !empty($provErp->c_email)) {
                            $emailDestino = trim($provErp->c_email);
                        }
                    } catch (\Throwable $eErp) {}
                }
            }

            if (!$emailDestino) {
                $this->warn("Orden {$o->numero_oc}: No se encontró un correo registrado para el RIF {$codProv}.");
                continue;
            }

            $infoCorreo = (object)[
                'numero_oc' => $o->numero_oc,
                'proveedor' => $o->proveedor ?: ($resumen['Nombre_Proveedor'] ?? 'Proveedor'),
                'username' => $user ? $user->username : $codProv,
                'email_destino' => $emailDestino,
                'vendedor_nombre' => $vendedorNombre ?: ($o->proveedor ?: 'Estimado Proveedor'),
            ];

            try {
                Mail::to($emailDestino)->send(new NotificacionReactivacionOdc($infoCorreo));
                $this->info("✅ Correo enviado a {$emailDestino} para la ODC {$o->numero_oc} ({$infoCorreo->proveedor})");
                $enviados++;
            } catch (\Exception $e) {
                $this->error("❌ Error enviando a {$emailDestino} (ODC {$o->numero_oc}): " . $e->getMessage());
                $errores++;
            }
        }

        $this->info("\nProceso finalizado. Correos enviados exitosamente: {$enviados}. Errores: {$errores}.");
        return 0;
    }
}

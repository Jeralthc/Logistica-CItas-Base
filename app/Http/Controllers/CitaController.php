<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Operario;
use Carbon\Carbon;

class CitaController extends Controller
{
    public function __construct()
    {
        Carbon::setLocale('es');
    }

    /**
     * Obtener slots disponibles para una fecha dada.
     * Horario: 8:00 AM - 7:00 PM. Última reservación: 6:00 PM.
     */
    public function slotsDisponibles(Request $request)
    {
        $fecha = $request->input('fecha', Carbon::today()->format('Y-m-d'));
        $duracionMinutos = (int) $request->input('duracion', 60);
        $sucursal = $request->input('sucursal', '0101'); // Por defecto Hiper

        // Horario laboral
        $horaInicio = 8;  // 8:00 AM
        $horaFin = 18;    // 6:00 PM (última reservación)
        $intervalo = 30;  // slots cada 30 minutos

        // Obtener citas ya reservadas para esa fecha
        $citasExistentes = DB::table('appointments')
            ->whereDate('fecha_cita', $fecha)
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->select('fecha_cita', 'muelle_asignado', 'numero_oc', 'duracion_minutos')
            ->get();

        // Mapeo Real de Muelles por Sucursal (Fase 6)
        // El usuario indica usar números para los muelles
        $configMuelles = [
            '0101' => ['0101'], // TU EMPRESA
            '0102' => ['0102'], // Deposito Gral
            '0111' => ['0111'], // Produccion
            '0115' => ['0115'], // Insumos
            '0161' => ['0161'], // Andinka
            '0180' => ['01', '02', '03', '04'], // Galpón Central
        ];

        $muelles = $configMuelles[$sucursal] ?? [$sucursal]; // Fallback usa el código de sucursal

        $slots = [];
        for ($h = $horaInicio; $h < $horaFin; $h++) {
            for ($m = 0; $m < 60; $m += $intervalo) {
                $horaStr = sprintf('%02d:%02d', $h, $m);
                $slotInicio = Carbon::parse("$fecha $horaStr");
                $slotFin = $slotInicio->copy()->addMinutes((int) $duracionMinutos);

                // No pasar de las 7 PM
                $limiteLaboral = Carbon::parse("$fecha 19:00");
                if ($slotFin->gt($limiteLaboral)) continue;

                // Ver qué muelles están libres en ese slot
                $muellesOcupados = [];
                foreach ($citasExistentes as $cita) {
                    $citaInicio = Carbon::parse($cita->fecha_cita);
                    // Usar la duración real guardada en BD (Fase 6)
                    $duracionReal = $cita->duracion_minutos ?? $duracionMinutos;
                    $citaFin = $citaInicio->copy()->addMinutes((int) $duracionReal);

                    // Hay solapamiento si: inicio < citaFin AND fin > citaInicio
                    if ($slotInicio->lt($citaFin) && $slotFin->gt($citaInicio)) {
                        $muellesOcupados[] = $cita->muelle_asignado;
                    }
                }

                $muellesLibres = array_values(array_diff($muelles, $muellesOcupados));

                $slots[] = [
                    'hora' => $horaStr,
                    'hora_formato' => $slotInicio->format('h:i A'),
                    'hora_fin' => $slotFin->format('h:i A'),
                    'disponible' => count($muellesLibres) > 0,
                    'muelles_libres' => count($muellesLibres),
                    'muelles' => $muellesLibres,
                ];
            }
        }

        // Fechas disponibles (próximos 7 días, excluyendo domingos)
        $fechasDisponibles = [];
        for ($i = 0; $i < 10; $i++) {
            $d = Carbon::today()->addDays($i);
            if ($d->dayOfWeek !== Carbon::SUNDAY) {
                $fechasDisponibles[] = [
                    'fecha' => $d->format('Y-m-d'),
                    'dia' => $d->isoFormat('ddd'),
                    'dia_largo' => $d->isoFormat('dddd D [de] MMMM'),
                    'es_hoy' => $d->isToday(),
                ];
                if (count($fechasDisponibles) >= 7) break;
            }
        }

        return response()->json([
            'fecha' => $fecha,
            'slots' => $slots,
            'fechas_disponibles' => $fechasDisponibles,
            'duracion_minutos' => $duracionMinutos,
        ]);
    }

    /**
     * Registrar una cita/reservación.
     */
    public function reservar(Request $request)
    {
        $validated = $request->validate([
            'numero_oc' => 'required|string',
            'proveedor' => 'required|string',
            'rif_proveedor' => 'nullable|string',
            'fecha_cita' => 'required|date',
            'muelle_asignado' => 'required|string',
            'duracion_minutos' => 'required|integer', // Nueva validación
            'observaciones' => 'nullable|string',
        ]);
        
        // Fase 3 (Hardening): Sanitización de entradas contra XSS
        $validated['observaciones'] = !empty($validated['observaciones']) ? strip_tags($validated['observaciones']) : null;

        $fechaCita = Carbon::parse($validated['fecha_cita']);
        $duracion = $validated['duracion_minutos'];
        $fechaFin = $fechaCita->copy()->addMinutes((int) $duracion);

        // Validar horario
        if ($fechaCita->hour < 8 || $fechaCita->hour >= 18) {
            return response()->json(['error' => 'El horario de reservación es de 8:00 AM a 6:00 PM.'], 422);
        }

        // Validar que no sea domingo
        if ($fechaCita->dayOfWeek === Carbon::SUNDAY) {
            return response()->json(['error' => 'No se reciben reservaciones los domingos.'], 422);
        }

        // Verificar que el muelle esté libre (Rango)
        $citasExistentes = DB::table('appointments')
            ->where('muelle_asignado', $validated['muelle_asignado'])
            ->whereDate('fecha_cita', $fechaCita->format('Y-m-d'))
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->get();

        foreach ($citasExistentes as $cita) {
            $inicioExistente = Carbon::parse($cita->fecha_cita);
            $finExistente = $inicioExistente->copy()->addMinutes((int) $cita->duracion_minutos);

            // Hay solapamiento si: inicio < citaFin AND fin > citaInicio
            if ($fechaCita->lt($finExistente) && $fechaFin->gt($inicioExistente)) {
                return response()->json(['error' => 'Conflicto: Este muelle ya tiene una cita de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A')], 422);
            }
        }

        // Verificar que la OC no tenga ya una cita activa
        $citaExistente = DB::table('appointments')
            ->where('numero_oc', $validated['numero_oc'])
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->first();

        if ($citaExistente) {
            return response()->json(['error' => 'Esta orden ya tiene una cita programada.'], 422);
        }

        $id = DB::table('appointments')->insertGetId([
            'numero_oc' => $validated['numero_oc'],
            'proveedor' => $validated['proveedor'],
            'rif_proveedor' => $validated['rif_proveedor'] ?? null,
            'fecha_cita' => $fechaCita,
            'muelle_asignado' => $validated['muelle_asignado'],
            'duracion_minutos' => $duracion,
            'estatus' => 'programada',
            'user_id' => auth('web')->id() ?? 1,
            'observaciones' => $validated['observaciones'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar en Bitácora de Rutas Logísticas
        \App\Models\AppointmentRouteLog::create([
            'numero_oc' => $validated['numero_oc'],
            'estatus_anterior' => null,
            'estatus_nuevo' => 'programada',
            'user_id' => auth()->id() ?? 1,
            'user_name' => auth()->user() ? auth()->user()->name : 'Sistema',
        ]);

        // Registrar en Bitácora Global
        \App\Services\AuditLogger::log(
            module: 'Citas',
            action: 'Agendar Cita',
            motive: 'Programación inicial',
            auditableType: 'Appointment',
            auditableId: $id,
            oldValues: null,
            newValues: ['fecha_cita' => $fechaCita->toDateTimeString(), 'muelle' => $validated['muelle_asignado']]
        );

        // Crear notificación para el área de recepción
        \App\Models\Notificacion::create([
            'numero_oc' => $validated['numero_oc'],
            'proveedor' => $validated['proveedor'],
            'tipo' => 'nueva_cita',
            'fecha_oc' => now(), // fecha de registro de la cita
            'fecha_recepcion' => $fechaCita,
            'status_erp' => 'CITA', // Indicador local
        ]);

        // Ahora NUNCA enviamos el correo aquí. Siempre pasamos por el paso D (registrarProveedor) 
        // para que elijan o agreguen un vendedor/contacto y asociarlo.
        $proveedorRegistrado = false;
        $contactos = [];
        if (!empty($validated['rif_proveedor'])) {
            $user = \App\Models\User::with('contactos')->where('username', $validated['rif_proveedor'])->first();
            if ($user) {
                $proveedorRegistrado = true;
                $contactos = $user->contactos;
            }
        }

        return response()->json([
            'message' => 'Cita reservada exitosamente.',
            'proveedor_registrado' => $proveedorRegistrado,
            'contactos' => $contactos,
            'cita' => [
                'id' => $id,
                'numero_oc' => $validated['numero_oc'],
                'fecha' => $fechaCita->isoFormat('dddd D [de] MMMM [de] YYYY'),
                'hora' => $fechaCita->format('h:i A'),
                'hora_fin' => $fechaFin->format('h:i A'),
                'muelle' => $validated['muelle_asignado'],
                'duracion_minutos' => $duracion,
            ],
        ], 201);
    }

    /**
     * Reprogramar una cita.
     */
    public function reprogramar(Request $request, $id)
    {
        if (auth('web')->check() && auth('web')->user()->role === 'comprador') {
            $modificadoPorReceptor = \App\Models\SystemAuditLog::where('auditable_id', $id)
                ->where('auditable_type', 'Appointment')
                ->where('user_role', 'receptor')
                ->exists();
            if ($modificadoPorReceptor) {
                return response()->json(['error' => 'No tienes permisos. Recepción ya ha modificado esta cita.'], 403);
            }
        }

        $validated = $request->validate([
            'fecha_cita' => 'required|date',
            'muelle_asignado' => 'required|string',
            'motivo' => 'required|string',
        ]);
        
        // Fase 3: Sanitizar XSS
        $validated['motivo'] = strip_tags($validated['motivo']);

        $validated['muelle_asignado'] = trim($validated['muelle_asignado']);
        $cita = DB::table('appointments')->where('id', $id)->first();
        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada.'], 404);
        }

        $fechaCita = Carbon::parse($validated['fecha_cita']);
        $duracion = $cita->duracion_minutos ?? 60;
        $fechaFin = $fechaCita->copy()->addMinutes((int) $duracion);

        if ($fechaCita->hour < 8 || $fechaCita->hour >= 18) {
            return response()->json(['error' => 'El horario de reservación es de 8:00 AM a 6:00 PM.'], 422);
        }

        if ($fechaCita->dayOfWeek === Carbon::SUNDAY) {
            return response()->json(['error' => 'No se reciben reservaciones los domingos.'], 422);
        }

        // Verificar que el muelle esté libre (Rango), excluyendo la cita actual
        $citasExistentes = DB::table('appointments')
            ->where('id', '!=', $id)
            ->where('muelle_asignado', $validated['muelle_asignado'])
            ->whereDate('fecha_cita', $fechaCita->format('Y-m-d'))
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->get();

        foreach ($citasExistentes as $c) {
            $inicioExistente = Carbon::parse($c->fecha_cita);
            $finExistente = $inicioExistente->copy()->addMinutes((int) $c->duracion_minutos);

            // Hay solapamiento si: inicio < citaFin AND fin > citaInicio
            if ($fechaCita->lt($finExistente) && $fechaFin->gt($inicioExistente)) {
                return response()->json(['error' => 'Conflicto: Este muelle ya tiene una cita de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A')], 422);
            }
        }

        $oldValues = [
            'fecha_cita' => $cita->fecha_cita,
            'muelle_asignado' => $cita->muelle_asignado
        ];

        DB::table('appointments')->where('id', $id)->update([
            'fecha_cita' => $fechaCita,
            'muelle_asignado' => $validated['muelle_asignado'],
            'updated_at' => now(),
        ]);

        // Registrar en Bitácora Global
        \App\Services\AuditLogger::log(
            module: 'Citas',
            action: 'Reprogramar Cita',
            motive: $validated['motivo'],
            auditableType: 'Appointment',
            auditableId: $id,
            oldValues: $oldValues,
            newValues: ['fecha_cita' => $fechaCita->toDateTimeString(), 'muelle_asignado' => $validated['muelle_asignado']]
        );

        // --- CREAR NOTIFICACIÓN EN EL SISTEMA ---
        \App\Models\Notificacion::create([
            'numero_oc' => $cita->numero_oc,
            'proveedor' => $cita->proveedor,
            'tipo' => 'reprogramada',
            'fecha_oc' => now(),
            'fecha_recepcion' => $fechaCita,
            'status_erp' => 'Reprogramada',
            'leida' => false,
        ]);

        // --- ENVIAR CORREO DE REPROGRAMACIÓN ---
        try {
            $citaCompleta = DB::table('appointments')
                ->leftJoin('proveedor_contactos', 'appointments.contacto_id', '=', 'proveedor_contactos.id')
                ->leftJoin('users as creador', 'appointments.user_id', '=', 'creador.id')
                ->select('proveedor_contactos.email as proveedor_email', 'creador.email as creador_email')
                ->where('appointments.id', $id)
                ->first();

            $emailsDestino = [];
            if ($citaCompleta && !empty($citaCompleta->proveedor_email)) {
                $emailsDestino[] = $citaCompleta->proveedor_email;
            }
            if ($citaCompleta && !empty($citaCompleta->creador_email)) {
                $emailsDestino[] = $citaCompleta->creador_email;
            }
            
            // Buscar correo del comprador
            $ordenSync = DB::table('erp_ordenes_sync')->where('numero_oc', $cita->numero_oc)->first();
            if ($ordenSync && $ordenSync->habilitada_por_user_id) {
                $comprador = DB::table('users')->where('id', $ordenSync->habilitada_por_user_id)->first();
                if ($comprador && !empty($comprador->email)) {
                    $emailsDestino[] = $comprador->email;
                }
            }
            
            $emailsDestino = array_unique($emailsDestino);
            
            if (count($emailsDestino) > 0) {
                defer(fn () => Mail::to($emailsDestino)->send(new \App\Mail\CitaReprogramada($cita, $validated['motivo'], $oldValues['fecha_cita'])));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo reprogramacion: ' . $e->getMessage());
            return response()->json(['message' => 'Cita reprogramada, pero error enviando correo: ' . $e->getMessage()]);
        }

        return response()->json(['message' => 'Cita reprogramada exitosamente.']);
    }

    /**
     * Listar citas programadas.
     */
    public function listar(Request $request)
    {
        $status = $request->query('status', 'activas');

        $query = DB::table('appointments')
            ->leftJoin('proveedor_contactos', 'appointments.contacto_id', '=', 'proveedor_contactos.id')
            ->leftJoin('users as creador', 'appointments.user_id', '=', 'creador.id')
            ->select(
                'appointments.*', 
                'proveedor_contactos.nombre as vendedor_nombre', 
                'proveedor_contactos.email as vendedor_email', 
                'proveedor_contactos.telefono as vendedor_telefono', 
                'creador.name as registrado_por_nombre'
            );

        if ($status === 'finalizadas') {
            $query->where('appointments.estatus', 'finalizada')
                  ->orderBy('appointments.fecha_cita', 'desc');
        } elseif ($status === 'todas') {
            $query->orderBy('appointments.fecha_cita', 'desc');
        } else {
            // 'activas'
            $query->whereIn('appointments.estatus', ['programada', 'en muelle'])
                  ->orderBy('appointments.fecha_cita', 'asc');
        }

        // Ocultar citas a usuarios no autenticados (guests) por seguridad
        if (!auth('web')->check()) {
            return response()->json(['citas' => []]);
        }

        if (auth('web')->user()->role === 'proveedor') {
            $query->where('appointments.rif_proveedor', auth('web')->user()->username);
        }

        $citas = $query->get();

        $userRole = auth('web')->check() ? auth('web')->user()->role : 'guest';

        $citas = $citas->map(function ($cita) use ($userRole) {
            $cita->bloqueado_para_comprador = \App\Models\SystemAuditLog::where('auditable_id', $cita->id)
                ->where('auditable_type', 'Appointment')
                ->where('user_role', 'receptor')
                ->exists();
                
            // Buscar si la orden fue habilitada por un comprador para sobrescribir el Atendido Por
            $ordenLimpia = preg_replace('/^E/i', '', $cita->numero_oc);
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
            $ordenConE = 'E' . $ordenPad;
            
            $compradorEncontrado = false;
            
            // Intentar primero desde erp_ordenes_sync
            $syncRow = DB::table('erp_ordenes_sync')
                ->whereIn('numero_oc', [$cita->numero_oc, $ordenLimpia, $ordenPad, $ordenConE])
                ->first();
                
            if ($syncRow && $syncRow->habilitada_por_user_id) {
                $comprador = DB::table('users')->where('id', $syncRow->habilitada_por_user_id)->first();
                if ($comprador) {
                    $cita->registrado_por_nombre = $comprador->name;
                    $compradorEncontrado = true;
                }
            }
            
            // Fallback: usar habilitada_por_user_id guardado directamente en la cita
            if (!$compradorEncontrado && isset($cita->habilitada_por_user_id) && $cita->habilitada_por_user_id) {
                $comprador = DB::table('users')->where('id', $cita->habilitada_por_user_id)->first();
                if ($comprador) {
                    $cita->registrado_por_nombre = $comprador->name;
                }
            }
            
            // Convertir factura_path a URL pública completa
            if (!empty($cita->factura_path)) {
                $cita->factura_url = \Illuminate\Support\Facades\Storage::url($cita->factura_path);
            } else {
                $cita->factura_url = null;
            }
            
            // Si no hay vendedor_nombre (no hay contacto vinculado), usar el nombre del usuario proveedor que creó la cita
            if (empty($cita->vendedor_nombre) && $cita->user_id) {
                $creadorUser = DB::table('users')->where('id', $cita->user_id)->first();
                if ($creadorUser && $creadorUser->role === 'proveedor') {
                    $cita->vendedor_nombre = $creadorUser->name;
                }
            }
                
            // Ocultar detalles operativos al comprador
            if ($userRole === 'comprador') {
                unset($cita->formato_carga);
                unset($cita->tipo_vehiculo);
            }
            
            return $cita;
        });

        return response()->json(['citas' => $citas]);
    }

    /**
     * Cancelar una cita.
     */
    public function cancelar(Request $request, $id)
    {
        if (auth('web')->check() && auth('web')->user()->role === 'comprador') {
            $modificadoPorReceptor = \App\Models\SystemAuditLog::where('auditable_id', $id)
                ->where('auditable_type', 'Appointment')
                ->where('user_role', 'receptor')
                ->exists();
            if ($modificadoPorReceptor) {
                return response()->json(['error' => 'No tienes permisos. Recepción ya ha modificado esta cita.'], 403);
            }
        }

        $validated = $request->validate([
            'motivo' => 'required|string|min:5',
        ]);
        
        // Fase 3: Sanitizar XSS
        $validated['motivo'] = strip_tags($validated['motivo']);

        $cita = DB::table('appointments')->where('id', $id)->first();
        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada.'], 404);
        }

        DB::table('appointments')->where('id', $id)->update([
            'estatus' => 'cancelada',
            'updated_at' => now(),
        ]);

        // Registrar en Bitácora de Rutas Logísticas
        \App\Models\AppointmentRouteLog::create([
            'numero_oc' => $cita->numero_oc,
            'estatus_anterior' => $cita->estatus,
            'estatus_nuevo' => 'cancelada',
            'user_id' => auth('web')->id() ?? 1,
            'user_name' => auth('web')->user() ? auth('web')->user()->name : 'Sistema',
        ]);

        // Registrar en Bitácora Global
        \App\Services\AuditLogger::log(
            module: 'Citas',
            action: 'Cancelar Cita',
            motive: $validated['motivo'],
            auditableType: 'Appointment',
            auditableId: $id,
            oldValues: ['estatus' => $cita->estatus],
            newValues: ['estatus' => 'cancelada']
        );

        // --- CREAR NOTIFICACIÓN EN EL SISTEMA ---
        \App\Models\Notificacion::create([
            'numero_oc' => $cita->numero_oc,
            'proveedor' => $cita->proveedor,
            'tipo' => 'cancelada',
            'fecha_oc' => now(),
            'fecha_recepcion' => $cita->fecha_cita,
            'status_erp' => 'Cancelada',
            'leida' => false,
        ]);

        // --- ENVIAR CORREO DE CANCELACIÓN ---
        try {
            $citaCompleta = DB::table('appointments')
                ->leftJoin('proveedor_contactos', 'appointments.contacto_id', '=', 'proveedor_contactos.id')
                ->leftJoin('users as creador', 'appointments.user_id', '=', 'creador.id')
                ->select('proveedor_contactos.email as proveedor_email', 'creador.email as creador_email')
                ->where('appointments.id', $id)
                ->first();

            $emailsDestino = [];
            if ($citaCompleta && !empty($citaCompleta->proveedor_email)) {
                $emailsDestino[] = $citaCompleta->proveedor_email;
            }
            if ($citaCompleta && !empty($citaCompleta->creador_email)) {
                $emailsDestino[] = $citaCompleta->creador_email;
            }
            
            // Buscar correo del comprador
            $ordenSync = DB::table('erp_ordenes_sync')->where('numero_oc', $cita->numero_oc)->first();
            if ($ordenSync && $ordenSync->habilitada_por_user_id) {
                $comprador = DB::table('users')->where('id', $ordenSync->habilitada_por_user_id)->first();
                if ($comprador && !empty($comprador->email)) {
                    $emailsDestino[] = $comprador->email;
                }
            }
            
            $emailsDestino = array_unique($emailsDestino);
            
            if (count($emailsDestino) > 0) {
                defer(fn () => Mail::to($emailsDestino)->send(new \App\Mail\CitaCancelada($cita, $validated['motivo'])));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo cancelacion: ' . $e->getMessage());
            return response()->json(['message' => 'Cita cancelada, pero error enviando correo: ' . $e->getMessage()]);
        }

        return response()->json(['message' => 'Cita cancelada exitosamente.']);
    }

    /**
     * Marca una cita como recibida y finalizada
     */
    public function finalizar(Request $request, $id)
    {
        if (auth('web')->check() && !in_array(auth('web')->user()->role, ['admin', 'receptor'])) {
            return response()->json(['error' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $cita = DB::table('appointments')->where('id', $id)->first();
        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada.'], 404);
        }

        if ($cita->estatus === 'finalizada') {
            return response()->json(['error' => 'La cita ya se encuentra finalizada.'], 400);
        }

        DB::table('appointments')->where('id', $id)->update([
            'estatus' => 'finalizada',
            'updated_at' => now(),
        ]);

        // Registrar en Bitácora de Rutas Logísticas
        \App\Models\AppointmentRouteLog::create([
            'numero_oc' => $cita->numero_oc,
            'estatus_anterior' => $cita->estatus,
            'estatus_nuevo' => 'finalizada',
            'user_id' => auth('web')->id() ?? 1,
            'user_name' => auth('web')->user() ? auth('web')->user()->name : 'Sistema',
        ]);

        // Registrar en Bitácora Global
        \App\Services\AuditLogger::log(
            module: 'Citas',
            action: 'Finalizar Cita',
            motive: 'Marcada como recibida y validada por el personal de logística',
            auditableType: 'Appointment',
            auditableId: $id,
            oldValues: ['estatus' => $cita->estatus],
            newValues: ['estatus' => 'finalizada']
        );

        // Buscar el comprador que habilitó la orden para notificarle
        $compradorEmail = null;
        $ordenLimpia = preg_replace('/^E/i', '', $cita->numero_oc);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;

        $syncRow = DB::table('erp_ordenes_sync')
            ->whereIn('numero_oc', [$cita->numero_oc, $ordenLimpia, $ordenPad, $ordenConE])
            ->first();

        if ($syncRow && $syncRow->habilitada_por_user_id) {
            $comprador = DB::table('users')->where('id', $syncRow->habilitada_por_user_id)->first();
            if ($comprador && !empty($comprador->email)) {
                $compradorEmail = $comprador->email;
            }
        }

        if ($compradorEmail) {
            try {
                defer(fn () => \Illuminate\Support\Facades\Mail::to($compradorEmail)->send(new \App\Mail\CitaFinalizada($cita)));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error deferring finalization mail: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'La cita ha sido marcada como recibida y finalizada con éxito.'
        ]);
    }

    /**
     * Paso C y D: Registrar cuenta del proveedor y enviar credenciales y cita
     */
    public function registrarProveedor(Request $request)
    {
        $validated = $request->validate([
            'rif' => 'required|string',
            'password_base' => ['nullable', \Illuminate\Validation\Rules\Password::defaults()],
            'email' => 'required|email',
            'telefono' => 'required|string',
            'asesor' => 'required|string',
            'cita_id' => 'required|integer',
            'contacto_id' => 'nullable|integer',
        ]);
        
        $validated['asesor'] = strip_tags($validated['asesor']);

        $user = User::where('username', $validated['rif'])->first();
        
        // Si no existe el usuario, lo creamos y obligamos a que tenga password
        if (!$user) {
            if (empty($validated['password_base'])) {
                return response()->json(['message' => 'La contraseña es requerida para un proveedor nuevo.'], 422);
            }
            $user = User::create([
                'name' => $validated['asesor'],
                'username' => $validated['rif'],
                'email' => $validated['email'],
                'role' => 'proveedor',
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password_base']),
            ]);
        } else {
            // Actualizar la contraseña si se proporcionó una nueva
            if (!empty($validated['password_base'])) {
                $user->password = \Illuminate\Support\Facades\Hash::make($validated['password_base']);
                $user->save();
            }
        }

        // Buscar si eligió un contacto existente
        $contactoId = $validated['contacto_id'] ?? null;
        $contacto = null;

        if ($contactoId) {
            $contacto = \App\Models\ProveedorContacto::where('id', $contactoId)->where('user_id', $user->id)->first();
        }

        // Si no eligió contacto o no existe, creamos uno nuevo
        if (!$contacto) {
            $contacto = \App\Models\ProveedorContacto::create([
                'user_id' => $user->id,
                'nombre' => $validated['asesor'],
                'email' => $validated['email'],
                'telefono' => $validated['telefono'],
            ]);
        } else {
            // Actualizamos los datos del contacto por si acaso
            $contacto->update([
                'nombre' => $validated['asesor'],
                'email' => $validated['email'],
                'telefono' => $validated['telefono'],
            ]);
        }

        // Actualizar la cita con el contacto_id
        DB::table('appointments')->where('id', $validated['cita_id'])->update([
            'contacto_id' => $contacto->id,
            'updated_at' => now(),
        ]);

        // Obtener la cita para el correo
        $cita = DB::table('appointments')->where('id', $validated['cita_id'])->first();

        // --- ENVIAR CORREO DE NUEVA CITA ---
        try {
            $emailsDestino = array_unique([$user->email, $contacto->email]);
            
            if ($cita) {
                // Reconstruimos un objeto stdClass con la info de la cita para el correo
                $citaObj = (object)[
                    'numero_oc' => $cita->numero_oc,
                    'proveedor' => $cita->proveedor,
                    'fecha_cita' => \Carbon\Carbon::parse($cita->fecha_cita),
                    'muelle_asignado' => $cita->muelle_asignado,
                    'duracion_minutos' => $cita->duracion_minutos ?? 60,
                    'username' => $validated['rif'],
                    'password' => $validated['password_base'] ?? '******** (Ya configurada)',
                    'vendedor_nombre' => $contacto->nombre,
                ];
                
                defer(fn () => \Illuminate\Support\Facades\Mail::to($emailsDestino)->send(new \App\Mail\NuevaCita($citaObj)));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo de nueva cita: ' . $e->getMessage());
            return response()->json(['message' => 'Cuenta registrada, pero hubo un error enviando el correo: ' . $e->getMessage()]);
        }

        return response()->json(['message' => 'Cuenta/Contacto registrado y correo enviado con éxito.']);
    }

    /**
     * Comprador habilita una ODC para que el proveedor la agende.
     */
    public function habilitarOdc(Request $request)
    {
        $validated = $request->validate([
            'numero_oc' => 'required|string',
            'proveedor' => 'required|string',
            'rif' => 'required|string',
            'contacto_id' => 'nullable|integer',
            'email' => 'required|email',
            'telefono' => 'required|string',
            'asesor' => 'required|string',
        ]);

        // Marcar ODC como habilitada en erp_ordenes_sync (si existe allí)
        $ordenLimpia = preg_replace('/^E/i', '', $validated['numero_oc']);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;
        
        $syncRow = DB::table('erp_ordenes_sync')
            ->whereIn('numero_oc', [$validated['numero_oc'], $ordenLimpia, $ordenPad, $ordenConE])
            ->first();

        if ($syncRow) {
            // Prevenir doble habilitación si ya está habilitada o tiene cita activa
            if ($syncRow->estatus_habilitacion === 'habilitada') {
                $tieneCita = DB::table('appointments')
                    ->where('numero_oc', $syncRow->numero_oc)
                    ->whereIn('estatus', ['programada', 'en muelle'])
                    ->exists();

                if ($tieneCita) {
                    return response()->json(['error' => 'Esta orden de compra ya tiene una cita agendada activa.'], 422);
                }
                return response()->json(['error' => 'Esta orden de compra ya fue habilitada previamente.'], 422);
            }

            $currentResumen = json_decode($syncRow->resumen_json, true) ?? [];
            $currentResumen['Codigo_Proveedor'] = $validated['rif'];
            
            DB::table('erp_ordenes_sync')->where('numero_oc', $syncRow->numero_oc)->update([
                'estatus_habilitacion' => 'habilitada',
                'habilitada_por_user_id' => auth()->id() ?? 1,
                'resumen_json' => json_encode($currentResumen)
            ]);
        } else {
            DB::table('erp_ordenes_sync')->insert([
                'numero_oc' => $validated['numero_oc'],
                'proveedor' => $validated['proveedor'],
                'estatus_habilitacion' => 'habilitada',
                'habilitada_por_user_id' => auth()->id() ?? 1,
                'resumen_json' => json_encode(['Codigo_Proveedor' => $validated['rif']]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Verificar si el proveedor ya tiene cuenta registrada o crearla para persistir el correo
        $proveedorUser = User::where('username', $validated['rif'])->first();
        if (!$proveedorUser) {
            $proveedorUser = User::create([
                'name' => $validated['asesor'] ?: $validated['proveedor'],
                'username' => $validated['rif'],
                'email' => $validated['email'],
                'role' => 'proveedor',
                'password' => \Illuminate\Support\Facades\Hash::make($validated['rif']), // Contraseña temporal = RIF
            ]);
        } else {
            if (empty($proveedorUser->email) || $proveedorUser->email !== $validated['email']) {
                $proveedorUser->email = $validated['email'];
                $proveedorUser->save();
            }
        }

        // Registrar/actualizar contacto para persistir el correo y teléfono del proveedor
        \App\Models\ProveedorContacto::updateOrCreate(
            [
                'user_id' => $proveedorUser->id,
                'email' => $validated['email'],
            ],
            [
                'nombre' => $validated['asesor'] ?: $validated['proveedor'],
                'telefono' => $validated['telefono'] ?: '0000000000',
            ]
        );

        $yaRegistrado = false;
        if ($proveedorUser && !empty($proveedorUser->password)) {
            // Si la clave es su RIF, se considera que aún no ha completado el registro
            if (!\Illuminate\Support\Facades\Hash::check($validated['rif'], $proveedorUser->password)) {
                $yaRegistrado = true;
            }
        }

        // Enviar el correo de notificación
        $infoCorreo = (object)[
            'numero_oc' => $validated['numero_oc'],
            'proveedor' => $validated['proveedor'],
            'username' => $validated['rif'],
            'email_destino' => $validated['email'],
            'vendedor_nombre' => $validated['asesor'],
            'comprador_nombre' => auth()->user() ? auth()->user()->name : 'Comprador',
        ];

        try {
            if ($yaRegistrado) {
                // Proveedor ya tiene cuenta → enviar correo simplificado (sin enlace de registro)
                defer(fn () => \Illuminate\Support\Facades\Mail::to($validated['email'])->send(new \App\Mail\OdcHabilitadaRegistrado($infoCorreo)));
            } else {
                // Proveedor nuevo → enviar correo con enlace de registro
                defer(fn () => \Illuminate\Support\Facades\Mail::to($validated['email'])->send(new \App\Mail\OdcHabilitada($infoCorreo)));
            }

            // Crear notificación in-app para el proveedor (campana + toast)
            if ($proveedorUser) {
                \App\Models\Notificacion::create([
                    'numero_oc' => $validated['numero_oc'],
                    'proveedor' => $validated['proveedor'],
                    'tipo' => 'odc_habilitada',
                    'fecha_oc' => now(),
                    'target_user_id' => $proveedorUser->id,
                    'leida' => false,
                ]);
            }

            $msg = $yaRegistrado 
                ? 'ODC habilitada. El proveedor ya está registrado, se le envió notificación para que agende directamente.'
                : 'ODC habilitada y correo con enlace de registro enviado al proveedor exitosamente.';

            return response()->json(['message' => $msg, 'ya_registrado' => $yaRegistrado]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo de ODC habilitada: ' . $e->getMessage());
            return response()->json(['message' => 'ODC habilitada, pero hubo un error enviando el correo de notificación.', 'error_mail' => $e->getMessage()], 200);
        }
    }

    /**
     * API para que el portal del proveedor liste sus ODCs habilitadas pendientes
     */
    public function odcsPendientesProveedor()
    {
        if (!auth('web')->check() || auth('web')->user()->role !== 'proveedor') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $rif = auth('web')->user()->username;
        
        // Buscar en erp_ordenes_sync las órdenes de este RIF que estén habilitadas
        // Asumimos que el proveedor contiene el RIF o que ya implementaremos otra forma
        // Dado que en el ERP Sync a veces el RIF está en el JSON de resumen
        
        $ordenes = DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada')
            ->orderBy('fecha_emision', 'desc')
            ->get();
        // Filtrar manualmente por RIF dentro del JSON (solución temporal rápida)
        // En un caso ideal, 'rif_proveedor' debería ser una columna directa
        $misOrdenes = [];
        foreach ($ordenes as $o) {
            $resumen = json_decode($o->resumen_json, true) ?? [];
            $codProv = trim($resumen['Codigo_Proveedor'] ?? '');
            
            // Buscar si la orden ya tiene una cita (busqueda robusta)
            $ordenLimpia = preg_replace('/^E/i', '', $o->numero_oc);
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
            
            $tieneCita = DB::table('appointments')
                ->whereIn('numero_oc', [$o->numero_oc, $ordenLimpia, $ordenPad])
                ->whereIn('estatus', ['programada', 'en muelle'])
                ->exists();
                
            if (strtoupper($codProv) === strtoupper(trim($rif)) && !$tieneCita) {
                $o->resumen = $resumen;
                $misOrdenes[] = $o;
            }
        }

        return response()->json(['ordenes' => $misOrdenes]);
    }

    /**
     * API para calcular duración en tiempo real desde el formulario Vue
     */
    public function calcularDuracionApi(Request $request)
    {
        $validated = $request->validate([
            'categoria' => 'required|string',
            'peso_ton' => 'required|numeric',
            'formato_carga' => 'required|string'
        ]);

        $minutos = \App\Services\AppointmentDurationService::calcular(
            $validated['categoria'],
            $validated['peso_ton'],
            $validated['formato_carga'],
            0
        );

        return response()->json(['duracion_minutos' => $minutos]);
    }

    /**
     * Proveedor agenda su propia cita con el Formulario Inteligente
     */
    public function reservarProveedor(Request $request)
    {
        $validated = $request->validate([
            'numero_oc' => 'required|string',
            'proveedor' => 'required|string',
            'fecha_cita' => 'required|date',
            'muelle_asignado' => 'required|string',
            'numero_factura' => 'required|string',
            'peso_factura_ton' => 'required|numeric',
            'formato_carga' => 'required|string',
            'tipo_vehiculo' => 'required|string',
            'categoria_sugerida' => 'nullable|string',
            'tipo_mercancia' => 'nullable|string',
            'factura_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'email_contacto' => 'nullable|email',
        ]);

        $rif = auth()->user()->username;
        $user_id = auth()->id();

        // Obtener el contacto del proveedor
        $contacto = \App\Models\ProveedorContacto::where('user_id', $user_id)->first();
        $contacto_id = $contacto ? $contacto->id : null;

        // Actualizar email si fue proporcionado/editado
        $nuevoEmail = $validated['email_contacto'] ?? null;
        if ($nuevoEmail) {
            if ($contacto) {
                $contacto->update(['email' => $nuevoEmail]);
            }
            if (auth()->user()->email !== $nuevoEmail) {
                auth()->user()->update(['email' => $nuevoEmail]);
            }
        }

        $fechaCita = Carbon::parse($validated['fecha_cita']);
        
        $tipoMercancia = $validated['tipo_mercancia'] ?? $validated['categoria_sugerida'] ?? null;
        $catNombre = $tipoMercancia ?? 'Alimentos 1 (Viveres)';

        // Calcular duración exacta
        $duracion = \App\Services\AppointmentDurationService::calcular(
            $catNombre,
            $validated['peso_factura_ton'],
            $validated['formato_carga'],
            0
        );

        $fechaFin = $fechaCita->copy()->addMinutes((int) $duracion);

        // Validaciones de horario...
        if ($fechaCita->hour < 8 || $fechaCita->hour >= 18) {
            return response()->json(['error' => 'El horario de reservación es de 8:00 AM a 6:00 PM.'], 422);
        }
        if ($fechaCita->dayOfWeek === Carbon::SUNDAY) {
            return response()->json(['error' => 'No se reciben reservaciones los domingos.'], 422);
        }

        // Verificar muelle
        $citasExistentes = DB::table('appointments')
            ->where('muelle_asignado', $validated['muelle_asignado'])
            ->whereDate('fecha_cita', $fechaCita->format('Y-m-d'))
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->get();

        foreach ($citasExistentes as $cita) {
            $inicioExistente = Carbon::parse($cita->fecha_cita);
            $finExistente = $inicioExistente->copy()->addMinutes((int) $cita->duracion_minutos);

            if ($fechaCita->lt($finExistente) && $fechaFin->gt($inicioExistente)) {
                return response()->json(['error' => 'Conflicto: Este muelle ya tiene una cita de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A')], 422);
            }
        }

        // Verificar si la OC ya tiene cita
        $citaExistente = DB::table('appointments')
            ->where('numero_oc', $validated['numero_oc'])
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->first();

        if ($citaExistente) {
            return response()->json(['error' => 'Esta orden ya tiene una cita programada.'], 422);
        }

        // Obtener categoría ID
        $catModel = \App\Models\CategoriaRendimiento::where('nombre', $catNombre)->first();
        if (strtolower($validated['formato_carga']) === 'paletizada') {
            $paletizadaCat = \App\Models\CategoriaRendimiento::where('nombre', 'Carga Paletizada General')->first();
            if ($paletizadaCat) {
                $catModel = $paletizadaCat;
            }
        }
        $catId = $catModel ? $catModel->id : null;

        // Subir archivo de factura si existe
        $facturaPath = null;
        if ($request->hasFile('factura_file')) {
            $facturaPath = $request->file('factura_file')->store('facturas', 'public');
        }

        // Recuperar quién la habilitó para enviarle notificación
        $ordenLimpia = preg_replace('/^E/i', '', $validated['numero_oc']);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;
        
        $syncRow = DB::table('erp_ordenes_sync')->whereIn('numero_oc', [$validated['numero_oc'], $ordenLimpia, $ordenPad, $ordenConE])->first();
        $compradorId = $syncRow ? $syncRow->habilitada_por_user_id : null;

        $id = DB::table('appointments')->insertGetId([
            'numero_oc' => $validated['numero_oc'],
            'proveedor' => $validated['proveedor'],
            'rif_proveedor' => $rif,
            'contacto_id' => $contacto_id,
            'fecha_cita' => $fechaCita,
            'muelle_asignado' => $validated['muelle_asignado'],
            'duracion_minutos' => $duracion,
            'estatus' => 'programada',
            'user_id' => auth()->user() ? auth()->id() : 1,
            'numero_factura' => $validated['numero_factura'],
            'peso_factura_ton' => $validated['peso_factura_ton'],
            'formato_carga' => $validated['formato_carga'],
            'tipo_vehiculo' => $validated['tipo_vehiculo'],
            'tipo_mercancia' => $tipoMercancia,
            'factura_path' => $facturaPath,
            'categoria_rendimiento_id' => $catId,
            'habilitada_por_user_id' => $compradorId,
            'datos_proveedor_completos' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($syncRow) {
            DB::table('erp_ordenes_sync')->where('numero_oc', $syncRow->numero_oc)->update([
                'estatus_habilitacion' => 'agendada'
            ]);
        }

        // Log
        \App\Models\AppointmentRouteLog::create([
            'numero_oc' => $validated['numero_oc'],
            'estatus_anterior' => null,
            'estatus_nuevo' => 'programada',
            'user_id' => auth()->id() ?? 1,
            'user_name' => auth()->user() ? auth()->user()->name : 'Proveedor',
        ]);

        \App\Services\AuditLogger::log(
            module: 'Citas',
            action: 'Proveedor Agenda Cita',
            motive: 'Programación por proveedor',
            auditableType: 'Appointment',
            auditableId: $id,
            oldValues: null,
            newValues: ['fecha_cita' => $fechaCita->toDateTimeString(), 'muelle' => $validated['muelle_asignado']]
        );

        // Notificación para Recepción (General)
        \App\Models\Notificacion::create([
            'numero_oc' => $validated['numero_oc'],
            'proveedor' => $validated['proveedor'],
            'tipo' => 'nueva_cita',
            'fecha_oc' => now(),
            'fecha_recepcion' => $fechaCita,
            'status_erp' => 'CITA',
        ]);

        // Notificación para Comprador (Dirigida o General si no se sabe)
        \App\Models\Notificacion::create([
            'numero_oc' => $validated['numero_oc'],
            'proveedor' => $validated['proveedor'],
            'tipo' => 'nueva_cita',
            'fecha_oc' => now(),
            'fecha_recepcion' => $fechaCita,
            'status_erp' => 'CITA',
            'target_user_id' => $compradorId
        ]);

        // Enviar correo al comprador
        try {
            $compradores = $compradorId 
                ? \App\Models\User::where('id', $compradorId)->get()
                : \App\Models\User::whereIn('role', ['comprador', 'admin'])->get();
                
            foreach ($compradores as $comprador) {
                if (!empty($comprador->email)) {
                    $infoCorreo = (object)[
                        'numero_oc' => $validated['numero_oc'],
                        'proveedor' => $validated['proveedor'],
                        'fecha_cita' => $fechaCita,
                        'muelle_asignado' => $validated['muelle_asignado'],
                        'duracion_minutos' => $duracion,
                        'vendedor_nombre' => $contacto ? $contacto->nombre : 'Proveedor',
                        'comprador_nombre' => $comprador->name,
                    ];
                    defer(fn () => \Illuminate\Support\Facades\Mail::to($comprador->email)->send(new \App\Mail\ProveedorReservoCita($infoCorreo)));
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo ProveedorReservoCita: ' . $e->getMessage());
        }

        // Enviar correo de confirmación al proveedor
        try {
            $emailsProveedor = array_unique(array_filter([auth()->user()->email, $contacto ? $contacto->email : null]));
            if (count($emailsProveedor) > 0) {
                $infoCita = (object)[
                    'numero_oc' => $validated['numero_oc'],
                    'proveedor' => $validated['proveedor'],
                    'fecha_cita' => $fechaCita,
                    'muelle_asignado' => $validated['muelle_asignado'],
                    'duracion_minutos' => $duracion,
                    'username' => auth()->user()->username,
                    'password' => '******** (Ya configurada)',
                    'vendedor_nombre' => $contacto ? $contacto->nombre : auth()->user()->name,
                ];
                defer(fn () => \Illuminate\Support\Facades\Mail::to($emailsProveedor)->send(new \App\Mail\NuevaCita($infoCita)));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo NuevaCita a proveedor: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Cita agendada exitosamente.',
            'cita' => [
                'id' => $id,
                'numero_oc' => $validated['numero_oc'],
                'fecha' => $fechaCita->isoFormat('dddd D [de] MMMM [de] YYYY'),
                'hora' => $fechaCita->format('h:i A'),
                'hora_fin' => $fechaFin->format('h:i A'),
                'muelle' => $validated['muelle_asignado'],
                'duracion_minutos' => $duracion,
            ],
        ], 201);
    }

    /**
     * Obtener detalles de la cita por ODC (Para el Modal de Notificaciones)
     */
    public function detallePorOdc($numero_oc)
    {
        $cita = DB::table('appointments')
            ->where('numero_oc', $numero_oc)
            ->first();

        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada'], 404);
        }

        // Obtener el nombre de quien agendó si es un usuario
        $registradoPor = 'Sistema';
        if ($cita->registrado_por_user_id) {
            $user = DB::table('users')->where('id', $cita->registrado_por_user_id)->first();
            if ($user) {
                $registradoPor = $user->name;
            }
        }

        return response()->json([
            'cita' => [
                'numero_oc' => $cita->numero_oc,
                'proveedor' => $cita->proveedor,
                'fecha_cita' => $cita->fecha_cita,
                'estatus' => $cita->estatus,
                'muelle_asignado' => $cita->muelle_asignado,
                'observaciones' => $cita->observaciones,
                'vendedor_nombre' => $cita->vendedor_nombre,
                'registrado_por' => $registradoPor,
                'fecha_creacion' => $cita->created_at,
            ]
        ]);
    }
}

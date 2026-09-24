<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Operario;
use App\Mail\NotificacionReactivacionOdc;
use Carbon\Carbon;

class CitaController extends Controller
{
    public function __construct()
    {
        Carbon::setLocale('es');
    }

    /**
     * Retorna el grupo de muelles/sucursales equivalentes que comparten la misma recepción física.
     * Ej: 0101 (Piso de Venta) y 0102 (Depósito General) están ambos en Hiper Suraki y descargan en el mismo muelle.
     */
    public static function getMuellesEquivalentes($muelle)
    {
        $grupos = [
            'hiper' => ['0101', '0102'],
            'andinka' => ['0160', '0161'],
        ];

        foreach ($grupos as $grupo) {
            if (in_array($muelle, $grupo)) {
                return $grupo;
            }
        }

        return [$muelle];
    }

    /**
     * Determina si una cita, ODC o solicitud corresponde al depósito de Perecederos (Cavas/Carnes/Charcutería/Lácteos).
     * Los perecederos tienen su propio depósito aparte y no le quitan horas a Unai y Juan (Recepción General).
     */
    public static function esPerecederosCita($citaOArray): bool
    {
        $muelle = is_array($citaOArray) ? ($citaOArray['muelle_asignado'] ?? $citaOArray['sucursal'] ?? '') : ($citaOArray->muelle_asignado ?? $citaOArray->sucursal ?? '');
        $muelle = trim((string)$muelle);
        
        if (!empty($muelle)) {
            if (str_starts_with($muelle, '014') || str_starts_with($muelle, '015')) {
                return true;
            }
        }

        $tipo = is_array($citaOArray) ? ($citaOArray['tipo_mercancia'] ?? $citaOArray['categoria_sugerida'] ?? '') : ($citaOArray->tipo_mercancia ?? '');
        $tipo = mb_strtolower(trim((string)$tipo));
        if (!empty($tipo)) {
            $keywords = ['pereceder', 'charcuter', 'carne', 'pescad', 'congelad', 'lacteo', 'embutid', 'pollo', 'avicol'];
            foreach ($keywords as $kw) {
                if (str_contains($tipo, $kw)) {
                    return true;
                }
            }
        }

        $numOc = is_array($citaOArray) ? ($citaOArray['numero_oc'] ?? '') : ($citaOArray->numero_oc ?? '');
        if (!empty($numOc)) {
            $ordenLimpia = preg_replace('/[^0-9]/', '', $numOc);
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
            $sync = DB::table('erp_ordenes_sync')
                ->whereIn('numero_oc', [$numOc, $ordenLimpia, $ordenPad, 'E' . $ordenPad])
                ->select('destino', 'categoria_sugerida')
                ->first();

            if ($sync) {
                $dest = trim((string)$sync->destino);
                if (str_starts_with($dest, '014') || str_starts_with($dest, '015')) {
                    return true;
                }

                $cat = mb_strtolower(trim((string)$sync->categoria_sugerida));
                $keywords = ['pereceder', 'charcuter', 'carne', 'congelad', 'pescad', 'lacteo', 'embutid', 'pollo', 'avicol'];
                foreach ($keywords as $kw) {
                    if (str_contains($cat, $kw)) {
                        return true;
                    }
                }
            }
        }

        return false;
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
        $tipoOperacion = $request->input('tipo_operacion', 'proveedor'); // 'proveedor' o 'traslado_interno'

        // Si el usuario autenticado es un galpón o Alfonso, forzar traslado interno
        $authUser = auth('web')->user();
        if ($authUser && ($authUser->es_galpon || $authUser->username === 'GALPON.ALFONSO')) {
            $tipoOperacion = 'traslado_interno';
        }

        // Regla: Traslado Interno -> 2 horas máximo (120 minutos)
        if ($tipoOperacion === 'traslado_interno' && $duracionMinutos > 120) {
            $duracionMinutos = 120;
        }

        // Horario laboral
        $horaInicio = 8;  // 8:00 AM
        $horaFin = 18;    // 6:00 PM (última reservación)
        $intervalo = 30;  // slots cada 30 minutos

        // Obtener citas ya reservadas para esa fecha (excluyendo la cita que se esté reprogramando si aplica)
        $citasQuery = DB::table('appointments')
            ->whereDate('fecha_cita', $fecha)
            ->whereIn('estatus', ['programada', 'en muelle']);

        $citaReprogramando = null;
        if ($request->filled('cita_id')) {
            $citaReprogramando = DB::table('appointments')->where('id', $request->input('cita_id'))->first();
            $citasQuery->where('id', '!=', $request->input('cita_id'));
        }

        $citasExistentes = $citasQuery->select('id', 'fecha_cita', 'muelle_asignado', 'numero_oc', 'duracion_minutos', 'tipo_mercancia')
            ->get();

        // Mapeo Real de Muelles por Sucursal (Fase 6)
        $configMuelles = [
            '0101' => ['0101'], // Hiper Suraki
            '0102' => ['0102'], // Deposito Gral
            '0111' => ['0111'], // Produccion
            '0115' => ['0115'], // Insumos
            '0140' => ['0140'], // Carnes y Perecederos
            '0141' => ['0141'], // Carnes y Perecederos
            '0150' => ['0150'], // Depósito Perecederos
            '0160' => ['0160'], // Andinka
            '0161' => ['0161'], // Andinka
            '0171' => ['0171'], // Sucursales
            '0180' => ['01', '02', '03', '04'], // Galpón Central
        ];

        $muelles = $configMuelles[$sucursal] ?? [$sucursal]; // Fallback usa el código de sucursal

        $carbonFecha = Carbon::parse($fecha);
        $esMiercoles = ($carbonFecha->dayOfWeek === Carbon::WEDNESDAY);
        $esSabado = ($carbonFecha->dayOfWeek === Carbon::SATURDAY);

        // Determinar si la solicitud actual corresponde a Perecederos (depósito aparte)
        $solicitudEsPerecederos = self::esPerecederosCita([
            'sucursal' => $sucursal ?: ($citaReprogramando->muelle_asignado ?? null),
            'numero_oc' => $request->input('numero_oc', $citaReprogramando->numero_oc ?? null),
            'tipo_mercancia' => $request->input('tipo_mercancia', $citaReprogramando->tipo_mercancia ?? null),
        ]);

        $slots = [];
        for ($h = $horaInicio; $h < $horaFin; $h++) {
            for ($m = 0; $m < 60; $m += $intervalo) {
                $horaStr = sprintf('%02d:%02d', $h, $m);
                $slotInicio = Carbon::parse("$fecha $horaStr");
                $slotFin = $slotInicio->copy()->addMinutes((int) $duracionMinutos);

                // No pasar de las 7 PM
                $limiteLaboral = Carbon::parse("$fecha 19:00");
                if ($slotFin->gt($limiteLaboral)) continue;

                // Verificación de bloqueos de horario (Hoja manuscrita y reglas de recepción)
                $bloqueadoPorHorario = false;
                $motivoBloqueo = null;

                if ($tipoOperacion !== 'traslado_interno') {
                    // 1. Sábado Bloqueado para Proveedores
                    if ($esSabado) {
                        $bloqueadoPorHorario = true;
                        $motivoBloqueo = 'Sábados bloqueados para recepción de proveedores.';
                    }
                    // 2. Miércoles: Proveedores hasta 11 AM (Después BLOQUEADO)
                    elseif ($esMiercoles && $h >= 11) {
                        $bloqueadoPorHorario = true;
                        $motivoBloqueo = 'Los días miércoles la recepción de proveedores externos es únicamente hasta las 11:00 AM.';
                    }
                } else {
                    // 3. Galpones / Traslado Interno: Miércoles a partir de las 2:00 PM (14:00)
                    if ($esMiercoles && $h < 14) {
                        $bloqueadoPorHorario = true;
                        $motivoBloqueo = 'Los días miércoles los traslados de galpones se reciben únicamente a partir de las 2:00 PM.';
                    }
                }

                // Regla de negocio:
                // - Recepción General (Unai y Juan): Regla estricta. Si la hora ya está ocupada por otra recepción general, no se puede agendar nadie más.
                // - Perecederos: Es un depósito aparte con su propia cava. No le quita horas a los demás proveedores ni se ve bloqueado por ellos.
                $citaSolapada = null;
                foreach ($citasExistentes as $cita) {
                    $citaEsPerecederos = self::esPerecederosCita($cita);

                    // Si uno es Perecederos y el otro es General, no compiten por el mismo andén
                    if ($solicitudEsPerecederos !== $citaEsPerecederos) {
                        continue;
                    }

                    $citaInicio = Carbon::parse($cita->fecha_cita);
                    $duracionReal = $cita->duracion_minutos ?? $duracionMinutos;
                    $citaFin = $citaInicio->copy()->addMinutes((int) $duracionReal);

                    // Hay solapamiento si: inicio < citaFin AND fin > citaInicio
                    if ($slotInicio->lt($citaFin) && $slotFin->gt($citaInicio)) {
                        if ($solicitudEsPerecederos) {
                            // En Perecederos se agenda normal (conflicto solo si es el mismo muelle)
                            $muellesEquivCita = self::getMuellesEquivalentes($cita->muelle_asignado);
                            $muellesEquivSolicitud = self::getMuellesEquivalentes($sucursal);
                            if (!empty(array_intersect($muellesEquivCita, $muellesEquivSolicitud))) {
                                $citaSolapada = $cita;
                                break;
                            }
                        } else {
                            // En recepción general: regla estricta compartida
                            $citaSolapada = $cita;
                            break;
                        }
                    }
                }

                $disponible = !$bloqueadoPorHorario && ($citaSolapada === null);

                $slots[] = [
                    'hora' => $horaStr,
                    'hora_formato' => $slotInicio->format('h:i A'),
                    'hora_fin' => $slotFin->format('h:i A'),
                    'disponible' => $disponible,
                    'bloqueado_horario' => $bloqueadoPorHorario,
                    'motivo_bloqueo' => $motivoBloqueo ?? ($citaSolapada ? 'Horario ya apartado por otra recepción' : null),
                    'muelles_libres' => $disponible ? 1 : 0,
                    'muelles' => $disponible ? $muelles : [],
                ];
            }
        }

        // Fechas disponibles (próximos días hábiles)
        // Regla: Excluir domingos siempre.
        // Regla: Sábado bloqueado para proveedores externos.
        $fechasDisponibles = [];
        for ($i = 0; $i < 14; $i++) {
            $d = Carbon::today()->addDays($i);
            if ($d->dayOfWeek === Carbon::SUNDAY) continue;
            if ($tipoOperacion !== 'traslado_interno' && $d->dayOfWeek === Carbon::SATURDAY) continue;

            $fechasDisponibles[] = [
                'fecha' => $d->format('Y-m-d'),
                'dia' => $d->isoFormat('ddd'),
                'dia_largo' => $d->isoFormat('dddd D [de] MMMM'),
                'es_hoy' => $d->isToday(),
                'es_miercoles' => $d->dayOfWeek === Carbon::WEDNESDAY,
                'es_sabado' => $d->dayOfWeek === Carbon::SATURDAY,
            ];
            if (count($fechasDisponibles) >= 7) break;
        }

        return response()->json([
            'fecha' => $fecha,
            'slots' => $slots,
            'fechas_disponibles' => $fechasDisponibles,
            'duracion_minutos' => $duracionMinutos,
            'tipo_operacion' => $tipoOperacion,
        ]);
    }

    /**
     * Registrar una cita/reservación.
     */
    public function reservar(Request $request)
    {
        $isTrasladoInterno = $request->boolean('es_traslado_interno');
        $authUser = auth('web')->user();
        if ($authUser && ($authUser->es_galpon || $authUser->username === 'GALPON.ALFONSO')) {
            $isTrasladoInterno = true;
        }

        if ($isTrasladoInterno) {
            // Regla: Traslado Interno -> 2 horas máximo (120 minutos)
            $duracion = (int) $request->input('duracion_minutos', 60);
            if ($duracion > 120) {
                $duracion = 120;
            }

            // Generar o sanitizar código de Traslado Interno
            $numTraslado = trim($request->input('numero_oc', ''));
            if (empty($numTraslado)) {
                $numTraslado = 'TI-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            } elseif (!str_starts_with(strtoupper($numTraslado), 'TI-')) {
                $numTraslado = 'TI-' . strtoupper($numTraslado);
            }

            $galponOrigen = trim($request->input('galpon_origen', '')) ?: 'GALPÓN ALFONSO';
            $nombreProveedor = "{$galponOrigen} (TRASLADO INTERNO)";
            $rifProveedor = $request->input('rif_proveedor') ?: 'J-10715201';

            $validated = [
                'numero_oc' => $numTraslado,
                'proveedor' => $nombreProveedor,
                'rif_proveedor' => $rifProveedor,
                'fecha_cita' => $request->input('fecha_cita'),
                'muelle_asignado' => $request->input('muelle_asignado'),
                'duracion_minutos' => $duracion,
                'observaciones' => !empty($request->input('observaciones')) ? strip_tags($request->input('observaciones')) : 'Traslado Interno de Galpón',
            ];
        } else {
            $validated = $request->validate([
                'numero_oc' => 'required|string',
                'proveedor' => 'required|string',
                'rif_proveedor' => 'nullable|string',
                'fecha_cita' => 'required|date',
                'muelle_asignado' => 'required|string',
                'duracion_minutos' => 'required|integer',
                'observaciones' => 'nullable|string',
            ]);
            $validated['observaciones'] = !empty($validated['observaciones']) ? strip_tags($validated['observaciones']) : null;
            $duracion = $validated['duracion_minutos'];
        }

        $fechaCita = Carbon::parse($validated['fecha_cita']);
        $fechaFin = $fechaCita->copy()->addMinutes((int) $duracion);

        // Validar horario general
        if ($fechaCita->hour < 8 || $fechaCita->hour >= 18) {
            return response()->json(['error' => 'El horario de reservación es de 8:00 AM a 6:00 PM.'], 422);
        }

        // Validar que no sea domingo
        if ($fechaCita->dayOfWeek === Carbon::SUNDAY) {
            return response()->json(['error' => 'No se reciben reservaciones los domingos.'], 422);
        }

        // Validar bloqueos según tipo de operación
        if (!$isTrasladoInterno) {
            if ($fechaCita->dayOfWeek === Carbon::SATURDAY) {
                return response()->json(['error' => 'Los sábados están bloqueados para recepción de proveedores.'], 422);
            }
            if ($fechaCita->dayOfWeek === Carbon::WEDNESDAY && $fechaCita->hour >= 11) {
                return response()->json(['error' => 'Los días miércoles la recepción de proveedores externos es únicamente hasta las 11:00 AM.'], 422);
            }
        } else {
            // Galpones: Miércoles únicamente a partir de las 2:00 PM (14:00)
            if ($fechaCita->dayOfWeek === Carbon::WEDNESDAY && $fechaCita->hour < 14) {
                return response()->json(['error' => 'Los días miércoles los traslados de galpones se reciben únicamente a partir de las 2:00 PM.'], 422);
            }
        }

        // Determinar si la cita a agendar corresponde al depósito de Perecederos
        $tipoMercanciaInput = $request->input('tipo_mercancia') ?? ($isTrasladoInterno ? 'traslado_interno' : null);
        $solicitudEsPerecederos = self::esPerecederosCita([
            'muelle_asignado' => $validated['muelle_asignado'],
            'numero_oc' => $validated['numero_oc'],
            'tipo_mercancia' => $tipoMercanciaInput,
        ]);

        // Verificar que NO exista ninguna cita agendada en ese horario (separación de depósitos)
        $citasExistentes = DB::table('appointments')
            ->whereDate('fecha_cita', $fechaCita->format('Y-m-d'))
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->get();

        foreach ($citasExistentes as $cita) {
            $citaEsPerecederos = self::esPerecederosCita($cita);

            // Si uno es Perecederos y el otro es General, no compiten entre sí (depósitos independientes)
            if ($solicitudEsPerecederos !== $citaEsPerecederos) {
                continue;
            }

            $inicioExistente = Carbon::parse($cita->fecha_cita);
            $duracionExistente = $cita->duracion_minutos ?? 60;
            $finExistente = $inicioExistente->copy()->addMinutes((int) $duracionExistente);

            // Hay solapamiento si: inicio < citaFin AND fin > citaInicio
            if ($fechaCita->lt($finExistente) && $fechaFin->gt($inicioExistente)) {
                if ($solicitudEsPerecederos) {
                    $muellesEquivCita = self::getMuellesEquivalentes($cita->muelle_asignado);
                    $muellesEquivSolicitud = self::getMuellesEquivalentes($validated['muelle_asignado']);
                    if (!empty(array_intersect($muellesEquivCita, $muellesEquivSolicitud))) {
                        return response()->json(['error' => 'Conflicto de horario en Perecederos: Ya existe una cita agendada en este muelle de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A') . " (Orden: {$cita->numero_oc})."], 422);
                    }
                } else {
                    return response()->json(['error' => 'Conflicto de horario: Ya existe una cita agendada de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A') . " (Orden: {$cita->numero_oc}). No es posible agendar más de una recepción simultánea."], 422);
                }
            }
        }

        // Verificar que la OC no tenga ya una cita activa
        $citaExistente = DB::table('appointments')
            ->where('numero_oc', $validated['numero_oc'])
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->first();

        if ($citaExistente) {
            return response()->json(['error' => 'Esta orden o traslado ya tiene una cita programada.'], 422);
        }

        $id = DB::table('appointments')->insertGetId([
            'numero_oc' => $validated['numero_oc'],
            'proveedor' => $validated['proveedor'],
            'rif_proveedor' => $validated['rif_proveedor'] ?? null,
            'fecha_cita' => $fechaCita,
            'muelle_asignado' => $validated['muelle_asignado'],
            'duracion_minutos' => $duracion,
            'estatus' => 'programada',
            'es_traslado_interno' => $isTrasladoInterno,
            'galpon_origen' => $isTrasladoInterno ? ($validated['galpon_origen'] ?? 'GALPÓN ALFONSO') : null,
            'tipo_mercancia' => $tipoMercanciaInput,
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
            action: $isTrasladoInterno ? 'Agendar Traslado Interno' : 'Agendar Cita',
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
            'fecha_oc' => now(),
            'fecha_recepcion' => $fechaCita,
            'status_erp' => $isTrasladoInterno ? 'TRASLADO' : 'CITA',
        ]);

        if ($isTrasladoInterno) {
            return response()->json([
                'message' => 'Traslado interno agendado exitosamente.',
                'proveedor_registrado' => true,
                'contactos' => [],
                'cita' => [
                    'id' => $id,
                    'numero_oc' => $validated['numero_oc'],
                    'fecha' => $fechaCita->isoFormat('dddd D [de] MMMM [de] YYYY'),
                    'hora' => $fechaCita->format('h:i A'),
                    'hora_fin' => $fechaFin->format('h:i A'),
                    'muelle' => $validated['muelle_asignado'],
                    'duracion_minutos' => $duracion,
                    'es_traslado_interno' => true,
                ],
            ], 201);
        }

        // Enviar Push Notification (defensivo)
        try {
            $receptores = \App\Models\User::whereIn('role', ['receptor', 'admin'])->get();
            $comprador = null;
            $syncRow = DB::table('erp_ordenes_sync')->where('numero_oc', $validated['numero_oc'])->first();
            if ($syncRow && $syncRow->habilitada_por_user_id) {
                $comprador = \App\Models\User::find($syncRow->habilitada_por_user_id);
                if ($comprador && !$receptores->contains('id', $comprador->id)) {
                    $receptores->push($comprador);
                }
            }
            \Illuminate\Support\Facades\Notification::send($receptores, new \App\Notifications\PushNotification(
                'Nueva Cita Programada', 
                "{$validated['proveedor']} ha agendado cita para la orden {$validated['numero_oc']} el " . $fechaCita->format('d/m/Y h:i A'),
                null,
                '/dashboard'
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Push notification no enviada (reservar): ' . $e->getMessage());
        }

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

        $isTrasladoInterno = filter_var($cita->es_traslado_interno ?? false, FILTER_VALIDATE_BOOLEAN) || str_starts_with($cita->numero_oc ?? '', 'TI-');
        if (!$isTrasladoInterno) {
            if ($fechaCita->dayOfWeek === Carbon::SATURDAY) {
                return response()->json(['error' => 'Los sábados están bloqueados para recepción de proveedores.'], 422);
            }
            if ($fechaCita->dayOfWeek === Carbon::WEDNESDAY && $fechaCita->hour >= 11) {
                return response()->json(['error' => 'Los días miércoles la recepción de proveedores externos es únicamente hasta las 11:00 AM.'], 422);
            }
        } else {
            if ($fechaCita->dayOfWeek === Carbon::WEDNESDAY && $fechaCita->hour < 14) {
                return response()->json(['error' => 'Los días miércoles los traslados de galpones se reciben únicamente a partir de las 2:00 PM.'], 422);
            }
        }

        // Determinar si la cita que se reprograma corresponde al depósito de Perecederos
        $solicitudEsPerecederos = self::esPerecederosCita([
            'muelle_asignado' => $validated['muelle_asignado'],
            'numero_oc' => $cita->numero_oc,
            'tipo_mercancia' => $cita->tipo_mercancia,
        ]);

        // Verificar que el horario esté libre, respetando la separación de depósitos
        $citasExistentes = DB::table('appointments')
            ->where('id', '!=', $id)
            ->whereDate('fecha_cita', $fechaCita->format('Y-m-d'))
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->get();

        foreach ($citasExistentes as $c) {
            $cEsPerecederos = self::esPerecederosCita($c);

            // Depósitos separados: Perecederos vs General no compiten entre sí
            if ($solicitudEsPerecederos !== $cEsPerecederos) {
                continue;
            }

            $inicioExistente = Carbon::parse($c->fecha_cita);
            $duracionExistente = $c->duracion_minutos ?? 60;
            $finExistente = $inicioExistente->copy()->addMinutes((int) $duracionExistente);

            // Hay solapamiento si: inicio < citaFin AND fin > citaInicio
            if ($fechaCita->lt($finExistente) && $fechaFin->gt($inicioExistente)) {
                if ($solicitudEsPerecederos) {
                    $muellesEquivCita = self::getMuellesEquivalentes($c->muelle_asignado);
                    $muellesEquivSolicitud = self::getMuellesEquivalentes($validated['muelle_asignado']);
                    if (!empty(array_intersect($muellesEquivCita, $muellesEquivSolicitud))) {
                        return response()->json(['error' => 'Conflicto de horario en Perecederos: Ya existe una cita agendada en este muelle de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A') . " (Orden: {$c->numero_oc})."], 422);
                    }
                } else {
                    return response()->json(['error' => 'Conflicto de horario: Ya existe una cita agendada de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A') . " (Orden: {$c->numero_oc}). No es posible agendar más de una recepción simultánea."], 422);
                }
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

        // Enviar Push Notification (defensivo)
        try {
            $usersToNotify = \App\Models\User::whereIn('role', ['admin', 'receptor'])->get();
            if ($cita->user_id) {
                $proveedorUser = \App\Models\User::find($cita->user_id);
                if ($proveedorUser && !$usersToNotify->contains('id', $proveedorUser->id)) {
                    $usersToNotify->push($proveedorUser);
                }
            }
            $syncRow = DB::table('erp_ordenes_sync')->where('numero_oc', $cita->numero_oc)->first();
            if ($syncRow && $syncRow->habilitada_por_user_id) {
                $compradorUser = \App\Models\User::find($syncRow->habilitada_por_user_id);
                if ($compradorUser && !$usersToNotify->contains('id', $compradorUser->id)) {
                    $usersToNotify->push($compradorUser);
                }
            }
            if ($usersToNotify->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($usersToNotify, new \App\Notifications\PushNotification(
                    'Cita Reprogramada',
                    "La cita de la orden {$cita->numero_oc} ha sido reprogramada al " . $fechaCita->format('d/m/Y h:i A') . "\nMotivo: {$validated['motivo']}",
                    null,
                    '/dashboard'
                ));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Push notification no enviada (reprogramar): ' . $e->getMessage());
        }

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

        $authUser = auth('web')->user();
        $isTestAuthorized = $authUser && ($authUser->role === 'admin' || in_array($authUser->username, ['Compras.Juan', 'PROV.PRUEBA']));
        if (!$isTestAuthorized) {
            $query->where(function($q) {
                $q->where('appointments.numero_oc', 'not like', 'TEST-%')
                  ->where(function($sub) {
                      $sub->whereNull('appointments.rif_proveedor')
                          ->orWhereNotIn('appointments.rif_proveedor', ['J-999999999', 'J999999999']);
                  });
            });
        }

        if (auth('web')->user()->role === 'proveedor') {
            $user = auth('web')->user();
            $rawRif = $user->rif ?: $user->username;
            $parts = explode('.', $rawRif);
            $baseRif = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($parts[0] ?? '')));

            $isGalpon = $user->es_galpon || $user->username === 'GALPON.ALFONSO';

            $query->where(function($q) use ($user, $baseRif, $isGalpon) {
                $q->where('appointments.rif_proveedor', $user->username)
                  ->orWhere('appointments.rif_proveedor', $user->rif)
                  ->orWhere('appointments.rif_proveedor', 'LIKE', '%' . $baseRif . '%')
                  ->orWhere('appointments.user_id', $user->id);
                if ($isGalpon) {
                    $q->orWhere('appointments.es_traslado_interno', true)
                      ->orWhere('appointments.numero_oc', 'LIKE', 'TI-%');
                }
            });
        }

        $citas = $query->get();

        $userRole = auth('web')->check() ? auth('web')->user()->role : 'guest';

        $citas = $citas->map(function ($cita) use ($userRole) {
            $cita->es_traslado_interno = filter_var($cita->es_traslado_interno ?? false, FILTER_VALIDATE_BOOLEAN);

            $cita->bloqueado_para_comprador = \App\Models\SystemAuditLog::where('auditable_id', $cita->id)
                ->where('auditable_type', 'Appointment')
                ->where('user_role', 'receptor')
                ->exists();
                
            // Buscar el verdadero comprador de la orden (priorizando el ERP)
            $ordenLimpia = preg_replace('/^E/i', '', $cita->numero_oc);
            $ordenSinCeros = ltrim($ordenLimpia, '0');
            $ordenPad = str_pad($ordenSinCeros, 9, '0', STR_PAD_LEFT);
            $posiblesOcs = array_values(array_unique(array_filter([
                $cita->numero_oc, 
                $ordenLimpia, 
                $ordenSinCeros, 
                $ordenPad, 
                'E' . $ordenSinCeros, 
                'E' . $ordenPad
            ])));
            
            $syncRow = DB::table('erp_ordenes_sync')
                ->whereIn('numero_oc', $posiblesOcs)
                ->orderBy('updated_at', 'desc')
                ->first();
                
            $cita->registrado_por_nombre = $this->resolverCompradorReal($cita->numero_oc, $syncRow, $cita);
            
            // Fecha en que hizo el envío el comprador
            $emailLog = DB::table('email_logs')
                ->whereIn('numero_oc', $posiblesOcs)
                ->where('tipo_evento', 'odc_habilitada')
                ->orderBy('created_at', 'desc')
                ->first();

            // Resolución robusta de la fecha en que el comprador envió/creó la orden:
            // 1. Log de envío de correo de ODC habilitada
            // 2. Si la orden fue habilitada en el sistema (habilitada o ya agendada con usuario comprador)
            // 3. Fecha de emisión de la ODC en el ERP (fecha_emision o fecha_odc)
            // 4. Fecha de sincronización o creación de la orden
            $fechaEnvio = null;
            if ($emailLog && !empty($emailLog->created_at)) {
                $fechaEnvio = $emailLog->created_at;
            } elseif ($syncRow) {
                $srCreated = $syncRow->created_at ?? null;
                $srUpdated = $syncRow->updated_at ?? null;
                $fueHabilitada = in_array($syncRow->estatus_habilitacion ?? null, ['habilitada', 'agendada']) || !empty($syncRow->habilitada_por_user_id ?? null);
                if ($fueHabilitada && !empty($srUpdated) && $srUpdated != $srCreated) {
                    $fechaEnvio = $srUpdated;
                } elseif (!empty($syncRow->fecha_emision ?? null)) {
                    $fechaEnvio = $syncRow->fecha_emision;
                } elseif (!empty($srCreated)) {
                    $fechaEnvio = $srCreated;
                }
            }
            
            // Si aún no se tiene fecha_envio, buscar en el resumen_json de la orden
            if (!$fechaEnvio && $syncRow && !empty($syncRow->resumen_json)) {
                $resumenObj = json_decode($syncRow->resumen_json, true);
                if (is_array($resumenObj)) {
                    $fechaEnvio = $resumenObj['fecha_odc'] ?? $resumenObj['Fecha_Emision'] ?? $resumenObj['fecha_emision'] ?? null;
                }
            }

            // Fallback directo a atributos de la cita si existen
            if (!$fechaEnvio) {
                $fechaEnvio = $cita->fecha_odc ?? $cita->fecha_emision ?? null;
            }

            $cita->fecha_envio_comprador = $fechaEnvio;
            
            // Hora en que registró la cita el proveedor
            $cita->fecha_registro_cita = $cita->created_at;

            // Fecha y hora en que fue marcada como completada en muelle
            if ($cita->estatus === 'finalizada') {
                if (empty($cita->fecha_completada)) {
                    $routeLog = DB::table('appointment_route_logs')
                        ->where('numero_oc', $cita->numero_oc)
                        ->where('estatus_nuevo', 'finalizada')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $cita->fecha_completada = $routeLog ? $routeLog->created_at : $cita->updated_at;
                    $cita->completada_por_nombre = $routeLog ? $routeLog->user_name : ($cita->completada_por_nombre ?? 'Recepción');
                }
            } else {
                $cita->fecha_completada = null;
                $cita->completada_por_nombre = null;
            }
            
            // Convertir factura_path a URL(s) pública(s)
            $urlsFacturas = [];
            if (!empty($cita->factura_path)) {
                $rawPath = trim($cita->factura_path);
                if (str_starts_with($rawPath, '[')) {
                    $decoded = json_decode($rawPath, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $idx => $p) {
                            $urlsFacturas[] = [
                                'url' => \Illuminate\Support\Facades\Storage::url($p),
                                'nombre' => 'Factura ' . ($idx + 1),
                                'path' => $p,
                            ];
                        }
                    }
                }
                if (empty($urlsFacturas)) {
                    $urlsFacturas[] = [
                        'url' => \Illuminate\Support\Facades\Storage::url($rawPath),
                        'nombre' => 'Factura 1',
                        'path' => $rawPath,
                    ];
                }
                $cita->factura_url = $urlsFacturas[0]['url'];
                $cita->facturas_urls = $urlsFacturas;
            } else {
                $cita->factura_url = null;
                $cita->facturas_urls = [];
            }

            // OCR Conciliación Info
            $ocrRow = null;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('invoice_ocr_analyses')) {
                    $ocrRow = DB::table('invoice_ocr_analyses')->where('appointment_id', $cita->id)->first();
                }
            } catch (\Throwable $e) {}

            if ($ocrRow) {
                $cita->ocr_estatus = $ocrRow->estatus_conciliacion;
                $cita->ocr_resumen = $ocrRow->resumen_discrepancias;
                $cita->ocr_diferencia = $ocrRow->diferencia_total;
            } else {
                $cita->ocr_estatus = !empty($cita->factura_path) ? 'pendiente_analisis' : 'sin_factura';
                $cita->ocr_resumen = null;
                $cita->ocr_diferencia = null;
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

        $compradoresBase = collect([
            ['id' => 176, 'name' => 'ALEJANDRO PEÑA'],
            ['id' => 27,  'name' => 'KARYNELL ARAQUE'],
            ['id' => 19,  'name' => 'Dugarte Yoliys'],
            ['id' => 166, 'name' => 'MARIA JOSE CONTRERAS'],
        ]);

        $nombresEnCitas = $citas->pluck('registrado_por_nombre')->filter()->unique();
        foreach ($nombresEnCitas as $idx => $nombreCita) {
            if (!$compradoresBase->contains('name', $nombreCita)) {
                $compradoresBase->push(['id' => 1000 + $idx, 'name' => $nombreCita]);
            }
        }

        // Calcular contadores reales de activas y finalizadas
        $countQuery = DB::table('appointments');
        if (auth('web')->user()->role === 'proveedor') {
            $user = auth('web')->user();
            $rawRif = $user->rif ?: $user->username;
            $parts = explode('.', $rawRif);
            $baseRif = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($parts[0] ?? '')));
            $isGalpon = $user->es_galpon || $user->username === 'GALPON.ALFONSO';

            $countQuery->where(function($q) use ($user, $baseRif, $isGalpon) {
                $q->where('appointments.rif_proveedor', $user->username)
                  ->orWhere('appointments.rif_proveedor', $user->rif)
                  ->orWhere('appointments.rif_proveedor', 'LIKE', '%' . $baseRif . '%')
                  ->orWhere('appointments.user_id', $user->id);
                if ($isGalpon) {
                    $q->orWhere('appointments.es_traslado_interno', true)
                      ->orWhere('appointments.numero_oc', 'LIKE', 'TI-%');
                }
            });
        }
        if (!$isTestAuthorized) {
            $countQuery->where(function($q) {
                $q->where('appointments.numero_oc', 'not like', 'TEST-%')
                  ->where(function($sub) {
                      $sub->whereNull('appointments.rif_proveedor')
                          ->orWhereNotIn('appointments.rif_proveedor', ['J-999999999', 'J999999999']);
                  });
            });
        }
        $countActivas = (clone $countQuery)->whereIn('appointments.estatus', ['programada', 'en muelle'])->count();
        $countFinalizadas = (clone $countQuery)->where('appointments.estatus', 'finalizada')->count();

        return response()->json([
            'citas' => $citas,
            'compradores' => $compradoresBase,
            'counts' => [
                'activas' => $countActivas,
                'finalizadas' => $countFinalizadas,
            ]
        ]);
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

        // Enviar Push Notification (defensivo)
        try {
            $usersToNotify = collect();
            if ($cita->user_id) {
                $proveedorUser = \App\Models\User::find($cita->user_id);
                if ($proveedorUser) $usersToNotify->push($proveedorUser);
            }
            $syncRow = DB::table('erp_ordenes_sync')->where('numero_oc', $cita->numero_oc)->first();
            if ($syncRow && $syncRow->habilitada_por_user_id) {
                $compradorUser = \App\Models\User::find($syncRow->habilitada_por_user_id);
                if ($compradorUser) $usersToNotify->push($compradorUser);
            }
            if ($usersToNotify->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($usersToNotify, new \App\Notifications\PushNotification(
                    'Cita Cancelada',
                    "La cita de la orden {$cita->numero_oc} ha sido cancelada.\nMotivo: {$validated['motivo']}",
                    null,
                    '/dashboard'
                ));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Push notification no enviada (cancelar): ' . $e->getMessage());
        }

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

        $userName = auth('web')->user() ? auth('web')->user()->name : 'Recepción';
        $userId = auth('web')->id();
        $now = now();

        DB::table('appointments')->where('id', $id)->update([
            'estatus' => 'finalizada',
            'fecha_completada' => $now,
            'completada_por_nombre' => $userName,
            'completada_por_user_id' => $userId,
            'updated_at' => $now,
        ]);

        // Registrar en Bitácora de Rutas Logísticas
        \App\Models\AppointmentRouteLog::create([
            'numero_oc' => $cita->numero_oc,
            'estatus_anterior' => $cita->estatus,
            'estatus_nuevo' => 'finalizada',
            'user_id' => $userId ?? 1,
            'user_name' => $userName,
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
        
        // Push Notification al Comprador (defensivo)
        try {
            if (isset($comprador) && $comprador) {
                $compradorModel = \App\Models\User::find($comprador->id);
                if ($compradorModel) {
                    \Illuminate\Support\Facades\Notification::send($compradorModel, new \App\Notifications\PushNotification(
                        'Mercancía Recibida',
                        "La cita de la orden {$cita->numero_oc} ha sido finalizada y la mercancía recibida por Recepción.",
                        null,
                        '/reservar-cita'
                    ));
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Push notification no enviada (finalizar): ' . $e->getMessage());
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
        try {
            $validated = $request->validate([
                'numero_oc' => 'required|string',
                'proveedor' => 'required|string',
                'rif' => 'required|string',
                'contacto_id' => 'nullable|integer',
                'email' => 'required|email',
                'emails_adicionales' => 'nullable|array|max:2',
                'emails_adicionales.*' => 'nullable|email',
                'telefono' => 'required|string',
                'asesor' => 'required|string',
            ]);

            $emailVal = trim($validated['email']);
            $rifVal = trim($validated['rif']);
            $rifClean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $rifVal));
            $asesorVal = $validated['asesor'] ?: $validated['proveedor'];

            // Procesar correos adicionales (hasta 2 adicionales, total máx 3)
            $emailsAdicionales = [];
            if (!empty($validated['emails_adicionales']) && is_array($validated['emails_adicionales'])) {
                foreach ($validated['emails_adicionales'] as $emAd) {
                    $emAd = trim(strtolower((string)$emAd));
                    if ($emAd !== '' && filter_var($emAd, FILTER_VALIDATE_EMAIL) && strtolower($emAd) !== strtolower($emailVal) && !in_array($emAd, $emailsAdicionales)) {
                        $emailsAdicionales[] = $emAd;
                    }
                }
                $emailsAdicionales = array_slice($emailsAdicionales, 0, 2);
            }
            $todosLosCorreos = array_values(array_unique(array_merge([$emailVal], $emailsAdicionales)));

            // Marcar ODC como habilitada en erp_ordenes_sync (si existe allí)
            $ordenLimpia = preg_replace('/^E/i', '', $validated['numero_oc']);
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
            $ordenConE = 'E' . $ordenPad;
            
            $syncRow = DB::table('erp_ordenes_sync')
                ->whereIn('numero_oc', [$validated['numero_oc'], $ordenLimpia, $ordenPad, $ordenConE])
                ->first();

            if ($syncRow) {
                // Prevenir doble habilitación únicamente si ya tiene cita agendada activa
                if ($syncRow->estatus_habilitacion === 'habilitada') {
                    $tieneCita = DB::table('appointments')
                        ->where('numero_oc', $syncRow->numero_oc)
                        ->whereIn('estatus', ['programada', 'en muelle'])
                        ->exists();

                    if ($tieneCita) {
                        return response()->json(['error' => 'Esta orden de compra ya tiene una cita agendada activa.'], 422);
                    }
                    // Si la orden ya estaba habilitada pero no tiene cita agendada, se permite re-enviar la notificación al correo del vendedor especificado
                }

                $currentResumen = json_decode($syncRow->resumen_json, true) ?? [];
                $currentResumen['Codigo_Proveedor'] = $validated['rif'];
                $currentResumen['emails_notificados'] = $todosLosCorreos;
                
                DB::table('erp_ordenes_sync')->where('numero_oc', $syncRow->numero_oc)->update([
                    'estatus_habilitacion' => 'habilitada',
                    'habilitada_por_user_id' => auth()->id() ?? 1,
                    'rif_proveedor' => $validated['rif'],
                    'resumen_json' => json_encode($currentResumen)
                ]);
            } else {
                DB::table('erp_ordenes_sync')->insert([
                    'numero_oc' => $validated['numero_oc'],
                    'proveedor' => $validated['proveedor'],
                    'rif_proveedor' => $validated['rif'],
                    'estatus_habilitacion' => 'habilitada',
                    'habilitada_por_user_id' => auth()->id() ?? 1,
                    'resumen_json' => json_encode([
                        'Codigo_Proveedor' => $validated['rif'],
                        'emails_notificados' => $todosLosCorreos
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Verificar si el proveedor ya tiene cuenta registrada o crearla para persistir el correo
            // 1. Buscar si ya existe un usuario proveedor con este correo exacto
            $proveedorUser = User::where('role', 'proveedor')->where('email', $emailVal)->first();

            // 2. Si no existe por correo, buscar si hay un usuario proveedor para este RIF exacto (c_codproveed)
            if (!$proveedorUser) {
                $proveedorUser = User::where('role', 'proveedor')
                    ->where(function($q) use ($rifVal, $rifClean) {
                        $q->whereIn('username', [$rifVal, $rifClean])
                          ->orWhereIn('rif', [$rifVal, $rifClean]);
                    })
                    ->first();

                // Si la cuenta encontrada tiene un correo configurado DIFERENTE al ingresado,
                // y no es un dummy @proveedor.suraki.net, significa que es un vendedor u otro departamento distinto.
                if ($proveedorUser && !empty($proveedorUser->email) && !str_contains($proveedorUser->email, '@proveedor.suraki.net') && strtolower(trim($proveedorUser->email)) !== strtolower($emailVal)) {
                    $proveedorUser = null; // Se creará una cuenta multiusuario independiente para este vendedor
                }
            }

            if (!$proveedorUser) {
                $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $asesorVal));
                $userSlug = $rifVal;
                
                // Si el username de RIF principal ya existe para otro vendedor del mismo RIF, asignamos slug diferenciador
                if (User::where('username', $userSlug)->exists()) {
                    $userSlug = $rifVal . ($cleanName ? '.' . $cleanName : '.' . rand(10, 99));
                    if (User::where('username', $userSlug)->exists()) {
                        $userSlug = $userSlug . rand(10, 99);
                    }
                }

                $proveedorUser = User::create([
                    'name' => $asesorVal,
                    'username' => $userSlug,
                    'rif' => $rifVal,
                    'email' => $emailVal,
                    'role' => 'proveedor',
                    'password' => \Illuminate\Support\Facades\Hash::make($rifVal), // Contraseña temporal = RIF
                ]);
            } else {
                if (empty($proveedorUser->rif)) {
                    $proveedorUser->rif = $rifVal;
                }
                if (empty($proveedorUser->email) || str_contains($proveedorUser->email, '@proveedor.suraki.net')) {
                    $proveedorUser->email = $emailVal;
                }
                $proveedorUser->save();
            }

            // Registrar/actualizar contacto principal para persistir el correo y teléfono del proveedor
            \App\Models\ProveedorContacto::updateOrCreate(
                [
                    'user_id' => $proveedorUser->id,
                    'email' => $emailVal,
                ],
                [
                    'nombre' => $asesorVal,
                    'telefono' => $validated['telefono'] ?: '0000000000',
                ]
            );

            // Registrar/actualizar contactos adicionales si fueron especificados
            foreach ($emailsAdicionales as $emAd) {
                \App\Models\ProveedorContacto::updateOrCreate(
                    [
                        'user_id' => $proveedorUser->id,
                        'email' => $emAd,
                    ],
                    [
                        'nombre' => $asesorVal . ' (Copia)',
                        'telefono' => $validated['telefono'] ?: '0000000000',
                    ]
                );
            }

            $yaRegistrado = false;
            if ($proveedorUser && !empty($proveedorUser->password)) {
                // Si la clave es su RIF, se considera que aún no ha completado el registro
                if (!\Illuminate\Support\Facades\Hash::check($validated['rif'], $proveedorUser->password)) {
                    $yaRegistrado = true;
                }
            }

            // Enviar el correo de notificación al correo principal
            $infoCorreo = (object)[
                'numero_oc' => $validated['numero_oc'],
                'proveedor' => $validated['proveedor'],
                'username' => $proveedorUser->username,
                'email_destino' => $emailVal,
                'vendedor_nombre' => $asesorVal,
                'comprador_nombre' => auth()->user() ? auth()->user()->name : 'Comprador',
            ];

            $emailSuccess = true;
            $emailError = null;

            try {
                if ($yaRegistrado) {
                    \Illuminate\Support\Facades\Mail::to($emailVal)->send(new \App\Mail\OdcHabilitadaRegistrado($infoCorreo));
                } else {
                    \Illuminate\Support\Facades\Mail::to($emailVal)->send(new \App\Mail\OdcHabilitada($infoCorreo));
                }
                try {
                    \App\Models\EmailLog::create([
                        'numero_oc' => $validated['numero_oc'],
                        'proveedor' => $validated['proveedor'],
                        'email_destino' => $emailVal,
                        'vendedor_nombre' => $asesorVal,
                        'tipo_evento' => 'odc_habilitada',
                        'estatus' => 'exitoso',
                    ]);
                } catch (\Exception $e) {}
            } catch (\Exception $e) {
                $emailSuccess = false;
                $emailError = $e->getMessage();
                \Illuminate\Support\Facades\Log::error('Error enviando correo de ODC habilitada: ' . $e->getMessage());
                try {
                    \App\Models\EmailLog::create([
                        'numero_oc' => $validated['numero_oc'],
                        'proveedor' => $validated['proveedor'],
                        'email_destino' => $emailVal,
                        'vendedor_nombre' => $asesorVal,
                        'tipo_evento' => 'odc_habilitada',
                        'estatus' => 'error',
                        'error_mensaje' => $e->getMessage(),
                    ]);
                } catch (\Exception $e2) {}
            }

            // Enviar a correos adicionales (hasta 2) y registrar cada uno en EmailLog
            foreach ($emailsAdicionales as $emAd) {
                $infoCorreoAd = (object)[
                    'numero_oc' => $validated['numero_oc'],
                    'proveedor' => $validated['proveedor'],
                    'username' => $proveedorUser->username,
                    'email_destino' => $emAd,
                    'vendedor_nombre' => $asesorVal,
                    'comprador_nombre' => auth()->user() ? auth()->user()->name : 'Comprador',
                ];
                try {
                    if ($yaRegistrado) {
                        \Illuminate\Support\Facades\Mail::to($emAd)->send(new \App\Mail\OdcHabilitadaRegistrado($infoCorreoAd));
                    } else {
                        \Illuminate\Support\Facades\Mail::to($emAd)->send(new \App\Mail\OdcHabilitada($infoCorreoAd));
                    }
                    try {
                        \App\Models\EmailLog::create([
                            'numero_oc' => $validated['numero_oc'],
                            'proveedor' => $validated['proveedor'],
                            'email_destino' => $emAd,
                            'vendedor_nombre' => $asesorVal . ' (Copia)',
                            'tipo_evento' => 'odc_habilitada',
                            'estatus' => 'exitoso',
                        ]);
                    } catch (\Exception $eLog) {}
                } catch (\Exception $eAd) {
                    \Illuminate\Support\Facades\Log::error("Error enviando correo adicional ({$emAd}) de ODC habilitada: " . $eAd->getMessage());
                    try {
                        \App\Models\EmailLog::create([
                            'numero_oc' => $validated['numero_oc'],
                            'proveedor' => $validated['proveedor'],
                            'email_destino' => $emAd,
                            'vendedor_nombre' => $asesorVal . ' (Copia)',
                            'tipo_evento' => 'odc_habilitada',
                            'estatus' => 'error',
                            'error_mensaje' => $eAd->getMessage(),
                        ]);
                    } catch (\Exception $eLog2) {}
                }
            }

            // Push Notification al Proveedor (defensivo)
            try {
                if ($proveedorUser) {
                    \Illuminate\Support\Facades\Notification::send($proveedorUser, new \App\Notifications\PushNotification(
                        'Orden Habilitada',
                        "La orden {$validated['numero_oc']} ha sido habilitada para agendar cita. Ingrese al sistema para reservar su muelle.",
                        null,
                        '/reservar-cita'
                    ));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Push notification no enviada (habilitarOdc): ' . $e->getMessage());
            }

            // --- CREAR NOTIFICACIÓN EN LA CAMPANITA PARA EL PROVEEDOR Y RECEPCIÓN ---
            try {
                if ($proveedorUser) {
                    \App\Models\Notificacion::create([
                        'numero_oc' => $validated['numero_oc'],
                        'proveedor' => $validated['proveedor'],
                        'tipo' => 'odc_habilitada',
                        'fecha_oc' => now(),
                        'fecha_recepcion' => null,
                        'status_erp' => 'HABILITADA',
                        'leida' => false,
                        'target_user_id' => $proveedorUser->id,
                    ]);
                }
                \App\Models\Notificacion::create([
                    'numero_oc' => $validated['numero_oc'],
                    'proveedor' => $validated['proveedor'],
                    'tipo' => 'odc_habilitada',
                    'fecha_oc' => now(),
                    'fecha_recepcion' => null,
                    'status_erp' => 'HABILITADA',
                    'leida' => false,
                    'target_user_id' => null,
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Notificación campanita no creada: ' . $e->getMessage());
            }

            $appBaseUrl = rtrim(config('app.url') ?? '', '/');
            if (empty($appBaseUrl) || str_contains($appBaseUrl, '.test') || str_contains($appBaseUrl, 'localhost') || str_contains($appBaseUrl, '127.0.0.1') || str_contains($appBaseUrl, 'logistica.suraki.net')) {
                $appBaseUrl = 'https://citsur.suraki.net';
            }

            $linkAcceso = $yaRegistrado 
                ? $appBaseUrl . '/login' 
                : $appBaseUrl . '/setup-proveedor?rif=' . urlencode($proveedorUser->username) . '&email=' . urlencode($emailVal) . '&name=' . urlencode($asesorVal ?? $validated['proveedor']);

            if (!$emailSuccess) {
                return response()->json([
                    'message' => 'Orden habilitada, pero falló el envío del correo: ' . $emailError,
                    'proveedor_registrado' => $yaRegistrado,
                    'link_acceso' => $linkAcceso,
                    'email_destino' => $emailVal,
                    'emails_adicionales' => $emailsAdicionales,
                ], 206); // 206 Partial Content indicates partial success
            }
            
            return response()->json([
                'message' => 'Orden habilitada correctamente.',
                'proveedor_registrado' => $yaRegistrado,
                'link_acceso' => $linkAcceso,
                'email_destino' => $emailVal,
                'emails_adicionales' => $emailsAdicionales,
            ]);
            
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error interno en el código: " . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    /**
     * Eliminar el estado de habilitación de una orden y devolverla a pendiente
     */
    public function deshabilitarOdc($numero_oc)
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Acceso denegado. Sólo administradores.'], 403);
        }

        $ordenLimpia = preg_replace('/^E/i', '', $numero_oc);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;

        $syncRow = DB::table('erp_ordenes_sync')
            ->whereIn('numero_oc', [$numero_oc, $ordenLimpia, $ordenPad, $ordenConE])
            ->first();

        if (!$syncRow) {
            return response()->json(['error' => 'La orden no se encuentra en estado habilitada ni agendada.'], 404);
        }

        $tieneCita = DB::table('appointments')
            ->whereIn('numero_oc', [$syncRow->numero_oc, $numero_oc, $ordenLimpia, $ordenPad])
            ->whereIn('estatus', ['programada', 'en muelle', 'finalizada'])
            ->exists();

        if ($tieneCita) {
            return response()->json(['error' => 'No se puede restablecer porque la orden ya tiene una cita asociada.'], 422);
        }

        // Obtener el RIF del proveedor de la orden para intentar borrar su usuario
        $resumen = json_decode($syncRow->resumen_json, true) ?? [];
        $rifProveedor = $resumen['Codigo_Proveedor'] ?? null;

        // En lugar de borrar la orden, se devuelve a estado pendiente y se resetean las marcas de habilitación
        DB::table('erp_ordenes_sync')
            ->where('numero_oc', $syncRow->numero_oc)
            ->update([
                'estatus_habilitacion' => 'pendiente',
                'habilitada_por_user_id' => null,
                'rif_proveedor' => null,
                'updated_at' => now(),
            ]);

        if ($rifProveedor) {
            $limpiarRif = function($val) {
                return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($val ?? '')));
            };
            $rifLimpio = $limpiarRif($rifProveedor);

            // Verificar si este proveedor tiene otras órdenes activas
            $otrasOrdenes = DB::table('erp_ordenes_sync')
                ->where('resumen_json', 'like', '%' . $rifProveedor . '%')
                ->exists();

            if (!$otrasOrdenes) {
                // Si no tiene más órdenes, buscamos usuarios coincidentes (por RIF o username)
                $proveedoresUsers = \App\Models\User::where('role', 'proveedor')
                    ->get()
                    ->filter(function($u) use ($limpiarRif, $rifLimpio) {
                        return $limpiarRif($u->rif) === $rifLimpio || $limpiarRif($u->username) === $rifLimpio;
                    });

                foreach ($proveedoresUsers as $proveedorUser) {
                    try {
                        \App\Models\ProveedorContacto::where('user_id', $proveedorUser->id)->delete();
                        DB::table('push_subscriptions')
                            ->where('subscribable_type', \App\Models\User::class)
                            ->where('subscribable_id', $proveedorUser->id)
                            ->delete();
                        $proveedorUser->delete();
                    } catch (\Throwable $ex) {}
                }
            }
        }

        return response()->json([
            'message' => 'Orden restablecida a estado pendiente correctamente.'
        ]);
    }

    /**
     * Enviar correo masivo profesional a todos los proveedores con ODCs habilitadas
     */
    public function notificarOdcsHabilitadas(Request $request)
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Acceso denegado. Sólo administradores.'], 403);
        }

        $ordenes = DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada')
            ->get();

        if (empty($ordenes) || (is_object($ordenes) && method_exists($ordenes, 'isEmpty') && $ordenes->isEmpty()) || (is_array($ordenes) && count($ordenes) === 0)) {
            return response()->json(['message' => 'No hay órdenes en estado habilitada para notificar.'], 404);
        }

        $enviados = 0;
        $detalles = [];

        $limpiarRif = function($val) {
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($val ?? '')));
        };

        foreach ($ordenes as $o) {
            $resumen = json_decode($o->resumen_json, true) ?? [];
            $codProv = trim($o->rif_proveedor ?: ($resumen['Codigo_Proveedor'] ?? $resumen['c_RIF'] ?? $resumen['Cod_Proveedor'] ?? ''));
            $rifLimpio = $limpiarRif($codProv);

            // Buscar TODOS los usuarios proveedor vinculados a este RIF
            $users = User::where('role', 'proveedor')
                ->get()
                ->filter(function($u) use ($limpiarRif, $rifLimpio) {
                    return (!empty($rifLimpio) && ($limpiarRif($u->rif) === $rifLimpio || $limpiarRif($u->username) === $rifLimpio));
                });

            $emailsTarget = [];

            if ($users->count() > 0) {
                foreach ($users as $uProv) {
                    if (!empty($uProv->email)) {
                        $emailsTarget[$uProv->email] = $uProv->name;
                    }
                    $contactos = \App\Models\ProveedorContacto::where('user_id', $uProv->id)->get();
                    foreach ($contactos as $cnt) {
                        if (!empty($cnt->email)) {
                            $emailsTarget[$cnt->email] = $cnt->nombre ?: $uProv->name;
                        }
                    }

                    // Notificación en la campanita para el usuario
                    try {
                        $existeNotifProv = \App\Models\Notificacion::where('numero_oc', $o->numero_oc)
                            ->where('tipo', 'odc_habilitada')
                            ->where('target_user_id', $uProv->id)
                            ->exists();
                        if (!$existeNotifProv) {
                            \App\Models\Notificacion::create([
                                'numero_oc' => $o->numero_oc,
                                'proveedor' => $o->proveedor ?: ($resumen['Nombre_Proveedor'] ?? 'Proveedor'),
                                'tipo' => 'odc_habilitada',
                                'fecha_oc' => now(),
                                'fecha_recepcion' => null,
                                'status_erp' => 'HABILITADA',
                                'leida' => false,
                                'target_user_id' => $uProv->id,
                            ]);
                        }
                    } catch (\Exception $e) {}
                }
            }

            if (empty($emailsTarget)) {
                $rawEmail = $resumen['Email'] ?? $resumen['email'] ?? null;
                if ($rawEmail) {
                    $emailsTarget[$rawEmail] = $o->proveedor ?: 'Estimado Proveedor';
                }
            }

            // Notificación campanita general
            try {
                $existeGeneral = \App\Models\Notificacion::where('numero_oc', $o->numero_oc)
                    ->where('tipo', 'odc_habilitada')
                    ->whereNull('target_user_id')
                    ->exists();
                if (!$existeGeneral) {
                    \App\Models\Notificacion::create([
                        'numero_oc' => $o->numero_oc,
                        'proveedor' => $o->proveedor ?: ($resumen['Nombre_Proveedor'] ?? 'Proveedor'),
                        'tipo' => 'odc_habilitada',
                        'fecha_oc' => now(),
                        'fecha_recepcion' => null,
                        'status_erp' => 'HABILITADA',
                        'leida' => false,
                        'target_user_id' => null,
                    ]);
                }
            } catch (\Exception $e) {}

            // Enviar notificación a todos los correos de vendedores/contactos encontrados de forma síncrona
            foreach ($emailsTarget as $emailDestino => $vendedorNombre) {
                $uProv = \App\Models\User::where('username', $codProv)->first();
                $yaRegistrado = false;
                if ($uProv && !empty($uProv->password)) {
                    if (!\Illuminate\Support\Facades\Hash::check($codProv, $uProv->password)) {
                        $yaRegistrado = true;
                    }
                }

                $infoCorreo = (object)[
                    'numero_oc' => $o->numero_oc,
                    'proveedor' => $o->proveedor ?: ($resumen['Nombre_Proveedor'] ?? 'Proveedor'),
                    'username' => $codProv,
                    'email_destino' => $emailDestino,
                    'vendedor_nombre' => $vendedorNombre ?: ($o->proveedor ?: 'Estimado Proveedor'),
                    'ya_registrado' => $yaRegistrado,
                ];

                try {
                    \Illuminate\Support\Facades\Mail::to($emailDestino)->send(new \App\Mail\NotificacionReactivacionOdc($infoCorreo));
                    $enviados++;
                    try {
                        \App\Models\EmailLog::create([
                            'numero_oc' => $o->numero_oc,
                            'proveedor' => $o->proveedor ?: ($resumen['Nombre_Proveedor'] ?? 'Proveedor'),
                            'email_destino' => $emailDestino,
                            'vendedor_nombre' => $vendedorNombre ?: ($o->proveedor ?: 'Estimado Proveedor'),
                            'tipo_evento' => 'reactivacion',
                            'estatus' => 'exitoso',
                        ]);
                    } catch (\Exception $e) {}
                    $detalles[] = [
                        'numero_oc' => $o->numero_oc,
                        'email' => $emailDestino,
                        'estatus' => 'Enviado'
                    ];
                } catch (\Exception $e) {
                    try {
                        \App\Models\EmailLog::create([
                            'numero_oc' => $o->numero_oc,
                            'proveedor' => $o->proveedor ?: ($resumen['Nombre_Proveedor'] ?? 'Proveedor'),
                            'email_destino' => $emailDestino,
                            'vendedor_nombre' => $vendedorNombre ?: ($o->proveedor ?: 'Estimado Proveedor'),
                            'tipo_evento' => 'reactivacion',
                            'estatus' => 'error',
                            'error_mensaje' => $e->getMessage(),
                        ]);
                    } catch (\Exception $e2) {}
                    $detalles[] = [
                        'numero_oc' => $o->numero_oc,
                        'email' => $emailDestino,
                        'estatus' => 'Error: ' . $e->getMessage()
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'Exitoso',
            'enviados' => $enviados,
            'detalles' => $detalles
        ]);
    }

    /**
     * Endpoint para probar el envío de notificaciones Push desde el servidor
     */
    public function probarPushServidor(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        try {
            if (class_exists(\App\Notifications\PushNotification::class)) {
                $user->notify(new \App\Notifications\PushNotification(
                    '🧪 Notificación Servidor - Suraki Logística',
                    '¡Excelente! Las notificaciones Push desde el servidor Laravel están funcionando correctamente.',
                    '/icon.png',
                    '/dashboard'
                ));
            }
            return response()->json([
                'status' => 'Exitoso',
                'message' => 'Notificación Push de prueba enviada exitosamente a tu dispositivo.'
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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

        $user = auth('web')->user();
        $rawRif = $user->rif ?: $user->username;
        
        $limpiarRif = function($val) {
            if (!$val) return '';
            $parts = explode('.', $val);
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($parts[0] ?? '')));
        };

        $userRifLimpio = $limpiarRif($rawRif);
        
        if (empty($userRifLimpio)) {
            return response()->json(['ordenes' => []]);
        }
        
        // Buscar en erp_ordenes_sync las órdenes habilitadas
        $ordenes = DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada')
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $authUser = auth('web')->user();
        $isTestAuthorized = $authUser && ($authUser->role === 'admin' || $authUser->username === 'PROV.PRUEBA');

        $misOrdenes = [];
        foreach ($ordenes as $o) {
            if (str_starts_with(strtoupper($o->numero_oc ?? ''), 'TEST-') && !$isTestAuthorized) {
                continue;
            }
            $resumen = json_decode($o->resumen_json, true) ?? [];
            
            // === BÚSQUEDA DE RIF MULTI-FUENTE (robusta) ===
            $rifMatch = false;
            
            // 1. Columna dedicada rif_proveedor (más confiable, no se sobrescribe en sync)
            if (!empty($o->rif_proveedor) && $limpiarRif($o->rif_proveedor) === $userRifLimpio) {
                $rifMatch = true;
            }
            
            // 2. Codigo_Proveedor en resumen_json
            if (!$rifMatch && !empty($resumen['Codigo_Proveedor'])) {
                if ($limpiarRif($resumen['Codigo_Proveedor']) === $userRifLimpio) {
                    $rifMatch = true;
                }
            }
            
            // 3. c_RIF en resumen_json (campo del ERP)
            if (!$rifMatch && !empty($resumen['c_RIF'])) {
                if ($limpiarRif($resumen['c_RIF']) === $userRifLimpio) {
                    $rifMatch = true;
                }
            }
            
            // 4. Codigo_Proveedor formateado como RIF puro en resumen (v2)
            if (!$rifMatch && !empty($resumen['Cod_Proveedor'])) {
                if ($limpiarRif($resumen['Cod_Proveedor']) === $userRifLimpio) {
                    $rifMatch = true;
                }
            }

            if (!$rifMatch) continue;
            
            // Buscar si la orden ya tiene una cita (busqueda robusta)
            $ordenLimpia = preg_replace('/^E/i', '', $o->numero_oc);
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
            
            $tieneCita = DB::table('appointments')
                ->whereIn('numero_oc', [$o->numero_oc, $ordenLimpia, $ordenPad])
                ->whereIn('estatus', ['programada', 'en muelle'])
                ->exists();
                
            if (!$tieneCita) {
                $o->resumen = $resumen;
                $misOrdenes[] = $o;
            }
        }

        return response()->json(['ordenes' => $misOrdenes]);
    }

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
            'numero_factura' => 'nullable|string',
            'peso_factura_ton' => 'nullable|numeric',
            'formato_carga' => 'nullable|string',
            'tipo_vehiculo' => 'nullable|string',
            'categoria_sugerida' => 'nullable|string',
            'tipo_mercancia' => 'nullable|string',
            'factura_file' => 'nullable',
            'factura_files' => 'nullable|array',
            'factura_files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:15360',
            'email_contacto' => 'nullable|email',
        ]);

        $rif = auth()->user()->rif ?: auth()->user()->username;
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

        $pesoFacturaTon = (isset($validated['peso_factura_ton']) && (float)$validated['peso_factura_ton'] > 0) ? (float)$validated['peso_factura_ton'] : 1.0;
        // Detección inteligente: Si ingresaron > 35 (ej: 50 kg), convertir a toneladas para almacenar el valor real (0.050 Ton)
        if ($pesoFacturaTon > 35) {
            $pesoFacturaTon = round($pesoFacturaTon / 1000, 4);
        }
        $formatoCarga = !empty($validated['formato_carga']) ? $validated['formato_carga'] : 'suelta';
        $numFactura = !empty($validated['numero_factura']) ? $validated['numero_factura'] : 'Por facturar';
        $tipoVehiculo = !empty($validated['tipo_vehiculo']) ? $validated['tipo_vehiculo'] : 'camioneta_panel';

        // Calcular duración exacta
        $duracion = \App\Services\AppointmentDurationService::calcular(
            $catNombre,
            $pesoFacturaTon,
            $formatoCarga,
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
        if ($fechaCita->dayOfWeek === Carbon::SATURDAY) {
            return response()->json(['error' => 'Los sábados están bloqueados para recepción de proveedores.'], 422);
        }
        if ($fechaCita->dayOfWeek === Carbon::WEDNESDAY && $fechaCita->hour >= 11) {
            return response()->json(['error' => 'Los días miércoles la recepción de proveedores externos es únicamente hasta las 11:00 AM.'], 422);
        }

        // Determinar si la solicitud del proveedor corresponde a Perecederos (depósito aparte)
        $solicitudEsPerecederos = self::esPerecederosCita([
            'muelle_asignado' => $validated['muelle_asignado'],
            'numero_oc' => $validated['numero_oc'],
            'tipo_mercancia' => $tipoMercancia,
            'categoria_sugerida' => $validated['categoria_sugerida'] ?? null,
        ]);

        // Verificar disponibilidad de horario respetando la separación de depósitos
        $citasExistentes = DB::table('appointments')
            ->whereDate('fecha_cita', $fechaCita->format('Y-m-d'))
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->get();

        foreach ($citasExistentes as $cita) {
            $citaEsPerecederos = self::esPerecederosCita($cita);

            // Depósitos separados: Perecederos vs General no compiten entre sí
            if ($solicitudEsPerecederos !== $citaEsPerecederos) {
                continue;
            }

            $inicioExistente = Carbon::parse($cita->fecha_cita);
            $duracionExistente = $cita->duracion_minutos ?? 60;
            $finExistente = $inicioExistente->copy()->addMinutes((int) $duracionExistente);

            if ($fechaCita->lt($finExistente) && $fechaFin->gt($inicioExistente)) {
                if ($solicitudEsPerecederos) {
                    $muellesEquivCita = self::getMuellesEquivalentes($cita->muelle_asignado);
                    $muellesEquivSolicitud = self::getMuellesEquivalentes($validated['muelle_asignado']);
                    if (!empty(array_intersect($muellesEquivCita, $muellesEquivSolicitud))) {
                        return response()->json(['error' => 'Conflicto de horario en Perecederos: Ya existe una cita agendada en este muelle de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A') . " (Orden: {$cita->numero_oc})."], 422);
                    }
                } else {
                    return response()->json(['error' => 'Conflicto de horario: Ya existe una cita agendada de ' . $inicioExistente->format('h:i A') . ' a ' . $finExistente->format('h:i A') . " (Orden: {$cita->numero_oc}). No es posible agendar más de una recepción simultánea."], 422);
                }
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
        if (strtolower($formatoCarga) === 'paletizada') {
            $paletizadaCat = \App\Models\CategoriaRendimiento::where('nombre', 'Carga Paletizada General')->first();
            if ($paletizadaCat) {
                $catModel = $paletizadaCat;
            }
        }
        $catId = $catModel ? $catModel->id : null;

        // Subir archivo(s) de factura(s) si existen (admite múltiples facturas)
        $savedFacturaPaths = [];
        if ($request->hasFile('factura_files')) {
            foreach ($request->file('factura_files') as $file) {
                if ($file && $file->isValid()) {
                    $savedFacturaPaths[] = $file->store('facturas', 'public');
                }
            }
        }
        if ($request->hasFile('factura_file')) {
            $f = $request->file('factura_file');
            if (is_array($f)) {
                foreach ($f as $file) {
                    if ($file && $file->isValid()) {
                        $savedFacturaPaths[] = $file->store('facturas', 'public');
                    }
                }
            } elseif ($f && $f->isValid()) {
                $savedFacturaPaths[] = $f->store('facturas', 'public');
            }
        }

        $facturaPath = null;
        if (count($savedFacturaPaths) === 1) {
            $facturaPath = $savedFacturaPaths[0];
        } elseif (count($savedFacturaPaths) > 1) {
            $facturaPath = json_encode(array_values(array_unique($savedFacturaPaths)));
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
            'numero_factura' => $numFactura,
            'peso_factura_ton' => $pesoFacturaTon,
            'formato_carga' => $formatoCarga,
            'tipo_vehiculo' => $tipoVehiculo,
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
                ? \App\Models\User::where('id', $compradorId)->where('activo', true)->get()
                : \App\Models\User::whereIn('role', ['comprador', 'admin'])->where('activo', true)->get();
                
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

        // Enviar correo de confirmación al proveedor (hasta los 3 correos registrados)
        try {
            $emailsProveedor = array_unique(array_filter([auth()->user()->email, $contacto ? $contacto->email : null]));

            // Recuperar correos notificados durante la habilitación de la orden
            $ordenLimpia = preg_replace('/^E/i', '', $validated['numero_oc']);
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
            $ordenConE = 'E' . $ordenPad;
            $syncRow = DB::table('erp_ordenes_sync')
                ->whereIn('numero_oc', [$validated['numero_oc'], $ordenLimpia, $ordenPad, $ordenConE])
                ->first();

            if ($syncRow && !empty($syncRow->resumen_json)) {
                $resSync = json_decode($syncRow->resumen_json, true);
                if (!empty($resSync['emails_notificados']) && is_array($resSync['emails_notificados'])) {
                    $emailsProveedor = array_merge($emailsProveedor, $resSync['emails_notificados']);
                }
            }
            $emailsProveedor = array_slice(array_values(array_unique(array_filter($emailsProveedor))), 0, 3);

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

                foreach ($emailsProveedor as $emP) {
                    try {
                        \App\Models\EmailLog::create([
                            'numero_oc' => $validated['numero_oc'],
                            'proveedor' => $validated['proveedor'],
                            'email_destino' => $emP,
                            'vendedor_nombre' => $contacto ? $contacto->nombre : auth()->user()->name,
                            'tipo_evento' => 'cita_agendada',
                            'estatus' => 'exitoso',
                        ]);
                    } catch (\Exception $eLog) {}
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo NuevaCita a proveedor: ' . $e->getMessage());
        }

        // Enviar Push Notification a Receptores, Comprador y Administradores
        try {
            $destinatariosPush = \App\Models\User::whereIn('role', ['receptor', 'admin'])->where('activo', true)->get();
            if ($compradorId) {
                $compradorPush = \App\Models\User::find($compradorId);
                if ($compradorPush && !$destinatariosPush->contains('id', $compradorPush->id)) {
                    $destinatariosPush->push($compradorPush);
                }
            }
            \Illuminate\Support\Facades\Notification::send($destinatariosPush, new \App\Notifications\PushNotification(
                'Nueva Cita Agendada por Proveedor',
                "{$validated['proveedor']} ha agendado cita para la orden {$validated['numero_oc']} el " . $fechaCita->format('d/m/Y h:i A'),
                null,
                '/dashboard'
            ));
        } catch (\Exception $ePush) {
            \Illuminate\Support\Facades\Log::warning('Push notification no enviada (reservarProveedor): ' . $ePush->getMessage());
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
        if (str_starts_with(strtoupper($numero_oc), 'TEST-')) {
            $authUser = auth('web')->user() ?: request()->user();
            $isTestAuthorized = $authUser && ($authUser->role === 'admin' || in_array($authUser->username, ['Compras.Juan', 'PROV.PRUEBA']));
            if (!$isTestAuthorized) {
                return response()->json(['error' => 'Cita no encontrada'], 404);
            }
        }

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
    public function anularFactura(Request $request, $id)
    {
        if (!auth('web')->check() || auth('web')->user()->role !== 'proveedor') {
            return response()->json(['error' => 'No tienes permisos.'], 403);
        }

        $cita = DB::table('appointments')->where('id', $id)->first();
        
        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada.'], 404);
        }
        
        if ($cita->rif_proveedor !== auth('web')->user()->username) {
            return response()->json(['error' => 'No puedes modificar esta cita.'], 403);
        }

        if ($cita->estatus === 'finalizada' || $cita->estatus === 'cancelada') {
            return response()->json(['error' => 'No puedes modificar una cita ' . $cita->estatus], 400);
        }

        if ($cita->factura_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($cita->factura_path);
        }

        DB::table('appointments')->where('id', $id)->update([
            'numero_factura' => null,
            'peso_factura_ton' => null,
            'factura_path' => null,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Factura anulada correctamente.']);
    }

    public function actualizarFactura(Request $request, $id)
    {
        if (!auth('web')->check() || auth('web')->user()->role !== 'proveedor') {
            return response()->json(['error' => 'No tienes permisos.'], 403);
        }

        $cita = DB::table('appointments')->where('id', $id)->first();
        
        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada.'], 404);
        }
        
        if ($cita->rif_proveedor !== auth('web')->user()->username) {
            return response()->json(['error' => 'No puedes modificar esta cita.'], 403);
        }

        $validated = $request->validate([
            'numero_factura' => 'required|string',
            'peso_factura_ton' => 'required|numeric',
            'factura_file' => 'nullable',
            'factura_files' => 'nullable|array',
            'factura_files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:15360',
        ]);

        $savedFacturaPaths = [];
        if ($request->hasFile('factura_files')) {
            foreach ($request->file('factura_files') as $file) {
                if ($file && $file->isValid()) {
                    $savedFacturaPaths[] = $file->store('facturas', 'public');
                }
            }
        }
        if ($request->hasFile('factura_file')) {
            $f = $request->file('factura_file');
            if (is_array($f)) {
                foreach ($f as $file) {
                    if ($file && $file->isValid()) {
                        $savedFacturaPaths[] = $file->store('facturas', 'public');
                    }
                }
            } elseif ($f && $f->isValid()) {
                $savedFacturaPaths[] = $f->store('facturas', 'public');
            }
        }

        $facturaPath = $cita->factura_path;
        if (!empty($savedFacturaPaths)) {
            if ($facturaPath) {
                if (str_starts_with(trim($facturaPath), '[')) {
                    $oldPaths = json_decode($facturaPath, true) ?: [];
                    foreach ($oldPaths as $op) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($op);
                    }
                } else {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($facturaPath);
                }
            }
            if (count($savedFacturaPaths) === 1) {
                $facturaPath = $savedFacturaPaths[0];
            } else {
                $facturaPath = json_encode(array_values(array_unique($savedFacturaPaths)));
            }
        }

        $pesoTon = (float) $validated['peso_factura_ton'];
        if ($pesoTon > 35) {
            $pesoTon = round($pesoTon / 1000, 4);
        }

        // Recalcular la duración con el nuevo peso de la factura
        $catNombre = $cita->tipo_mercancia ?? 'Alimentos 1 (Viveres)';
        $formatoCarga = $cita->formato_carga ?? 'suelta';
        $nuevaDuracion = \App\Services\AppointmentDurationService::calcular(
            $catNombre,
            $pesoTon,
            $formatoCarga,
            0
        );

        DB::table('appointments')->where('id', $id)->update([
            'numero_factura' => $validated['numero_factura'],
            'peso_factura_ton' => $pesoTon,
            'duracion_minutos' => $nuevaDuracion,
            'factura_path' => $facturaPath,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Factura actualizada correctamente.', 'duracion_minutos' => $nuevaDuracion]);
    }

    /**
     * Resolver el comprador real de una Orden de Compra:
     * Mapea exactamente a los 4 compradores reales del sistema:
     * ALEJANDRO PEÑA, KARYNELL ARAQUE, Dugarte Yoliys, MARIA JOSE CONTRERAS
     */
    private function resolverCompradorReal($numeroOc, $syncRow = null, $cita = null)
    {
        $ordenLimpia = preg_replace('/^E/i', '', (string)$numeroOc);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;

        $posiblesOcs = array_unique(array_filter([$numeroOc, $ordenLimpia, $ordenPad, $ordenConE]));

        // 1. Buscar en TODOS los registros sincronizados de esta OC
        $syncRows = DB::table('erp_ordenes_sync')
            ->whereIn('numero_oc', $posiblesOcs)
            ->orderBy('updated_at', 'desc')
            ->get();

        $codComprador = null;

        foreach ($syncRows as $row) {
            if (!empty($row->resumen_json)) {
                $resumen = json_decode($row->resumen_json, true);
                if (isset($resumen['resumen']) && is_array($resumen['resumen'])) {
                    $resumen = array_merge($resumen, $resumen['resumen']);
                }
                $cod = $resumen['Comprador_Interno'] ?? $resumen['c_CODCOMPRADOR'] ?? $resumen['Cod_Comprador'] ?? null;
                if ($cod && trim((string)$cod) !== '' && trim((string)$cod) !== 'General') {
                    $codComprador = trim((string)$cod);
                    break;
                }
            }
        }

        // 2. Si no se resolvió por sync y hay conexión local con SQLSRV
        if (!$codComprador && $numeroOc) {
            try {
                $sqlRow = DB::connection('sqlsrv')->selectOne("
                    SELECT c_CODCOMPRADOR FROM MA_ODC WITH (NOLOCK) WHERE c_DOCUMENTO IN (?, ?, ?)
                ", [$numeroOc, $ordenLimpia, $ordenPad]);
                if ($sqlRow && !empty($sqlRow->c_CODCOMPRADOR)) {
                    $codComprador = trim($sqlRow->c_CODCOMPRADOR);
                }
            } catch (\Throwable $e) {}
        }

        $codClean = trim((string)($codComprador ?? ''));

        if ($codClean !== '') {
            $nombreMapeado = $this->formatearNombreComprador($codClean);
            if ($nombreMapeado !== 'Comprador ERP') {
                return $nombreMapeado;
            }
        }

        // 3. Verificar si fue habilitada por un usuario comprador web
        $userId = ($syncRow ? $syncRow->habilitada_por_user_id : null) ?? ($cita ? $cita->habilitada_por_user_id : null);
        if ($userId) {
            $u = DB::table('users')->where('id', $userId)->first();
            if ($u && $u->role === 'comprador') {
                return $this->formatearNombreComprador(null, $u->name);
            }
        }

        // 4. Si el creador registrado es un comprador específico
        if ($cita && !empty($cita->registrado_por_nombre)) {
            $nombreCreador = $this->formatearNombreComprador(null, $cita->registrado_por_nombre);
            if ($nombreCreador !== 'Comprador ERP') {
                return $nombreCreador;
            }
        }

        // 5. Mapeo directo por número de orden para órdenes del sistema
        $mapaDirectoOc = [
            '33078' => 'KARYNELL ARAQUE',
            '33107' => 'KARYNELL ARAQUE',
            '33065' => 'KARYNELL ARAQUE',
            '33113' => 'KARYNELL ARAQUE',
            '33116' => 'Dugarte Yoliys',
            '33118' => 'Dugarte Yoliys',
            '33122' => 'Dugarte Yoliys',
            '33119' => 'MARIA JOSE CONTRERAS',
            '33120' => 'MARIA JOSE CONTRERAS',
            '33121' => 'MARIA JOSE CONTRERAS',
            '33123' => 'MARIA JOSE CONTRERAS',
        ];

        $ocLimpiaKey = ltrim($ordenLimpia, '0');
        if (isset($mapaDirectoOc[$ocLimpiaKey])) {
            return $mapaDirectoOc[$ocLimpiaKey];
        }

        return 'KARYNELL ARAQUE';
    }

    /**
     * Mapeo estricto a los compradores reales del sistema
     */
    private function formatearNombreComprador($codigo, $nombreOriginal = null)
    {
        $codClean = trim((string)($codigo ?? ''));
        $codPadded = $codClean !== '' ? str_pad($codClean, 3, '0', STR_PAD_LEFT) : '';

        $mapaCompradoresExacto = [
            '027' => 'KARYNELL ARAQUE',
            '176' => 'ALEJANDRO PEÑA',
            '019' => 'Dugarte Yoliys',
            '166' => 'MARIA JOSE CONTRERAS',
            '228' => 'DANIEL (SURAKARNES)',
        ];

        if ($codPadded !== '' && isset($mapaCompradoresExacto[$codPadded])) {
            return $mapaCompradoresExacto[$codPadded];
        }

        if (isset($mapaCompradoresExacto[$codClean])) {
            return $mapaCompradoresExacto[$codClean];
        }

        if ($nombreOriginal) {
            $n = trim($nombreOriginal);
            if (preg_match('/KARYNELL/i', $n)) return 'KARYNELL ARAQUE';
            if (preg_match('/PEÑA|ALEJANDRO/i', $n)) return 'ALEJANDRO PEÑA';
            if (preg_match('/YOLI|DUGARTE/i', $n)) return 'Dugarte Yoliys';
            if (preg_match('/MARIA\s*J/i', $n)) return 'MARIA JOSE CONTRERAS';
            if (preg_match('/DANIEL/i', $n)) return 'DANIEL (SURAKARNES)';
            
            if (preg_match('/Jeralth|Admin/i', $n)) {
                return 'KARYNELL ARAQUE';
            }
            return $n;
        }

        return 'KARYNELL ARAQUE';
    }
}

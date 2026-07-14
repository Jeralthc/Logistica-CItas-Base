<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Reprogramada</title>
    <style>
        body { font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
        .header { background-color: #2563eb; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px 40px; }
        .content p { font-size: 16px; line-height: 1.6; color: #475569; margin-top: 0; }
        .details-card { background-color: #f8fafc; border-radius: 12px; padding: 20px; margin: 25px 0; border: 1px solid #e2e8f0; border-left: 4px solid #2563eb; }
        .details-card h3 { margin-top: 0; margin-bottom: 15px; color: #0f172a; font-size: 18px; }
        .detail-row { margin-bottom: 10px; font-size: 15px; }
        .detail-label { font-weight: 600; color: #334155; display: inline-block; width: 140px; }
        .detail-value { color: #0f172a; font-weight: 700; }
        .changed-value { color: #2563eb; font-weight: 800; background-color: #eff6ff; padding: 2px 6px; border-radius: 4px; }
        .old-value { text-decoration: line-through; color: #94a3b8; font-size: 13px; margin-left: 10px; }
        .reason { margin-top: 25px; padding-top: 20px; border-top: 1px dashed #cbd5e1; }
        .reason h4 { margin: 0 0 10px 0; color: #2563eb; font-size: 16px; }
        .reason p { font-style: italic; color: #334155; }
        .footer { background-color: #0f172a; color: #94a3b8; padding: 20px; text-align: center; font-size: 13px; }
        .footer p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cita Reprogramada</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $cita->proveedor }}</strong>,</p>
            <p>Te informamos que tu cita para la recepción de mercancía ha sido <strong>reprogramada</strong>.</p>
            
            <div class="details-card">
                <h3>Nuevos Detalles de la Cita</h3>
                <div class="detail-row">
                    <span class="detail-label">Orden de Compra:</span>
                    <span class="detail-value">{{ $cita->numero_oc }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Proveedor:</span>
                    <span class="detail-value">{{ $cita->proveedor }}</span>
                </div>
                @php
                    $fechaInicio = \Carbon\Carbon::parse($cita->fecha_cita);
                    $duracion = (int) ($cita->duracion_minutos ?? 60);
                    $fechaFin = $fechaInicio->copy()->addMinutes($duracion);

                    $fechaAnteriorInicio = \Carbon\Carbon::parse($fechaAnterior);
                    $fechaAnteriorFin = $fechaAnteriorInicio->copy()->addMinutes($duracion);
                @endphp
                <div class="detail-row">
                    <span class="detail-label">Nueva Fecha:</span>
                    <span class="detail-value changed-value">{{ $fechaInicio->format('d/m/Y h:i A') }} a {{ $fechaFin->format('h:i A') }}</span>
                    <span class="old-value">{{ $fechaAnteriorInicio->format('d/m/Y h:i A') }} a {{ $fechaAnteriorFin->format('h:i A') }}</span>
                </div>
                @php
                    $sucursalesMap = [
                        '0101' => 'Tu Empresa',
                        '0102' => 'Depósito General',
                        '0111' => 'Producción',
                        '0115' => 'Insumos',
                        '0161' => 'Andinka',
                        '01' => 'Galpón Central',
                        '02' => 'Galpón Central',
                        '03' => 'Galpón Central',
                        '04' => 'Galpón Central',
                    ];
                    $nombreSucursal = $sucursalesMap[$cita->muelle_asignado] ?? $cita->muelle_asignado;
                @endphp
                <div class="detail-row">
                    <span class="detail-label">Sucursal Asignada:</span>
                    <span class="detail-value">{{ $nombreSucursal }}</span>
                </div>
            </div>

            <div class="reason">
                <h4>Motivo de Reprogramación:</h4>
                <p>"{{ $motivo }}"</p>
            </div>

            <p style="margin-top: 30px;">Por favor, asegúrate de presentarte en la nueva fecha y hora asignada.</p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a esta dirección.</p>
            <p>&copy; {{ date('Y') }} Logística Empresa Base. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>

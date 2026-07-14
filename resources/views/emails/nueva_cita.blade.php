<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Confirmada</title>
    <style>
        body { font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
        .header { background-color: #10b981; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px 40px; }
        .content p { font-size: 16px; line-height: 1.6; color: #475569; margin-top: 0; }
        .details-card { background-color: #ecfdf5; border-radius: 12px; padding: 20px; margin: 25px 0; border: 1px solid #a7f3d0; border-left: 4px solid #10b981; }
        .details-card h3 { margin-top: 0; margin-bottom: 15px; color: #0f172a; font-size: 18px; }
        .detail-row { margin-bottom: 10px; font-size: 15px; }
        .detail-label { font-weight: 600; color: #334155; display: inline-block; width: 140px; }
        .detail-value { color: #0f172a; font-weight: 700; }
        .footer { background-color: #0f172a; color: #94a3b8; padding: 20px; text-align: center; font-size: 13px; }
        .footer p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cita Confirmada</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $cita->proveedor }}</strong>,</p>
            <p>Te informamos que tu cita para la recepción de mercancía ha sido <strong>programada exitosamente</strong>.</p>
            
            <div class="details-card">
                <h3>Detalles de la Cita</h3>
                <div class="detail-row">
                    <span class="detail-label">Orden de Compra:</span>
                    <span class="detail-value">{{ $cita->numero_oc }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Proveedor:</span>
                    <span class="detail-value">{{ $cita->proveedor }}</span>
                </div>
                @if(isset($cita->vendedor_nombre))
                <div class="detail-row">
                    <span class="detail-label">Atendido por:</span>
                    <span class="detail-value">{{ $cita->vendedor_nombre }}</span>
                </div>
                @endif
                @if(isset($cita->username) && isset($cita->password))
                <div class="detail-row">
                    <span class="detail-label">Usuario (RIF):</span>
                    <span class="detail-value">{{ $cita->username }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Contraseña:</span>
                    <span class="detail-value">{{ $cita->password }}</span>
                </div>
                @endif
                @php
                    $fechaInicio = \Carbon\Carbon::parse($cita->fecha_cita);
                    $duracion = (int) ($cita->duracion_minutos ?? 60);
                    $fechaFin = $fechaInicio->copy()->addMinutes($duracion);
                @endphp
                <div class="detail-row">
                    <span class="detail-label">Fecha y Hora:</span>
                    <span class="detail-value">{{ $fechaInicio->format('d/m/Y h:i A') }} a {{ $fechaFin->format('h:i A') }}</span>
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

            <p style="margin-top: 30px;">Por favor, asegúrate de presentarte en la fecha y hora asignada.</p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a esta dirección.</p>
            <p>&copy; {{ date('Y') }} Logística Empresa Base. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>

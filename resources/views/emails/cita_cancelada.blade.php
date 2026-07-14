<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Cancelada</title>
    <style>
        body { font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
        .header { background-color: #ef4444; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px 40px; }
        .content p { font-size: 16px; line-height: 1.6; color: #475569; margin-top: 0; }
        .details-card { background-color: #f1f5f9; border-radius: 12px; padding: 20px; margin: 25px 0; border-left: 4px solid #ef4444; }
        .details-card h3 { margin-top: 0; margin-bottom: 15px; color: #0f172a; font-size: 18px; }
        .detail-row { margin-bottom: 10px; font-size: 15px; }
        .detail-label { font-weight: 600; color: #334155; display: inline-block; width: 140px; }
        .detail-value { color: #0f172a; font-weight: 700; }
        .reason { margin-top: 25px; padding-top: 20px; border-top: 1px dashed #cbd5e1; }
        .reason h4 { margin: 0 0 10px 0; color: #ef4444; font-size: 16px; }
        .reason p { font-style: italic; color: #334155; }
        .footer { background-color: #0f172a; color: #94a3b8; padding: 20px; text-align: center; font-size: 13px; }
        .footer p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cita Cancelada</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $cita->proveedor }}</strong>,</p>
            <p>Te informamos que la cita programada para la recepción de mercancía ha sido <strong>cancelada</strong>.</p>
            
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
                <div class="detail-row">
                    <span class="detail-label">Fecha original:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($cita->fecha_cita)->format('d/m/Y h:i A') }}</span>
                </div>
            </div>

            <div class="reason">
                <h4>Motivo de Cancelación:</h4>
                <p>"{{ $motivo }}"</p>
            </div>

            <p style="margin-top: 30px;">Si consideras que esto es un error o necesitas programar una nueva cita, por favor ingresa nuevamente al sistema.</p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a esta dirección.</p>
            <p>&copy; {{ date('Y') }} Logística Empresa Base. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>

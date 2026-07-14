<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cita Agendada por Proveedor</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background-color: #1e293b; padding: 30px 20px; text-align: center; color: white; border-bottom: 4px solid #dc2626; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px; }
        .content p { line-height: 1.6; margin-bottom: 15px; }
        .details-box { background-color: #f1f5f9; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .details-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; }
        .details-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .details-label { font-weight: 700; color: #64748b; font-size: 13px; text-transform: uppercase; }
        .details-value { font-weight: bold; color: #0f172a; text-align: right; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cita Agendada Exitosamente</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $info->comprador_nombre }}</strong>,</p>
            
            <p>Le notificamos que el proveedor <strong>{{ $info->proveedor }}</strong> ha completado los datos de su despacho y ha agendado su cita de recepción.</p>

            <div class="details-box">
                <div class="details-row">
                    <span class="details-label">Orden de Compra</span>
                    <span class="details-value">{{ $info->numero_oc }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Contacto (Proveedor)</span>
                    <span class="details-value">{{ $info->vendedor_nombre }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Fecha Reservada</span>
                    <span class="details-value">{{ \Carbon\Carbon::parse($info->fecha_cita)->format('d/m/Y') }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Hora Estimada</span>
                    <span class="details-value">{{ \Carbon\Carbon::parse($info->fecha_cita)->format('h:i A') }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Sucursal Asignada</span>
                    <span class="details-value">{{ $info->muelle_asignado }}</span>
                </div>
            </div>

            <p>Ya puede visualizar el estatus de esta recepción en su panel de monitoreo. Tenga en cuenta que el personal de recepción ya ha sido notificado sobre la llegada de este proveedor.</p>
        </div>
        <div class="footer">
            <p>Sistema Logístico Empresa Base - Notificaciones Internas</p>
        </div>
    </div>
</body>
</html>

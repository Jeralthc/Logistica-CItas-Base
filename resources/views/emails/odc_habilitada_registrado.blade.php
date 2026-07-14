<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orden de Compra Habilitada</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background-color: #dc2626; padding: 30px 20px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px; }
        .content p { line-height: 1.6; margin-bottom: 15px; }
        .details-box { background-color: #f1f5f9; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .details-box p { margin: 5px 0; font-size: 15px; }
        .button-container { text-align: center; margin: 30px 0; }
        .button { background-color: #1e293b; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>¡Nueva Orden Habilitada!</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $info->vendedor_nombre }}</strong>,</p>
            
            <p>Le informamos que el comprador <strong>{{ $info->comprador_nombre }}</strong> ha habilitado una nueva Orden de Compra para su despacho en nuestro Centro de Distribución Empresa Base.</p>

            <div class="details-box">
                <p><strong>Orden de Compra:</strong> {{ $info->numero_oc }}</p>
                <p><strong>Proveedor:</strong> {{ $info->proveedor }}</p>
            </div>

            <p>Ya puede ingresar a su portal con sus credenciales habituales para completar los datos de despacho y reservar su cita de recepción.</p>

            <div class="button-container">
                <a href="{{ url('/login') }}" style="background-color: #dc2626; color: white; padding: 14px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 16px;">Ingresar al Portal y Agendar</a>
            </div>

            <p style="font-size: 12px; color: #64748b; margin-top: 20px;">Si el botón no funciona, copie y pegue el siguiente enlace en su navegador:<br>
            <a href="{{ url('/login') }}" style="color: #3b82f6; word-break: break-all;">{{ url('/login') }}</a></p>

            <p>Por favor asegúrese de tener a mano el número de factura y el peso real de su carga para poder agendar su cita correctamente.</p>
        </div>
        <div class="footer">
            <p>Este es un mensaje automático del Sistema Logístico Empresa Base. Por favor no responda a este correo.</p>
        </div>
    </div>
</body>
</html>

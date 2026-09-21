@php
    $appUrl = config('app.url');
    if (empty($appUrl) || str_contains($appUrl, '.test') || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'logistica.suraki.net')) {
        $appUrl = 'https://citsur.suraki.net';
    }
    $appUrl = rtrim($appUrl, '/');
    $linkAcceso = $appUrl . '/setup-proveedor?rif=' . urlencode($info->username) . '&email=' . urlencode($info->email_destino) . '&name=' . urlencode($info->vendedor_nombre ?? $info->proveedor);
@endphp
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
            <h1>¡Hola, {{ $info->vendedor_nombre }}!</h1>
        </div>
        <div class="content">
            <p>Le informamos que el comprador <strong>{{ $info->comprador_nombre }}</strong> ha habilitado una Orden de Compra para su despacho en Hipersuraki.</p>
            
            <p>Ya puede ingresar a nuestro portal para completar los datos de su despacho y reservar su cita de recepción en el horario que mejor le convenga.</p>

            <div class="details-box">
                <p><strong>Orden de Compra:</strong> {{ $info->numero_oc }}</p>
                <p><strong>Proveedor:</strong> {{ $info->proveedor }}</p>
            </div>

            <p>A continuación le indicamos el usuario que deberá utilizar. Deberá ingresar y establecer su propia contraseña en nuestro portal.</p>

            <div class="details-box">
                <p><strong>Usuario / RIF:</strong> {{ $info->username }}</p>
                <p><strong>Correo:</strong> {{ $info->email_destino }}</p>
            </div>

            <div class="button-container" style="text-align: center; margin: 30px 0;">
                <a href="{{ $linkAcceso }}" style="background-color: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">Establecer Contraseña y Acceder</a>
            </div>

            <p style="font-size: 12px; color: #64748b; margin-top: 20px;">Si el botón no funciona, copie y pegue el siguiente enlace en su navegador:<br>
            <a href="{{ $linkAcceso }}" style="color: #3b82f6; word-break: break-all;">{{ $linkAcceso }}</a></p>

            <p>Por favor asegúrese de tener a mano el número de factura y el peso real de su carga para poder agendar su cita correctamente.</p>

            <!-- Descarga de Manual de Usuario Oficial -->
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin: 25px 0; text-align: center;">
                <p style="margin: 0 0 6px 0; font-size: 14px; font-weight: bold; color: #0f172a;">
                    📖 ¿Primera vez en nuestro portal o necesitas ayuda?
                </p>
                <p style="margin: 0 0 14px 0; font-size: 13px; color: #64748b; line-height: 1.5;">
                    Hemos preparado un <strong>Manual de Usuario en PDF</strong> con imágenes y el paso a paso detallado para que configures tu cuenta y reserves tu cita sin complicaciones.
                </p>
                <a href="{{ $appUrl }}/Manual_Usuario_Portal_Proveedor_CITSUR.pdf" target="_blank" style="background-color: #0f172a; color: #ffffff; padding: 10px 22px; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: bold; display: inline-block;">
                    📥 Descargar Manual de Usuario (PDF)
                </a>
            </div>

            <!-- Recomendación de Entrega y Correo Corporativo -->
            <div style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 8px; padding: 14px 18px; margin: 25px 0; text-align: left;">
                <p style="margin: 0 0 6px 0; font-size: 13px; font-weight: bold; color: #0369a1;">
                    💡 Importante para asegurar la recepción de futuras órdenes:
                </p>
                <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #334155;">
                    Por favor revisa tu <strong>Bandeja de Entrada</strong> o tu carpeta de <strong>Correo no Deseado</strong>. Si este mensaje te cayó en Correo no Deseado, dale clic derecho y selecciona <strong>"Marcar como correo seguro / No es correo no deseado"</strong> para que todas las futuras órdenes te entren directo a la bandeja principal.
                </p>
            </div>
        </div>
        <div class="footer">
            <p>Este es un mensaje automático del Sistema Logístico Suraki. Por favor no responda a este correo.</p>
        </div>
    </div>
</body>
</html>

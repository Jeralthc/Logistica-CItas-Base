@php
    $appUrl = config('app.url');
    if (empty($appUrl) || str_contains($appUrl, '.test') || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'logistica.suraki.net')) {
        $appUrl = 'https://citsur.suraki.net';
    }
    $appUrl = rtrim($appUrl, '/');
    
    $yaRegistrado = $info->ya_registrado ?? false;

    if ($yaRegistrado) {
        $linkAcceso = $appUrl . '/login';
        $textoBoton = 'Ingresar al Portal y Reservar Cita';
    } else {
        $linkAcceso = $appUrl . '/setup-proveedor?rif=' . urlencode($info->username) . '&email=' . urlencode($info->email_destino) . '&name=' . urlencode($info->vendedor_nombre ?? $info->proveedor);
        $textoBoton = 'Establecer Contraseña y Reservar Cita';
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orden de Compra Disponible para Cita - Suraki</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 35px 25px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .header p { margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 35px; }
        .greeting { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 15px; }
        .text { font-size: 15px; line-height: 1.6; color: #475569; margin-bottom: 20px; }
        .apology-box { background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px 20px; border-radius: 0 12px 12px 0; margin: 20px 0; }
        .apology-box p { margin: 0; font-size: 14px; color: #991b1b; line-height: 1.5; font-weight: 500; }
        .details-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px border-slate-100; font-size: 14px; }
        .details-row:last-child { border-bottom: none; }
        .label { font-weight: 700; color: #64748b; }
        .value { font-weight: 800; color: #0f172a; font-family: monospace; }
        .button-container { text-align: center; margin: 35px 0 25px 0; }
        .button { background-color: #dc2626; color: #ffffff !important; padding: 14px 36px; text-decoration: none; border-radius: 12px; font-weight: 800; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25); transition: all 0.2s ease; }
        .footer { background-color: #f1f5f9; padding: 25px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 4px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Hipersuraki Logística</h1>
            <p>Notificación de Actualización de Sistema</p>
        </div>
        <div class="content">
            <div class="greeting">Estimado(a) {{ $info->vendedor_nombre ?? $info->proveedor }},</div>
            
            <p class="text">Le saludamos cordialmente desde el equipo de Recepción y Logística de <strong>Hipersuraki</strong>.</p>
            
            <div class="apology-box">
                <p>Le ofrecemos nuestras más sinceras disculpas por los inconvenientes o problemas técnicos presentados recientemente en la plataforma.</p>
            </div>

            <p class="text">Le solicitamos por favor <strong>recargar la página en su navegador (presionando F5 o refrescando la pantalla)</strong>. Al hacerlo, ya deberían aparecerle sus órdenes de compra habilitadas totalmente listas para programar su cita de entrega.</p>
            
            <p class="text" style="background-color: #f1f5f9; padding: 12px 16px; border-radius: 8px; font-size: 14px;"><strong>Nota importante:</strong> Si por alguna razón después de recargar la página no le aparece su orden de compra, le agradecemos <strong>comunicarse directamente con su comprador asignado</strong> para verificar el estatus de habilitación.</p>

            <div class="details-box">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px;">
                    <tr>
                        <td style="padding: 6px 0; font-weight: bold; color: #64748b;">Orden de Compra:</td>
                        <td style="padding: 6px 0; text-align: right; font-weight: 800; color: #dc2626; font-family: monospace; font-size: 16px;">{{ $info->numero_oc }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-weight: bold; color: #64748b;">Empresa / Proveedor:</td>
                        <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #0f172a;">{{ $info->proveedor }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-weight: bold; color: #64748b;">Usuario / RIF de Acceso:</td>
                        <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #0f172a; font-family: monospace;">{{ $info->username }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-weight: bold; color: #64748b;">Correo Notificado:</td>
                        <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #2563eb;">{{ $info->email_destino }}</td>
                    </tr>
                </table>
            </div>

            <p class="text">Ya puede ingresar a la plataforma, seleccionar su horario de preferencia y adjuntar los datos requeridos para su entrega.</p>

            @if(!$yaRegistrado)
                <p class="text" style="color: #dc2626; font-weight: bold;">Es necesario que establezca su contraseña para poder acceder al portal.</p>
            @endif

            <div class="button-container">
                <a href="{{ $linkAcceso }}" class="button">
                    {{ $textoBoton }}
                </a>
            </div>

            <p style="font-size: 12px; color: #64748b; text-align: center; margin-top: 25px;">
                Si el botón anterior no abre directamente, copie y pegue la siguiente dirección en su navegador:<br>
                <a href="{{ $linkAcceso }}" style="color: #2563eb; word-break: break-all;">{{ $linkAcceso }}</a>
            </p>

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
            <p><strong>Departamento de Recepción y Logística — Hipersuraki</strong></p>
            <p>Este es un mensaje automático informativo. Gracias por su valiosa comprensión y cooperación.</p>
        </div>
    </div>
</body>
</html>

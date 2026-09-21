<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Restablecimiento de Contraseña - Hipersuraki</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 30px 20px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 800; }
        .header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.9; }
        .content { padding: 30px; }
        .content p { line-height: 1.6; margin-bottom: 15px; font-size: 15px; color: #334155; }
        .details-box { background-color: #f8fafc; border-left: 4px solid #dc2626; padding: 14px 18px; margin: 20px 0; border-radius: 0 8px 8px 0; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        .details-box p { margin: 4px 0; font-size: 14px; }
        .button-container { text-align: center; margin: 30px 0; }
        .button { background-color: #dc2626; color: white !important; padding: 14px 32px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25); }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 4px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Hipersuraki — Portal Logístico</h1>
            <p>Recuperación Segura de Acceso</p>
        </div>
        <div class="content">
            <p>Estimado(a) <strong>{{ $user->name ?? $user->username }}</strong>:</p>
            
            <p>Hemos recibido una solicitud para restablecer la contraseña de su cuenta en el Portal Logístico Suraki.</p>

            <div class="details-box">
                <p><strong>Usuario / RIF:</strong> {{ $user->username }}</p>
                <p><strong>Correo Electrónico:</strong> {{ $user->email }}</p>
            </div>

            <p>Para establecer su nueva contraseña, haga clic en el siguiente botón:</p>

            <div class="button-container">
                <a href="{{ $url }}" class="button">Restablecer Contraseña</a>
            </div>

            <p style="font-size: 12px; color: #64748b; margin-top: 25px;">
                Si el botón no funciona, copie y pegue la siguiente dirección en su navegador:<br>
                <a href="{{ $url }}" style="color: #2563eb; word-break: break-all;">{{ $url }}</a>
            </p>

            <p style="font-size: 13px; color: #64748b; background-color: #f1f5f9; padding: 10px 14px; border-radius: 8px;">
                ⏳ <strong>Nota de Seguridad:</strong> Este enlace expirará en 60 minutos. Si usted no solicitó este cambio, ignore este mensaje; su contraseña actual continuará siendo la misma.
            </p>

            <!-- Recomendación de Entrega y Correo Corporativo -->
            <div style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 8px; padding: 12px 16px; margin: 25px 0; text-align: left;">
                <p style="margin: 0 0 4px 0; font-size: 12px; font-weight: bold; color: #0369a1;">
                    💡 Consejo para asegurar futuras notificaciones:
                </p>
                <p style="margin: 0; font-size: 12px; line-height: 1.4; color: #334155;">
                    Si este correo llegó a su carpeta de <strong>Correo no Deseado (Spam)</strong>, seleccione <strong>"Marcar como correo seguro"</strong> para recibir directamente todas las órdenes de compra e información de sus citas.
                </p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Soporte y Logística — Hipersuraki</strong></p>
            <p>📞 0424-7170326 | ✉️ hipersurakica@gmail.com</p>
            <p>Este es un mensaje automático institucional. Por favor no responda a este correo.</p>
        </div>
    </div>
</body>
</html>

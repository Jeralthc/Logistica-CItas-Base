<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credenciales de Acceso - Portal de Traslados Internos Suraki</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 100%); color: #ffffff; padding: 35px 25px; text-align: center; }
        .header .badge { display: inline-block; background-color: rgba(255, 255, 255, 0.2); color: #e0e7ff; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 12px; rounded-full; border-radius: 9999px; letter-spacing: 1px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .header p { margin: 6px 0 0 0; font-size: 14px; opacity: 0.85; }
        .content { padding: 32px; }
        .greeting { font-size: 16px; line-height: 1.6; color: #334155; margin-bottom: 20px; }
        .credentials-card { background-color: #f8fafc; border: 1px solid #cbd5e1; border-left: 5px solid #4f46e5; border-radius: 12px; padding: 20px; margin: 24px 0; }
        .credentials-card h3 { margin: 0 0 15px 0; color: #1e1b4b; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
        .cred-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; }
        .cred-row:last-child { border-bottom: none; }
        .cred-label { font-size: 13px; font-weight: 600; color: #64748b; }
        .cred-value { font-size: 14px; font-weight: 800; color: #0f172a; font-family: monospace; }
        .features-box { background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 18px 20px; margin: 24px 0; }
        .features-box h4 { margin: 0 0 10px 0; color: #166534; font-size: 14px; font-weight: 800; }
        .features-box ul { margin: 0; padding-left: 20px; color: #15803d; font-size: 13px; line-height: 1.6; }
        .btn-container { text-align: center; margin: 30px 0 20px 0; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: #ffffff !important; text-decoration: none; padding: 14px 36px; border-radius: 12px; font-weight: 800; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .link-text { font-size: 12px; color: #64748b; word-break: break-all; margin-top: 15px; text-align: center; }
        .link-text a { color: #4f46e5; }
        .footer { background-color: #0f172a; color: #94a3b8; padding: 22px; text-align: center; font-size: 12px; }
        .footer p { margin: 4px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="badge">📦 Portal Logístico Suraki</span>
            <h1>Credenciales de Acceso - Traslado Interno</h1>
            <p>Habilitación de cuenta de Proveedor para Galpón Alfonso</p>
        </div>

        <div class="content">
            <p class="greeting">
                Estimado(a) <strong>{{ $data['nombre'] ?? 'Alfonso Contreras' }}</strong>,
            </p>
            <p class="greeting">
                Se ha generado exitosamente su cuenta con perfil de <strong>Proveedor de Galpón</strong> en el Sistema de Citas y Recepción de Suraki. A través de este perfil podrá realizar el <strong>agendamiento automático e inmediato</strong> de traslados internos de mercancía hacia los muelles de recepción.
            </p>

            <div class="credentials-card">
                <h3>🔐 Datos de Ingreso al Sistema</h3>
                <div class="cred-row">
                    <span class="cred-label">Portal de Acceso:</span>
                    <span class="cred-value">{{ $data['url_login'] ?? 'https://citsur.suraki.net/login' }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Usuario / RIF:</span>
                    <span class="cred-value" style="color: #4f46e5;">{{ $data['username'] ?? 'GALPON.ALFONSO' }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Contraseña:</span>
                    <span class="cred-value" style="background-color: #e0e7ff; padding: 3px 8px; border-radius: 6px; color: #3730a3;">{{ $data['password'] ?? 'Paula.2810' }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">RIF Registrado:</span>
                    <span class="cred-value">{{ $data['rif'] ?? 'J-10715201' }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Correo Notificado:</span>
                    <span class="cred-value" style="font-family: inherit;">{{ $data['email'] ?? 'ilalcon@gmail.com' }}</span>
                </div>
            </div>

            <div class="features-box">
                <h4>✨ Novedades del Formulario Simplificado de Galpón:</h4>
                <ul>
                    <li><strong>Sin búsqueda de ODC:</strong> No requiere ingresar número de Orden de Compra comercial del ERP.</li>
                    <li><strong>Agendamiento directo:</strong> No requiere habilitación previa por parte de compradores.</li>
                    <li><strong>Sin facturas externas:</strong> Formulario mínimo enfocado exclusivamente en la logística de recepción.</li>
                    <li><strong>Control de Muelles:</strong> Permite seleccionar muelle destino, duración estimada (hasta 2 horas / 120 min) y número de nota de entrega opcional.</li>
                </ul>
            </div>

            <div class="btn-container">
                <a href="{{ $data['url_login'] ?? 'https://citsur.suraki.net/login' }}" class="btn-primary">
                    Iniciar Sesión en el Portal →
                </a>
            </div>

            <p class="link-text">
                Enlace directo:<br>
                <a href="{{ $data['url_login'] ?? 'https://citsur.suraki.net/login' }}">{{ $data['url_login'] ?? 'https://citsur.suraki.net/login' }}</a>
            </p>

            <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
                <em>Nota: Su usuario receptor habitual (<code>RECEPCION.ALFONSO</code>) se mantiene completamente operativo para tareas de almacén. Use este nuevo usuario (<code>GALPON.ALFONSO</code>) cada vez que necesite reservar la entrega de mercancía proveniente de galpón.</em>
            </p>
        </div>

        <div class="footer">
            <p><strong>SURAKI - Sistema de Gestión Logística & Control de Citas</strong></p>
            <p>Este es un correo automático de seguridad y asignación de credenciales. Por favor consérvelo en sus registros.</p>
        </div>
    </div>
</body>
</html>

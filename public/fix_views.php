<?php
// REPARADOR TOTAL - Escribe directamente todas las vistas Blade faltantes
// Sube a public_html/ y abre: https://citsur.Empresa Base.net/fix_views.php
$root = realpath(__DIR__ . '/..');

$dirs = [
    $root . '/resources/views/emails',
    $root . '/resources/views/errors',
];

foreach ($dirs as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}

$created = [];

// === resources/views/app.blade.php ===
$f = $root . '/resources/views/app.blade.php';
if (!file_exists($f)) {
    file_put_contents($f, '<!DOCTYPE html>
<html lang="{{ str_replace(\'_\', \'-\', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config(\'app.name\', \'Laravel\') }}</title>
        <link rel="icon" href="/favicon.ico" type="image/x-icon">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite([\'resources/js/app.js\', "resources/js/Pages/{$page[\'component\']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
');
    $created[] = 'resources/views/app.blade.php';
}

// === resources/views/emails/nueva_cita.blade.php ===
$f = $root . '/resources/views/emails/nueva_cita.blade.php';
if (!file_exists($f)) {
    file_put_contents($f, file_get_contents('https://raw.githubusercontent.com/placeholder') ?: '');
    // Escribimos directamente
    file_put_contents($f, '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Confirmada</title>
    <style>
        body { font-family: \'Inter\', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
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
            <p>Hola,</p>
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
                    <span class="detail-value">{{ $fechaInicio->format(\'d/m/Y h:i A\') }} a {{ $fechaFin->format(\'h:i A\') }}</span>
                </div>
                @php
                    $sucursalesMap = [
                        \'0101\' => \'Tu Empresa\',
                        \'0102\' => \'Depósito General\',
                        \'0111\' => \'Producción\',
                        \'0115\' => \'Insumos\',
                        \'0161\' => \'Andinka\',
                        \'01\' => \'Galpón Central\',
                        \'02\' => \'Galpón Central\',
                        \'03\' => \'Galpón Central\',
                        \'04\' => \'Galpón Central\',
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
            <p>&copy; {{ date(\'Y\') }} Logística Empresa Base. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
');
    $created[] = 'resources/views/emails/nueva_cita.blade.php';
}

// === resources/views/emails/cita_cancelada.blade.php ===
$f = $root . '/resources/views/emails/cita_cancelada.blade.php';
if (!file_exists($f)) {
    file_put_contents($f, '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Cancelada</title>
    <style>
        body { font-family: \'Inter\', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
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
            <p>Hola,</p>
            <p>Te informamos que el proveedor ha <strong>cancelado</strong> la cita programada para la recepción de mercancía.</p>
            
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
                    <span class="detail-value">{{ \Carbon\Carbon::parse($cita->fecha_cita)->format(\'d/m/Y h:i A\') }}</span>
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
            <p>&copy; {{ date(\'Y\') }} Logística Empresa Base. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
');
    $created[] = 'resources/views/emails/cita_cancelada.blade.php';
}

// === resources/views/emails/cita_reprogramada.blade.php ===
$f = $root . '/resources/views/emails/cita_reprogramada.blade.php';
if (!file_exists($f)) {
    file_put_contents($f, '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Reprogramada</title>
    <style>
        body { font-family: \'Inter\', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
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
            <p>Hola,</p>
            <p>Te informamos que el proveedor ha <strong>reprogramado</strong> la cita para la recepción de mercancía exitosamente.</p>
            
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
                    <span class="detail-value changed-value">{{ $fechaInicio->format(\'d/m/Y h:i A\') }} a {{ $fechaFin->format(\'h:i A\') }}</span>
                    <span class="old-value">{{ $fechaAnteriorInicio->format(\'d/m/Y h:i A\') }} a {{ $fechaAnteriorFin->format(\'h:i A\') }}</span>
                </div>
                @php
                    $sucursalesMap = [
                        \'0101\' => \'Tu Empresa\',
                        \'0102\' => \'Depósito General\',
                        \'0111\' => \'Producción\',
                        \'0115\' => \'Insumos\',
                        \'0161\' => \'Andinka\',
                        \'01\' => \'Galpón Central\',
                        \'02\' => \'Galpón Central\',
                        \'03\' => \'Galpón Central\',
                        \'04\' => \'Galpón Central\',
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
            <p>&copy; {{ date(\'Y\') }} Logística Empresa Base. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
');
    $created[] = 'resources/views/emails/cita_reprogramada.blade.php';
}

// === resources/views/errors/503.blade.php ===
$f = $root . '/resources/views/errors/503.blade.php';
if (!file_exists($f)) {
    file_put_contents($f, '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - Logística Empresa Base</title>
    <meta http-equiv="Content-Security-Policy" content="default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com;">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; width: 100%; height: 100%; font-family: \'Outfit\', sans-serif; background: #0f172a; color: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .bg-animation { position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, #1e1b4b 0%, #0f172a 30%, #020617 70%); animation: rotateBg 30s linear infinite; z-index: 0; }
        @keyframes rotateBg { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .glass-container { position: relative; z-index: 10; background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 30px; padding: 60px 50px; text-align: center; max-width: 600px; width: 90%; box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1); animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .icon-container { width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px auto; box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3); }
        .icon-container svg { width: 40px; height: 40px; color: white; }
        h1 { font-size: 36px; font-weight: 800; margin: 0 0 15px 0; background: linear-gradient(to right, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        p { font-size: 16px; color: #94a3b8; line-height: 1.6; margin: 0 0 40px 0; font-weight: 300; }
        .countdown { display: flex; justify-content: center; gap: 20px; }
        .time-box { background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px; padding: 20px; min-width: 100px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.5); position: relative; overflow: hidden; }
        .time-box::before { content: \'\'; position: absolute; top: 0; left: 0; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); }
        .number { font-size: 42px; font-weight: 800; font-family: monospace; line-height: 1; color: #f8fafc; }
        .label { font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #64748b; margin-top: 8px; font-weight: 600; }
        .loader { display: none; margin-top: 30px; color: #3b82f6; font-weight: 600; font-size: 14px; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .security-badge { margin-top: 40px; display: inline-flex; gap: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); padding: 8px 16px; border-radius: 20px; color: #34d399; font-size: 12px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    <div class="glass-container">
        <div class="icon-container">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
        <h1>Modo de Mantenimiento</h1>
        <p>El sistema se encuentra temporalmente fuera de línea debido a una actualización. Tus datos y sesiones están seguros. Regresaremos en breve.</p>
        @php
            $endTime = null;
            $path = storage_path(\'app/maintenance_time.json\');
            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path), true);
                if (isset($data[\'end_time\'])) { $endTime = $data[\'end_time\']; }
            }
        @endphp
        @if($endTime)
            <div class="countdown" id="countdown">
                <div class="time-box"><div class="number" id="hours">00</div><div class="label">Horas</div></div>
                <div class="time-box"><div class="number" id="minutes">00</div><div class="label">Minutos</div></div>
                <div class="time-box"><div class="number" id="seconds">00</div><div class="label">Segundos</div></div>
            </div>
            <div class="loader" id="loader">Verificando conexión con el sistema...</div>
            <script>
                const endTimeString = {!! json_encode($endTime) !!};
                const targetDate = new Date(endTimeString).getTime();
                let isPolling = false;
                const pad = (num) => String(num).padStart(2, \'0\');
                const timer = setInterval(() => {
                    const now = new Date().getTime();
                    const distance = targetDate - now;
                    if (distance <= 0) {
                        clearInterval(timer);
                        document.getElementById(\'countdown\').style.display = \'none\';
                        document.getElementById(\'loader\').style.display = \'block\';
                        if (!isPolling) { isPolling = true; pollStatus(); }
                        return;
                    }
                    document.getElementById(\'hours\').innerText = pad(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)));
                    document.getElementById(\'minutes\').innerText = pad(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)));
                    document.getElementById(\'seconds\').innerText = pad(Math.floor((distance % (1000 * 60)) / 1000));
                }, 1000);
                function pollStatus() {
                    setInterval(() => {
                        fetch(window.location.href, { method: \'HEAD\' }).then(r => { if (r.status === 200) window.location.reload(); }).catch(e => {});
                    }, 5000);
                }
            </script>
        @else
            <div class="loader" style="display: block;">Mantenimiento en curso...</div>
            <script>
                setInterval(() => { fetch(window.location.href, { method: \'HEAD\' }).then(r => { if (r.status === 200) window.location.reload(); }).catch(e => {}); }, 15000);
            </script>
        @endif
    </div>
</body>
</html>
');
    $created[] = 'resources/views/errors/503.blade.php';
}

echo "<h2 style='color:green'>✅ Vistas reparadas</h2>";
echo "<p>Archivos creados:</p><ul>";
foreach ($created as $c) echo "<li style='color:green'>$c</li>";
if (empty($created)) echo "<li>Todos los archivos ya existían.</li>";
echo "</ul>";
echo "<p><strong>Recarga tu página principal con Ctrl+F5.</strong></p>";
unlink(__FILE__);
echo "<p style='color:gray;font-size:11px'>Script eliminado.</p>";

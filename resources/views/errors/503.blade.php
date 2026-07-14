<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - Logística Empresa Base</title>
    <!-- Content Security Policy para máxima seguridad -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        * { box-sizing: border-box; }
        body, html {
            margin: 0; padding: 0; width: 100%; height: 100%;
            font-family: 'Outfit', sans-serif;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        /* Fondo Animado de Gradiente Premium */
        .bg-animation {
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle at 50% 50%, #1e1b4b 0%, #0f172a 30%, #020617 70%);
            animation: rotateBg 30s linear infinite;
            z-index: 0;
        }
        
        @keyframes rotateBg {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .glass-container {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 30px;
            padding: 60px 50px;
            text-align: center;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .icon-container {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 30px auto;
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
        }

        .icon-container svg { width: 40px; height: 40px; color: white; }

        h1 {
            font-size: 36px; font-weight: 800; margin: 0 0 15px 0;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        p {
            font-size: 16px; color: #94a3b8; line-height: 1.6; margin: 0 0 40px 0; font-weight: 300;
        }

        /* Countdown */
        .countdown {
            display: flex; justify-content: center; gap: 20px;
        }

        .time-box {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 20px;
            min-width: 100px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }

        .time-box::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        }

        .number {
            font-size: 42px; font-weight: 800; font-family: monospace; line-height: 1;
            color: #f8fafc;
        }

        .label {
            font-size: 12px; text-transform: uppercase; letter-spacing: 2px;
            color: #64748b; margin-top: 8px; font-weight: 600;
        }

        /* Loader */
        .loader {
            display: none; margin-top: 30px;
            color: #3b82f6; font-weight: 600; font-size: 14px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        .security-badge {
            margin-top: 40px; display: inline-flex; items-center; gap: 8px;
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 8px 16px; border-radius: 20px; color: #34d399; font-size: 12px; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    
    <div class="glass-container">
        <div class="icon-container">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
        </div>
        
        <h1>Modo de Mantenimiento</h1>
        <p>El sistema se encuentra temporalmente fuera de línea debido a una actualización. Tus datos y sesiones están seguros. Regresaremos en breve.</p>

        @php
            $endTime = null;
            $path = storage_path('app/maintenance_time.json');
            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path), true);
                if (isset($data['end_time'])) {
                    $endTime = $data['end_time'];
                }
            }
        @endphp

        @if($endTime)
            <div class="countdown" id="countdown">
                <div class="time-box">
                    <div class="number" id="hours">00</div>
                    <div class="label">Horas</div>
                </div>
                <div class="time-box">
                    <div class="number" id="minutes">00</div>
                    <div class="label">Minutos</div>
                </div>
                <div class="time-box">
                    <div class="number" id="seconds">00</div>
                    <div class="label">Segundos</div>
                </div>
            </div>
            
            <div class="loader" id="loader">
                Verificando conexión con el sistema...
            </div>

            <script>
                // Evitamos XSS al inyectar de manera segura la fecha en JS usando PHP JSON encode
                const endTimeString = {!! json_encode($endTime) !!};
                const targetDate = new Date(endTimeString).getTime();
                
                let isPolling = false;

                const pad = (num) => String(num).padStart(2, '0');

                const timer = setInterval(() => {
                    const now = new Date().getTime();
                    const distance = targetDate - now;

                    if (distance <= 0) {
                        clearInterval(timer);
                        document.getElementById('countdown').style.display = 'none';
                        document.getElementById('loader').style.display = 'block';
                        
                        // Si se acaba el tiempo, intentamos recargar suavemente
                        if (!isPolling) {
                            isPolling = true;
                            pollStatus();
                        }
                        return;
                    }

                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    document.getElementById('hours').innerText = pad(hours);
                    document.getElementById('minutes').innerText = pad(minutes);
                    document.getElementById('seconds').innerText = pad(seconds);
                }, 1000);

                // Función para verificar silenciosamente si ya se levantó el mantenimiento
                function pollStatus() {
                    setInterval(() => {
                        fetch(window.location.href, { method: 'HEAD' })
                            .then(response => {
                                // Si el servidor devuelve 200, significa que ya no está en 503
                                if (response.status === 200) {
                                    window.location.reload();
                                }
                            }).catch(err => {
                                // Ignorar errores silenciosamente
                            });
                    }, 5000);
                }
            </script>
        @else
            <div class="loader" style="display: block;">
                Mantenimiento en curso...
            </div>
            <script>
                // Si no hay tiempo estimado, recarga silenciosa cada 15 segs
                setInterval(() => {
                    fetch(window.location.href, { method: 'HEAD' })
                        .then(response => {
                            if (response.status === 200) {
                                window.location.reload();
                            }
                        }).catch(e => {});
                }, 15000);
            </script>
        @endif


    </div>
</body>
</html>

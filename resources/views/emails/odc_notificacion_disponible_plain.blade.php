@php
    $appUrl = config('app.url');
    if (empty($appUrl) || str_contains($appUrl, '.test') || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'logistica.suraki.net')) {
        $appUrl = 'https://citsur.suraki.net';
    }
    $appUrl = rtrim($appUrl, '/');
    
    $yaRegistrado = $info->ya_registrado ?? false;

    if ($yaRegistrado) {
        $linkAcceso = $appUrl . '/login';
    } else {
        $linkAcceso = $appUrl . '/setup-proveedor?rif=' . urlencode($info->username) . '&email=' . urlencode($info->email_destino) . '&name=' . urlencode($info->vendedor_nombre ?? $info->proveedor);
    }
@endphp
Estimado(a) {{ $info->vendedor_nombre ?? $info->proveedor }}:

Le saludamos cordialmente desde el equipo de Recepción y Logística de Hipersuraki.

Le informamos que su Orden de Compra ya se encuentra disponible en la plataforma para programar su cita de entrega.

Por favor recargue la página en su navegador (presionando F5 o refrescando la pantalla) para visualizar sus órdenes de compra habilitadas.

Detalles de la Orden:
- Orden de Compra: {{ $info->numero_oc }}
- Empresa / Proveedor: {{ $info->proveedor }}
- Usuario / RIF de Acceso: {{ $info->username }}
- Correo Notificado: {{ $info->email_destino }}

Para acceder al portal, utilice el siguiente enlace:
{{ $linkAcceso }}

Si por alguna razón después de recargar la página no le aparece su orden de compra, comuníquese directamente con su comprador asignado para verificar el estatus de habilitación.

💡 IMPORTANTE PARA ASEGURAR FUTURAS NOTIFICACIONES:
Revisa tu Bandeja de Entrada o tu carpeta de Correo no Deseado. Si este mensaje te cayó en Correo no Deseado, dale clic derecho y selecciona "Marcar como correo seguro / No es correo no deseado" para que todas las futuras órdenes te entren directo a la bandeja principal.

---
Departamento de Recepción y Logística - Hipersuraki
Este es un mensaje automático institucional.

@php
    $appUrl = config('app.url');
    if (empty($appUrl) || str_contains($appUrl, '.test') || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'logistica.suraki.net')) {
        $appUrl = 'https://citsur.suraki.net';
    }
    $appUrl = rtrim($appUrl, '/');
    $linkAcceso = $appUrl . '/setup-proveedor?rif=' . urlencode($info->username) . '&email=' . urlencode($info->email_destino) . '&name=' . urlencode($info->vendedor_nombre ?? $info->proveedor);
@endphp
Hola, {{ $info->vendedor_nombre ?? $info->proveedor }}:

Le informamos que el comprador {{ $info->comprador_nombre ?? 'Comprador' }} ha habilitado una Orden de Compra para su despacho en Hipersuraki.

Ya puede ingresar a nuestro portal para completar los datos de despacho y reservar su cita de recepción:

- Orden de Compra: {{ $info->numero_oc }}
- Proveedor: {{ $info->proveedor }}
- Usuario / RIF: {{ $info->username }}
- Correo Notificado: {{ $info->email_destino }}

Para establecer su contraseña y acceder directamente al portal, ingrese al siguiente enlace:
{{ $linkAcceso }}

Por favor asegúrese de tener a mano el número de factura y el peso real de su carga para poder agendar su cita correctamente.

📖 Manual de Usuario Ilustrado (Paso a Paso en PDF):
{{ $appUrl }}/Manual_Usuario_Portal_Proveedor_CITSUR.pdf

💡 IMPORTANTE PARA ASEGURAR FUTURAS NOTIFICACIONES:
Revisa tu Bandeja de Entrada o tu carpeta de Correo no Deseado. Si este mensaje te cayó en Correo no Deseado, dale clic derecho y selecciona "Marcar como correo seguro / No es correo no deseado" para que todas las futuras órdenes te entren directo a la bandeja principal.

---
Departamento de Logística y Recepción - Hipersuraki
Este es un mensaje automático institucional. Por favor no responda a este correo.

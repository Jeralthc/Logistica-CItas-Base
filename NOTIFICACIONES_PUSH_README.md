# 📌 Guía Completa de Notificaciones Push y Telegram - Suraki HelpDesk

Este documento resume todos los componentes, configuraciones y la arquitectura técnica implementada para lograr el funcionamiento de las **Notificaciones Push en Navegadores (Móviles y PC)** y las **Notificaciones Automáticas en Telegram**.

---

## 🚀 1. Arquitectura de Notificaciones Implementada

El sistema utiliza un esquema omnicanal dividido en 3 niveles:

| Canal | Tecnología / Protocolo | Alcance | Frecuencia / Latencia |
| :--- | :--- | :--- | :--- |
| **Telegram Bot** | Telegram Bot API + HTTP POST (cURL) | Grupo de Telegram *Sistemas Suraki* | **Instantáneo (1 a 2s)** |
| **Push Navegador / Móvil** | Service Worker (`firebase-messaging-sw.js`) + Notification API | Android, iOS, Windows, Mac | **En tiempo real** |
| **Campanita Interna (In-App)** | Livewire 3 (`wire:poll.15s.visible`) | Panel Web de Usuarios y Administradores | **15s (Sólo pestañas activas)** |

---

## 🤖 2. Integración del Bot de Telegram

### Configuración
- **Bot Username:** `@Report_Suraki_IT_Bot`
- **Bot Token:** `8646858864:AAHrnNtBw19jjupBJNjewZmsSBAYxxwf1wQ`
- **Telegram Chat ID:** `-5035415559` (Grupo *Sistemas Suraki*)

### Archivos Involucrados:
- [app/Services/TelegramService.php](file:///c:/laragon/www/suraki-helpdesk/app/Services/TelegramService.php): Contiene el método `sendTicketNotification($ticket, $type)` para construir y enviar el mensaje formateado en HTML.
- [app/Observers/TicketObserver.php](file:///c:/laragon/www/suraki-helpdesk/app/Observers/TicketObserver.php): Escucha los eventos `created` y `updated` de los tickets y dispara el envío automático a Telegram.

---

## 📲 3. Notificaciones Push Móviles y Web con Firebase

### Configuración del Proyecto Firebase
- **SDK Instalado:** `firebase` vía NPM.
- **Project ID:** `surakihelpdesk`
- **Sender ID:** `1055509216576`
- **App ID:** `1:1055509216576:web:4e4bf1f8eb7a4fce254ea5`

### Archivos Creados y Modificados:
1. **[public/firebase-messaging-sw.js](file:///c:/laragon/www/suraki-helpdesk/public/firebase-messaging-sw.js):**
   Service Worker en segundo plano encargado de interceptar y mostrar la notificación emergente del sistema operativo en celulares y PCs incluso si el navegador está minimizado.
2. **[resources/views/layouts/app.blade.php](file:///c:/laragon/www/suraki-helpdesk/resources/views/layouts/app.blade.php) & [guest.blade.php](file:///c:/laragon/www/suraki-helpdesk/resources/views/layouts/guest.blade.php):**
   - Registro automático del Service Worker `navigator.serviceWorker.register('/firebase-messaging-sw.js')`.
   - Receptor de eventos JavaScript `window.addEventListener('browser-push', ...)` que invoca `reg.showNotification(...)` para compatibilidad nativa con Android y iOS.
   - Banner interactivo flotante en móviles para solicitar permisos mediante un toque de usuario.

---

## 🛡️ 4. Optimizaciones de Rendimiento y Anti-DOS

Para evitar sobrecargas en la base de datos MySQL cuando hay múltiples usuarios en la plataforma:
- **Directiva `.visible`:** En [notification-bell.blade.php](file:///c:/laragon/www/suraki-helpdesk/resources/views/livewire/layout/notification-bell.blade.php) se utiliza `wire:poll.15s.visible="loadNotifications"`.
- **Suspensión Automática:** Si el usuario minimiza el navegador, bloquea el celular o cambia a otra pestaña, **Livewire detiene automáticamente todas las consultas a la base de datos**.

---

## 🔒 5. Políticas de Seguridad (CSP - Content Security Policy)

En [app/Http/Middleware/AddContentSecurityPolicyHeaders.php](file:///c:/laragon/www/suraki-helpdesk/app/Http/Middleware/AddContentSecurityPolicyHeaders.php) se actualizaron las cabeceras para permitir comunicaciones de fondo sin bloqueos de navegador:
```php
connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://*.firebaseio.com https://fcmregistrations.googleapis.com https://firebaseinstallations.googleapis.com https://*.googleapis.com;
```

---

## 🧪 6. Pruebas y Diagnóstico

1. **Botón "🧪 Probar Push":**
   Ubicado en el menú desplegable de la campanita de notificaciones en el panel web. Al hacer clic, dispara inmediatamente un aviso Push flotante de prueba al dispositivo conectado.
2. **Comando cURL de Prueba para Telegram:**
   ```bash
   curl.exe -X POST https://api.telegram.org/bot8646858864:AAHrnNtBw19jjupBJNjewZmsSBAYxxwf1wQ/sendMessage -d "chat_id=-5035415559" -d "text=🔔 Prueba de Notificación" -d "parse_mode=HTML"
   ```

---
*Documento generado automáticamente para el equipo de desarrollo y sistemas de Suraki.*

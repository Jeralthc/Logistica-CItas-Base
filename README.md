# Logística Suraki - Sistema de Gestión de Citas y Recepción (Monitor ODC)

Un ecosistema avanzado, desarrollado sobre **Laravel 11, Vue.js 3 e Inertia.js**, diseñado para centralizar, controlar y sincronizar el flujo logístico de recepción de mercancía y la asignación de citas (ventanas horarias) a proveedores de manera bidireccional con el ERP central corporativo.

---

## 🏗 Arquitectura del Sistema

El proyecto sigue una arquitectura monolítica moderna impulsada por la SPA (Single Page Application) de Vue 3, alimentada por las rutas y el ORM de Laravel 11. 

### Stack Tecnológico:
- **Backend:** PHP 8.3, Laravel 11.x, Eloquent ORM.
- **Frontend:** Vue 3 (Composition API), Inertia.js, TailwindCSS.
- **Base de Datos:** MySQL / MariaDB.
- **Integraciones:** GuzzleHTTP (Consumo de API ERP).
- **Notificaciones:** Mailtrap/SMTP nativo de Laravel.

---

## 👥 Roles y Niveles de Acceso

El sistema cuenta con un control de acceso por roles (RBAC) estricto:

1. **Superadministrador (`admin`):** 
   - Acceso total a todas las funciones, módulos, auditorías, configuración del ERP y modo de mantenimiento.
2. **Recepción Logística (`receptor`):** 
   - Encargados del muelle. Tienen acceso al "Dashboard" para atender citas del día y al "Monitor ODC" para planificar y agendar a los proveedores.
3. **Comprador (`comprador`):** 
   - Personal de compras corporativas. Tienen la responsabilidad de buscar las Órdenes de Compra (ODC) sincronizadas y "Reservar Cita" para sus proveedores.
4. **Proveedor (`proveedor`):** 
   - Acceso limitado únicamente a visualizar sus citas programadas, descargar comprobantes, reprogramar o cancelar su propia visita (sujeto a validación temporal).

---

## ⚙️ Módulos Principales (Características y Flujos)

### 1. Monitor ODC (Centro de Control Logístico)
- **Visualización Integral:** Tabla dinámica que cruza la información sincronizada del ERP (Órdenes de Compra pendientes) con las Citas locales agendadas.
- **Asignación Rápida:** Los receptores pueden asignar la fecha, hora, duración y muelle/sucursal a una ODC directamente desde un modal.
- **Gestión de Operarios:** Permite asignar trabajadores físicos (caleteros/montacarguistas) a la descarga de la cita.
- **Búsqueda Reactiva:** Sistema de filtrado reactivo (Vue) que permite buscar instantáneamente por ODC, Proveedor, RIF o Estatus.

### 2. Dashboard de Recepción (Panel de Turnos)
- **Actualización en Tiempo Real:** El panel hace un auto-refresh (polling de 10-15s) silencioso para que el personal del muelle nunca tenga que presionar F5 y siempre vea a los camiones nuevos llegar.
- **Flujo de Recepción (Estados):**
  - *Programada* ➔ *En Muelle* (Llegó el camión) ➔ *Procesando* (Descarga) ➔ *Finalizada* / *Diferida* / *Devuelta*.
- **Alertas Visuales:** Las citas se tornan naranjas/rojas visualmente cuando el camión supera el tiempo estimado de descarga.

### 3. Sincronización ERP (Bidireccional)
- **Extracción Automática:** Mediante un script silencioso en Windows (`sync_runner.vbs`) y el comando de Laravel (`SyncErpData.php`), el sistema extrae cada 10-15 minutos las ODC nuevas del ERP (Endpoint `/api/ordenes-compra-pendientes`).
- **Token JWT Seguro:** Todo el intercambio de información con el ERP usa un token de autorización configurado desde el panel de `Configuración ERP`.

### 4. Motor de Reservaciones (Compradores)
- **Buscador de ODC:** El comprador busca la Orden de Compra sincronizada mediante su número exacto o RIF.
- **Formulario Inteligente:** Al asignar la cita, el sistema le obliga a registrar los datos de contacto del proveedor (asesor, teléfono, email) para crear automáticamente su usuario de acceso.
- **Prevención de Modificación:** Una vez que el Receptor modifica la cita, el sistema bloquea al Comprador para que no pueda deshacer ni cambiar la cita (Bitácora de seguridad).

### 5. Motor de Notificaciones por Correo Electrónico
- **Plantillas HTML Premium:** Correos estéticos y adaptables para "Nueva Cita", "Cita Cancelada" y "Cita Reprogramada".
- **Envío Dinámico Dual:** El sistema captura tanto el correo registrado del contacto del proveedor (`proveedor_contactos.email`) como el correo del comprador que creó la cita, y les notifica a ambos simultáneamente cualquier cambio (Cancelación/Reprogramación).
- **Protección Carbon/Date:** Se ha protegido el sistema de caídas de PHP causadas por variables de tipo "String" en la sumatoria de minutos de duración.

### 6. Centro de Alertas Internas (Campana)
- Una campana en la barra de navegación que alerta a Recepción y Admin sobre "Nuevas Citas Creadas".
- Animaciones "Pulse" y Toasts flotantes que saltan en la pantalla (esquina inferior derecha) cuando un proveedor agenda y muestran una barra de progreso de 8 segundos.

### 7. Auditoría Total (Tracking Transaccional)
- **Global Audit Log (`SystemAuditLog`):** Registra CADA clic que cambia información. Quien movió la cita, qué campo cambió, valores viejos vs valores nuevos.
- **Ruta Logística (`AppointmentRouteLog`):** Mantiene la historia de cómo la cita pasó de "Programada" a "Finalizada", guardando los timestamps de cada etapa del proceso en el muelle.

### 8. Mantenimiento Nivel Kernel (Bunker Mode)
- **Bloqueo Infranqueable:** Al activar el mantenimiento, se bloquea el núcleo HTTP usando `php artisan down`. Esto protege contra XSS, CSRF, inyección y secuestro de sesiones.
- **Reloj de Cuenta Regresiva:** Pantalla `503` moderna (`resources/views/errors/503.blade.php`) con Glassmorphism que lee el tiempo estimado en vivo (inyectado vía `maintenance_time.json`).
- **Auto-Refresh Inteligente:** Cuando el contador llega a 0, la vista hace "ping" (HEAD) al sistema. Si el mantenimiento fue desactivado, la pantalla se recarga sola para dejar entrar al usuario sin intervención manual.
- **Bypass Secreto:** Permite al superadministrador entrar mediante el enlace oculto `/suraki-admin`.

---

## 🗄 Estructura Principal de la Base de Datos

- `users` (Tabla de Autenticación, Usuarios y Roles)
- `appointments` (Tabla central. Almacena las citas, tiempos, estatus, muelle, duraciones, y métricas). Relacionada al ODC original de la tabla ODC local.
- `orden_compras` (Tabla espejo sincronizada del ERP).
- `proveedor_contactos` (Contactos que representan comercialmente a los proveedores).
- `notificaciones` (Registro de la campana del layout).
- `system_audit_logs` (Historial de todos los cambios de base de datos).
- `appointment_route_logs` (Historial cronológico de estatus de las citas).
- `operarios` (Lista de caleteros o montacarguistas).
- `appointment_operario` (Tabla pivote muchos-a-muchos).

---

## 🚀 Despliegue y Comandos de Consola (Internos)

- **Sincronización manual del ERP:**
  `php artisan erp:sync`
- **Revisión de Correos y Errores:**
  `php artisan mail:test`
- **Compilación de Vistas (Producción):**
  `npm run build`
- **Modo Mantenimiento CLI:**
  `php artisan down --secret="suraki-admin"` / `php artisan up`

> *"Logística Suraki ha sido diseñado para nunca perder el rastro de un camión, una cita o un cambio, automatizando la pesada tarea de cruzar órdenes corporativas con el patio físico de descarga."*

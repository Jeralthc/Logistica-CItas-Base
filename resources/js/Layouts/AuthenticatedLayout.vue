<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

const showingNavigationDropdown = ref(false);

// === MANTENIMIENTO ===
const showMaintenanceModal = ref(false);
const maintenanceEndTime = ref('');
const isMaintenanceActive = ref(false);

const loadMaintenanceStatus = async () => {
    try {
        const resp = await axios.get('/api/mantenimiento/estado');
        isMaintenanceActive.value = resp.data.is_down;
        if (resp.data.end_time) {
            maintenanceEndTime.value = resp.data.end_time.substring(0, 16);
        }
    } catch (e) {
        if (e.response?.status === 503) {
            window.location.reload();
            return;
        }
        console.error('Error cargando estado de mantenimiento', e);
    }
};

const toggleMaintenance = async () => {
    try {
        if (isMaintenanceActive.value) {
            await axios.post('/api/mantenimiento/desactivar');
            isMaintenanceActive.value = false;
            maintenanceEndTime.value = '';
            alert('Modo de mantenimiento DESACTIVADO');
            showMaintenanceModal.value = false;
        } else {
            if (!maintenanceEndTime.value) {
                alert('Debe seleccionar una fecha y hora.');
                return;
            }
            const resp = await axios.post('/api/mantenimiento/activar', { end_time: maintenanceEndTime.value });
            isMaintenanceActive.value = true;
            alert(`Modo de mantenimiento ACTIVADO.\nURL de acceso: ${resp.data.secret_url}`);
            showMaintenanceModal.value = false;
        }
    } catch (e) {
        console.error(e);
        alert(e.response?.data?.message || 'Error al cambiar estado de mantenimiento.');
    }
};

// === NOTIFICACIONES ===
const notificaciones = ref([]);
const totalNoLeidas = ref(0);
const mostrarPanelNotif = ref(false);
const toastVisible = ref(false);
const toastData = ref(null);
let toastTimer = null;
let intervaloSync = null;

const toastTheme = computed(() => {
    const t = toastData.value?.tipo;
    if (t === 'cancelada') {
        return {
            title: 'Cita Cancelada',
            iconBg: 'bg-indigo-600',
            iconShadow: 'shadow-indigo-600/30',
            textColor: 'text-indigo-400',
            btnBg: 'bg-indigo-600 hover:bg-indigo-500',
            progressBg: 'bg-indigo-600'
        };
    } else if (t === 'reprogramada') {
        return {
            title: 'Cita Reprogramada',
            iconBg: 'bg-amber-500',
            iconShadow: 'shadow-amber-500/30',
            textColor: 'text-amber-400',
            btnBg: 'bg-amber-500 hover:bg-amber-400',
            progressBg: 'bg-amber-500'
        };
    } else if (t === 'odc_habilitada') {
        return {
            title: '¡Orden Habilitada!',
            iconBg: 'bg-emerald-600',
            iconShadow: 'shadow-emerald-600/30',
            textColor: 'text-emerald-400',
            btnBg: 'bg-emerald-600 hover:bg-emerald-500',
            progressBg: 'bg-emerald-600'
        };
    } else {
        return {
            title: 'Nueva Cita Programada',
            iconBg: 'bg-blue-600',
            iconShadow: 'shadow-blue-600/30',
            textColor: 'text-blue-400',
            btnBg: 'bg-blue-600 hover:bg-blue-500',
            progressBg: 'bg-blue-600'
        };
    }
});

const mostrarToast = (notif) => {
    toastData.value = notif;
    toastVisible.value = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastVisible.value = false;
    }, 8000);
};

let isSyncing = false;
const sincronizarNotificaciones = async () => {
    if (isSyncing) return;
    isSyncing = true;
    try {
        const oldTotal = totalNoLeidas.value;
        // Sincronizar (ya no hace mucho pero mantenemos la ruta)
        await axios.post('/api/notificaciones/sincronizar');
        
        const resp = await axios.get('/api/notificaciones');
        notificaciones.value = resp.data.notificaciones;
        totalNoLeidas.value = resp.data.total_no_leidas;

        // Si hay más no leídas que antes, mostrar toast
        if (totalNoLeidas.value > oldTotal) {
            const nueva = notificaciones.value.find(n => !n.leida);
            if (nueva) {
                mostrarToast(nueva);
                // Lanzar evento global para que las tablas se actualicen en tiempo real
                window.dispatchEvent(new CustomEvent('actualizacion-tiempo-real'));
            }
        }
    } catch (e) {
        if (e.response?.status === 503) {
            // Ya no recargamos la página para no tumbar más el servidor
            console.error('Servidor en mantenimiento o colapsado (503)');
            return;
        }
        console.error('Error sincronizando notificaciones:', e);
    } finally {
        isSyncing = false;
    }
};

const marcarLeida = async (notif) => {
    if (notif.leida) return;
    try {
        notif.leida = true;
        totalNoLeidas.value = Math.max(0, totalNoLeidas.value - 1);
        await axios.post(`/api/notificaciones/${notif.id}/leer`);
        // Ya no eliminamos la notificación de la lista
    } catch (e) {
        console.error('Error:', e);
    }
};

const marcarTodasLeidas = async () => {
    try {
        await axios.post('/api/notificaciones/leer-todas');
        notificaciones.value.forEach(n => n.leida = true);
        totalNoLeidas.value = 0;
    } catch (e) {
        console.error('Error:', e);
    }
};

const notifModalData = ref(null);
const mostrarNotifModal = ref(false);
const cargandoNotifModal = ref(false);

const buscarOrdenDesdeNotif = async (notif) => {
    marcarLeida(notif);
    
    // Si es un proveedor y es solo habilitada, que vaya a reservar
    if (notif.tipo === 'odc_habilitada' && usePage().props.auth.user.role === 'proveedor') {
        mostrarPanelNotif.value = false;
        if (!route().current('reservar.index')) {
            router.visit(route('reservar.index'));
        }
        return;
    }

    // Para otros roles o notificaciones de cita, mostrar modal
    mostrarPanelNotif.value = false;
    mostrarNotifModal.value = true;
    cargandoNotifModal.value = true;
    notifModalData.value = null;

    try {
        const resp = await axios.get(`/api/citas/detalle/${notif.numero_oc}`);
        notifModalData.value = resp.data.cita;
    } catch (e) {
        console.error('Error fetching cita detail:', e);
        notifModalData.value = {
            numero_oc: notif.numero_oc,
            proveedor: notif.proveedor,
            fecha_cita: 'Pendiente por agendar',
            estatus: 'Habilitada',
            vendedor_nombre: '-',
            registrado_por: '-',
            muelle_asignado: '-',
            observaciones: 'Esta orden no tiene una cita agendada en el sistema actualmente.'
        };
    } finally {
        cargandoNotifModal.value = false;
    }
};

const cerrarNotifModal = () => {
    mostrarNotifModal.value = false;
    notifModalData.value = null;
};

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric' });
};

const tiempoRelativo = (fecha) => {
    if (!fecha) return '';
    const ahora = new Date();
    const f = new Date(fecha);
    const diff = ahora - f;
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Ahora';
    if (mins < 60) return `hace ${mins}m`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `hace ${hrs}h`;
    const dias = Math.floor(hrs / 24);
    return `hace ${dias}d`;
};

import { usePage } from '@inertiajs/vue3';

onMounted(() => {
    const role = usePage().props.auth.user.role;
    if (role === 'admin' || role === 'receptor' || role === 'proveedor') {
        sincronizarNotificaciones();
        // Polling cada 60 segundos para evitar colapsar el servidor
        intervaloSync = setInterval(sincronizarNotificaciones, 60000);
    }
    if (role === 'admin') {
        loadMaintenanceStatus();
    }
});

onUnmounted(() => {
    if (intervaloSync) clearInterval(intervaloSync);
});

// Cerrar panel al hacer clic fuera
const cerrarPanelSiFuera = (e) => {
    if (!e.target.closest('.notif-panel') && !e.target.closest('.notif-trigger')) {
        mostrarPanelNotif.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', cerrarPanelSiFuera);
});
onUnmounted(() => {
    document.removeEventListener('click', cerrarPanelSiFuera);
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Recepción
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                                    :href="route('monitor-odc')"
                                    :active="route().current('monitor-odc')"
                                >
                                    Monitor ODC
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                                    :href="route('operarios')"
                                    :active="route().current('operarios')"
                                >
                                    Operarios
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'comprador' || $page.props.auth.user.role === 'admin'"
                                    :href="route('reservar-cita')"
                                    :active="route().current('reservar-cita')"
                                >
                                    Reservar Cita
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'admin'"
                                    :href="route('auditoria')"
                                    :active="route().current('auditoria')"
                                >
                                    Auditoría
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'admin'"
                                    :href="route('configuracion-erp')"
                                    :active="route().current('configuracion-erp')"
                                >
                                    Configuración ERP
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'admin'"
                                    :href="route('despliegue')"
                                    :active="route().current('despliegue')"
                                >
                                    Control de Cambios
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.role === 'admin'"
                                    :href="route('categorias')"
                                    :active="route().current('categorias')"
                                >
                                    Categorías
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-4">

                            <!-- === CAMPANA DE NOTIFICACIONES === -->
                            <div class="relative" v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor' || $page.props.auth.user.role === 'proveedor'">
                                <button
                                    @click.stop="mostrarPanelNotif = !mostrarPanelNotif"
                                    class="notif-trigger relative p-2 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-all"
                                    title="Notificaciones"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                    <!-- Badge -->
                                    <span
                                        v-if="totalNoLeidas > 0"
                                        class="absolute -top-0.5 -right-0.5 bg-indigo-600 text-white text-[10px] font-black min-w-[18px] h-[18px] flex items-center justify-center rounded-full px-1 animate-pulse"
                                    >
                                        {{ totalNoLeidas > 99 ? '99+' : totalNoLeidas }}
                                    </span>
                                </button>

                                <!-- Panel desplegable de notificaciones -->
                                <Transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0 translate-y-1"
                                >
                                    <div
                                        v-show="mostrarPanelNotif"
                                        class="notif-panel absolute right-0 mt-3 w-[400px] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-50"
                                    >
                                        <!-- Header del panel -->
                                        <div class="bg-slate-900 px-5 py-4 flex items-center justify-between">
                                            <div>
                                                <h3 class="text-white font-bold text-sm">Centro de Notificaciones</h3>
                                                <p class="text-slate-400 text-xs mt-0.5">Nuevas citas programadas</p>
                                            </div>
                                            <button
                                                v-if="totalNoLeidas > 0"
                                                @click="marcarTodasLeidas"
                                                class="text-xs text-indigo-400 hover:text-indigo-300 font-bold transition-colors"
                                            >
                                                Leer todas
                                            </button>
                                        </div>

                                        <!-- Lista de notificaciones -->
                                        <div class="max-h-[360px] overflow-y-auto divide-y divide-slate-100">
                                            <div
                                                v-for="notif in notificaciones"
                                                :key="notif.id"
                                                class="px-5 py-3.5 transition-colors cursor-pointer group"
                                                :class="notif.leida ? 'hover:bg-slate-50 opacity-70 bg-white' : 'hover:bg-indigo-50/50 bg-white'"
                                                @click="buscarOrdenDesdeNotif(notif)"
                                            >
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <span v-if="!notif.leida" class="w-2 h-2 bg-indigo-500 rounded-full flex-shrink-0 animate-pulse"></span>
                                                            <span class="text-sm font-black text-slate-800 font-mono">{{ notif.numero_oc }}</span>
                                                            <span v-if="!notif.leida && notif.tipo === 'cancelada'" class="text-[10px] font-bold bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded">CANCELADA</span>
                                                            <span v-else-if="!notif.leida && notif.tipo === 'reprogramada'" class="text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">REPROGRAMADA</span>
                                                            <span v-else-if="!notif.leida && notif.tipo === 'odc_habilitada'" class="text-[10px] font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">ODC HABILITADA</span>
                                                            <span v-else-if="!notif.leida" class="text-[10px] font-bold bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">NUEVA CITA</span>
                                                        </div>
                                                        <p class="text-xs text-slate-600 font-semibold truncate">{{ notif.proveedor }}</p>
                                                        <p class="text-[10px] text-slate-400 mt-1">
                                                            📅 Registrada: {{ formatFecha(notif.fecha_oc) }}
                                                            <span v-if="notif.fecha_recepcion"> · 🚛 Cita: {{ formatFecha(notif.fecha_recepcion) }}</span>
                                                        </p>
                                                    </div>
                                                    <div class="text-right flex-shrink-0 flex flex-col items-end gap-2">
                                                        <span class="text-[10px] text-slate-400 font-medium">{{ tiempoRelativo(notif.created_at) }}</span>
                                                        <div class="flex items-center gap-2">
                                                            <button 
                                                                v-if="!notif.leida" 
                                                                @click.stop="marcarLeida(notif)" 
                                                                class="p-1 text-slate-300 hover:text-emerald-500 hover:bg-emerald-50 rounded transition-colors"
                                                                title="Marcar como leída">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                            </button>
                                                            <p class="text-[10px] text-blue-500 font-bold opacity-0 group-hover:opacity-100 transition-opacity">Detalles →</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Sin notificaciones -->
                                            <div v-if="notificaciones.length === 0" class="px-5 py-10 text-center">
                                                <div class="text-3xl mb-2">✅</div>
                                                <p class="text-slate-400 text-sm font-medium">No hay citas nuevas</p>
                                                <p class="text-slate-300 text-xs mt-1">Las notificaciones aparecerán aquí automáticamente</p>
                                            </div>
                                        </div>

                                        <!-- Footer -->
                                        <div class="bg-slate-50 border-t border-slate-200 px-5 py-2.5 text-center">
                                            <p class="text-[10px] text-slate-400 font-medium">Sincronización automática cada 15 segundos</p>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <button
                                            v-if="$page.props.auth.user.role === 'admin'"
                                            @click="showMaintenanceModal = true"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                        >
                                            <span class="flex items-center text-indigo-600 font-bold">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                Mantenimiento
                                            </span>
                                        </button>
                                        <DropdownLink :href="route('profile.edit')">
                                            Perfil
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Cerrar Sesión
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                            :href="route('dashboard')" :active="route().current('dashboard')">
                            Recepción
                        </ResponsiveNavLink>
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                            :href="route('monitor-odc')" :active="route().current('monitor-odc')">
                            Monitor ODC
                        </ResponsiveNavLink>
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                            :href="route('operarios')" :active="route().current('operarios')">
                            Operarios
                        </ResponsiveNavLink>
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'comprador' || $page.props.auth.user.role === 'admin'"
                            :href="route('reservar-cita')" :active="route().current('reservar-cita')">
                            Reservar Cita
                        </ResponsiveNavLink>
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('auditoria')" :active="route().current('auditoria')">
                            Auditoría
                        </ResponsiveNavLink>
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('configuracion-erp')" :active="route().current('configuracion-erp')">
                            Configuración ERP
                        </ResponsiveNavLink>
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('despliegue')" :active="route().current('despliegue')">
                            Control de Cambios
                        </ResponsiveNavLink>
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('categorias')" :active="route().current('categorias')">
                            Categorías
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Perfil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                Cerrar Sesión
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>

        <!-- TOAST NOTIFICATION (Fase 6) -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="toastVisible && toastData" class="fixed bottom-5 right-5 z-[100] max-w-sm w-full bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 overflow-hidden pointer-events-auto">
                <div class="p-4 flex items-start gap-4">
                    <div class="flex-shrink-0 pt-0.5">
                        <div :class="[toastTheme.iconBg, toastTheme.iconShadow]" class="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <svg v-if="toastData.tipo === 'cancelada'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            <svg v-else-if="toastData.tipo === 'reprogramada'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <svg v-else-if="toastData.tipo === 'odc_habilitada'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p :class="toastTheme.textColor" class="text-xs font-black uppercase tracking-widest">{{ toastTheme.title }}</p>
                        <p class="text-sm font-bold text-white mt-0.5">{{ toastData.numero_oc }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ toastData.proveedor }}</p>
                        <div class="mt-3 flex items-center gap-2">
                            <button @click="buscarOrdenDesdeNotif(toastData)" :class="toastTheme.btnBg" class="text-[10px] font-black text-white px-3 py-1.5 rounded-lg transition-colors uppercase">
                                Ver Detalle
                            </button>
                            <button @click="toastVisible = false" class="text-[10px] font-black text-slate-400 hover:text-white px-3 py-1.5 transition-colors uppercase">
                                Cerrar
                            </button>
                        </div>
                    </div>
                    <button @click="toastVisible = false" class="flex-shrink-0 text-slate-500 hover:text-slate-400 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div :class="toastTheme.progressBg" class="h-1 animate-progress"></div>
            </div>
        </Transition>

        <!-- MODAL DE MANTENIMIENTO -->
        <div v-if="showMaintenanceModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showMaintenanceModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-slide-up">
                <div class="bg-indigo-600 px-6 py-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <h3 class="text-white font-bold text-lg">Modo de Mantenimiento</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 mb-6">Bloquea el acceso al sistema. Todos los usuarios (excepto el superadmin con bypass) verán la pantalla de mantenimiento.</p>
                    
                    <div v-if="isMaintenanceActive" class="p-4 bg-indigo-50 border border-indigo-100 rounded-xl mb-4 text-center">
                        <p class="text-indigo-600 font-bold text-sm mb-1">¡EL SISTEMA ESTÁ EN MANTENIMIENTO!</p>
                        <p class="text-xs text-indigo-500 mb-2">Finaliza estimado: {{ maintenanceEndTime.replace('T', ' ') }}</p>
                    </div>

                    <div v-else class="mb-5">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Hora y Fecha Estimada de Regreso</label>
                        <input type="datetime-local" v-model="maintenanceEndTime" class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="text-xs text-slate-400 mt-1">Este tiempo se usará para el contador en la pantalla 503.</p>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button @click="showMaintenanceModal = false" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700">Cancelar</button>
                        <button 
                            @click="toggleMaintenance" 
                            :class="isMaintenanceActive ? 'bg-slate-800 hover:bg-slate-700' : 'bg-indigo-600 hover:bg-indigo-500'" 
                            class="px-5 py-2 text-sm font-bold text-white rounded-xl shadow-lg transition-colors"
                        >
                            {{ isMaintenanceActive ? 'Apagar Mantenimiento' : 'Activar Mantenimiento Seguro' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DETALLE DE NOTIFICACION -->
        <Modal :show="mostrarNotifModal" @close="cerrarNotifModal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
                    <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Detalles de la Cita
                    </h2>
                    <button @click="cerrarNotifModal" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div v-if="cargandoNotifModal" class="flex justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>

                <div v-else-if="notifModalData" class="space-y-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">Orden de Compra</p>
                            <p class="text-lg font-black text-slate-800 font-mono">{{ notifModalData.numero_oc }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">Estatus</p>
                            <span class="inline-block mt-0.5 text-xs font-bold px-2 py-1 rounded bg-blue-100 text-blue-700 uppercase">
                                {{ notifModalData.estatus }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs font-bold text-slate-500 uppercase">Proveedor</p>
                            <p class="text-sm font-semibold text-slate-700">{{ notifModalData.proveedor }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">Fecha de Cita</p>
                            <p class="text-sm font-semibold text-slate-800">{{ notifModalData.fecha_cita }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">Muelle</p>
                            <p class="text-sm font-semibold text-slate-800">{{ notifModalData.muelle_asignado }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">Vendedor/Asesor</p>
                            <p class="text-sm font-semibold text-slate-800">{{ notifModalData.vendedor_nombre }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">Atendido Por</p>
                            <p class="text-sm font-semibold text-slate-800">{{ notifModalData.registrado_por }}</p>
                        </div>
                    </div>

                    <div v-if="notifModalData.observaciones" class="bg-amber-50 p-3 rounded-lg border border-amber-100">
                        <p class="text-xs font-bold text-amber-700 uppercase mb-1">Observaciones</p>
                        <p class="text-sm text-amber-900">{{ notifModalData.observaciones }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button @click="cerrarNotifModal" class="px-5 py-2.5 bg-slate-800 text-white rounded-xl font-bold text-sm hover:bg-slate-700 transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </Modal>

    </div>
</template>

<style scoped>
@keyframes progress {
    from { width: 100%; }
    to { width: 0%; }
}
.animate-progress {
    animation: progress 8s linear forwards;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-up {
    animation: slideUp 0.3s ease-out forwards;
}
</style>


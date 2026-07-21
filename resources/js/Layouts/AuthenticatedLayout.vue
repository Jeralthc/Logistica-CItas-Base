<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

const showingSidebar = ref(true);

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
        await axios.post('/api/notificaciones/sincronizar');
        
        const resp = await axios.get('/api/notificaciones');
        notificaciones.value = resp.data.notificaciones;
        totalNoLeidas.value = resp.data.total_no_leidas;

        if (totalNoLeidas.value > oldTotal) {
            const nueva = notificaciones.value.find(n => !n.leida);
            if (nueva) {
                mostrarToast(nueva);
                window.dispatchEvent(new CustomEvent('actualizacion-tiempo-real'));
            }
        }
    } catch (e) {
        if (e.response?.status === 503) {
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
    
    if (notif.tipo === 'odc_habilitada' && usePage().props.auth.user.role === 'proveedor') {
        mostrarPanelNotif.value = false;
        if (!route().current('reservar.index')) {
            router.visit(route('reservar.index'));
        }
        return;
    }

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

onMounted(() => {
    const role = usePage().props.auth.user.role;
    if (role === 'admin' || role === 'receptor' || role === 'proveedor') {
        sincronizarNotificaciones();
        intervaloSync = setInterval(sincronizarNotificaciones, 60000);
    }
    if (role === 'admin') {
        loadMaintenanceStatus();
    }
});

onUnmounted(() => {
    if (intervaloSync) clearInterval(intervaloSync);
});

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
    <div class="min-h-screen bg-[#fcfbf8] text-[#1c1c1c] font-sans flex overflow-hidden">
        
        <!-- SIDEBAR DE NAVEGACIÓN (Estilo Lovable) -->
        <aside 
            class="w-64 border-r border-[#eef0eb] bg-white flex flex-col justify-between shrink-0 transition-transform duration-300 z-40 fixed md:static inset-y-0 left-0"
            :class="{ 'translate-x-0': showingSidebar, '-translate-x-full md:translate-x-0': !showingSidebar }"
        >
            <div class="flex flex-col flex-1 min-h-0">
                <!-- Sidebar Header -->
                <div class="h-16 flex items-center px-6 border-b border-[#eef0eb] gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-accent shadow-md">
                        <svg class="h-4.5 w-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold tracking-tight text-[#1c1c1c]">Logi<span class="text-[#888c80]">Sync</span><span class="text-primary font-black">.</span></span>
                </div>

                <!-- Navigation Links grouped -->
                <nav class="flex-1 px-4 py-6 space-y-7 overflow-y-auto">
                    
                    <!-- Grupo 1: Operativo -->
                    <div class="space-y-2" v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor' || $page.props.auth.user.role === 'proveedor' || $page.props.auth.user.role === 'comprador'">
                        <p class="text-[10px] font-bold text-[#888c80] uppercase tracking-[0.2em] px-3 mb-3">Operativo</p>
                        
                        <Link 
                            v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor' || $page.props.auth.user.role === 'proveedor'"
                            :href="route('dashboard')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('dashboard') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">01</span>
                                Centro de Control
                            </span>
                        </Link>

                        <Link 
                            v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                            :href="route('monitor-odc')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('monitor-odc') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">02</span>
                                Órdenes de Compra
                            </span>
                        </Link>

                        <Link 
                            v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'receptor'"
                            :href="route('operarios')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('operarios') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">03</span>
                                Personal de Muelle
                            </span>
                        </Link>

                        <Link 
                            v-if="$page.props.auth.user.role === 'comprador' || $page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'proveedor'"
                            :href="route('reservar-cita')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('reservar-cita') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">04</span>
                                Programar Citas
                            </span>
                        </Link>
                    </div>

                    <!-- Grupo 2: Sistema (Solo Admin) -->
                    <div class="space-y-2" v-if="$page.props.auth.user.role === 'admin'">
                        <p class="text-[10px] font-bold text-[#888c80] uppercase tracking-[0.2em] px-3 mb-3">Sistema</p>
                        
                        <Link 
                            :href="route('auditoria')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('auditoria') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">05</span>
                                Bitácora de Sistema
                            </span>
                        </Link>

                        <Link 
                            :href="route('configuracion-erp')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('configuracion-erp') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">06</span>
                                Conector ERP
                            </span>
                        </Link>

                        <Link 
                            :href="route('despliegue')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('despliegue') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">07</span>
                                Historial de Versiones
                            </span>
                        </Link>

                        <Link 
                            :href="route('categorias')" 
                            class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-xl transition-all hover:translate-x-0.5 duration-200"
                            :class="route().current('categorias') ? 'bg-[#f5f6f2] text-primary font-semibold' : 'text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2]/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold opacity-60 font-mono">08</span>
                                Parámetros de Carga
                            </span>
                        </Link>
                    </div>
                </nav>
            </div>

            <!-- Profile Widget bottom -->
            <div class="p-4 border-t border-[#eef0eb] flex items-center justify-between gap-2 bg-[#fcfbf8]/40">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-primary font-mono text-sm shrink-0">
                        {{ $page.props.auth.user.name[0] }}
                    </div>
                    <div class="text-left min-w-0 leading-tight">
                        <Link :href="route('profile.edit')" class="text-xs font-bold text-[#1c1c1c] hover:underline truncate block">
                            {{ $page.props.auth.user.name }}
                        </Link>
                        <p class="text-[9px] text-[#888c80] uppercase font-black tracking-wider mt-0.5">{{ $page.props.auth.user.role }}</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-1 shrink-0">
                    <button
                        v-if="$page.props.auth.user.role === 'admin'"
                        @click="showMaintenanceModal = true"
                        class="p-2 text-[#888c80] hover:text-primary hover:bg-[#f5f6f2] rounded-xl transition-all"
                        title="Mantenimiento"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                    
                    <Link :href="route('logout')" method="post" as="button" class="p-2 text-[#888c80] hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all border border-transparent hover:border-rose-100" title="Cerrar Sesión">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- MAIN PANEL RIGHT -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto max-h-screen relative">
            
            <!-- Main Header -->
            <header class="h-16 border-b border-[#eef0eb] bg-white/70 backdrop-blur-xl flex items-center justify-between px-6 md:px-8 sticky top-0 z-30">
                <div class="flex items-center gap-4">
                    <!-- Hamburger button for mobile -->
                    <button @click="showingSidebar = !showingSidebar" class="md:hidden p-2 hover:bg-[#f5f6f2] rounded-xl text-[#6c7263]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    
                    <!-- App Sync Tag / Breadcrumbs -->
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[#888c80]">LogiSync</span>
                        <span class="h-3 w-[1px] bg-[#eef0eb]"></span>
                        <div class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100 flex items-center gap-1.5 shadow-sm">
                            <span class="h-1.5 w-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                            Sincronizado con ERP
                        </div>
                    </div>
                </div>

                <!-- Right Header Actions (Notificaciones) -->
                <div class="flex items-center gap-4">
                    <!-- CAMPANA DE NOTIFICACIONES -->
                    <div class="relative" v-if="['admin', 'receptor', 'proveedor'].includes($page.props.auth.user.role)">
                        <button
                            @click.stop="mostrarPanelNotif = !mostrarPanelNotif"
                            class="notif-trigger relative p-2 rounded-xl text-[#6c7263] hover:text-[#1c1c1c] hover:bg-[#f5f6f2] transition-all"
                        >
                            <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span
                                v-if="totalNoLeidas > 0"
                                class="absolute -top-0.5 -right-0.5 bg-primary text-white text-[9px] font-bold min-w-[17px] h-[17px] flex items-center justify-center rounded-full px-1 shadow-md animate-pulse"
                            >
                                {{ totalNoLeidas }}
                            </span>
                        </button>

                        <!-- Panel de Notificaciones -->
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
                                class="notif-panel absolute right-0 mt-3 w-96 bg-white rounded-2xl shadow-[0_20px_45px_rgba(0,0,0,0.1)] border border-[#eef0eb] overflow-hidden z-50"
                            >
                                <div class="bg-[#1c1c1c] px-5 py-4 flex items-center justify-between">
                                    <div>
                                        <h3 class="text-white font-bold text-sm">Centro de Notificaciones</h3>
                                        <p class="text-[#888c80] text-xs mt-0.5">Alertas y cambios en tiempo real</p>
                                    </div>
                                    <button
                                        v-if="totalNoLeidas > 0"
                                        @click="marcarTodasLeidas"
                                        class="text-xs text-primary hover:text-white font-bold transition-colors"
                                    >
                                        Leer todas
                                    </button>
                                </div>

                                <div class="max-h-[300px] overflow-y-auto divide-y divide-[#eef0eb]">
                                    <div
                                        v-for="notif in notificaciones"
                                        :key="notif.id"
                                        class="px-5 py-3.5 transition-colors cursor-pointer group hover:bg-[#fcfbf8]"
                                        :class="notif.leida ? 'opacity-65' : ''"
                                        @click="buscarOrdenDesdeNotif(notif)"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span v-if="!notif.leida" class="w-1.5 h-1.5 bg-primary rounded-full animate-pulse"></span>
                                                    <span class="text-sm font-bold text-[#1c1c1c] font-mono">{{ notif.numero_oc }}</span>
                                                    <span v-if="!notif.leida" class="text-[9px] font-black px-1.5 py-0.5 rounded bg-primary/10 text-primary">{{ notif.tipo.toUpperCase() }}</span>
                                                </div>
                                                <p class="text-xs text-[#6c7263] font-semibold truncate">{{ notif.proveedor }}</p>
                                            </div>
                                            <span class="text-[9px] text-[#888c80] font-medium">{{ tiempoRelativo(notif.created_at) }}</span>
                                        </div>
                                    </div>

                                    <div v-if="notificaciones.length === 0" class="px-5 py-8 text-center text-[#888c80]">
                                        <div class="text-2xl mb-2">🎉</div>
                                        <p class="text-sm font-medium">Estás al día</p>
                                        <p class="text-xs mt-0.5">No hay notificaciones sin leer.</p>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <!-- Page Main Content Slot -->
            <main class="flex-1 p-6 md:p-8">
                <slot />
            </main>
        </div>

        <!-- TOAST NOTIFICATION -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="toastVisible && toastData" class="fixed bottom-5 right-5 z-[100] max-w-sm w-full bg-white rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-[#eef0eb] overflow-hidden pointer-events-auto p-4 flex items-start gap-4">
                <div :class="toastTheme.iconBg" class="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-lg shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="flex-grow min-w-0">
                    <p class="text-xs font-bold text-primary uppercase tracking-wider">{{ toastTheme.title }}</p>
                    <p class="text-sm font-bold text-[#1c1c1c] mt-0.5">{{ toastData.numero_oc }}</p>
                    <p class="text-xs text-[#6c7263] truncate">{{ toastData.proveedor }}</p>
                    <div class="mt-3 flex gap-2">
                        <button @click="buscarOrdenDesdeNotif(toastData)" class="bg-[#1c1c1c] text-white hover:bg-zinc-800 text-[10px] font-bold px-3 py-1.5 rounded-lg transition-colors uppercase">Ver Detalle</button>
                        <button @click="toastVisible = false" class="text-[10px] text-[#6c7263] hover:text-[#1c1c1c] px-2 py-1.5 transition-colors uppercase">Cerrar</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- MODAL DE MANTENIMIENTO -->
        <Modal :show="showMaintenanceModal" @close="showMaintenanceModal = false">
            <div class="p-6">
                <h3 class="text-[#1c1c1c] font-bold text-lg mb-3">Modo de Mantenimiento</h3>
                <p class="text-sm text-[#6c7263] mb-6">Bloquea temporalmente el sistema para realizar mantenimientos técnicos.</p>
                
                <div v-if="isMaintenanceActive" class="p-4 bg-indigo-50 border border-indigo-100 rounded-xl mb-4 text-center">
                    <p class="text-indigo-600 font-bold text-sm">¡Mantenimiento Activo!</p>
                </div>

                <div v-else class="mb-5">
                    <label class="block text-sm font-bold text-[#6c7263] mb-1">Regreso Estimado</label>
                    <input type="datetime-local" v-model="maintenanceEndTime" class="w-full rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20">
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showMaintenanceModal = false" class="px-4 py-2 text-sm font-bold text-[#6c7263]">Cancelar</button>
                    <button @click="toggleMaintenance" class="bg-[#1c1c1c] hover:bg-zinc-800 text-white px-5 py-2 text-sm font-bold rounded-xl shadow-lg transition-colors">
                        {{ isMaintenanceActive ? 'Apagar Mantenimiento' : 'Activar Mantenimiento' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- MODAL DETALLE DE NOTIFICACION -->
        <Modal :show="mostrarNotifModal" @close="cerrarNotifModal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4 border-b border-[#eef0eb] pb-3">
                    <h2 class="text-lg font-bold text-[#1c1c1c] flex items-center gap-2">Detalles de la Cita</h2>
                    <button @click="cerrarNotifModal" class="text-[#6c7263] hover:text-[#1c1c1c]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div v-if="cargandoNotifModal" class="flex justify-center py-8">
                    <svg class="animate-spin h-7 w-7 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>

                <div v-else-if="notifModalData" class="space-y-4">
                    <div class="bg-[#fcfbf8] p-4 rounded-xl border border-[#eef0eb] grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-bold text-[#888c80] uppercase">Orden de Compra</p>
                            <p class="text-base font-bold text-[#1c1c1c] font-mono">{{ notifModalData.numero_oc }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-[#888c80] uppercase">Estatus</p>
                            <span class="inline-block mt-0.5 text-xs font-bold px-2 py-0.5 rounded bg-primary/10 text-primary uppercase">
                                {{ notifModalData.estatus }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[10px] font-bold text-[#888c80] uppercase">Proveedor</p>
                            <p class="text-sm font-semibold text-[#1c1c1c]">{{ notifModalData.proveedor }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-[10px] font-bold text-[#888c80] uppercase">Fecha de Cita</p>
                            <p class="font-semibold text-[#1c1c1c]">{{ notifModalData.fecha_cita }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-[#888c80] uppercase">Muelle</p>
                            <p class="font-semibold text-[#1c1c1c]">{{ notifModalData.muelle_asignado }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button @click="cerrarNotifModal" class="px-4 py-2 bg-[#1c1c1c] hover:bg-zinc-800 text-white rounded-xl font-bold text-xs">Cerrar</button>
                </div>
            </div>
        </Modal>
    </div>
</template>

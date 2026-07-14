<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const cargando = ref(true);
const ordenes = ref([]);
const errorMensaje = ref('');
const busquedaFiltro = ref(''); // Nuevo filtro
const filtroSucursal = ref('todas');
const filtroFechaInicio = ref('');
const filtroFechaFin = ref('');
const filtroEstado = ref('todas'); // 'todas', 'pendientes', 'completadas'
const pestanaActiva = ref('secos'); // Nuevo estado para pestañas
const subFiltroFruver = ref('todos'); // Sub-categorías para Fruver
const subFiltroPerecederos = ref('todos'); // Sub-categorías para Perecederos (Fase 5)
const autoRefresh = ref(true);
let refreshInterval = null;

const filasExpandidas = ref([]);

const toggleFila = (numero_oc) => {
    const index = filasExpandidas.value.indexOf(numero_oc);
    if (index === -1) {
        filasExpandidas.value.push(numero_oc);
    } else {
        filasExpandidas.value.splice(index, 1);
    }
};

// Diccionario de sucursales según DB
const destinos = {
    '0101': '01 PISO DE VENTA TU EMPRESA',
    '0102': '02 DEPOSITO GRAL TU EMPRESA'
};

const BASE_INTERVAL = 30000; // 30 segundos (base)
const MAX_INTERVAL = 300000; // 5 minutos (descanso máximo)
const IDLE_THRESHOLD = 5; // Chequeos sin cambios antes de subir el tiempo
let currentInterval = BASE_INTERVAL;
let noChangeCounter = 0;
let lastOrdersString = ''; // Para comparar si hay cambios

let isFetchingOrdenes = false;
const cargarOrdenes = async (showLoading = true) => {
    if (isFetchingOrdenes) return;
    isFetchingOrdenes = true;
    if (showLoading) cargando.value = true;
    try {
        const resp = await axios.get('/api/erp/ordenes-pendientes');
        const nuevasOrdenes = resp.data.ordenes || [];
        
        // Logica de Auto-descanso (Adaptive Polling)
        const currentString = nuevasOrdenes.length + '-' + (nuevasOrdenes[0]?.numero_oc || '');
        if (lastOrdersString !== currentString) {
            // Hay nueva actividad: resetear al tiempo rápido
            noChangeCounter = 0;
            currentInterval = BASE_INTERVAL;
            lastOrdersString = currentString;
        } else {
            // No hay cambios
            noChangeCounter++;
            if (noChangeCounter >= IDLE_THRESHOLD) {
                // Aumentar progresivamente hasta el máximo
                currentInterval = Math.min(MAX_INTERVAL, currentInterval + 30000);
            }
        }
        
        ordenes.value = nuevasOrdenes;
    } catch (e) {
        if (e.response?.status !== 503) {
            errorMensaje.value = e.response?.data?.error || e.response?.statusText || e.message || 'Error al cargar el monitor ODC.';
            console.error(e);
        }
    } finally {
        if (showLoading) cargando.value = false;
        isFetchingOrdenes = false;
    }
};

let refreshTimeout = null;
const startAutoRefresh = () => {
    stopAutoRefresh();
    const tick = async () => {
        if (autoRefresh.value) {
            await cargarOrdenes(false); // Actualización en segundo plano
        }
        refreshTimeout = setTimeout(tick, currentInterval);
    };
    refreshTimeout = setTimeout(tick, currentInterval);
};

const stopAutoRefresh = () => {
    if (refreshTimeout) clearTimeout(refreshTimeout);
};

const handleActualizacionTiempoReal = () => cargarOrdenes(false);

onMounted(() => {
    cargarOrdenes();
    startAutoRefresh();
    window.addEventListener('actualizacion-tiempo-real', handleActualizacionTiempoReal);
});

onUnmounted(() => {
    stopAutoRefresh();
    window.removeEventListener('actualizacion-tiempo-real', handleActualizacionTiempoReal);
});

const ordenesAgrupadas = computed(() => {
    const grupos = {};
    const terminoBusqueda = busquedaFiltro.value.toLowerCase().trim();

    ordenes.value.forEach(o => {
        // Filtrar por pestaña activa (asegurarse de tratar como número)
        if (pestanaActiva.value === 'secos' && Number(o.es_secos) === 0) return;
        
        if (pestanaActiva.value === 'perecederos') {
            if (Number(o.es_perecederos) === 0) return;
            // Sub-filtros Perecederos (Fase 5)
            if (subFiltroPerecederos.value === 'carnes' && Number(o.es_carnes) === 0) return;
            if (subFiltroPerecederos.value === 'charcuteria' && Number(o.es_charcuteria) === 0) return;
            if (subFiltroPerecederos.value === 'pescaderia' && Number(o.es_pescaderia) === 0) return;
            if (subFiltroPerecederos.value === 'congelados' && Number(o.es_congelados) === 0) return;
        }
        
        if (pestanaActiva.value === 'fruver') {
            if (Number(o.es_fruver) === 0) return;
            // Sub-filtros Fruver (Fase 4)
            if (subFiltroFruver.value === 'frutas' && Number(o.es_fruta) === 0) return;
            if (subFiltroFruver.value === 'verduras' && Number(o.es_verdura) === 0) return;
            if (subFiltroFruver.value === 'hortalizas' && Number(o.es_hortaliza) === 0) return;
        }

        // Filtrar por sucursal
        if (filtroSucursal.value !== 'todas' && o.destino !== filtroSucursal.value) return;

        // Filtrar por Estado (Pendiente vs Agendada/Completada)
        if (filtroEstado.value === 'completadas' && o.estatus_habilitacion !== 'agendada') return;
        if (filtroEstado.value === 'pendientes' && o.estatus_habilitacion === 'agendada') return;

        // Filtrar por rango de fechas (usando fecha de emisión d_FECHA)
        if (filtroFechaInicio.value || filtroFechaFin.value) {
            const fechaOC = new Date(o.fecha_emision).toISOString().split('T')[0];
            if (filtroFechaInicio.value && fechaOC < filtroFechaInicio.value) return;
            if (filtroFechaFin.value && fechaOC > filtroFechaFin.value) return;
        }

        // Lógica de filtrado de búsqueda
        const coincide = terminoBusqueda === '' || 
                         o.numero_oc.toLowerCase().includes(terminoBusqueda) || 
                         (o.proveedor && o.proveedor.toLowerCase().includes(terminoBusqueda));

        if (coincide) {
            if (!grupos[o.destino]) {
                grupos[o.destino] = {
                    codigo: o.destino,
                    nombre: destinos[o.destino] || `Destino ${o.destino}`,
                    ordenes: []
                };
            }
            grupos[o.destino].ordenes.push(o);
        }
    });
    // Convertir a array y ordenar por código de destino
    return Object.values(grupos).sort((a, b) => a.codigo.localeCompare(b.codigo));
});

const formatFecha = (f) => {
    if (!f) return '—';
    const d = new Date(f);
    // Para que no se modifique por zona horaria, limpiamos la cadena
    return d.toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' });
};

onMounted(cargarOrdenes);
</script>

<template>
    <Head title="Monitor ODC" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight flex items-center gap-2">
                    Monitor ODC
                    <span v-if="cargando && autoRefresh" class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                </h2>
                
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <!-- Filtros Avanzados -->
                    <div class="flex items-center gap-2">
                        <input v-model="filtroFechaInicio" type="date" class="block w-full sm:w-auto py-2 px-3 border border-slate-300 rounded-xl leading-5 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm" title="Fecha Desde">
                        <span class="text-slate-400">-</span>
                        <input v-model="filtroFechaFin" type="date" class="block w-full sm:w-auto py-2 px-3 border border-slate-300 rounded-xl leading-5 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm" title="Fecha Hasta">
                        
                        <select v-model="filtroSucursal" class="block w-full sm:w-auto py-2 px-3 border border-slate-300 rounded-xl leading-5 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                            <option value="todas">Todas las Sucursales</option>
                            <option v-for="(nombre, codigo) in destinos" :key="codigo" :value="codigo">
                                {{ nombre }}
                            </option>
                        </select>
                    </div>

                    <!-- Buscador en Vivo -->
                    <div class="relative w-full sm:w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input v-model="busquedaFiltro" type="text" placeholder="Buscar RIF, proveedor, orden..." class="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-xl leading-5 bg-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out shadow-sm">
                    </div>

                    <!-- Toggle Auto-Refresh (Fase 6) -->
                    <div class="flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-xl shadow-sm cursor-pointer hover:bg-slate-50 transition-colors shrink-0" @click="autoRefresh = !autoRefresh">
                        <div class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none" :class="autoRefresh ? 'bg-blue-600' : 'bg-slate-200'">
                            <span class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform" :class="autoRefresh ? 'translate-x-5' : 'translate-x-1'"></span>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-tight hidden lg:inline" :class="autoRefresh ? 'text-blue-600' : 'text-slate-400'">
                            {{ autoRefresh ? 'Live' : 'Pausa' }}
                        </span>
                    </div>

                    <button @click="cargarOrdenes" class="flex items-center gap-2 text-sm font-bold text-white bg-slate-800 px-4 py-2 rounded-xl hover:bg-slate-700 transition-colors shadow-lg shadow-slate-800/20 active:scale-95 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span class="hidden sm:inline">Actualizar</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- Pestañas de Categorización -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <button @click="pestanaActiva = 'secos'" 
                            :class="pestanaActiva === 'secos' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                            class="px-6 py-3 rounded-2xl font-bold text-sm transition-all flex items-center gap-2">
                        📦 Recepción (Secos)
                    </button>
                    <button @click="pestanaActiva = 'perecederos'" 
                            :class="pestanaActiva === 'perecederos' ? 'bg-rose-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                            class="px-6 py-3 rounded-2xl font-bold text-sm transition-all flex items-center gap-2">
                        🥩 Perecederos
                    </button>
                    <button @click="pestanaActiva = 'fruver'" 
                            :class="pestanaActiva === 'fruver' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                            class="px-6 py-3 rounded-2xl font-bold text-sm transition-all flex items-center gap-2">
                        🍎 Fruver
                    </button>
                </div>
                
                <!-- Sub-Filtros de Estado de Cita (Aplica para todas las áreas) -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <button @click="filtroEstado = 'todas'"
                            :class="filtroEstado === 'todas' ? 'bg-slate-800 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all">
                        📋 Mostrar Todas
                    </button>
                    <button @click="filtroEstado = 'pendientes'"
                            :class="filtroEstado === 'pendientes' ? 'bg-amber-500 text-white shadow-md' : 'bg-white text-amber-600 border border-amber-200 hover:bg-amber-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all"
                            title="Órdenes que aún no tienen una cita reservada por el proveedor">
                        ⏳ Pendientes por Agendar
                    </button>
                    <button @click="filtroEstado = 'completadas'"
                            :class="filtroEstado === 'completadas' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-emerald-600 border border-emerald-200 hover:bg-emerald-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all"
                            title="Órdenes que ya el proveedor agendó (tienen cita)">
                        ✅ Agendadas / Completadas
                    </button>
                </div>
                
                <!-- Sub-Filtros Perecederos (Solo si Perecederos está activo) -->
                <div v-if="pestanaActiva === 'perecederos'" class="flex flex-wrap gap-2 mb-6 bg-rose-50/50 p-4 rounded-3xl border border-rose-100 animate-in fade-in slide-in-from-top-2 duration-300">
                    <button @click="subFiltroPerecederos = 'todos'"
                            :class="subFiltroPerecederos === 'todos' ? 'bg-rose-600 text-white shadow-lg shadow-rose-200' : 'bg-white text-rose-600 border border-rose-200 hover:bg-rose-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        📑 Todos
                    </button>
                    <button @click="subFiltroPerecederos = 'carnes'"
                            :class="subFiltroPerecederos === 'carnes' ? 'bg-rose-600 text-white shadow-lg shadow-rose-200' : 'bg-white text-rose-600 border border-rose-200 hover:bg-rose-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        🥩 Carnes / Aves
                    </button>
                    <button @click="subFiltroPerecederos = 'charcuteria'"
                            :class="subFiltroPerecederos === 'charcuteria' ? 'bg-amber-500 text-white shadow-lg shadow-amber-200' : 'bg-white text-amber-600 border border-amber-200 hover:bg-amber-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        🧀 Charcutería / Lácteos
                    </button>
                    <button @click="subFiltroPerecederos = 'pescaderia'"
                            :class="subFiltroPerecederos === 'pescaderia' ? 'bg-blue-500 text-white shadow-lg shadow-blue-200' : 'bg-white text-blue-600 border border-blue-200 hover:bg-blue-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        🐟 Pescadería
                    </button>
                    <button @click="subFiltroPerecederos = 'congelados'"
                            :class="subFiltroPerecederos === 'congelados' ? 'bg-cyan-600 text-white shadow-lg shadow-cyan-200' : 'bg-white text-cyan-600 border border-cyan-200 hover:bg-cyan-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        ❄️ Congelados
                    </button>
                </div>
                
                <!-- Sub-Filtros Fruver (Solo si Fruver está activo) -->
                <div v-if="pestanaActiva === 'fruver'" class="flex flex-wrap gap-2 mb-6 bg-emerald-50/50 p-4 rounded-3xl border border-emerald-100 animate-in fade-in slide-in-from-top-2 duration-300">
                    <button @click="subFiltroFruver = 'todos'"
                            :class="subFiltroFruver === 'todos' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200' : 'bg-white text-emerald-600 border border-emerald-200 hover:bg-emerald-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        📑 Todos Fruver
                    </button>
                    <button @click="subFiltroFruver = 'frutas'"
                            :class="subFiltroFruver === 'frutas' ? 'bg-orange-500 text-white shadow-lg shadow-orange-200' : 'bg-white text-orange-600 border border-orange-200 hover:bg-orange-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        🍎 Frutas
                    </button>
                    <button @click="subFiltroFruver = 'verduras'"
                            :class="subFiltroFruver === 'verduras' ? 'bg-green-600 text-white shadow-lg shadow-green-200' : 'bg-white text-green-600 border border-green-200 hover:bg-green-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        🥦 Verduras
                    </button>
                    <button @click="subFiltroFruver = 'hortalizas'"
                            :class="subFiltroFruver === 'hortalizas' ? 'bg-lime-600 text-white shadow-lg shadow-lime-200' : 'bg-white text-lime-600 border border-lime-200 hover:bg-lime-50'"
                            class="px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                        🥬 Hortalizas
                    </button>
                </div>
                
                <div v-if="cargando" class="text-center py-20 text-slate-400 font-bold">
                    <svg class="animate-spin h-8 w-8 mx-auto mb-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Consultando VAD10...
                </div>

                <div v-else-if="errorMensaje" class="bg-red-50 text-red-600 p-6 rounded-2xl border border-red-100 font-bold text-center">
                    ⚠️ {{ errorMensaje }}
                </div>

                <div v-else-if="ordenes.length === 0" class="text-center py-20">
                    <div class="text-6xl mb-4">🎉</div>
                    <h3 class="text-2xl font-black text-slate-800">¡Todo al día!</h3>
                    <p class="text-slate-500">No hay órdenes de compra pendientes (DPE/DCO) en el ERP.</p>
                </div>

                <template v-else>
                    <div v-for="grupo in ordenesAgrupadas" :key="grupo.codigo" class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100">
                        <!-- Cabecera del destino -->
                        <div class="bg-slate-900 px-8 py-4 flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <span class="bg-blue-600 text-white font-black px-3 py-1 rounded-lg text-sm tracking-widest">{{ grupo.codigo }}</span>
                                <h3 class="text-white font-bold text-lg">{{ grupo.nombre }}</h3>
                            </div>
                            <span class="bg-white/10 text-white text-xs font-bold px-3 py-1 rounded-full">
                                {{ grupo.ordenes.length }} ODC
                            </span>
                        </div>

                        <!-- Lista de órdenes -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th class="px-8 py-4">Orden</th>
                                        <th class="px-6 py-4">Proveedor</th>
                                        <th class="px-6 py-4">F. Emisión</th>
                                        <th class="px-6 py-4">F. Recepción</th>
                                        <th class="px-6 py-4">Estatus</th>
                                        <th v-if="pestanaActiva === 'secos'" class="px-6 py-4 text-center">Bultos</th>
                                        <th v-if="pestanaActiva === 'perecederos'" class="px-6 py-4 text-center">KG</th>
                                        <th v-if="pestanaActiva === 'fruver'" class="px-6 py-4 text-center">KG / UND</th>
                                        <th class="px-6 py-4 text-center">SKUs</th>
                                        <th class="px-8 py-4 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template v-for="odc in grupo.ordenes" :key="odc.numero_oc">
                                        <tr @click="toggleFila(odc.numero_oc)"
                                            class="hover:bg-blue-50/30 transition-colors group cursor-pointer"
                                            :class="{'bg-red-50/50': Number(odc.es_fruver) === 1, 'bg-slate-50': filasExpandidas.includes(odc.numero_oc)}">
                                        
                                        <td class="px-8 py-4">
                                            <div class="flex items-center gap-2">
                                                <span class="font-black text-slate-800 font-mono text-base">{{ odc.numero_oc }}</span>
                                                <!-- Alerta Depto 14 (Fase 3) -->
                                                <span v-if="Number(odc.es_fruver) === 1" class="bg-red-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded uppercase animate-pulse" title="Contiene productos del Departamento 14">
                                                    DEPTO 14
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 font-bold text-slate-700 max-w-xs truncate" :title="odc.proveedor">
                                            {{ odc.proveedor }}
                                        </td>
                                        
                                        <td class="px-6 py-4 text-slate-500">{{ formatFecha(odc.fecha_emision) }}</td>
                                        <td class="px-6 py-4 text-slate-500 font-medium">{{ formatFecha(odc.fecha_recepcion) }}</td>
                                        
                                        <td class="px-6 py-4">
                                            <span v-if="odc.estatus_habilitacion === 'agendada'" class="bg-emerald-100 text-emerald-700 font-black px-2 py-1 rounded text-[10px] uppercase">Agendada</span>
                                            <span v-else-if="odc.estatus_habilitacion === 'habilitada'" class="bg-blue-100 text-blue-700 font-black px-2 py-1 rounded text-[10px] uppercase">Habilitada</span>
                                            <span v-else class="bg-slate-100 text-slate-600 font-black px-2 py-1 rounded text-[10px] uppercase">Pendiente</span>
                                        </td>
                                        
                                        <td v-if="pestanaActiva === 'secos'" class="px-6 py-4 text-center">
                                            <span class="bg-slate-100 text-slate-700 font-black px-2.5 py-1 rounded-lg">{{ Math.round(odc.total_bultos || 0) }}</span>
                                        </td>
                                        <td v-if="pestanaActiva === 'perecederos'" class="px-6 py-4 text-center">
                                            <div class="flex flex-col items-center gap-1">
                                                <!-- Lógica Dinámica de Totales según Sub-filtro (Fase 5) -->
                                                <template v-if="subFiltroPerecederos === 'carnes' || subFiltroPerecederos === 'todos'">
                                                    <span v-if="parseFloat(odc.total_kg_carnes) > 0" class="bg-rose-100 text-rose-700 font-black px-2 text-[10px] py-0.5 rounded-md">🥩 {{ parseFloat(odc.total_kg_carnes).toFixed(2) }} kg</span>
                                                    <span v-if="parseFloat(odc.total_und_carnes) > 0" class="bg-rose-50 text-rose-600 font-black px-2 text-[10px] py-0.5 rounded-md">🥩 {{ Math.round(odc.total_und_carnes) }} und</span>
                                                </template>
                                                <template v-if="subFiltroPerecederos === 'charcuteria' || subFiltroPerecederos === 'todos'">
                                                    <span v-if="parseFloat(odc.total_kg_charcuteria) > 0" class="bg-amber-100 text-amber-700 font-black px-2 text-[10px] py-0.5 rounded-md">🧀 {{ parseFloat(odc.total_kg_charcuteria).toFixed(2) }} kg</span>
                                                    <span v-if="parseFloat(odc.total_und_charcuteria) > 0" class="bg-amber-50 text-amber-600 font-black px-2 text-[10px] py-0.5 rounded-md">🧀 {{ Math.round(odc.total_und_charcuteria) }} und</span>
                                                </template>
                                                <template v-if="subFiltroPerecederos === 'pescaderia' || subFiltroPerecederos === 'todos'">
                                                    <span v-if="parseFloat(odc.total_kg_pescaderia) > 0" class="bg-blue-100 text-blue-700 font-black px-2 text-[10px] py-0.5 rounded-md">🐟 {{ parseFloat(odc.total_kg_pescaderia).toFixed(2) }} kg</span>
                                                    <span v-if="parseFloat(odc.total_und_pescaderia) > 0" class="bg-blue-50 text-blue-600 font-black px-2 text-[10px] py-0.5 rounded-md">🐟 {{ Math.round(odc.total_und_pescaderia) }} und</span>
                                                </template>
                                                <template v-if="subFiltroPerecederos === 'congelados' || subFiltroPerecederos === 'todos'">
                                                    <span v-if="parseFloat(odc.total_kg_congelados) > 0" class="bg-cyan-100 text-cyan-700 font-black px-2 text-[10px] py-0.5 rounded-md">❄️ {{ parseFloat(odc.total_kg_congelados).toFixed(2) }} kg</span>
                                                    <span v-if="parseFloat(odc.total_und_congelados) > 0" class="bg-cyan-50 text-cyan-600 font-black px-2 text-[10px] py-0.5 rounded-md">❄️ {{ Math.round(odc.total_und_congelados) }} und</span>
                                                </template>
                                                <span v-if="Number(odc.total_kg_carnes) + Number(odc.total_und_carnes) + Number(odc.total_kg_charcuteria) + Number(odc.total_und_charcuteria) + Number(odc.total_kg_pescaderia) + Number(odc.total_und_pescaderia) + Number(odc.total_kg_congelados) + Number(odc.total_und_congelados) === 0" class="text-slate-300">-</span>
                                            </div>
                                        </td>
                                        <td v-if="pestanaActiva === 'fruver'" class="px-6 py-4 text-center">
                                            <div class="flex flex-col items-center gap-1">
                                                <!-- Lógica Dinámica de Totales según Sub-filtro (Fase 4) -->
                                                <template v-if="subFiltroFruver === 'frutas' || subFiltroFruver === 'todos'">
                                                    <span v-if="parseFloat(odc.total_kg_frutas) > 0" class="bg-orange-100 text-orange-700 font-black px-2 text-[10px] py-0.5 rounded-md">🍎 {{ parseFloat(odc.total_kg_frutas).toFixed(2) }} kg</span>
                                                    <span v-if="parseFloat(odc.total_und_frutas) > 0" class="bg-orange-50 text-orange-600 font-black px-2 text-[10px] py-0.5 rounded-md">🍎 {{ Math.round(odc.total_und_frutas) }} und</span>
                                                </template>
                                                
                                                <template v-if="subFiltroFruver === 'verduras' || subFiltroFruver === 'todos'">
                                                    <span v-if="parseFloat(odc.total_kg_verduras) > 0" class="bg-green-100 text-green-700 font-black px-2 text-[10px] py-0.5 rounded-md">🥦 {{ parseFloat(odc.total_kg_verduras).toFixed(2) }} kg</span>
                                                    <span v-if="parseFloat(odc.total_und_verduras) > 0" class="bg-green-50 text-green-600 font-black px-2 text-[10px] py-0.5 rounded-md">🥦 {{ Math.round(odc.total_und_verduras) }} und</span>
                                                </template>

                                                <template v-if="subFiltroFruver === 'hortalizas' || subFiltroFruver === 'todos'">
                                                    <span v-if="parseFloat(odc.total_kg_hortalizas) > 0" class="bg-lime-100 text-lime-700 font-black px-2 text-[10px] py-0.5 rounded-md">🥬 {{ parseFloat(odc.total_kg_hortalizas).toFixed(2) }} kg</span>
                                                    <span v-if="parseFloat(odc.total_und_hortalizas) > 0" class="bg-lime-50 text-lime-600 font-black px-2 text-[10px] py-0.5 rounded-md">🥬 {{ Math.round(odc.total_und_hortalizas) }} und</span>
                                                </template>

                                                <span v-if="Number(odc.total_kg_frutas) + Number(odc.total_und_frutas) + Number(odc.total_kg_verduras) + Number(odc.total_und_verduras) + Number(odc.total_kg_hortalizas) + Number(odc.total_und_hortalizas) === 0" class="text-slate-300">-</span>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-center text-slate-500 font-medium">{{ odc.cant_productos }}</td>
                                        
                                        <td class="px-8 py-4 text-right">
                                            <Link v-if="$page.props.auth.user.role === 'comprador' || $page.props.auth.user.role === 'admin'"
                                                :href="route('reservar-cita')"
                                                class="text-blue-600 hover:text-blue-800 font-bold bg-blue-50 px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1 opacity-0 group-hover:opacity-100"
                                                title="Configurar y Habilitar esta orden para el Proveedor">
                                                Habilitar <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                            </Link>
                                            <span v-else class="text-slate-400 text-xs italic opacity-0 group-hover:opacity-100">Solo Lectura</span>
                                        </td>
                                    </tr>
                                    
                                    <!-- Fila Expandida: Detalles Básicos -->
                                    <tr v-if="filasExpandidas.includes(odc.numero_oc)" class="bg-slate-50 border-b-2 border-slate-200">
                                        <td colspan="9" class="px-8 py-6">
                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                                <div v-if="Number(odc.es_secos) === 1">
                                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Secos</h4>
                                                    <div class="space-y-1">
                                                        <div class="flex justify-between items-center text-[11px] font-bold pb-2 mb-2 border-b border-slate-100">
                                                            <span class="text-slate-500 uppercase">Total Bultos (Secos)</span>
                                                            <span class="font-black bg-slate-200 px-2 rounded text-slate-700">{{ Math.round(odc.total_bultos) }}</span>
                                                        </div>
                                                        <div v-if="Number(odc.total_bultos_viveres) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-amber-700">Víveres</span>
                                                            <span>{{ Math.round(odc.total_bultos_viveres) }} Bultos</span>
                                                        </div>
                                                        <div v-if="Number(odc.total_bultos_limpieza) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-teal-600">Limpieza</span>
                                                            <span>{{ Math.round(odc.total_bultos_limpieza) }} Bultos</span>
                                                        </div>
                                                        <div v-if="Number(odc.total_bultos_cuidado_personal) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-pink-600">Cuidado Personal</span>
                                                            <span>{{ Math.round(odc.total_bultos_cuidado_personal) }} Bultos</span>
                                                        </div>
                                                        <div v-if="Number(odc.total_bultos_licor_bebidas) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-purple-600">Licor/Bebidas</span>
                                                            <span>{{ Math.round(odc.total_bultos_licor_bebidas) }} Bultos</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Variedad (SKUs)</h4>
                                                    <div class="space-y-2">
                                                        <div class="flex justify-between items-center text-sm">
                                                            <span class="text-slate-600 font-bold">Cant. de SKUs</span>
                                                            <span class="font-black bg-slate-200 px-2 rounded">{{ odc.cant_productos }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div v-if="Number(odc.es_perecederos) === 1">
                                                    <h4 class="text-xs font-black text-rose-400 uppercase tracking-widest mb-3">Perecederos</h4>
                                                    <div class="space-y-1">
                                                        <div v-if="parseFloat(odc.total_kg_carnes) > 0 || parseFloat(odc.total_und_carnes) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-rose-600">Carnes/Aves</span>
                                                            <span>{{ parseFloat(odc.total_kg_carnes) > 0 ? parseFloat(odc.total_kg_carnes).toFixed(2) + ' kg' : Math.round(odc.total_und_carnes) + ' und' }}</span>
                                                        </div>
                                                        <div v-if="parseFloat(odc.total_kg_charcuteria) > 0 || parseFloat(odc.total_und_charcuteria) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-amber-600">Charcutería</span>
                                                            <span>{{ parseFloat(odc.total_kg_charcuteria) > 0 ? parseFloat(odc.total_kg_charcuteria).toFixed(2) + ' kg' : Math.round(odc.total_und_charcuteria) + ' und' }}</span>
                                                        </div>
                                                        <div v-if="parseFloat(odc.total_kg_pescaderia) > 0 || parseFloat(odc.total_und_pescaderia) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-blue-600">Pescadería</span>
                                                            <span>{{ parseFloat(odc.total_kg_pescaderia) > 0 ? parseFloat(odc.total_kg_pescaderia).toFixed(2) + ' kg' : Math.round(odc.total_und_pescaderia) + ' und' }}</span>
                                                        </div>
                                                        <div v-if="parseFloat(odc.total_kg_congelados) > 0 || parseFloat(odc.total_und_congelados) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-cyan-600">Congelados</span>
                                                            <span>{{ parseFloat(odc.total_kg_congelados) > 0 ? parseFloat(odc.total_kg_congelados).toFixed(2) + ' kg' : Math.round(odc.total_und_congelados) + ' und' }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-if="Number(odc.es_fruver) === 1">
                                                    <h4 class="text-xs font-black text-emerald-400 uppercase tracking-widest mb-3">Fruver</h4>
                                                    <div class="space-y-1">
                                                        <div v-if="parseFloat(odc.total_kg_frutas) > 0 || parseFloat(odc.total_und_frutas) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-orange-600">Frutas</span>
                                                            <span>{{ parseFloat(odc.total_kg_frutas) > 0 ? parseFloat(odc.total_kg_frutas).toFixed(2) + ' kg' : Math.round(odc.total_und_frutas) + ' und' }}</span>
                                                        </div>
                                                        <div v-if="parseFloat(odc.total_kg_verduras) > 0 || parseFloat(odc.total_und_verduras) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-green-600">Verduras</span>
                                                            <span>{{ parseFloat(odc.total_kg_verduras) > 0 ? parseFloat(odc.total_kg_verduras).toFixed(2) + ' kg' : Math.round(odc.total_und_verduras) + ' und' }}</span>
                                                        </div>
                                                        <div v-if="parseFloat(odc.total_kg_hortalizas) > 0 || parseFloat(odc.total_und_hortalizas) > 0" class="flex justify-between text-[11px] font-bold">
                                                            <span class="text-lime-600">Hortalizas</span>
                                                            <span>{{ parseFloat(odc.total_kg_hortalizas) > 0 ? parseFloat(odc.total_kg_hortalizas).toFixed(2) + ' kg' : Math.round(odc.total_und_hortalizas) + ' und' }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-if="odc.numero_factura">
                                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Factura de Entrega</h4>
                                                    <div class="space-y-2">
                                                        <div class="flex justify-between items-center text-sm">
                                                            <span class="text-slate-600 font-bold">Nº Factura</span>
                                                            <span class="font-mono font-bold text-slate-800">{{ odc.numero_factura }}</span>
                                                        </div>
                                                        <div v-if="odc.factura_url" class="mt-2 text-right">
                                                            <a :href="odc.factura_url" target="_blank"
                                                                class="inline-flex items-center gap-1 text-[10px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 px-2 py-1 rounded transition-colors shadow-sm">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                                Ver Factura
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

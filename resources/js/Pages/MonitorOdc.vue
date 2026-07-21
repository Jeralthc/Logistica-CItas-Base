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
    <Head title="Órdenes de Compra" />
    <AuthenticatedLayout>
        
        <div class="space-y-6 max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header and Actions (Moved from slot header to main page content) -->
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 border-b border-[#eef0eb] pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c] flex items-center gap-2.5">
                        Órdenes de Compra
                        <span v-if="cargando && autoRefresh" class="flex h-2 w-2 relative shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                        </span>
                    </h1>
                    <p class="text-xs text-[#6c7263] mt-1 font-medium">Supervisión en tiempo real de Órdenes de Compra pendientes en el ERP y estado de habilitación.</p>
                </div>
                
                <!-- Filters Panel -->
                <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    
                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="filtroFechaInicio" type="date" class="py-2 px-3 text-xs rounded-xl border-[#eef0eb] bg-[#faf9f6] focus:border-primary focus:ring-primary/20 text-[#6c7263] font-bold" title="Fecha Inicio">
                        <span class="text-[#888c80] text-xs font-bold">-</span>
                        <input v-model="filtroFechaFin" type="date" class="py-2 px-3 text-xs rounded-xl border-[#eef0eb] bg-[#faf9f6] focus:border-primary focus:ring-primary/20 text-[#6c7263] font-bold" title="Fecha Fin">
                        
                        <select v-model="filtroSucursal" class="py-2 px-3 text-xs rounded-xl border-[#eef0eb] bg-[#faf9f6] focus:border-primary focus:ring-primary/20 text-[#6c7263] font-bold bg-white">
                            <option value="todas">Todas las Sucursales</option>
                            <option v-for="(nombre, codigo) in destinos" :key="codigo" :value="codigo">
                                {{ nombre }}
                            </option>
                        </select>
                    </div>

                    <!-- Buscador -->
                    <div class="relative flex-grow sm:flex-grow-0 sm:w-56">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-[#888c80]">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </span>
                        <input v-model="busquedaFiltro" type="text" placeholder="Buscar ODC, proveedor..." class="block w-full pl-9 pr-3 py-2 border-[#eef0eb] rounded-xl text-xs bg-[#faf9f6] focus:border-primary focus:ring-primary/20 font-semibold">
                    </div>

                    <!-- Live Toggle -->
                    <div class="flex items-center gap-2 px-3 py-2 bg-white border border-[#eef0eb] rounded-xl cursor-pointer hover:bg-[#faf9f6] transition-colors shrink-0 select-none" @click="autoRefresh = !autoRefresh">
                        <div class="relative inline-flex h-3.5 w-7 items-center rounded-full transition-colors" :class="autoRefresh ? 'bg-primary' : 'bg-zinc-200'">
                            <span class="inline-block h-2 w-2 transform rounded-full bg-white transition-transform" :class="autoRefresh ? 'translate-x-4' : 'translate-x-1'"></span>
                        </div>
                        <span class="text-[8px] font-black uppercase tracking-wider" :class="autoRefresh ? 'text-primary' : 'text-[#888c80]'">
                            {{ autoRefresh ? 'Live' : 'Pausa' }}
                        </span>
                    </div>

                    <button @click="cargarOrdenes" class="flex items-center gap-1.5 text-xs font-bold text-white bg-[#1c1c1c] px-4 py-2 rounded-xl hover:bg-zinc-800 transition-all shadow-sm active:scale-95 whitespace-nowrap uppercase tracking-wider">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Actualizar
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-6">
                
                <!-- Pestañas de Categorización (Sleek layout tabs) -->
                <div class="flex gap-1 bg-[#faf9f6] p-1 rounded-xl w-fit border border-[#eef0eb]">
                    <button @click="pestanaActiva = 'secos'" 
                            :class="pestanaActiva === 'secos' ? 'bg-white text-primary shadow-sm font-extrabold border border-[#eef0eb]' : 'text-[#6c7263] hover:text-[#1c1c1c]'"
                            class="px-4 py-2 rounded-lg font-bold text-xs transition-all flex items-center gap-1.5">
                        📦 Secos
                    </button>
                    <button @click="pestanaActiva = 'perecederos'" 
                            :class="pestanaActiva === 'perecederos' ? 'bg-white text-rose-700 shadow-sm font-extrabold border border-rose-100' : 'text-[#6c7263] hover:text-rose-600'"
                            class="px-4 py-2 rounded-lg font-bold text-xs transition-all flex items-center gap-1.5">
                        🥩 Perecederos
                    </button>
                    <button @click="pestanaActiva = 'fruver'" 
                            :class="pestanaActiva === 'fruver' ? 'bg-white text-lime-700 shadow-sm font-extrabold border border-lime-100' : 'text-[#6c7263] hover:text-lime-600'"
                            class="px-4 py-2 rounded-lg font-bold text-xs transition-all flex items-center gap-1.5">
                        🍎 Fruver
                    </button>
                </div>
                
                <!-- Sub-Filtros de Estado de Cita -->
                <div class="flex flex-wrap gap-1.5">
                    <button @click="filtroEstado = 'todas'"
                        class="px-3.5 py-1.5 rounded-xl font-bold text-[9px] uppercase tracking-wider transition-all border"
                        :class="filtroEstado === 'todas' ? 'bg-[#1c1c1c] text-white border-[#1c1c1c]' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-[#faf9f6]'">
                        Mostrar Todas
                    </button>
                    <button @click="filtroEstado = 'pendientes'"
                        class="px-3.5 py-1.5 rounded-xl font-bold text-[9px] uppercase tracking-wider transition-all border"
                        :class="filtroEstado === 'pendientes' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-[#faf9f6]'"
                        title="Órdenes sin agendar">
                        ⏳ Pendientes por Agendar
                    </button>
                    <button @click="filtroEstado = 'completadas'"
                        class="px-3.5 py-1.5 rounded-xl font-bold text-[9px] uppercase tracking-wider transition-all border"
                        :class="filtroEstado === 'completadas' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-[#faf9f6]'"
                        title="Órdenes ya agendadas">
                        ✅ Con Cita Reservada
                    </button>
                </div>
                
                <!-- Sub-Filtros Perecederos -->
                <div v-if="pestanaActiva === 'perecederos'" class="flex flex-wrap gap-1.5 bg-[#faf9f6] p-3 rounded-2xl border border-[#eef0eb] animate-fade-in">
                    <span class="text-[9px] font-black text-[#888c80] uppercase tracking-wider px-2 py-1.5 mr-2 shrink-0">Subgrupo:</span>
                    <button v-for="sub in ['todos', 'carnes', 'charcuteria', 'pescaderia', 'congelados']" :key="sub"
                        @click="subFiltroPerecederos = sub"
                        class="px-3 py-1 rounded-xl text-[10px] font-bold transition-all border"
                        :class="subFiltroPerecederos === sub ? 'bg-rose-50 text-rose-700 border-rose-200 shadow-sm' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-rose-50/10'">
                        {{ sub === 'todos' ? 'Ver Todos' : sub === 'carnes' ? '🥩 Carnes/Aves' : sub === 'charcuteria' ? '🧀 Charcutería' : sub === 'pescaderia' ? '🐟 Pescadería' : '❄️ Congelados' }}
                    </button>
                </div>
                
                <!-- Sub-Filtros Fruver -->
                <div v-if="pestanaActiva === 'fruver'" class="flex flex-wrap gap-1.5 bg-[#faf9f6] p-3 rounded-2xl border border-[#eef0eb] animate-fade-in">
                    <span class="text-[9px] font-black text-[#888c80] uppercase tracking-wider px-2 py-1.5 mr-2 shrink-0">Subgrupo:</span>
                    <button v-for="sub in ['todos', 'frutas', 'verduras', 'hortalizas']" :key="sub"
                        @click="subFiltroFruver = sub"
                        class="px-3 py-1 rounded-xl text-[10px] font-bold transition-all border"
                        :class="subFiltroFruver === sub ? 'bg-lime-50 text-lime-700 border-lime-200 shadow-sm' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-lime-50/10'">
                        {{ sub === 'todos' ? 'Ver Todos' : sub === 'frutas' ? '🍎 Frutas' : sub === 'verduras' ? '🥦 Verduras' : '🥬 Hortalizas' }}
                    </button>
                </div>
                
                <!-- Loading State -->
                <div v-if="cargando" class="text-center py-20 bg-white border border-[#eef0eb] rounded-2xl shadow-sm">
                    <svg class="animate-spin h-8 w-8 mx-auto mb-4 text-primary" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-xs font-bold text-[#6c7263]">Consultando sistema ERP...</p>
                </div>

                <div v-else-if="errorMensaje" class="bg-indigo-50 text-primary p-6 rounded-2xl border border-indigo-100 font-bold text-center text-xs">
                    ⚠️ {{ errorMensaje }}
                </div>

                <div v-else-if="ordenes.length === 0" class="text-center py-20 bg-white border border-[#eef0eb] rounded-2xl shadow-sm">
                    <div class="text-4xl mb-3">🎉</div>
                    <h3 class="text-base font-bold text-[#1c1c1c]">Todo al día</h3>
                    <p class="text-xs text-[#6c7263] mt-0.5 font-medium">No hay órdenes de compra pendientes en el sistema ERP.</p>
                </div>

                <template v-else>
                    <div v-for="grupo in ordenesAgrupadas" :key="grupo.codigo" class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                        
                        <!-- Destino Header -->
                        <div class="bg-[#faf9f6] border-b border-[#eef0eb] px-6 py-4 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <span class="bg-primary/10 text-primary border border-primary/20 font-black px-2.5 py-0.5 rounded-lg text-xs font-mono">{{ grupo.codigo }}</span>
                                <h3 class="text-[#1c1c1c] font-bold text-sm leading-none">{{ grupo.nombre }}</h3>
                            </div>
                            <span class="text-[10px] font-bold text-[#6c7263] border border-[#eef0eb] bg-white px-2.5 py-0.5 rounded-full">
                                {{ grupo.ordenes.length }} Órdenes
                            </span>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs whitespace-nowrap">
                                <thead class="bg-[#faf9f6]/40 text-[#888c80] font-bold uppercase text-[9px] tracking-wider border-b border-[#eef0eb]">
                                    <tr>
                                        <th class="px-6 py-3.5">Nº Orden</th>
                                        <th class="px-6 py-3.5">Proveedor</th>
                                        <th class="px-6 py-3.5">F. Emisión</th>
                                        <th class="px-6 py-3.5">F. Recepción</th>
                                        <th class="px-6 py-3.5">Estatus</th>
                                        <th v-if="pestanaActiva === 'secos'" class="px-6 py-3.5 text-center">Bultos</th>
                                        <th v-if="pestanaActiva === 'perecederos'" class="px-6 py-3.5 text-center">KG / UND</th>
                                        <th v-if="pestanaActiva === 'fruver'" class="px-6 py-3.5 text-center">KG / UND</th>
                                        <th class="px-6 py-3.5 text-center">Variedad (SKUs)</th>
                                        <th class="px-6 py-3.5 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#eef0eb]">
                                    <template v-for="odc in grupo.ordenes" :key="odc.numero_oc">
                                        <tr @click="toggleFila(odc.numero_oc)"
                                            class="hover:bg-[#fcfbf8]/30 transition-colors group cursor-pointer"
                                            :class="{'bg-primary/5': filasExpandidas.includes(odc.numero_oc)}">
                                        
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-[#1c1c1c] font-mono text-sm">{{ odc.numero_oc }}</span>
                                                    <span v-if="Number(odc.es_fruver) === 1" class="bg-lime-50 text-lime-700 border border-lime-200 text-[8px] font-black px-1.5 py-0.5 rounded-full uppercase animate-pulse" title="Contiene productos Fruver">
                                                        FRUVER
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 font-semibold text-[#6c7263] max-w-xs truncate" :title="odc.proveedor">
                                                {{ odc.proveedor }}
                                            </td>
                                            
                                            <td class="px-6 py-4 text-[#888c80]">{{ formatFecha(odc.fecha_emision) }}</td>
                                            <td class="px-6 py-4 text-[#888c80] font-semibold">{{ formatFecha(odc.fecha_recepcion) }}</td>
                                            
                                            <td class="px-6 py-4">
                                                <span v-if="odc.estatus_habilitacion === 'agendada'" class="bg-emerald-50 text-emerald-700 border border-emerald-100 font-bold px-2 py-0.5 rounded-full text-[9px] uppercase">Con Cita</span>
                                                <span v-else-if="odc.estatus_habilitacion === 'habilitada'" class="bg-blue-50 text-blue-700 border border-blue-100 font-bold px-2 py-0.5 rounded-full text-[9px] uppercase">Habilitada</span>
                                                <span v-else class="bg-zinc-50 text-zinc-600 border border-zinc-200 font-bold px-2 py-0.5 rounded-full text-[9px] uppercase">Pendiente</span>
                                            </td>
                                            
                                            <td v-if="pestanaActiva === 'secos'" class="px-6 py-4 text-center">
                                                <span class="bg-[#f5f6f2] border border-[#eef0eb] text-[#1c1c1c] font-bold px-2.5 py-0.5 rounded-md">{{ Math.round(odc.total_bultos || 0) }}</span>
                                            </td>
                                            <td v-if="pestanaActiva === 'perecederos'" class="px-6 py-4 text-center">
                                                <div class="flex flex-col items-center gap-1">
                                                    <template v-if="subFiltroPerecederos === 'carnes' || subFiltroPerecederos === 'todos'">
                                                        <span v-if="parseFloat(odc.total_kg_carnes) > 0" class="bg-rose-50 text-rose-700 border border-rose-100 font-bold px-2 text-[9px] py-0.5 rounded">🥩 {{ parseFloat(odc.total_kg_carnes).toFixed(2) }} kg</span>
                                                        <span v-if="parseFloat(odc.total_und_carnes) > 0" class="bg-rose-50/40 text-rose-600 border border-rose-100/50 font-bold px-2 text-[9px] py-0.5 rounded">🥩 {{ Math.round(odc.total_und_carnes) }} und</span>
                                                    </template>
                                                    <template v-if="subFiltroPerecederos === 'charcuteria' || subFiltroPerecederos === 'todos'">
                                                        <span v-if="parseFloat(odc.total_kg_charcuteria) > 0" class="bg-amber-50 text-amber-700 border border-amber-100 font-bold px-2 text-[9px] py-0.5 rounded">🧀 {{ parseFloat(odc.total_kg_charcuteria).toFixed(2) }} kg</span>
                                                        <span v-if="parseFloat(odc.total_und_charcuteria) > 0" class="bg-amber-50/40 text-amber-600 border border-amber-100/50 font-bold px-2 text-[9px] py-0.5 rounded">🧀 {{ Math.round(odc.total_und_charcuteria) }} und</span>
                                                    </template>
                                                    <template v-if="subFiltroPerecederos === 'pescaderia' || subFiltroPerecederos === 'todos'">
                                                        <span v-if="parseFloat(odc.total_kg_pescaderia) > 0" class="bg-blue-50 text-blue-700 border border-blue-100 font-bold px-2 text-[9px] py-0.5 rounded">🐟 {{ parseFloat(odc.total_kg_pescaderia).toFixed(2) }} kg</span>
                                                        <span v-if="parseFloat(odc.total_und_pescaderia) > 0" class="bg-blue-50/40 text-blue-600 border border-blue-100/50 font-bold px-2 text-[9px] py-0.5 rounded">🐟 {{ Math.round(odc.total_und_pescaderia) }} und</span>
                                                    </template>
                                                    <template v-if="subFiltroPerecederos === 'congelados' || subFiltroPerecederos === 'todos'">
                                                        <span v-if="parseFloat(odc.total_kg_congelados) > 0" class="bg-cyan-50 text-cyan-700 border border-cyan-100 font-bold px-2 text-[9px] py-0.5 rounded">❄️ {{ parseFloat(odc.total_kg_congelados).toFixed(2) }} kg</span>
                                                        <span v-if="parseFloat(odc.total_und_congelados) > 0" class="bg-cyan-50/40 text-cyan-600 border border-cyan-100/50 font-bold px-2 text-[9px] py-0.5 rounded">❄️ {{ Math.round(odc.total_und_congelados) }} und</span>
                                                    </template>
                                                </div>
                                            </td>
                                            <td v-if="pestanaActiva === 'fruver'" class="px-6 py-4 text-center">
                                                <div class="flex flex-col items-center gap-1">
                                                    <template v-if="subFiltroFruver === 'frutas' || subFiltroFruver === 'todos'">
                                                        <span v-if="parseFloat(odc.total_kg_frutas) > 0" class="bg-orange-50 text-orange-700 border border-orange-100 font-bold px-2 text-[9px] py-0.5 rounded">🍎 {{ parseFloat(odc.total_kg_frutas).toFixed(2) }} kg</span>
                                                        <span v-if="parseFloat(odc.total_und_frutas) > 0" class="bg-orange-50/40 text-orange-600 border border-orange-100/50 font-bold px-2 text-[9px] py-0.5 rounded">🍎 {{ Math.round(odc.total_und_frutas) }} und</span>
                                                    </template>
                                                    <template v-if="subFiltroFruver === 'verduras' || subFiltroFruver === 'todos'">
                                                        <span v-if="parseFloat(odc.total_kg_verduras) > 0" class="bg-green-50 text-green-700 border border-green-100 font-bold px-2 text-[9px] py-0.5 rounded">🥦 {{ parseFloat(odc.total_kg_verduras).toFixed(2) }} kg</span>
                                                        <span v-if="parseFloat(odc.total_und_verduras) > 0" class="bg-green-50/40 text-green-600 border border-green-100/50 font-bold px-2 text-[9px] py-0.5 rounded">🥦 {{ Math.round(odc.total_und_verduras) }} und</span>
                                                    </template>
                                                    <template v-if="subFiltroFruver === 'hortalizas' || subFiltroFruver === 'todos'">
                                                        <span v-if="parseFloat(odc.total_kg_hortalizas) > 0" class="bg-lime-50 text-lime-700 border border-lime-100 font-bold px-2 text-[9px] py-0.5 rounded">🥬 {{ parseFloat(odc.total_kg_hortalizas).toFixed(2) }} kg</span>
                                                        <span v-if="parseFloat(odc.total_und_hortalizas) > 0" class="bg-lime-50/40 text-lime-600 border border-lime-100/50 font-bold px-2 text-[9px] py-0.5 rounded">🥬 {{ Math.round(odc.total_und_hortalizas) }} und</span>
                                                    </template>
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 text-center font-bold text-[#1c1c1c]">{{ odc.cant_productos }}</td>
                                            
                                            <td class="px-6 py-4 text-right">
                                                <Link v-if="$page.props.auth.user.role === 'comprador' || $page.props.auth.user.role === 'admin'"
                                                    :href="route('reservar-cita')"
                                                    class="bg-primary hover:bg-indigo-600 text-white px-3 py-1.5 rounded-xl text-[10px] font-bold shadow-sm transition-all inline-flex items-center gap-1 opacity-0 group-hover:opacity-100"
                                                    title="Configurar y Habilitar esta orden para el Proveedor">
                                                    Habilitar →
                                                </Link>
                                                <span v-else class="text-[#888c80] text-[10px] italic opacity-0 group-hover:opacity-100">Solo Lectura</span>
                                            </td>
                                        </tr>
                                        
                                        <!-- Fila Expandida: Detalles Básicos -->
                                        <tr v-if="filasExpandidas.includes(odc.numero_oc)" class="bg-[#fcfbf8]/60">
                                            <td colspan="10" class="px-6 py-5 border-t border-[#eef0eb]">
                                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-xs leading-relaxed">
                                                    
                                                    <!-- Secos -->
                                                    <div v-if="Number(odc.es_secos) === 1">
                                                        <h4 class="text-[9px] font-bold text-[#888c80] uppercase tracking-wider mb-2.5">Detalle Secos</h4>
                                                        <div class="space-y-1.5">
                                                            <div class="flex justify-between items-center text-[10px] font-bold pb-1.5 mb-1.5 border-b border-[#eef0eb]">
                                                                <span class="text-[#6c7263]">Total Bultos</span>
                                                                <span class="font-extrabold bg-[#f5f6f2] border border-[#eef0eb] px-2 py-0.5 rounded text-[#1c1c1c]">{{ Math.round(odc.total_bultos) }}</span>
                                                            </div>
                                                            <div v-if="Number(odc.total_bultos_viveres) > 0" class="flex justify-between font-medium">
                                                                <span class="text-amber-700">Víveres</span>
                                                                <span>{{ Math.round(odc.total_bultos_viveres) }} Bultos</span>
                                                            </div>
                                                            <div v-if="Number(odc.total_bultos_limpieza) > 0" class="flex justify-between font-medium">
                                                                <span class="text-teal-600">Limpieza</span>
                                                                <span>{{ Math.round(odc.total_bultos_limpieza) }} Bultos</span>
                                                            </div>
                                                            <div v-if="Number(odc.total_bultos_cuidado_personal) > 0" class="flex justify-between font-medium">
                                                                <span class="text-pink-600">Cuidado Personal</span>
                                                                <span>{{ Math.round(odc.total_bultos_cuidado_personal) }} Bultos</span>
                                                            </div>
                                                            <div v-if="Number(odc.total_bultos_licor_bebidas) > 0" class="flex justify-between font-medium">
                                                                <span class="text-purple-600">Licor/Bebidas</span>
                                                                <span>{{ Math.round(odc.total_bultos_licor_bebidas) }} Bultos</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- SKUs -->
                                                    <div>
                                                        <h4 class="text-[9px] font-bold text-[#888c80] uppercase tracking-wider mb-2.5">Variedad de Ítems</h4>
                                                        <div class="flex justify-between items-center">
                                                            <span class="text-[#6c7263] font-medium">Total de SKUs</span>
                                                            <span class="font-extrabold bg-[#f5f6f2] border border-[#eef0eb] px-2 py-0.5 rounded text-[#1c1c1c]">{{ odc.cant_productos }}</span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Perecederos -->
                                                    <div v-if="Number(odc.es_perecederos) === 1">
                                                        <h4 class="text-[9px] font-bold text-rose-500 uppercase tracking-wider mb-2.5">Detalle Perecederos</h4>
                                                        <div class="space-y-1.5">
                                                            <div v-if="parseFloat(odc.total_kg_carnes) > 0 || parseFloat(odc.total_und_carnes) > 0" class="flex justify-between font-medium">
                                                                <span class="text-rose-600">Carnes/Aves</span>
                                                                <span>{{ parseFloat(odc.total_kg_carnes) > 0 ? parseFloat(odc.total_kg_carnes).toFixed(2) + ' kg' : Math.round(odc.total_und_carnes) + ' und' }}</span>
                                                            </div>
                                                            <div v-if="parseFloat(odc.total_kg_charcuteria) > 0 || parseFloat(odc.total_und_charcuteria) > 0" class="flex justify-between font-medium">
                                                                <span class="text-amber-600">Charcutería</span>
                                                                <span>{{ parseFloat(odc.total_kg_charcuteria) > 0 ? parseFloat(odc.total_kg_charcuteria).toFixed(2) + ' kg' : Math.round(odc.total_und_charcuteria) + ' und' }}</span>
                                                            </div>
                                                            <div v-if="parseFloat(odc.total_kg_pescaderia) > 0 || parseFloat(odc.total_und_pescaderia) > 0" class="flex justify-between font-medium">
                                                                <span class="text-blue-600">Pescadería</span>
                                                                <span>{{ parseFloat(odc.total_kg_pescaderia) > 0 ? parseFloat(odc.total_kg_pescaderia).toFixed(2) + ' kg' : Math.round(odc.total_und_pescaderia) + ' und' }}</span>
                                                            </div>
                                                            <div v-if="parseFloat(odc.total_kg_congelados) > 0 || parseFloat(odc.total_und_congelados) > 0" class="flex justify-between font-medium">
                                                                <span class="text-cyan-600">Congelados</span>
                                                                <span>{{ parseFloat(odc.total_kg_congelados) > 0 ? parseFloat(odc.total_kg_congelados).toFixed(2) + ' kg' : Math.round(odc.total_und_congelados) + ' und' }}</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Fruver -->
                                                    <div v-if="Number(odc.es_fruver) === 1">
                                                        <h4 class="text-[9px] font-bold text-lime-600 uppercase tracking-wider mb-2.5">Detalle Fruver</h4>
                                                        <div class="space-y-1.5">
                                                            <div v-if="parseFloat(odc.total_kg_frutas) > 0 || parseFloat(odc.total_und_frutas) > 0" class="flex justify-between font-medium">
                                                                <span class="text-orange-600">Frutas</span>
                                                                <span>{{ parseFloat(odc.total_kg_frutas) > 0 ? parseFloat(odc.total_kg_frutas).toFixed(2) + ' kg' : Math.round(odc.total_und_frutas) + ' und' }}</span>
                                                            </div>
                                                            <div v-if="parseFloat(odc.total_kg_verduras) > 0 || parseFloat(odc.total_und_verduras) > 0" class="flex justify-between font-medium">
                                                                <span class="text-green-600">Verduras</span>
                                                                <span>{{ parseFloat(odc.total_kg_verduras) > 0 ? parseFloat(odc.total_kg_verduras).toFixed(2) + ' kg' : Math.round(odc.total_und_verduras) + ' und' }}</span>
                                                            </div>
                                                            <div v-if="parseFloat(odc.total_kg_hortalizas) > 0 || parseFloat(odc.total_und_hortalizas) > 0" class="flex justify-between font-medium">
                                                                <span class="text-lime-600">Hortalizas</span>
                                                                <span>{{ parseFloat(odc.total_kg_hortalizas) > 0 ? parseFloat(odc.total_kg_hortalizas).toFixed(2) + ' kg' : Math.round(odc.total_und_hortalizas) + ' und' }}</span>
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

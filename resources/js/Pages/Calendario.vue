<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const cargando = ref(false);
const errorMensaje = ref('');

// Fechas y selección
const fechaSeleccionada = ref(new Date().toISOString().split('T')[0]);
const dataCalendario = ref({
    fecha: '',
    fecha_formateada: '',
    es_hoy: true,
    es_manana: false,
    es_ayer: false,
    kpis: {
        total_citas: 0,
        total_horas_ocupadas: 0,
        total_horas_disponibles: 0,
        tasa_ocupacion: 0
    },
    muelles_config: [],
    citas: [],
    timeline: [],
    horas_disponibles_resumen: [],
    resumen_semana: []
});

// Filtros
const filtroMuelle = ref('todos'); // 'todos', o código de muelle
const filtroCategoria = ref('todos'); // 'todos', 'secos', 'perecederos', 'fruver'
const filtroDisponibilidad = ref('todos'); // 'todos', 'solo_citas', 'solo_libres'
const modoVista = ref('timeline'); // 'timeline', 'lista'
const busquedaTexto = ref('');

// Modal de detalle
const modalDetalle = ref(false);
const citaSeleccionada = ref(null);

const abrirDetalleCita = (cita) => {
    citaSeleccionada.value = cita;
    modalDetalle.value = true;
};

const cerrarDetalleCita = () => {
    modalDetalle.value = false;
    citaSeleccionada.value = null;
};

// Cargar data desde API
const cargarDataCalendario = async (fechaTarget = null) => {
    cargando.value = true;
    errorMensaje.value = '';
    const f = fechaTarget || fechaSeleccionada.value;
    
    try {
        const resp = await axios.get('/api/calendario/dia', {
            params: { fecha: f }
        });
        if (resp.data.status === 'Exitoso') {
            dataCalendario.value = resp.data;
            fechaSeleccionada.value = resp.data.fecha;
        } else {
            errorMensaje.value = resp.data.error || 'Error al obtener datos del calendario.';
        }
    } catch (e) {
        console.error('Error cargando calendario:', e);
        errorMensaje.value = e.response?.data?.error || e.message || 'Error de conexión con el servidor.';
    } finally {
        cargando.value = false;
    }
};

// Controles de Fecha Rápidos
const irAHoy = () => {
    const hoy = new Date().toISOString().split('T')[0];
    fechaSeleccionada.value = hoy;
    cargarDataCalendario(hoy);
};

const irAManana = () => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    const manana = d.toISOString().split('T')[0];
    fechaSeleccionada.value = manana;
    cargarDataCalendario(manana);
};

const cambiarDia = (offset) => {
    const parts = fechaSeleccionada.value.split('-');
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    d.setDate(d.getDate() + offset);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const nuevaFecha = `${y}-${m}-${day}`;
    fechaSeleccionada.value = nuevaFecha;
    cargarDataCalendario(nuevaFecha);
};

const seleccionarDiaSemana = (dia) => {
    if (!dia?.fecha) return;
    fechaSeleccionada.value = dia.fecha;
    cargarDataCalendario(dia.fecha);
};

// Muelles visibles según filtro
const muellesFiltrados = computed(() => {
    const list = dataCalendario.value.muelles_config || [];
    if (filtroMuelle.value === 'todos') return list;
    return list.filter(m => m.codigo === filtroMuelle.value);
});

// Citas filtradas
const citasFiltradas = computed(() => {
    let list = dataCalendario.value.citas || [];
    
    if (filtroMuelle.value !== 'todos') {
        list = list.filter(c => c.muelle_asignado === filtroMuelle.value);
    }
    
    if (filtroCategoria.value !== 'todos') {
        list = list.filter(c => {
            const cat = String(c.tipo_mercancia || '').toLowerCase();
            return cat.includes(filtroCategoria.value);
        });
    }

    if (busquedaTexto.value.trim() !== '') {
        const q = busquedaTexto.value.toLowerCase().trim();
        list = list.filter(c => 
            String(c.numero_oc || '').toLowerCase().includes(q) ||
            String(c.proveedor || '').toLowerCase().includes(q) ||
            String(c.rif_proveedor || '').toLowerCase().includes(q) ||
            String(c.vendedor_nombre || '').toLowerCase().includes(q)
        );
    }

    return list;
});

// Slots del timeline filtrados
const timelineFiltrado = computed(() => {
    const tl = dataCalendario.value.timeline || [];
    if (filtroDisponibilidad.value === 'todos') return tl;

    return tl.filter(slot => {
        let tieneOcupado = false;
        let tieneLibre = false;
        
        for (const mCod in slot.muelles) {
            if (filtroMuelle.value !== 'todos' && mCod !== filtroMuelle.value) continue;
            if (slot.muelles[mCod].ocupado) tieneOcupado = true;
            if (slot.muelles[mCod].disponible) tieneLibre = true;
        }

        if (filtroDisponibilidad.value === 'solo_citas') return tieneOcupado;
        if (filtroDisponibilidad.value === 'solo_libres') return tieneLibre;
        return true;
    });
});

const getBadgeCategoria = (tipo) => {
    const clean = String(tipo || '').toLowerCase();
    if (clean.includes('fruta') || clean.includes('verdura') || clean.includes('fruver')) {
        return { label: 'Fruver', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    }
    if (clean.includes('perecedero') || clean.includes('carne') || clean.includes('charcuteria') || clean.includes('pescaderia')) {
        return { label: 'Perecederos', class: 'bg-rose-100 text-rose-800 border-rose-200' };
    }
    return { label: 'Secos', class: 'bg-orange-100 text-orange-800 border-orange-200' };
};

const getBadgeEstatus = (estatus) => {
    const st = String(estatus || '').toLowerCase();
    if (st === 'completada') return 'bg-emerald-600 text-white';
    if (st === 'en muelle') return 'bg-blue-600 text-white animate-pulse';
    if (st === 'cancelada') return 'bg-red-600 text-white';
    return 'bg-blue-100 text-blue-800 border border-blue-200';
};

onMounted(() => {
    cargarDataCalendario();
});
</script>

<template>
    <Head title="Calendario de Citas & Horas Disponibles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">📅</span>
                        <h2 class="font-black text-xl sm:text-2xl text-slate-800 tracking-tight">
                            Calendario de Citas & Horarios Disponibles
                        </h2>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
                        Control visual de recepciones agendadas de hoy, mañana o cualquier fecha, y consulta de cupos libres por muelle.
                    </p>
                </div>

                <div class="flex items-center flex-wrap gap-2">
                    <Link :href="route('monitor-odc')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition-all shadow-sm flex items-center gap-1.5"
                        title="Ir a la lista tabular de ODCs">
                        <span>📋</span> Monitor ODC
                    </Link>
                    <Link :href="route('reservar-cita')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition-all shadow-md flex items-center gap-1.5"
                        title="Habilitar o agendar una cita">
                        <span>➕</span> Reservar / Habilitar Cita
                    </Link>
                    <button @click="cargarDataCalendario()"
                        :disabled="cargando"
                        class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5 disabled:opacity-50">
                        <svg class="w-4 h-4" :class="{'animate-spin': cargando}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Refrescar
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- ALERTA DE ERROR SI EXISTE -->
            <div v-if="errorMensaje" class="p-4 bg-red-50 border-l-4 border-red-500 rounded-xl flex items-center justify-between text-red-800 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-xl">⚠️</span>
                    <span>{{ errorMensaje }}</span>
                </div>
                <button @click="errorMensaje = ''" class="text-red-500 hover:text-red-700 font-bold">✕</button>
            </div>

            <!-- BARRA SEMANAL RÁPIDA (STRIP DE 7 DÍAS) -->
            <div class="bg-white rounded-2xl p-2.5 shadow-sm border border-slate-200">
                <div class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-2 px-2 flex items-center justify-between">
                    <span>Semana en Curso</span>
                    <span class="text-slate-400 font-normal">Haga clic en cualquier día para ver su cronograma</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
                    <button
                        v-for="dia in dataCalendario.resumen_semana"
                        :key="dia.fecha"
                        @click="seleccionarDiaSemana(dia)"
                        class="p-2.5 rounded-xl border text-left transition-all relative flex flex-col justify-between"
                        :class="{
                            'bg-blue-600 text-white border-blue-600 shadow-md ring-2 ring-blue-300': dia.es_seleccionado,
                            'bg-slate-50 border-slate-200 hover:bg-blue-50/60 hover:border-blue-300 text-slate-700': !dia.es_seleccionado
                        }"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-tight" :class="dia.es_seleccionado ? 'text-blue-100' : 'text-slate-400'">
                                {{ dia.dia_corto }}
                            </span>
                            <span v-if="dia.es_hoy" class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded" :class="dia.es_seleccionado ? 'bg-white text-blue-700' : 'bg-blue-100 text-blue-700'">
                                Hoy
                            </span>
                            <span v-else-if="dia.es_manana" class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded" :class="dia.es_seleccionado ? 'bg-white text-indigo-700' : 'bg-indigo-100 text-indigo-700'">
                                Mañana
                            </span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-xl sm:text-2xl font-black font-mono leading-none">
                                {{ dia.numero_dia }}
                            </span>
                            <span class="text-[11px] ml-1 opacity-75 font-semibold">{{ dia.mes_corto }}</span>
                        </div>
                        <div class="flex items-center gap-1 text-[11px] font-bold">
                            <span v-if="dia.total_citas > 0" class="flex items-center gap-1" :class="dia.es_seleccionado ? 'text-blue-100' : 'text-blue-700'">
                                🚛 {{ dia.total_citas }} {{ dia.total_citas === 1 ? 'cita' : 'citas' }}
                            </span>
                            <span v-else class="text-[10px]" :class="dia.es_seleccionado ? 'text-blue-200' : 'text-slate-400'">
                                🟢 Libre
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- CONTROLES PRINCIPALES DE FECHA Y FILTROS -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-200 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    
                    <!-- Botonera de Fechas Clave (Hoy / Mañana / Anterior / Siguiente / Picker) -->
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            @click="irAHoy"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-black transition-all flex items-center gap-1.5 border"
                            :class="dataCalendario.es_hoy ? 'bg-blue-600 text-white border-blue-600 shadow-md' : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200'"
                        >
                            ⚡ Hoy
                        </button>
                        <button
                            @click="irAManana"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-black transition-all flex items-center gap-1.5 border"
                            :class="dataCalendario.es_manana ? 'bg-blue-600 text-white border-blue-600 shadow-md' : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200'"
                        >
                            ⚡ Mañana
                        </button>

                        <div class="flex items-center bg-slate-100 rounded-xl p-1 border border-slate-200">
                            <button @click="cambiarDia(-1)" class="p-1.5 rounded-lg hover:bg-white text-slate-600 hover:text-slate-900 transition-colors" title="Día Anterior">
                                ◀
                            </button>
                            <input
                                type="date"
                                v-model="fechaSeleccionada"
                                @change="cargarDataCalendario(fechaSeleccionada)"
                                class="bg-transparent border-0 text-xs sm:text-sm font-bold text-slate-800 px-2 py-1 focus:ring-0 cursor-pointer"
                            />
                            <button @click="cambiarDia(1)" class="p-1.5 rounded-lg hover:bg-white text-slate-600 hover:text-slate-900 transition-colors" title="Día Siguiente">
                                ▶
                            </button>
                        </div>
                    </div>

                    <!-- Título de la fecha actual seleccionada -->
                    <div class="text-right flex flex-col justify-center">
                        <div class="text-base sm:text-lg font-black text-slate-900 flex items-center gap-2 lg:justify-end">
                            <span>{{ dataCalendario.fecha_formateada || 'Cargando fecha...' }}</span>
                            <span v-if="dataCalendario.es_hoy" class="bg-blue-100 text-blue-700 text-[10px] font-black uppercase px-2 py-0.5 rounded-full">
                                Hoy
                            </span>
                            <span v-else-if="dataCalendario.es_manana" class="bg-indigo-100 text-indigo-700 text-[10px] font-black uppercase px-2 py-0.5 rounded-full">
                                Mañana
                            </span>
                        </div>
                        <div class="text-xs text-slate-400 font-medium mt-0.5">
                            {{ dataCalendario.kpis.total_citas }} citas agendadas · {{ dataCalendario.kpis.total_horas_disponibles }} horas libres en muelles
                        </div>
                    </div>
                </div>

                <!-- FILTROS SECUNDARIOS Y MODO DE VISTA -->
                <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Filtro Muelle -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Muelle:</span>
                            <select v-model="filtroMuelle" class="text-xs font-bold text-slate-700 bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 focus:ring-2 focus:ring-blue-500">
                                <option value="todos">Todos los Muelles</option>
                                <option v-for="m in dataCalendario.muelles_config" :key="m.codigo" :value="m.codigo">
                                    {{ m.nombre }}
                                </option>
                            </select>
                        </div>

                        <!-- Filtro Categoría -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Mercancía:</span>
                            <select v-model="filtroCategoria" class="text-xs font-bold text-slate-700 bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 focus:ring-2 focus:ring-blue-500">
                                <option value="todos">Todas las Categorías</option>
                                <option value="secos">Secos</option>
                                <option value="perecederos">Perecederos</option>
                                <option value="fruver">Fruver</option>
                            </select>
                        </div>

                        <!-- Filtro Disponibilidad -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Mostrar:</span>
                            <select v-model="filtroDisponibilidad" class="text-xs font-bold text-slate-700 bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 focus:ring-2 focus:ring-blue-500">
                                <option value="todos">Todo (Citas y Horas Libres)</option>
                                <option value="solo_citas">Solo Citas Agendadas</option>
                                <option value="solo_libres">Solo Horas Disponibles</option>
                            </select>
                        </div>
                    </div>

                    <!-- Toggle Modo Vista (Timeline vs Lista) -->
                    <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200">
                        <button
                            @click="modoVista = 'timeline'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="modoVista === 'timeline' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                        >
                            <span>📊</span> Vista Línea de Tiempo
                        </button>
                        <button
                            @click="modoVista = 'lista'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="modoVista === 'lista' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                        >
                            <span>📋</span> Lista Cronológica
                        </button>
                    </div>
                </div>
            </div>

            <!-- TARJETAS KPIS DEL DÍA -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl font-black shrink-0">
                        🚛
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Citas Agendadas</p>
                        <p class="text-2xl font-black text-slate-900 leading-none mt-0.5">
                            {{ dataCalendario.kpis.total_citas }}
                        </p>
                        <p class="text-[10px] text-slate-500 mt-1">{{ dataCalendario.kpis.total_horas_ocupadas }} horas de muelle ocupadas</p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl font-black shrink-0">
                        🟢
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Horas Disponibles</p>
                        <p class="text-2xl font-black text-emerald-700 leading-none mt-0.5">
                            {{ dataCalendario.kpis.total_horas_disponibles }} hrs
                        </p>
                        <p class="text-[10px] text-emerald-600 font-semibold mt-1">Capacidad libre para recibir</p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl font-black shrink-0">
                        🏭
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Muelles Activos</p>
                        <p class="text-2xl font-black text-slate-900 leading-none mt-0.5">
                            {{ dataCalendario.muelles_config.length }}
                        </p>
                        <p class="text-[10px] text-slate-500 mt-1">Hiper Suraki & Galpón Central</p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl font-black shrink-0">
                        📈
                    </div>
                    <div class="flex-1">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tasa de Ocupación</p>
                        <p class="text-2xl font-black text-slate-900 leading-none mt-0.5">
                            {{ dataCalendario.kpis.tasa_ocupacion }}%
                        </p>
                        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                            <div class="bg-blue-600 h-1.5 rounded-full transition-all" :style="{ width: dataCalendario.kpis.tasa_ocupacion + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RESUMEN DE BLOQUES DE HORAS DISPONIBLES POR MUELLE -->
            <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-blue-50 border border-emerald-200 rounded-2xl p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">⏰</span>
                    <h3 class="text-sm font-black text-emerald-950 uppercase tracking-tight">
                        Horas Disponibles para Agendar ({{ dataCalendario.fecha_formateada }})
                    </h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="hdr in dataCalendario.horas_disponibles_resumen"
                        :key="hdr.muelle_codigo"
                        class="bg-white/80 backdrop-blur-xs p-3 rounded-xl border border-emerald-100 flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-black text-slate-800 font-mono">{{ hdr.nombre_corto || hdr.muelle_nombre }}</span>
                                <span class="text-[9px] font-bold text-slate-400 uppercase">{{ hdr.sucursal }}</span>
                            </div>
                            <div v-if="hdr.bloques_libres && hdr.bloques_libres.length > 0" class="flex flex-wrap gap-1">
                                <span
                                    v-for="(b, bIdx) in hdr.bloques_libres"
                                    :key="bIdx"
                                    class="text-[11px] font-bold text-emerald-800 bg-emerald-100/90 px-2 py-0.5 rounded-md border border-emerald-200"
                                >
                                    🟢 {{ b }}
                                </span>
                            </div>
                            <div v-else class="text-[11px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                                🔴 Sin horarios libres (Completamente ocupado)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISTA 1: TIMELINE GRID POR MUELLES -->
            <div v-if="modoVista === 'timeline'" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-black text-slate-800">Cronograma de Horas por Muelle</span>
                        <span class="text-xs text-slate-500 font-medium">· Bloques continuos de 30 minutos</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1.5 text-slate-600 font-bold">
                            <span class="w-3 h-3 rounded-full bg-blue-600 inline-block"></span> Cita Agendada
                        </span>
                        <span class="flex items-center gap-1.5 text-slate-600 font-bold">
                            <span class="w-3 h-3 rounded-full bg-emerald-400 inline-block"></span> Disponible
                        </span>
                        <span class="flex items-center gap-1.5 text-slate-600 font-bold">
                            <span class="w-3 h-3 rounded-full bg-slate-300 inline-block"></span> Bloqueado
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-slate-100/80 border-b border-slate-200 text-left text-xs font-black text-slate-700">
                                <th class="p-3 w-24 text-center border-r border-slate-200 uppercase tracking-wider text-[11px] text-slate-500">
                                    Hora
                                </th>
                                <th
                                    v-for="m in muellesFiltrados"
                                    :key="m.codigo"
                                    class="p-3.5 border-r border-slate-200 min-w-[220px]"
                                >
                                    <div class="font-black text-slate-800 text-sm font-mono">{{ m.nombre_corto || m.nombre }}</div>
                                    <div class="text-[10px] text-slate-500 font-medium mt-0.5">{{ m.sucursal }}</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <tr
                                v-for="slot in timelineFiltrado"
                                :key="slot.hora"
                                class="hover:bg-slate-50/50 transition-colors"
                            >
                                <!-- Columna de Hora -->
                                <td class="p-2.5 text-center font-bold text-slate-700 font-mono bg-slate-50/80 border-r border-slate-200 whitespace-nowrap">
                                    {{ slot.hora_12 }}
                                </td>

                                <!-- Celdas de cada Muelle -->
                                <td
                                    v-for="m in muellesFiltrados"
                                    :key="m.codigo"
                                    class="p-1.5 border-r border-slate-200 align-top h-14"
                                >
                                    <!-- ESTADO 1: OCUPADO CON CITA -->
                                    <div
                                        v-if="slot.muelles[m.codigo]?.ocupado && slot.muelles[m.codigo]?.cita"
                                        @click="abrirDetalleCita(slot.muelles[m.codigo].cita)"
                                        class="h-full rounded-xl p-2.5 border transition-all cursor-pointer shadow-xs group"
                                        :class="slot.muelles[m.codigo].es_primer_slot ? 'bg-blue-50/90 border-blue-300 hover:bg-blue-100/90' : 'bg-blue-50/40 border-blue-200/50 hover:bg-blue-50'"
                                    >
                                        <div class="flex items-start justify-between gap-1">
                                            <span class="font-black text-slate-900 font-mono text-[11px] group-hover:text-blue-700">
                                                {{ slot.muelles[m.codigo].cita.numero_oc }}
                                            </span>
                                            <span :class="getBadgeEstatus(slot.muelles[m.codigo].cita.estatus)" class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded-full">
                                                {{ slot.muelles[m.codigo].cita.estatus }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-slate-800 truncate text-[11px] mt-0.5" :title="slot.muelles[m.codigo].cita.proveedor">
                                            {{ slot.muelles[m.codigo].cita.proveedor }}
                                        </p>
                                        <div class="flex items-center gap-1.5 text-[10px] text-blue-800 font-semibold mt-1">
                                            <span>🕒 {{ slot.muelles[m.codigo].cita.rango_texto }}</span>
                                        </div>
                                    </div>

                                    <!-- ESTADO 2: DISPONIBLE (VERDE) -->
                                    <div
                                        v-else-if="slot.muelles[m.codigo]?.disponible"
                                        class="h-full rounded-xl p-2 bg-emerald-50/60 border border-dashed border-emerald-300 flex items-center justify-between text-emerald-800 hover:bg-emerald-100/60 transition-colors"
                                        title="Horario disponible para agendamiento de citas"
                                    >
                                        <span class="text-[11px] font-bold flex items-center gap-1">
                                            <span>🟢</span> Disponible
                                        </span>
                                        <span class="text-[10px] font-mono text-emerald-600 font-semibold">{{ slot.hora_12 }}</span>
                                    </div>

                                    <!-- ESTADO 3: BLOQUEADO O PASADO (GRIS) -->
                                    <div
                                        v-else
                                        class="h-full rounded-xl p-2 flex items-center gap-1.5 transition-opacity"
                                        :class="slot.muelles[m.codigo]?.pasado ? 'bg-slate-100/50 border border-slate-200 text-slate-400 opacity-65' : 'bg-slate-100/80 border border-slate-200 text-slate-500'"
                                        :title="slot.muelles[m.codigo]?.motivo_bloqueo || 'Horario no laborable o restringido'"
                                    >
                                        <span class="text-xs">{{ slot.muelles[m.codigo]?.pasado ? '⌛' : '🔒' }}</span>
                                        <span class="text-[10px] font-medium truncate">
                                            {{ slot.muelles[m.codigo]?.motivo_bloqueo || 'Restringido' }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VISTA 2: LISTA CRONOLÓGICA (AGENDA) -->
            <div v-else class="space-y-4">
                <div v-if="citasFiltradas.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="cita in citasFiltradas"
                        :key="cita.id"
                        @click="abrirDetalleCita(cita)"
                        class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-all cursor-pointer group flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-slate-900 font-mono text-base">{{ cita.numero_oc }}</span>
                                    <span :class="getBadgeEstatus(cita.estatus)" class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full">
                                        {{ cita.estatus }}
                                    </span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border" :class="getBadgeCategoria(cita.tipo_mercancia).class">
                                        {{ getBadgeCategoria(cita.tipo_mercancia).label }}
                                    </span>
                                </div>
                                <span class="text-xs font-mono font-bold text-slate-400">#{{ cita.id }}</span>
                            </div>

                            <h4 class="font-black text-slate-800 text-sm group-hover:text-blue-600 transition-colors">
                                {{ cita.proveedor }}
                            </h4>
                            <p v-if="cita.rif_proveedor" class="text-xs font-mono text-slate-500">
                                RIF: {{ cita.rif_proveedor }}
                            </p>

                            <div class="mt-3 bg-blue-50/70 p-2.5 rounded-xl border border-blue-200 space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-blue-800 font-bold flex items-center gap-1.5">
                                        🕒 Apartó: <strong>{{ cita.rango_texto }}</strong>
                                    </span>
                                    <span class="bg-white text-blue-900 font-black px-1.5 py-0.5 rounded border border-blue-200 text-[10px]">
                                        {{ cita.duracion_texto }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-blue-700 font-semibold flex items-center gap-1">
                                    <span>🏭</span> {{ cita.muelle_nombre }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span v-if="cita.vendedor_nombre" class="truncate">
                                👤 {{ cita.vendedor_nombre }} <span v-if="cita.vendedor_telefono">({{ cita.vendedor_telefono }})</span>
                            </span>
                            <span v-else class="text-slate-400">Sin contacto asignado</span>

                            <span class="text-blue-600 font-bold group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                                Ver detalle →
                            </span>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white rounded-2xl p-12 text-center border border-slate-200 shadow-sm">
                    <div class="text-4xl mb-3">🍃</div>
                    <h3 class="text-base font-black text-slate-800">No hay citas agendadas con los filtros seleccionados</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                        Para la fecha {{ dataCalendario.fecha_formateada }} todos los muelles están libres o disponibles para agendar.
                    </p>
                </div>
            </div>

            <!-- MODAL DETALLE DE LA CITA -->
            <div v-if="modalDetalle && citaSeleccionada" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all animate-in fade-in duration-200">
                    <div class="bg-gradient-to-r from-slate-900 to-blue-950 text-white p-5 flex items-start justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-300">Detalle de Recepción</span>
                            <h3 class="text-xl font-black font-mono mt-0.5">Orden {{ citaSeleccionada.numero_oc }}</h3>
                            <p class="text-xs text-slate-300 mt-0.5">{{ citaSeleccionada.proveedor }}</p>
                        </div>
                        <button @click="cerrarDetalleCita" class="text-slate-400 hover:text-white p-1 rounded-lg transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="p-5 space-y-4 text-xs">
                        <!-- Rango de hora destacado -->
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5">
                            <p class="text-[10px] font-bold text-blue-600 uppercase">Horario Apartado en Muelle</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-base font-black text-blue-950 font-mono">
                                    {{ citaSeleccionada.rango_texto }}
                                </span>
                                <span :class="getBadgeEstatus(citaSeleccionada.estatus)" class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full">
                                    {{ citaSeleccionada.estatus }}
                                </span>
                            </div>
                            <p class="text-[11px] font-bold text-blue-800 mt-1">
                                🏭 {{ citaSeleccionada.muelle_nombre }}
                            </p>
                        </div>

                        <!-- Grid de información -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Proveedor / RIF</p>
                                <p class="font-bold text-slate-800 mt-0.5 truncate">{{ citaSeleccionada.proveedor }}</p>
                                <p class="font-mono text-slate-500 text-[11px]">{{ citaSeleccionada.rif_proveedor || 'No especificado' }}</p>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Categoría</p>
                                <span class="inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded-full border" :class="getBadgeCategoria(citaSeleccionada.tipo_mercancia).class">
                                    {{ getBadgeCategoria(citaSeleccionada.tipo_mercancia).label }}
                                </span>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Vendedor / Contacto</p>
                                <p class="font-bold text-slate-800 mt-0.5">{{ citaSeleccionada.vendedor_nombre || 'No asignado' }}</p>
                                <p class="text-slate-500">{{ citaSeleccionada.vendedor_telefono || '-' }}</p>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Factura de Entrega</p>
                                <p class="font-bold text-slate-800 mt-0.5 font-mono">{{ citaSeleccionada.numero_factura || 'Sin Factura' }}</p>
                                <a v-if="citaSeleccionada.factura_url" :href="citaSeleccionada.factura_url" target="_blank"
                                    class="text-blue-600 font-bold hover:underline inline-flex items-center gap-1 mt-1 text-[11px]">
                                    👁️ Ver Factura PDF
                                </a>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div v-if="citaSeleccionada.observaciones" class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Observaciones</p>
                            <p class="text-slate-700 mt-0.5">{{ citaSeleccionada.observaciones }}</p>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 border-t border-slate-200 flex items-center justify-between">
                        <Link :href="route('monitor-odc')" class="text-blue-600 hover:text-blue-800 font-bold text-xs">
                            Ver en Monitor ODC →
                        </Link>
                        <button @click="cerrarDetalleCita" class="px-4 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs hover:bg-slate-800 transition-colors">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

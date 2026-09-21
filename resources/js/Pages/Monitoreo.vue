<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const page = usePage();
const userRole = page.props.auth?.user?.role || 'admin';
const esComprador = userRole === 'comprador';

// Pestaña Activa: 'trazabilidad', 'bitacora', 'correos', 'salud'
const pestanaActiva = ref(esComprador ? 'correos' : 'salud');

// --- 1. SALUD DEL SISTEMA Y METRICAS ---
const healthStats = ref(null);
const cargandoHealth = ref(false);
const procesandoAutoRepair = ref(false);
const mensajeAutoRepair = ref('');
const procesandoPurga = ref(false);
const mensajePurga = ref('');

const cargarHealthStats = async () => {
    cargandoHealth.value = true;
    try {
        const resp = await axios.get('/api/monitoreo/health');
        healthStats.value = resp.data;
    } catch (e) {
        console.error(e);
    } finally {
        cargandoHealth.value = false;
    }
};

const ejecutarAutoRepair = async () => {
    if (!confirm('¿Desea escanear las ODCs habilitadas sin RIF y auto-vincularlas con la tabla de proveedores del ERP?')) return;
    procesandoAutoRepair.value = true;
    mensajeAutoRepair.value = '';
    try {
        const resp = await axios.post('/api/monitoreo/auto-repair');
        mensajeAutoRepair.value = resp.data.mensaje;
        cargarHealthStats();
    } catch (e) {
        mensajeAutoRepair.value = 'Error al ejecutar auto-reparación: ' + (e.response?.data?.error || e.message);
    } finally {
        procesandoAutoRepair.value = false;
    }
};

const ejecutarOptimizarAlmacenamiento = async () => {
    if (!confirm('¿Desea purgar automáticamente los registros de auditoría y correos con más de 30 días de antigüedad para mantener la base de datos veloz?')) return;
    procesandoPurga.value = true;
    mensajePurga.value = '';
    try {
        const resp = await axios.post('/api/monitoreo/purge-logs');
        mensajePurga.value = `${resp.data.message} (Eliminados: ${resp.data.detalles?.email_logs_eliminados || 0} correos y ${resp.data.detalles?.audit_logs_eliminados || 0} logs de auditoría antiguos).`;
        cargarHealthStats();
    } catch (e) {
        mensajePurga.value = 'Error al optimizar almacenamiento: ' + (e.response?.data?.error || e.message);
    } finally {
        procesandoPurga.value = false;
    }
};

// --- 2. TRAZABILIDAD FORENSE POR ODC ---
const busquedaForenseOdc = ref('');
const cargandoForense = ref(false);
const resultadoForense = ref(null);
const errorForense = ref('');

const buscarForense = async () => {
    if (!busquedaForenseOdc.value) return;
    cargandoForense.value = true;
    errorForense.value = '';
    resultadoForense.value = null;
    try {
        const resp = await axios.get(`/api/monitoreo/forensic/${busquedaForenseOdc.value}`);
        resultadoForense.value = resp.data;
    } catch (e) {
        errorForense.value = e.response?.data?.error || 'Error al buscar información de la ODC.';
    } finally {
        cargandoForense.value = false;
    }
};

// Vinculación manual de RIF
const mostrarModalVincular = ref(false);
const formVincular = ref({ numero_oc: '', rif: '' });
const procesandoVincular = ref(false);

const abrirModalVincular = (numOc, rifActual = '') => {
    formVincular.value = { numero_oc: numOc, rif: rifActual };
    mostrarModalVincular.value = true;
};

const guardarVinculacionManual = async () => {
    if (!formVincular.value.rif) return;
    procesandoVincular.value = true;
    try {
        await axios.post('/api/monitoreo/vincular-proveedor', formVincular.value);
        mostrarModalVincular.value = false;
        buscarForense();
        cargarHealthStats();
    } catch (e) {
        alert(e.response?.data?.error || 'Error al vincular proveedor.');
    } finally {
        procesandoVincular.value = false;
    }
};

// --- 3. BITÁCORA GLOBAL & AUDITORÍA ---
const auditLogs = ref([]);
const auditPagination = ref({ current_page: 1, last_page: 1, total: 0 });
const filtroModuloAudit = ref('');
const busquedaAudit = ref('');
const cargandoAudit = ref(false);

const cargarAuditLogs = async (page = 1) => {
    cargandoAudit.value = true;
    try {
        const resp = await axios.get('/api/monitoreo/audit-logs', {
            params: {
                page: page,
                module: filtroModuloAudit.value,
                search: busquedaAudit.value
            }
        });
        auditLogs.value = resp.data.data;
        auditPagination.value = {
            current_page: resp.data.current_page,
            last_page: resp.data.last_page,
            total: resp.data.total
        };
    } catch (e) {
        console.error(e);
    } finally {
        cargandoAudit.value = false;
    }
};

// --- 4. BITÁCORA DE CORREOS ---
const emailLogs = ref([]);
const emailPagination = ref({ current_page: 1, last_page: 1, total: 0 });
const filtroEstatusEmail = ref('todos');
const busquedaEmail = ref('');
const cargandoEmail = ref(false);

const cargarEmailLogs = async (page = 1) => {
    cargandoEmail.value = true;
    try {
        const resp = await axios.get('/api/monitoreo/email-logs', {
            params: {
                page: page,
                estatus: filtroEstatusEmail.value,
                search: busquedaEmail.value
            }
        });
        emailLogs.value = resp.data.data;
        emailPagination.value = {
            current_page: resp.data.current_page,
            last_page: resp.data.last_page,
            total: resp.data.total
        };
    } catch (e) {
        console.error(e);
    } finally {
        cargandoEmail.value = false;
    }
};

const formatFecha = (str) => {
    if (!str) return '—';
    try {
        let isoStr = str;
        if (typeof str === 'string' && !str.endsWith('Z') && !str.includes('+')) {
            isoStr = str.replace(' ', 'T') + 'Z';
        }
        const d = new Date(isoStr);
        return d.toLocaleString('es-VE', { 
            timeZone: 'America/Caracas', 
            day: '2-digit', 
            month: '2-digit', 
            year: '2-digit', 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    } catch (e) { return str; }
};

onMounted(() => {
    if (!esComprador) {
        cargarHealthStats();
        cargarAuditLogs();
    }
    cargarEmailLogs();
});
</script>

<template>
    <Head title="Centro de Monitoreo & Auditoría" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="font-black text-xl sm:text-2xl text-slate-800 tracking-tight">
                        {{ esComprador ? 'Monitor de Correos y Trazabilidad ODC' : 'Centro de Monitoreo & Auditoría' }}
                    </h2>
                    <p class="text-sm text-slate-500 font-medium mt-0.5">
                        {{ esComprador ? 'Seguimiento de notificaciones enviadas a proveedores e inspección forense 360° de ODCs' : 'Salud de servicios, trazabilidad forense por ODC y bitácora unificada' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="if (!esComprador) cargarHealthStats(); if (!esComprador && pestanaActiva === 'bitacora') cargarAuditLogs(); if (pestanaActiva === 'correos') cargarEmailLogs();"
                        class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Refrescar Panel
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- BARRA DE PESTAÑAS -->
            <div class="bg-white rounded-2xl p-1.5 shadow-sm border border-slate-200 flex flex-wrap gap-1">
                <button
                    v-if="!esComprador"
                    @click="pestanaActiva = 'salud'"
                    :class="pestanaActiva === 'salud' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                >
                    🩺 Salud & Auto-Reparación
                </button>
                <button
                    @click="pestanaActiva = 'trazabilidad'"
                    :class="pestanaActiva === 'trazabilidad' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                >
                    🔍 Trazabilidad Forense ODC
                </button>
                <button
                    v-if="!esComprador"
                    @click="pestanaActiva = 'bitacora'"
                    :class="pestanaActiva === 'bitacora' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                >
                    📜 Bitácora Global (Auditoría)
                </button>
                <button
                    @click="pestanaActiva = 'correos'"
                    :class="pestanaActiva === 'correos' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                >
                    📡 Monitor de Correos
                </button>
            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 1: SALUD DEL SISTEMA & AUTO-REPARACIÓN -->
            <!-- ========================================== -->
            <div v-if="!esComprador && pestanaActiva === 'salud'" class="space-y-6">
                <!-- TARJETAS DE INDICADORES (SEMÁFOROS) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Conexión ERP -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Conexión API ERP</span>
                            <span :class="healthStats?.erp_online ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'" class="w-3 h-3 rounded-full animate-ping"></span>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-black text-slate-800">{{ healthStats?.erp_online ? 'CONECTADO' : 'DESCONECTADO' }}</span>
                            <span class="text-xs font-semibold text-slate-400">({{ healthStats?.erp_latency_ms || 0 }}ms)</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">Comunicación activa con servidor de datos ERP</p>
                    </div>

                    <!-- Base de Datos Local -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Base Local MySQL</span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-black text-slate-800">OPERATIVO</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">Tablas de sincronización y citas activas</p>
                    </div>

                    <!-- Órdenes Huérfanas -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm" :class="healthStats?.huerfanas_count > 0 ? 'border-amber-300 bg-amber-50/20' : ''">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">ODCs Sin RIF (Huérfanas)</span>
                            <span :class="healthStats?.huerfanas_count > 0 ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white'" class="px-2 py-0.5 rounded-full text-[10px] font-black">
                                {{ healthStats?.huerfanas_count || 0 }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <span class="text-3xl font-black" :class="healthStats?.huerfanas_count > 0 ? 'text-amber-600' : 'text-slate-800'">
                                {{ healthStats?.huerfanas_count || 0 }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">Órdenes habilitadas pendientes por vincular</p>
                    </div>

                    <!-- Correos Fallidos 24h -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm" :class="healthStats?.correos_fallidos_24h > 0 ? 'border-red-300 bg-red-50/20' : ''">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Correos Fallidos (24h)</span>
                            <span :class="healthStats?.correos_fallidos_24h > 0 ? 'bg-red-500 text-white' : 'bg-emerald-500 text-white'" class="px-2 py-0.5 rounded-full text-[10px] font-black">
                                {{ healthStats?.correos_fallidos_24h || 0 }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <span class="text-3xl font-black" :class="healthStats?.correos_fallidos_24h > 0 ? 'text-red-600' : 'text-slate-800'">
                                {{ healthStats?.correos_fallidos_24h || 0 }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">Fallas notificadas en envíos de correo</p>
                    </div>
                </div>

                <!-- ACCIONES DE AUTO-REPARACION Y OPTIMIZACION DE ALMACENAMIENTO -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Tarjeta Auto-Reparación -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-lg">
                                ⚡
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Escáner de Auto-Reparación</h3>
                                <p class="text-xs text-slate-500">Auto-vincula ODCs habilitadas que no tengan RIF con la tabla de proveedores ERP</p>
                            </div>
                        </div>

                        <button
                            @click="ejecutarAutoRepair"
                            :disabled="procesandoAutoRepair"
                            class="w-full py-3 px-4 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2"
                        >
                            <span v-if="procesandoAutoRepair" class="animate-spin">⏳</span>
                            <span>{{ procesandoAutoRepair ? 'Analizando y Vinculando...' : 'Ejecutar Auto-Reparación de RIFs' }}</span>
                        </button>

                        <div v-if="mensajeAutoRepair" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-medium">
                            {{ mensajeAutoRepair }}
                        </div>
                    </div>

                    <!-- Tarjeta Optimización Almacenamiento -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-lg">
                                🧹
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Optimización de Almacenamiento (Auto-Purga)</h3>
                                <p class="text-xs text-slate-500">Elimina registros antiguos (>30 días) para mantener la base de datos ultrarrápida</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <span>Audit Logs Totales: <strong>{{ healthStats?.total_audit_logs || 0 }}</strong></span>
                            <span>Email Logs Totales: <strong>{{ healthStats?.total_email_logs || 0 }}</strong></span>
                        </div>

                        <button
                            @click="ejecutarOptimizarAlmacenamiento"
                            :disabled="procesandoPurga"
                            class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2"
                        >
                            <span v-if="procesandoPurga" class="animate-spin">⏳</span>
                            <span>{{ procesandoPurga ? 'Optimizando Base de Datos...' : 'Optimizar Almacenamiento (Purga 30 días)' }}</span>
                        </button>

                        <div v-if="mensajePurga" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-medium">
                            {{ mensajePurga }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 2: TRAZABILIDAD FORENSE ODC -->
            <!-- ========================================== -->
            <div v-if="pestanaActiva === 'trazabilidad'" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 text-base">Buscador Forense de Órdenes de Compra</h3>
                    <p class="text-xs text-slate-500">Escribe el número de ODC para generar la trazabilidad 360° (ERP ➔ Sync ➔ RIF ➔ Correos ➔ Cita)</p>

                    <div class="flex gap-2 max-w-xl">
                        <input
                            v-model="busquedaForenseOdc"
                            @keyup.enter="buscarForense"
                            type="text"
                            placeholder="Ej: 000030696"
                            class="flex-1 rounded-xl border-slate-300 text-sm focus:border-red-500 focus:ring-red-500 font-mono"
                        />
                        <button
                            @click="buscarForense"
                            :disabled="cargandoForense"
                            class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2"
                        >
                            <span v-if="cargandoForense" class="animate-spin">⏳</span>
                            <span>Inspeccionar ODC</span>
                        </button>
                    </div>

                    <div v-if="errorForense" class="p-4 bg-red-50 text-red-700 rounded-xl text-sm font-medium border border-red-200">
                        {{ errorForense }}
                    </div>

                    <!-- RESULTADO TRAZABILIDAD FORENSE -->
                    <div v-if="resultadoForense" class="mt-6 space-y-6 border-t border-slate-100 pt-6">
                        <!-- DIAGNÓSTICO DE VISIBILIDAD EN PANEL (DESTACADO) -->
                        <div class="p-4 rounded-2xl border flex flex-col md:flex-row md:items-center justify-between gap-4"
                             :class="resultadoForense.diagnostico_visibilidad?.es_visible ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900'">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ resultadoForense.diagnostico_visibilidad?.es_visible ? '🟢' : '⚠️' }}</span>
                                    <h4 class="font-black text-sm uppercase tracking-wider">
                                        ¿Le aparece esta orden al Proveedor en su panel?
                                    </h4>
                                </div>
                                <p class="text-xs font-bold font-mono pl-7">
                                    {{ resultadoForense.diagnostico_visibilidad?.motivo }}
                                </p>
                            </div>
                            <div v-if="resultadoForense.sync_row" class="text-right">
                                <span class="text-[10px] font-bold text-slate-500 uppercase block">Comprador que Habilitó:</span>
                                <span class="text-xs font-black text-slate-800">
                                    {{ resultadoForense.comprador_info?.nombre || 'Sistema / Desconocido' }}
                                </span>
                                <span v-if="resultadoForense.comprador_info?.fecha_habilitacion" class="text-[10px] text-slate-400 block">
                                    {{ formatFecha(resultadoForense.comprador_info.fecha_habilitacion) }}
                                </span>
                            </div>
                        </div>

                        <!-- FILA DE 4 COLUMNAS DE DETALLE -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- 1. Estado ERP -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">1. Estado en ERP SQL Server</span>
                                <div v-if="resultadoForense.erp_datos" class="mt-2 text-xs space-y-1">
                                    <p class="font-bold text-slate-800">{{ resultadoForense.erp_datos.c_descripcio || 'Sin Nombre' }}</p>
                                    <p class="font-mono text-slate-600">CÓDIGO: {{ resultadoForense.erp_datos.c_CODPROVEEDOR || '—' }}</p>
                                    <p class="font-mono text-slate-600">RIF: {{ resultadoForense.erp_datos.c_rif || '—' }}</p>
                                    <p class="font-bold text-emerald-600">Status ERP: {{ resultadoForense.erp_datos.c_status }}</p>
                                </div>
                                <div v-else class="mt-2 text-xs text-slate-400 font-medium">
                                    No consultado o sincronizado vía API
                                </div>
                            </div>

                            <!-- 2. Estado Sync Local & Comprador -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">2. Sync Local & Comprador</span>
                                <div v-if="resultadoForense.sync_row" class="mt-2 text-xs space-y-1">
                                    <p class="font-bold text-slate-800">Proveedor: {{ resultadoForense.sync_row.proveedor || '—' }}</p>
                                    <p class="font-mono" :class="resultadoForense.rif_target ? 'text-emerald-700 font-bold' : 'text-red-600 font-bold'">
                                        RIF Vinculado: {{ resultadoForense.rif_target || '⚠️ SIN RIF' }}
                                    </p>
                                    <p class="font-bold" :class="resultadoForense.sync_row.estatus_habilitacion === 'habilitada' ? 'text-emerald-600' : 'text-slate-600'">
                                        Estatus: {{ resultadoForense.sync_row.estatus_habilitacion || 'pendiente' }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        👤 Comprador: <strong>{{ resultadoForense.comprador_info?.nombre || 'No registrado' }}</strong>
                                    </p>
                                    <button
                                        @click="abrirModalVincular(resultadoForense.sync_row.numero_oc, resultadoForense.rif_target)"
                                        class="mt-2 px-2.5 py-1 bg-slate-900 text-white rounded-lg text-[10px] font-bold hover:bg-slate-800 transition-colors"
                                    >
                                        ✏️ Editar / Vincular RIF Manualmente
                                    </button>
                                </div>
                                <div v-else class="mt-2 text-xs text-red-500 font-medium">
                                    ⚠️ No existe en la tabla erp_ordenes_sync
                                </div>
                            </div>

                            <!-- 3. Actividad / Conexión del Proveedor -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">3. Conexión del Proveedor</span>
                                    <span class="text-[10px] font-extrabold bg-slate-200 text-slate-700 px-1.5 py-0.5 rounded">
                                        {{ resultadoForense.proveedor_actividad?.length || 0 }} Cuenta(s)
                                    </span>
                                </div>
                                <div v-if="resultadoForense.proveedor_actividad && resultadoForense.proveedor_actividad.length > 0" class="mt-2 text-xs space-y-2 max-h-[220px] overflow-y-auto pr-1">
                                    <div v-for="prov in resultadoForense.proveedor_actividad" :key="prov.id" class="p-2 bg-white rounded-lg border border-slate-200 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-slate-800 truncate text-xs">{{ prov.nombre || 'Vendedor' }}</span>
                                            <span v-if="prov.se_conecto_recientemente" class="px-1.5 py-0.5 bg-emerald-100 text-emerald-800 text-[9px] font-black rounded uppercase">🟢 Activo</span>
                                        </div>
                                        <p class="font-mono text-slate-600 text-[10px]">Usuario: <strong>{{ prov.username }}</strong></p>
                                        <p class="text-[10px] font-bold" :class="prov.se_conecto_recientemente ? 'text-emerald-600' : 'text-amber-600'">
                                            {{ prov.se_conecto_recientemente ? '🟢 Conectado por última vez:' : '🔴 Sin inicios de sesión' }}
                                        </p>
                                        <p v-if="prov.ultimo_login" class="text-[10px] text-slate-500 font-mono">{{ formatFecha(prov.ultimo_login) }}</p>
                                    </div>
                                </div>
                                <div v-else class="mt-2 text-xs text-red-500 font-medium">
                                    ⚠️ No hay usuario registrado en el sistema con el RIF {{ resultadoForense.rif_target || 'asignado' }}.
                                </div>
                            </div>

                            <!-- 4. Cita Agendada -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">4. Cita Agendada</span>
                                <div v-if="resultadoForense.cita" class="mt-2 text-xs space-y-1">
                                    <p class="font-bold text-blue-700">🚛 Cita: {{ formatFecha(resultadoForense.cita.fecha_cita) }}</p>
                                    <p class="text-slate-600">Muelle: {{ resultadoForense.cita.muelle_asignado }}</p>
                                    <p class="text-slate-600">Estatus: <span class="font-bold uppercase">{{ resultadoForense.cita.estatus }}</span></p>
                                    <p class="text-slate-500">Factura: {{ resultadoForense.cita.numero_factura || 'Sin Factura' }}</p>
                                    <p v-if="resultadoForense.cita.created_at" class="text-[11px] font-semibold text-emerald-700 pt-1 border-t border-slate-200 mt-1">
                                        🕒 Agendada el: <strong>{{ formatFecha(resultadoForense.cita.created_at) }}</strong>
                                    </p>
                                </div>
                                <div v-else class="mt-2 text-xs text-slate-400 font-medium">
                                    Aún no se ha reservado cita para esta orden
                                </div>
                            </div>
                        </div>

                        <!-- HISTORIAL DE CORREOS PARA ESTA ODC -->
                        <div class="space-y-3">
                            <h4 class="font-bold text-xs text-slate-600 uppercase tracking-wider">Historial de Correos Enviados a esta ODC</h4>
                            <div v-if="resultadoForense.email_logs && resultadoForense.email_logs.length > 0" class="overflow-hidden border border-slate-200 rounded-xl">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 text-slate-600 font-bold">
                                        <tr>
                                            <th class="p-3">Fecha / Hora</th>
                                            <th class="p-3">Destinatario</th>
                                            <th class="p-3">Vendedor</th>
                                            <th class="p-3">Estatus</th>
                                            <th class="p-3">Detalle</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="log in resultadoForense.email_logs" :key="log.id">
                                            <td class="p-3 text-slate-500">{{ formatFecha(log.created_at) }}</td>
                                            <td class="p-3 font-semibold text-slate-800">{{ log.email_destino }}</td>
                                            <td class="p-3 text-slate-600">{{ log.vendedor_nombre || '—' }}</td>
                                            <td class="p-3">
                                                <span :class="log.estatus === 'exitoso' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'" class="px-2 py-0.5 rounded-full font-bold text-[10px] uppercase">
                                                    {{ log.estatus }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-slate-500 font-mono text-[11px]">{{ log.error_mensaje || 'Enviado correctamente' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="p-4 bg-slate-50 rounded-xl text-xs text-slate-400 text-center">
                                No hay registros de correos enviados para esta ODC.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 3: BITÁCORA GLOBAL (AUDITORÍA) -->
            <!-- ========================================== -->
            <div v-if="!esComprador && pestanaActiva === 'bitacora'" class="space-y-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap gap-3 items-center justify-between">
                    <div class="flex gap-2 flex-1 min-w-[280px]">
                        <input
                            v-model="busquedaAudit"
                            @keyup.enter="cargarAuditLogs(1)"
                            type="text"
                            placeholder="Buscar por usuario, acción, motivo o ID..."
                            class="flex-1 rounded-xl border-slate-300 text-xs focus:border-red-500 focus:ring-red-500"
                        />
                        <select v-model="filtroModuloAudit" @change="cargarAuditLogs(1)" class="rounded-xl border-slate-300 text-xs focus:border-red-500 focus:ring-red-500">
                            <option value="">Todos los módulos</option>
                            <option value="Citas">Citas</option>
                            <option value="ODC">ODC</option>
                            <option value="Usuarios">Usuarios</option>
                            <option value="Operarios">Operarios</option>
                        </select>
                    </div>
                    <button @click="cargarAuditLogs(1)" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition-colors">
                        Actualizar Bitácora
                    </button>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-900 text-white font-bold">
                            <tr>
                                <th class="p-3.5">Fecha / Hora</th>
                                <th class="p-3.5">Usuario</th>
                                <th class="p-3.5">Módulo / Acción</th>
                                <th class="p-3.5">Motivo / Justificación</th>
                                <th class="p-3.5">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-slate-50 transition-colors">
                                <td class="p-3.5 text-slate-500 whitespace-nowrap">{{ formatFecha(log.created_at) }}</td>
                                <td class="p-3.5">
                                    <p class="font-bold text-slate-800">{{ log.user_name }}</p>
                                    <p class="text-[10px] text-slate-400 uppercase font-semibold">{{ log.user_role }}</p>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-bold text-[10px] mr-1.5">{{ log.module }}</span>
                                    <span class="font-semibold text-slate-800">{{ log.action }}</span>
                                </td>
                                <td class="p-3.5 text-slate-600 max-w-xs truncate">{{ log.motive || '—' }}</td>
                                <td class="p-3.5 font-mono text-slate-400 text-[11px]">{{ log.ip_address || '—' }}</td>
                            </tr>
                            <tr v-if="auditLogs.length === 0">
                                <td colspan="5" class="p-8 text-center text-slate-400">
                                    No se encontraron registros de auditoría que coincidan con los filtros.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- PAGINACION BITACORA AUDITORIA -->
                    <div v-if="auditPagination.last_page > 1" class="p-4 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs text-slate-500">Página {{ auditPagination.current_page }} de {{ auditPagination.last_page }}</span>
                        <div class="flex gap-2">
                            <button
                                @click="cargarAuditLogs(auditPagination.current_page - 1)"
                                :disabled="auditPagination.current_page <= 1"
                                class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-700 rounded-lg text-xs font-bold transition-colors"
                            >
                                Anterior
                            </button>
                            <button
                                @click="cargarAuditLogs(auditPagination.current_page + 1)"
                                :disabled="auditPagination.current_page >= auditPagination.last_page"
                                class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-700 rounded-lg text-xs font-bold transition-colors"
                            >
                                Siguiente
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 4: MONITOR DE CORREOS -->
            <!-- ========================================== -->
            <div v-if="pestanaActiva === 'correos'" class="space-y-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap gap-3 items-center justify-between">
                    <div class="flex gap-2 flex-1 min-w-[280px]">
                        <input
                            v-model="busquedaEmail"
                            @keyup.enter="cargarEmailLogs(1)"
                            type="text"
                            placeholder="Buscar por ODC, proveedor o correo destinatario..."
                            class="flex-1 rounded-xl border-slate-300 text-xs focus:border-red-500 focus:ring-red-500"
                        />
                        <select v-model="filtroEstatusEmail" @change="cargarEmailLogs(1)" class="rounded-xl border-slate-300 text-xs focus:border-red-500 focus:ring-red-500">
                            <option value="todos">Todos los estatus</option>
                            <option value="exitoso">Exitosos</option>
                            <option value="error">Con Error</option>
                        </select>
                    </div>
                    <button @click="cargarEmailLogs(1)" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition-colors">
                        Actualizar Envíos
                    </button>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-900 text-white font-bold">
                            <tr>
                                <th class="p-3.5">Fecha / Hora</th>
                                <th class="p-3.5">ODC / Proveedor</th>
                                <th class="p-3.5">Correo Destino</th>
                                <th class="p-3.5">Estatus</th>
                                <th class="p-3.5">Detalle / Mensaje de Respuesta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in emailLogs" :key="log.id" class="hover:bg-slate-50 transition-colors">
                                <td class="p-3.5 text-slate-500 whitespace-nowrap">{{ formatFecha(log.created_at) }}</td>
                                <td class="p-3.5">
                                    <p class="font-mono font-bold text-slate-800">{{ log.numero_oc }}</p>
                                    <p class="text-[10px] text-slate-500 truncate max-w-[180px]">{{ log.proveedor || '—' }}</p>
                                </td>
                                <td class="p-3.5 font-bold text-blue-700">{{ log.email_destino }}</td>
                                <td class="p-3.5">
                                    <span :class="log.estatus === 'exitoso' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'" class="px-2 py-0.5 rounded-full font-bold text-[10px] uppercase">
                                        {{ log.estatus }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-slate-600 font-mono text-[11px]">{{ log.error_mensaje || 'Enviado satisfactoriamente' }}</td>
                            </tr>
                            <tr v-if="emailLogs.length === 0">
                                <td colspan="5" class="p-8 text-center text-slate-400">
                                    No hay registros de envíos de correo que coincidan con los filtros.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- PAGINACION CORREOS -->
                    <div v-if="emailPagination.last_page > 1" class="p-4 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs text-slate-500">Página {{ emailPagination.current_page }} de {{ emailPagination.last_page }}</span>
                        <div class="flex gap-2">
                            <button
                                @click="cargarEmailLogs(emailPagination.current_page - 1)"
                                :disabled="emailPagination.current_page <= 1"
                                class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-700 rounded-lg text-xs font-bold transition-colors"
                            >
                                Anterior
                            </button>
                            <button
                                @click="cargarEmailLogs(emailPagination.current_page + 1)"
                                :disabled="emailPagination.current_page >= emailPagination.last_page"
                                class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-700 rounded-lg text-xs font-bold transition-colors"
                            >
                                Siguiente
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL VINCULACION MANUAL RIF -->
        <Modal :show="mostrarModalVincular" @close="mostrarModalVincular = false">
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-bold text-slate-800">Vincular RIF a Orden de Compra</h3>
                <p class="text-xs text-slate-500">Ingresa el RIF del proveedor para asociarlo directamente a la ODC {{ formVincular.numero_oc }}</p>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">RIF del Proveedor</label>
                    <input
                        v-model="formVincular.rif"
                        type="text"
                        placeholder="Ej: J402875320"
                        class="w-full rounded-xl border-slate-300 text-sm font-mono focus:border-red-500 focus:ring-red-500 uppercase"
                    />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="mostrarModalVincular = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold">Cancelar</button>
                    <button
                        @click="guardarVinculacionManual"
                        :disabled="procesandoVincular"
                        class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold"
                    >
                        {{ procesandoVincular ? 'Guardando...' : 'Guardar Vinculación' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const numeroOrden = ref('');
const datosOrden = ref(null);
const productosOrden = ref([]);
const datosExtra = ref(null);
const cargando = ref(false);
const errorMensaje = ref('');
const mostrarModalSKU = ref(false);
const busquedaProducto = ref('');
const operariosSeleccionados = ref(2);
const tiempoOptimo = ref(null);
const recalculando = ref(false);

const citasProgramadas = ref([]);

// Modales y Reprogramación
const mostrarModalReprogramar = ref(false);
const citaReprogramar = ref(null);
const formReprogramar = ref({
    fecha_cita: '',
    muelle_asignado: '',
    motivo: ''
});
const procesandoReprogramacion = ref(false);
const errorReprogramacion = ref('');
const motivoReprogramacion = ref('');

const abrirModalReprogramar = (cita) => {
    citaReprogramar.value = cita;
    
    // Formatear fecha para datetime-local (YYYY-MM-DDThh:mm)
    const fecha = new Date(cita.fecha_cita);
    const tzoffset = (new Date()).getTimezoneOffset() * 60000;
    const localISOTime = (new Date(fecha - tzoffset)).toISOString().slice(0, 16);
    
    formReprogramar.value = {
        fecha_cita: localISOTime,
        muelle_asignado: cita.muelle_asignado,
        motivo: ''
    };
    errorReprogramacion.value = '';
    mostrarModalReprogramar.value = true;
};

const guardarReprogramacion = async () => {
    if (!formReprogramar.value.motivo || formReprogramar.value.motivo.length < 5) {
        errorReprogramacion.value = 'Debe ingresar un motivo detallado (mínimo 5 caracteres).';
        return;
    }
    
    procesandoReprogramacion.value = true;
    errorReprogramacion.value = '';
    
    try {
        await axios.post(`/api/citas/${citaReprogramar.value.id}/reprogramar`, formReprogramar.value);
        mostrarModalReprogramar.value = false;
        cargarCitas();
        if (numeroOrden.value === citaReprogramar.value.numero_oc) {
            buscarOrden();
        }
    } catch (error) {
        errorReprogramacion.value = error.response?.data?.error || 'Error al reprogramar la cita.';
    } finally {
        procesandoReprogramacion.value = false;
    }
};

// Cancelación por proveedor
const mostrarModalCancelar = ref(false);
const citaCancelar = ref(null);
const motivoCancelacion = ref('');
const procesandoCancelacion = ref(false);
const errorCancelacion = ref('');

const mostrarModalReprogramarProveedor = ref(false);

const abrirModalCancelar = (cita) => {
    citaCancelar.value = cita;
    motivoCancelacion.value = '';
    errorCancelacion.value = '';
    mostrarModalCancelar.value = true;
};

const iniciarReprogramacion = (cita) => {
    citaReprogramar.value = cita;
    motivoReprogramacion.value = '';
    errorReprogramacion.value = '';
    mostrarModalReprogramarProveedor.value = true;
};

const continuarReprogramacion = () => {
    if (!motivoReprogramacion.value || motivoReprogramacion.value.length < 5) {
        errorReprogramacion.value = 'Debe ingresar un motivo detallado (mínimo 5 caracteres).';
        return;
    }
    
    localStorage.setItem('reprogramar_cita_id', citaReprogramar.value.id);
    localStorage.setItem('reprogramar_cita', JSON.stringify(citaReprogramar.value));
    localStorage.setItem('reprogramar_motivo', motivoReprogramacion.value);
    
    window.location.href = route('reservar-cita');
};

const guardarCancelacion = async () => {
    if (!motivoCancelacion.value || motivoCancelacion.value.length < 5) {
        errorCancelacion.value = 'Debe ingresar un motivo detallado (mínimo 5 caracteres).';
        return;
    }
    
    procesandoCancelacion.value = true;
    errorCancelacion.value = '';
    
    try {
        await axios.post(`/api/citas/${citaCancelar.value.id}/cancelar`, { motivo: motivoCancelacion.value });
        mostrarModalCancelar.value = false;
        cargarCitas();
        if (numeroOrden.value === citaCancelar.value.numero_oc) {
            buscarOrden();
        }
    } catch (error) {
        errorCancelacion.value = error.response?.data?.error || 'Error al cancelar la cita.';
    } finally {
        procesandoCancelacion.value = false;
    }
};

// Finalizar cita por admin/receptor/comprador
const mostrarModalFinalizar = ref(false);
const citaFinalizar = ref(null);
const procesandoFinalizar = ref(false);
const errorFinalizar = ref('');

const abrirModalFinalizar = (cita) => {
    citaFinalizar.value = cita;
    errorFinalizar.value = '';
    mostrarModalFinalizar.value = true;
};

const confirmarFinalizar = async () => {
    if (!citaFinalizar.value) return;
    const oc = citaFinalizar.value.numero_oc;
    try {
        procesandoFinalizar.value = true;
        errorFinalizar.value = '';
        await axios.post(`/api/citas/${citaFinalizar.value.id}/finalizar`);
        mostrarModalFinalizar.value = false;
        citaFinalizar.value = null;
        cargarCitas();
        if (numeroOrden.value === oc) {
            buscarOrden();
        }
    } catch (error) {
        errorFinalizar.value = error.response?.data?.error || 'Error al finalizar la cita.';
    } finally {
        procesandoFinalizar.value = false;
    }
};

// Filtro por tipo de mercancía
const filtroTipoMercancia = ref('todos');

const obtenerTipoCita = (tipoMercancia) => {
    if (!tipoMercancia) return 'secos';
    const clean = tipoMercancia.toLowerCase();
    if (clean.includes('frutas') || clean.includes('verduras') || clean.includes('fruver')) {
        return 'fruver';
    }
    if (clean.includes('perecedero') || clean.includes('charcuteria') || clean.includes('carniceria') || clean.includes('pescaderia')) {
        return 'perecederos';
    }
    return 'secos';
};

const citasFiltradas = computed(() => {
    if (filtroTipoMercancia.value === 'todos') {
        return citasProgramadas.value;
    }
    return citasProgramadas.value.filter(cita => {
        return obtenerTipoCita(cita.tipo_mercancia) === filtroTipoMercancia.value;
    });
});

const pestanaCitas = ref('activas');

const cambiarPestanaCitas = (nueva) => {
    pestanaCitas.value = nueva;
    filtroTipoMercancia.value = 'todos';
    cargarCitas();
};

let isFetchingCitas = false;
const cargarCitas = async () => {
    if (isFetchingCitas) return;
    isFetchingCitas = true;
    try {
        const resp = await axios.get('/api/citas', {
            params: { status: pestanaCitas.value }
        });
        citasProgramadas.value = resp.data.citas;
    } catch (e) {
        if (e.response?.status !== 503) console.error(e);
    } finally {
        isFetchingCitas = false;
    }
};

const buscarOrden = async () => {
    if (!numeroOrden.value) return;
    cargando.value = true;
    errorMensaje.value = '';
    datosOrden.value = null;
    productosOrden.value = [];
    datosExtra.value = null;
    tiempoOptimo.value = null;

    try {
        const completa = await axios.get(`/api/orden-completa/${numeroOrden.value}`);

        if (completa.data.status === 'Exitoso' && completa.data.resumen) {
            datosOrden.value = completa.data.resumen;
            productosOrden.value = completa.data.detalles || [];
            datosExtra.value = {
                factura: completa.data.factura_proveedor,
                factura_url: completa.data.factura_path,
                fecha_orden: completa.data.fecha_orden,
                fecha_recepcion: completa.data.fecha_recepcion,
                status_orden: completa.data.status_orden,
                status_texto: completa.data.status_texto,
                tipo_vehiculo: completa.data.tipo_vehiculo,
                tiempos: completa.data.tiempos,
            };
            tiempoOptimo.value = completa.data.tiempos;
            operariosSeleccionados.value = completa.data.tiempos?.operarios_usados || 2;
        } else {
            errorMensaje.value = 'La Orden de Compra no existe en el ERP.';
            return;
        }
    } catch (error) {
        if (error.response && error.response.status === 404) {
            errorMensaje.value = 'La Orden de Compra no existe en el ERP.';
        } else {
            errorMensaje.value = 'Error de conexión con el servidor de datos.';
        }
    } finally {
        cargando.value = false;
    }
};

const recalcularTiempo = async () => {
    if (!numeroOrden.value) return;
    recalculando.value = true;
    try {
        const resp = await axios.post(`/api/orden-recalcular/${numeroOrden.value}`, {
            operarios: operariosSeleccionados.value
        });
        if (tiempoOptimo.value) {
            tiempoOptimo.value.tiempo_optimo_minutos = resp.data.tiempo_optimo_minutos;
            tiempoOptimo.value.operarios_usados = resp.data.operarios_usados;
        }
    } catch (e) { console.error(e); }
    finally { recalculando.value = false; }
};

const handleBuscarDesdeNotif = (e) => {
    numeroOrden.value = e.detail;
    buscarOrden();
};
let refreshInterval = null;

onMounted(() => {
    window.addEventListener('buscar-orden', handleBuscarDesdeNotif);
    window.addEventListener('actualizacion-tiempo-real', cargarCitas);
    cargarCitas();
    
    refreshInterval = setInterval(() => {
        cargarCitas();
    }, 60000);
});
onUnmounted(() => {
    window.removeEventListener('buscar-orden', handleBuscarDesdeNotif);
    window.removeEventListener('actualizacion-tiempo-real', cargarCitas);
    if (refreshInterval) clearInterval(refreshInterval);
});

const abrirModalSKU = () => { mostrarModalSKU.value = true; };
const cerrarModalSKU = () => { mostrarModalSKU.value = false; busquedaProducto.value = ''; };

const productosFiltrados = computed(() => {
    if (!busquedaProducto.value) return productosOrden.value;
    return productosOrden.value.filter(p => 
        (p.codigo && p.codigo.toLowerCase().includes(busquedaProducto.value.toLowerCase())) ||
        (p.producto && p.producto.toLowerCase().includes(busquedaProducto.value.toLowerCase()))
    );
});

const totalBultos = computed(() => {
    return productosOrden.value.reduce((sum, p) => sum + Math.round(p.bultos || 0), 0);
});

const formatMinutos = (mins) => {
    if (mins === null || mins === undefined) return '—';
    if (mins < 60) return `${mins} minutos`;
    const hrs = Math.floor(mins / 60);
    const rest = mins % 60;
    return rest > 0 ? `${hrs}h y ${rest}m` : `${hrs} horas`;
};

const formatRangoHora = (fechaInicio, duracion) => {
    if (!fechaInicio) return '—';
    const ini = new Date(fechaInicio);
    const fin = new Date(ini.getTime() + (duracion * 60000));
    
    const fmt = (d) => d.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true });
    return `${fmt(ini)} - ${fmt(fin)}`;
};

const getSucursalNombre = (muelle) => {
    if (!muelle) return '—';
    const m = String(muelle);
    if (m === '0101') return '0101 (Hiper)';
    if (m === '0102') return '0102 (Dep Gral)';
    if (m === '0111') return '0111 (Producción)';
    if (m === '0115') return '0115 (Insumos)';
    if (m === '0161') return '0161 (Andinka)';
    return `Muelle ${m}`;
};

const getVehiculoNombre = (tipo) => {
    const t = String(tipo);
    if (t === '1') return { nombre: 'Camión Grande (750 Cajas)', cap: '750 Cajas', frio: false };
    if (t === '2') return { nombre: 'Camión Mediano (350 Cajas)', cap: '350 Cajas', frio: false };
    if (t === '3') return { nombre: 'Camioneta (120 Cajas)', cap: '120 Cajas', frio: false };
    if (t === '4') return { nombre: 'Camión C-350 / Frio (300 Cajas)', cap: '300 Cajas', frio: true };
    if (t === '5') return { nombre: 'Gandola Frio (1100 Cajas)', cap: '1100 Cajas', frio: true };
    if (t === '6') return { nombre: 'Turbo / Frio (350 Cajas)', cap: '350 Cajas', frio: true };
    return { nombre: 'Otro Vehículo', cap: 'N/D', frio: false };
};

const statusColor = (status) => {
    if (status === 'C') return 'bg-emerald-100 text-emerald-800';
    if (status === 'A' || status === '1') return 'bg-blue-100 text-blue-800';
    return 'bg-amber-100 text-amber-800';
};

const citaStatusColor = (status) => {
    const s = status?.toLowerCase();
    if (s === 'completada' || s === 'finalizada' || s === 'recibida') return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
    if (s === 'cancelada') return 'bg-rose-50 text-rose-700 border border-rose-100';
    if (s === 'demorada' || s === 'retrasada') return 'bg-red-50 text-red-700 border border-red-100';
    if (s === 'reprogramada') return 'bg-amber-50 text-amber-700 border border-amber-100';
    return 'bg-blue-50 text-blue-700 border border-blue-100'; // 'programada'
};

const categoriaPorFactor = (factor) => {
    const f = parseFloat(factor);
    if (f >= 1.5) return { texto: 'Alto Rendimiento', color: 'bg-emerald-50 text-emerald-700 border border-emerald-100', icono: '⚡' };
    if (f >= 0.8) return { texto: 'Estándar', color: 'bg-blue-50 text-blue-700 border border-blue-100', icono: '📦' };
    return { texto: 'Bajo Rendimiento', color: 'bg-amber-50 text-amber-700 border border-amber-100', icono: '🐢' };
};

const imprimirTicket = () => { window.print(); };

// === NUEVO: CÁLCULOS DINÁMICOS PARA KPIS (Estilo Lovable) ===
const kpis = computed(() => {
    const todayStr = new Date().toISOString().split('T')[0];
    
    // 1. Citas programadas para hoy
    const citasHoy = citasProgramadas.value.filter(c => c.fecha_cita && c.fecha_cita.startsWith(todayStr));
    
    // 2. Ocupación de Andenes (cantidad de muelles distintos que tienen citas activas hoy)
    const andenesOcupados = new Set(
        citasProgramadas.value
            .filter(c => c.fecha_cita && c.fecha_cita.startsWith(todayStr) && c.estatus === 'programada')
            .map(c => c.muelle_asignado)
    ).size;
    
    // 3. Tiempo promedio de descarga (calculado de duraciones de las citas)
    const citasConDuracion = citasProgramadas.value.filter(c => c.duracion_minutos > 0);
    const promMinutos = citasConDuracion.length > 0
        ? Math.round(citasConDuracion.reduce((sum, c) => sum + c.duracion_minutos, 0) / citasConDuracion.length)
        : 45;

    // 4. Esperando (citas en estatus programada para hoy)
    const enEspera = citasHoy.filter(c => c.estatus === 'programada').length;

    return [
        { title: 'Citas de hoy', value: citasHoy.length, detail: 'Registradas en sistema', color: 'text-primary' },
        { title: 'Ocupación de muelles', value: `${andenesOcupados}/8`, detail: 'Andenes activos hoy', color: 'text-indigo-600' },
        { title: 'Promedio descarga', value: `${promMinutos}m`, detail: 'Calculado automáticamente', color: 'text-amber-600' },
        { title: 'Camiones esperando', value: enEspera, detail: 'En fila o programados', color: 'text-emerald-600' }
    ];
});

// === NUEVO: CÁLCULOS DINÁMICOS PARA ESTADO DE ANDENES (A-01 al A-08) ===
const andenes = computed(() => {
    // Listado de códigos de muelles según controlador
    const codigosMuelle = ['01', '02', '03', '04', '0101', '0102', '0111', '0115'];
    const names = ['Andén A-01', 'Andén A-02', 'Andén A-03', 'Andén A-04', 'Muelle Express', 'Depósito General', 'Producción', 'Insumos'];

    const todayStr = new Date().toISOString().split('T')[0];

    return codigosMuelle.map((codigo, idx) => {
        // Buscar cita hoy en este muelle en estatus 'programada'
        const citaActiva = citasProgramadas.value.find(c => 
            String(c.muelle_asignado) === String(codigo) && 
            c.fecha_cita && 
            c.fecha_cita.startsWith(todayStr) && 
            c.estatus === 'programada'
        );

        let status = 'Libre';
        let badgeColor = 'bg-emerald-50 text-emerald-700 border-[#eef0eb]';
        let dotColor = 'bg-emerald-500';
        let detail = 'Listo para recibir';

        if (citaActiva) {
            status = 'Descargando';
            badgeColor = 'bg-blue-50 text-blue-700 border-blue-100 animate-pulse';
            dotColor = 'bg-blue-500';
            detail = `OC: ${citaActiva.numero_oc} - ${citaActiva.proveedor}`;
        }

        return {
            id: codigo,
            name: names[idx],
            status,
            badgeColor,
            dotColor,
            detail
        };
    });
});
</script>

<template>
    <Head title="Centro de Control" />
    <AuthenticatedLayout class="print:hidden">
        
        <div class="space-y-6 animate-fade-in">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#eef0eb] pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c]">
                        <span v-if="$page.props.auth.user.role === 'proveedor'">Mis Citas Agendadas</span>
                        <span v-else>Centro de Control</span>
                    </h1>
                    <p class="text-xs text-[#6c7263] mt-1 font-medium">Monitoreo operativo de muelles, análisis de tiempos y registro de recepciones en tiempo real.</p>
                </div>
            </div>

            <!-- KPI GRID (Estilo Lovable) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div v-for="(kpi, idx) in kpis" :key="idx" class="bg-white p-5 rounded-2xl border border-[#eef0eb] shadow-sm flex flex-col justify-between hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <span class="text-[9px] font-black uppercase tracking-widest text-[#888c80]">{{ kpi.title }}</span>
                        <!-- KPI Icon based on title -->
                        <div class="w-7 h-7 rounded-lg bg-[#faf9f6] flex items-center justify-center border border-[#eef0eb]">
                            <svg v-if="kpi.title.includes('Citas')" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <svg v-else-if="kpi.title.includes('Ocupación')" class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            <svg v-else-if="kpi.title.includes('Promedio')" class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <svg v-else class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 5H4v10h18V8.5L18.5 5H13z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-3.5 font-extrabold tracking-tight text-[#1c1c1c]">{{ kpi.value }}</span>
                    </div>
                    <span class="text-[10px] text-[#6c7263] font-bold mt-1.5 flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                        {{ kpi.detail }}
                    </span>
                </div>
            </div>

            <!-- DOCKS STATUS GRID (Estado de Andenes - Estilo Lovable) -->
            <div class="bg-white border border-[#eef0eb] rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-[#eef0eb] pb-3 mb-5">
                    <div>
                        <h2 class="text-sm font-extrabold text-[#1c1c1c] flex items-center gap-2">
                            <span class="flex h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                            Monitor en Tiempo Real de Andenes
                        </h2>
                        <p class="text-xs text-[#6c7263] mt-0.5 font-medium">Asignación física de camiones y muelles operativos.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="anden in andenes" :key="anden.id" 
                        class="border rounded-2xl p-4 flex flex-col justify-between hover:bg-[#faf9f6] transition-all duration-300 relative overflow-hidden"
                        :class="anden.status === 'Descargando' ? 'border-primary/25 bg-primary/[0.01] shadow-sm' : 'border-[#eef0eb] bg-white'">
                        
                        <!-- Top status badge -->
                        <div class="flex items-center justify-between gap-2 border-b border-[#eef0eb]/50 pb-2 mb-3">
                            <div class="flex items-center gap-1.5">
                                <div class="w-1.5 h-1.5 rounded-full" :class="anden.status === 'Descargando' ? 'bg-primary animate-ping' : 'bg-emerald-500'"></div>
                                <span class="text-xs font-extrabold text-[#1c1c1c]">{{ anden.name }}</span>
                            </div>
                            <span :class="anden.badgeColor" class="text-[8px] font-black px-2 py-0.5 rounded-lg border uppercase tracking-wider">
                                {{ anden.status }}
                            </span>
                        </div>
                        <!-- Dock Body -->
                        <div class="py-1">
                            <div v-if="anden.status === 'Descargando'" class="space-y-1">
                                <span class="text-[8px] font-black text-[#888c80] uppercase tracking-wider leading-none">OC Activa</span>
                                <p class="text-xs font-bold text-[#1c1c1c] font-mono leading-none">{{ anden.detail.split(' - ')[0] }}</p>
                                <p class="text-[10px] text-[#6c7263] font-semibold truncate leading-tight mt-1">{{ anden.detail.split(' - ')[1] }}</p>
                            </div>
                            <div v-else class="py-2 text-center text-[10px] text-[#888c80] font-bold uppercase tracking-wider flex items-center justify-center gap-1">
                                <span>📥</span> Disponible
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BÚSQUEDA RÁPIDA ERP -->
            <div v-if="$page.props.auth.user.role !== 'proveedor'" class="bg-white border border-[#eef0eb] rounded-2xl p-5 shadow-sm">
                <div class="max-w-xl">
                    <h3 class="text-xs font-black text-primary uppercase tracking-widest mb-1">Diagnóstico Rápido ERP</h3>
                    <p class="text-xs text-[#6c7263] mb-4 font-medium">Consulta y estima tiempos óptimos de descarga directamente desde los registros del ERP.</p>
                    <div class="flex gap-2">
                        <div class="relative flex-grow">
                            <input v-model="numeroOrden" @keyup.enter="buscarOrden" type="text" placeholder="Ej: E00001167"
                                class="block w-full px-4 py-2.5 border-[#eef0eb] bg-[#faf9f6] focus:border-primary focus:ring-primary/20 rounded-xl transition-all text-xs font-bold font-mono uppercase">
                        </div>
                        <button @click="buscarOrden" :disabled="cargando"
                            class="bg-[#1c1c1c] text-white hover:bg-zinc-800 px-5 rounded-xl text-xs font-bold transition-all shadow-md active:scale-95 disabled:opacity-50 uppercase tracking-wider">
                            {{ cargando ? 'Buscando...' : 'BUSCAR' }}
                        </button>
                    </div>
                    <p v-if="errorMensaje" class="text-rose-500 font-bold mt-2 text-xs">⚠️ {{ errorMensaje }}</p>
                </div>
            </div>

            <!-- PANEL DE CITAS AGENDADAS (TABLA PRINCIPAL) -->
            <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                
                <div class="px-6 py-5 border-b border-[#eef0eb] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-[#1c1c1c]">Agenda de Entregas</h2>
                        <p class="text-xs text-[#6c7263] mt-0.5">Control cronológico de citas y recepciones.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="$page.props.auth.user.role === 'proveedor'" href="/reservar-cita" class="bg-primary hover:bg-[#4f46e5] text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-lg shadow-primary/25 transition-all flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                            AGENDAR CITA
                        </Link>
                        <button @click="cargarCitas" class="p-2 border border-[#eef0eb] hover:bg-[#fcfbf8] text-[#6c7263] rounded-xl transition-colors" title="Actualizar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Tabs de Filtro de Citas -->
                <div class="flex border-b border-[#eef0eb] bg-[#fcfbf8]/40 px-6 gap-6">
                    <button @click="cambiarPestanaCitas('activas')" 
                        class="text-xs font-bold uppercase tracking-wider py-3.5 border-b-2 transition-all"
                        :class="pestanaCitas === 'activas' ? 'border-primary text-primary font-black' : 'border-transparent text-[#888c80] hover:text-[#1c1c1c]'">
                        Citas Activas
                    </button>
                    <button @click="cambiarPestanaCitas('finalizadas')" 
                        class="text-xs font-bold uppercase tracking-wider py-3.5 border-b-2 transition-all"
                        :class="pestanaCitas === 'finalizadas' ? 'border-primary text-primary font-black' : 'border-transparent text-[#888c80] hover:text-[#1c1c1c]'">
                        Historial Completadas
                    </button>
                </div>

                <!-- Filtro de Categorías -->
                <div v-if="$page.props.auth.user.role !== 'proveedor'" class="flex flex-wrap items-center gap-2 px-6 py-3 bg-[#fcfbf8]/10 border-b border-[#eef0eb]">
                    <span class="text-[10px] font-bold text-[#888c80] uppercase tracking-wider mr-2">Mercancía:</span>
                    
                    <button @click="filtroTipoMercancia = 'todos'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                        :class="filtroTipoMercancia === 'todos' ? 'bg-[#1c1c1c] text-white border-[#1c1c1c]' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-[#fcfbf8]'">
                        Todos
                    </button>
                    
                    <button @click="filtroTipoMercancia = 'secos'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                        :class="filtroTipoMercancia === 'secos' ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-orange-50/30'">
                        Secos
                    </button>
                    
                    <button @click="filtroTipoMercancia = 'fruver'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                        :class="filtroTipoMercancia === 'fruver' ? 'bg-lime-50 text-lime-700 border-lime-200' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-lime-50/30'">
                        Fruver
                    </button>
                    
                    <button @click="filtroTipoMercancia = 'perecederos'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                        :class="filtroTipoMercancia === 'perecederos' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:bg-rose-50/30'">
                        Perecederos
                    </button>
                </div>

                <!-- Citas List -->
                <div v-if="citasFiltradas.length > 0" class="divide-y divide-[#eef0eb]">
                    <div v-for="cita in citasFiltradas" :key="cita.id" class="px-6 py-5 flex flex-col lg:flex-row lg:items-center justify-between hover:bg-[#fcfbf8]/30 transition-colors group gap-4">
                        <div class="flex items-start sm:items-center gap-4.5 min-w-0">
                            <!-- Fecha Block -->
                            <div class="w-14 h-14 shrink-0 bg-[#f5f6f2] rounded-2xl flex flex-col items-center justify-center border border-[#eef0eb]">
                                <span class="text-[9px] font-bold text-[#888c80] uppercase">{{ new Date(cita.fecha_cita).toLocaleDateString('es-VE', { month: 'short' }) }}</span>
                                <span class="text-xl font-extrabold text-[#1c1c1c] -mt-0.5">{{ new Date(cita.fecha_cita).getDate() }}</span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <p class="font-bold text-[#1c1c1c] font-mono text-base">{{ cita.numero_oc }}</p>
                                    <span :class="citaStatusColor(cita.estatus)" class="text-[9px] font-black px-2 py-0.5 rounded-full uppercase">{{ cita.estatus }}</span>
                                    <span v-if="cita.tipo_mercancia" class="text-[9px] font-black px-2 py-0.5 rounded-full uppercase border"
                                        :class="{
                                            'bg-orange-50 text-orange-700 border-orange-200': obtenerTipoCita(cita.tipo_mercancia) === 'secos',
                                            'bg-lime-50 text-lime-700 border-lime-200': obtenerTipoCita(cita.tipo_mercancia) === 'fruver',
                                            'bg-rose-50 text-rose-700 border-rose-200': obtenerTipoCita(cita.tipo_mercancia) === 'perecederos'
                                        }">
                                        {{ obtenerTipoCita(cita.tipo_mercancia) }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-[#6c7263] truncate">{{ cita.proveedor }}</p>
                                
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mt-2 text-xs text-[#888c80]">
                                    <span class="flex items-center gap-1.5">🕒 {{ formatRangoHora(cita.fecha_cita, cita.duracion_minutos || 0) }}</span>
                                    <span class="flex items-center gap-1.5">🚛 {{ getSucursalNombre(cita.muelle_asignado) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Admin/Receptor Actions -->
                            <template v-if="$page.props.auth.user.role !== 'proveedor'">
                                <button v-if="cita.estatus === 'programada'" @click="abrirModalFinalizar(cita)"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md transition-all">
                                    Completar
                                </button>
                                <button @click="abrirModalReprogramar(cita)"
                                    class="border border-[#eef0eb] hover:bg-[#fcfbf8] text-[#1c1c1c] px-4 py-2 rounded-xl text-xs font-bold transition-all">
                                    Reprogramar
                                </button>
                                <button @click="numeroOrden = cita.numero_oc; buscarOrden()"
                                    class="bg-[#1c1c1c] hover:bg-zinc-800 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-md">
                                    Ver Detalle
                                </button>
                            </template>
                            
                            <!-- Proveedor Actions -->
                            <template v-else-if="cita.estatus === 'programada'">
                                <button @click="iniciarReprogramacion(cita)"
                                    class="border border-[#eef0eb] hover:bg-[#fcfbf8] text-orange-600 px-4 py-2 rounded-xl text-xs font-bold transition-all">
                                    Reprogramar
                                </button>
                                <button @click="abrirModalCancelar(cita)"
                                    class="border border-[#eef0eb] hover:bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-xs font-bold transition-all">
                                    Cancelar
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div v-else class="px-6 py-16 text-center text-[#888c80]">
                    <div class="text-3xl mb-2">📭</div>
                    <p class="text-sm font-medium">No hay citas registradas</p>
                    <p class="text-xs mt-0.5">Las programaciones vigentes aparecerán listadas aquí.</p>
                </div>
            </div>

            <!-- DETALLE COMPLETO DE ORDEN ACTIVA -->
            <div v-if="datosOrden && $page.props.auth.user.role !== 'proveedor'" class="space-y-6 animate-scale-in">
                <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-[#1c1c1c] p-6 text-white flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <div>
                            <span class="bg-primary/20 text-primary text-[9px] font-black px-2 py-0.5 rounded border border-primary/30 uppercase tracking-wider mb-2.5 inline-block">Proveedor Seleccionado</span>
                            <h3 class="text-2xl font-bold leading-tight">{{ datosOrden.Nombre_Proveedor }}</h3>
                            <p class="text-xs text-[#888c80] mt-1 font-mono">RIF: {{ datosOrden.Codigo_Proveedor }}</p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-[#888c80] text-[10px] font-bold uppercase tracking-wider">Orden Compra</p>
                            <p class="text-xl font-bold font-mono text-primary mt-1">{{ datosOrden.Numero_OC }}</p>
                            <span v-if="datosExtra" :class="statusColor(datosExtra.status_orden)" class="inline-block mt-2 text-[9px] font-black px-2.5 py-0.5 rounded-full uppercase">
                                {{ datosExtra.status_texto }}
                            </span>
                        </div>
                    </div>

                    <!-- Datos Principales Grid -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Col 1: Contacto -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-[#1c1c1c] uppercase tracking-wider border-b border-[#eef0eb] pb-2">Contacto & Datos</h4>
                            <div>
                                <p class="text-[10px] font-bold text-[#888c80] uppercase">Teléfono</p>
                                <p class="text-sm font-semibold text-[#1c1c1c] mt-0.5">{{ datosOrden.Telefono_Proveedor || 'No registrado' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-[#888c80] uppercase">Comprador Asignado</p>
                                <p class="text-sm font-semibold text-[#1c1c1c] mt-0.5">{{ datosOrden.Comprador_Interno || 'General' }}</p>
                            </div>
                            <div v-if="datosExtra">
                                <p class="text-[10px] font-bold text-[#888c80] uppercase">Factura Asociada</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <p class="text-sm font-bold font-mono text-[#1c1c1c]">{{ datosExtra.factura || 'Por facturar' }}</p>
                                    <a v-if="datosExtra.factura_url" :href="datosExtra.factura_url" target="_blank"
                                        class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-2 py-0.5 rounded-lg text-[10px] font-bold hover:bg-emerald-100 transition-colors">
                                        Ver PDF
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Col 2: Tipo de Transporte -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-[#1c1c1c] uppercase tracking-wider border-b border-[#eef0eb] pb-2">Transporte & Mercancía</h4>
                            <div v-if="datosExtra && datosExtra.tipo_vehiculo" class="p-3.5 rounded-xl border" :class="getVehiculoNombre(datosExtra.tipo_vehiculo).frio ? 'bg-cyan-50 border-cyan-100' : 'bg-zinc-50 border-[#eef0eb]'">
                                <p class="text-[9px] font-bold uppercase tracking-wider flex items-center gap-1.5" :class="getVehiculoNombre(datosExtra.tipo_vehiculo).frio ? 'text-cyan-600' : 'text-[#6c7263]'">
                                    🛞 Tipo Vehículo
                                </p>
                                <p class="text-sm font-bold text-[#1c1c1c] mt-1">{{ getVehiculoNombre(datosExtra.tipo_vehiculo).nombre }}</p>
                                <p class="text-[10px] text-[#6c7263] mt-0.5">Capacidad: {{ getVehiculoNombre(datosExtra.tipo_vehiculo).cap }}</p>
                                <span v-if="getVehiculoNombre(datosExtra.tipo_vehiculo).frio" class="mt-2 inline-block text-[9px] font-black bg-cyan-600 text-white px-2 py-0.5 rounded-full uppercase">❄️ Cadena de Frío</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 bg-[#f5f6f2] rounded-xl flex items-center justify-center font-bold text-primary border border-[#eef0eb] shrink-0">{{ datosOrden.Total_SKUs }}</div>
                                <div>
                                    <p class="text-xs font-bold text-[#1c1c1c] uppercase">Variedad SKUs</p>
                                    <button @click="abrirModalSKU" class="text-[10px] text-primary hover:underline font-bold mt-0.5">Ver lista completa</button>
                                </div>
                            </div>
                        </div>

                        <!-- Col 3: Ciclos y Destino -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-[#1c1c1c] uppercase tracking-wider border-b border-[#eef0eb] pb-2">Destino Sugerido</h4>
                            <div class="bg-[#fcfbf8] rounded-2xl p-4 border border-[#eef0eb] flex justify-around items-center">
                                <div class="text-center">
                                    <span class="text-[9px] font-bold text-[#888c80] uppercase">Viajes Estimados</span>
                                    <p class="text-3xl font-extrabold text-[#1c1c1c] mt-1">{{ Math.round(datosOrden.Ciclos_Necesarios) }}</p>
                                </div>
                                <div class="h-8 w-[1px] bg-[#eef0eb]"></div>
                                <div class="text-center">
                                    <span class="text-[9px] font-bold text-[#888c80] uppercase">Sucursal</span>
                                    <p class="text-sm font-bold text-primary mt-2 truncate max-w-[100px]">{{ getSucursalNombre(datosOrden.Muelle_Destino) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tiempo Óptimo Recalculador Widget -->
                    <div v-if="tiempoOptimo" class="mx-6 mb-6 bg-[#f5f6f2] rounded-2xl p-5 border border-[#eef0eb]">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <span class="text-[9px] font-bold text-[#888c80] uppercase">Cálculo Optimizado de Tiempos</span>
                                <p class="text-2xl font-black text-[#1c1c1c] mt-1">
                                    {{ formatMinutos(tiempoOptimo.tiempo_optimo_minutos) }}
                                    <span class="text-xs text-[#6c7263] font-medium ml-2">con {{ tiempoOptimo.operarios_usados }} equipos de trabajo</span>
                                </p>
                                <p class="text-[11px] text-[#888c80] mt-0.5">Tiempo base estimado sin optimizar: {{ formatMinutos(tiempoOptimo.tiempo_base_minutos) }}</p>
                            </div>
                            <div class="flex items-center gap-4 bg-white p-3 rounded-xl border border-[#eef0eb]">
                                <div class="text-center">
                                    <span class="text-[9px] font-bold text-[#888c80] uppercase">Ajustar Equipos</span>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <button @click="operariosSeleccionados = Math.max(1, operariosSeleccionados - 1); recalcularTiempo()"
                                            class="w-7 h-7 bg-[#f5f6f2] rounded-lg flex items-center justify-center hover:bg-primary hover:text-white transition-colors font-bold text-xs">">−</button>
                                        <span class="text-base font-extrabold w-6 text-center">{{ operariosSeleccionados }}</span>
                                        <button @click="operariosSeleccionados++; recalcularTiempo()"
                                            class="w-7 h-7 bg-[#f5f6f2] rounded-lg flex items-center justify-center hover:bg-primary hover:text-white transition-colors font-bold text-xs">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Footer Action -->
                    <div class="px-6 py-4 bg-[#fcfbf8] border-t border-[#eef0eb] flex justify-between items-center text-xs">
                        <span class="text-[#888c80] italic">Cálculos proyectados basados en 96 bultos por ciclo.</span>
                        <button @click="imprimirTicket" class="text-primary hover:underline font-bold uppercase tracking-wider">Imprimir Ticket Recepción</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE PRODUCTOS (SKUS) -->
        <Modal :show="mostrarModalSKU" max-width="2xl" @close="cerrarModalSKU">
            <div class="bg-white rounded-2xl overflow-hidden">
                <div class="bg-[#1c1c1c] px-6 py-5 text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold">Detalle de Productos</h3>
                        <p class="text-xs text-[#888c80] mt-0.5">Variedad de la Orden: {{ datosOrden?.Numero_OC }}</p>
                    </div>
                    <button @click="cerrarModalSKU" class="text-[#888c80] hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="px-6 py-3 border-b border-[#eef0eb] bg-[#fcfbf8]/60">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-[#888c80]"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></span>
                        <input v-model="busquedaProducto" type="text" placeholder="Buscar por código o nombre de producto..."
                            class="block w-full pl-9 pr-4 py-2 border-[#eef0eb] bg-white focus:border-primary focus:ring-primary/20 rounded-xl transition-all text-xs">
                    </div>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-[#fcfbf8] sticky top-0 border-b border-[#eef0eb] z-10 text-[#888c80]">
                            <tr>
                                <th class="px-6 py-2.5 text-left font-bold uppercase">Cód.</th>
                                <th class="px-4 py-2.5 text-left font-bold uppercase">Descripción</th>
                                <th class="px-4 py-2.5 text-center font-bold uppercase">Cantidad</th>
                                <th class="px-6 py-2.5 text-center font-bold uppercase">Volumen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#eef0eb]">
                            <tr v-for="(producto, idx) in productosFiltrados" :key="idx" class="hover:bg-[#fcfbf8]/40 transition-colors">
                                <td class="px-6 py-3 font-mono font-bold text-primary">{{ producto.codigo }}</td>
                                <td class="px-4 py-3 font-medium text-[#1c1c1c]">{{ producto.producto }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold text-[#1c1c1c] bg-[#f5f6f2] px-2 py-0.5 rounded border border-[#eef0eb]">
                                        {{ Math.round(producto.cantidad_unidades) }} {{ producto.c_presenta || 'und' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <span :class="categoriaPorFactor(producto.factor_carrito).color" class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold">
                                        {{ categoriaPorFactor(producto.factor_carrito).texto }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>

        <!-- Modal de Reprogramación (Admin) -->
        <Modal :show="mostrarModalReprogramar" @close="mostrarModalReprogramar = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-[#1c1c1c] mb-4">Reprogramar Cita</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#888c80] uppercase mb-1">Nueva Fecha y Hora</label>
                        <input v-model="formReprogramar.fecha_cita" type="datetime-local" class="w-full rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#888c80] uppercase mb-1">Muelle / Destino</label>
                        <select v-model="formReprogramar.muelle_asignado" class="w-full rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20 bg-white">
                            <option value="0101">0101 (Hiper)</option>
                            <option value="0102">0102 (Dep Gral)</option>
                            <option value="0111">0111 (Producción)</option>
                            <option value="0115">0115 (Insumos)</option>
                            <option value="0161">0161 (Andinka)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#888c80] uppercase mb-1">Justificación del Cambio</label>
                        <textarea v-model="formReprogramar.motivo" rows="3" class="w-full rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20 text-xs" placeholder="Explique brevemente..."></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="mostrarModalReprogramar = false" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cancelar</button>
                    <button @click="guardarReprogramacion" :disabled="procesandoReprogramacion" class="bg-[#1c1c1c] hover:bg-zinc-800 text-white px-5 py-2 rounded-xl text-xs font-bold">
                        Guardar
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal Cancelar Cita (Proveedor) -->
        <Modal :show="mostrarModalCancelar" @close="mostrarModalCancelar = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-rose-600 mb-3">Cancelar Cita</h3>
                <p class="text-xs text-[#6c7263] mb-4">Esta acción cancelará definitivamente la cita. Debes justificar el motivo.</p>
                <textarea v-model="motivoCancelacion" rows="3" class="w-full rounded-xl border-[#eef0eb] focus:border-rose-500 focus:ring-rose-500/20 text-xs" placeholder="Indique el motivo..."></textarea>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="mostrarModalCancelar = false" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cerrar</button>
                    <button @click="guardarCancelacion" :disabled="procesandoCancelacion || motivoCancelacion.length < 5" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 rounded-xl text-xs font-bold">
                        Confirmar Cancelación
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal Finalizar Cita (Admin) -->
        <Modal :show="mostrarModalFinalizar" @close="mostrarModalFinalizar = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-[#1c1c1c] mb-3">Finalizar Recepción</h3>
                <p class="text-xs text-[#6c7263] mb-4">Marcarás la descarga de la OC {{ citaFinalizar?.numero_oc }} como completada en muelle.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="mostrarModalFinalizar = false" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cancelar</button>
                    <button @click="confirmarFinalizar" :disabled="procesandoFinalizar" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-xl text-xs font-bold">
                        Marcar Completada
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal Reprogramar Cita (Proveedor) -->
        <Modal :show="mostrarModalReprogramarProveedor" @close="mostrarModalReprogramarProveedor = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-orange-600 mb-3">Reprogramar Entrega</h3>
                <p class="text-xs text-[#6c7263] mb-4 font-medium">Justifica el cambio para habilitar el calendario de reprogramaciones.</p>
                <textarea v-model="motivoReprogramacion" rows="3" class="w-full rounded-xl border-[#eef0eb] focus:border-orange-500 focus:ring-orange-500/20 text-xs" placeholder="Motivo de reprogramación..."></textarea>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="mostrarModalReprogramarProveedor = false" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cerrar</button>
                    <button @click="continuarReprogramacion" :disabled="motivoReprogramacion.length < 5" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-xl text-xs font-bold">
                        Elegir Nueva Fecha
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Ticket de Impresión Oculto -->
        <div class="hidden print:block absolute inset-0 bg-white z-[99999] p-8 text-black font-sans" v-if="datosOrden">
            <div class="max-w-sm mx-auto border-2 border-black p-6 rounded-xl">
                <div class="text-center mb-6">
                    <h1 class="text-2xl font-black uppercase tracking-widest">TICKET DE RECEPCIÓN</h1>
                    <p class="text-sm font-bold mt-1">LOGÍSTICA</p>
                </div>
                
                <div class="border-t border-b border-black py-4 mb-4">
                    <div class="text-center mb-4">
                        <p class="text-xs uppercase font-bold text-gray-600">ORDEN DE COMPRA</p>
                        <p class="text-4xl font-black font-mono mt-1">{{ datosOrden.Numero_OC }}</p>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-500">Proveedor</p>
                            <p class="font-bold text-sm leading-tight">{{ datosOrden.Nombre_Proveedor }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-500">Sucursal Asignada</p>
                            <p class="font-black text-lg">{{ getSucursalNombre(datosOrden.Muelle_Destino) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mb-6 space-y-2">
                    <div class="flex justify-between border-b border-gray-300 pb-1">
                        <span class="text-xs font-bold uppercase">Variedad (SKUs)</span>
                        <span class="text-sm font-black">{{ datosOrden.Total_SKUs }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-300 pb-1" v-if="datosOrden.Total_Cajas_Fisicas > 0">
                        <span class="text-xs font-bold uppercase">Cajas Totales</span>
                        <span class="text-sm font-black">{{ Math.round(datosOrden.Total_Cajas_Fisicas) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-300 pb-1">
                        <span class="text-xs font-bold uppercase">Ciclos de Descarga</span>
                        <span class="text-sm font-black">{{ Math.round(datosOrden.Ciclos_Necesarios) }} viajes</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-300 pb-1" v-if="tiempoOptimo">
                        <span class="text-xs font-bold uppercase">Tiempo Estimado</span>
                        <span class="text-sm font-black">{{ formatMinutos(tiempoOptimo.tiempo_optimo_minutos) }}</span>
                    </div>
                </div>
                
                <div class="text-center text-[10px] font-bold mt-8 uppercase">
                    <p>Presentar este ticket en el andén de recepción</p>
                    <p class="mt-2">{{ new Date().toLocaleString('es-VE') }}</p>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
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
        cargarCitas(); // Refrescar lista
        // Si estábamos viendo el detalle de esta cita, buscar de nuevo
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
    
    // Guardar la intención en localStorage
    localStorage.setItem('reprogramar_cita_id', citaReprogramar.value.id);
    localStorage.setItem('reprogramar_cita', JSON.stringify(citaReprogramar.value));
    localStorage.setItem('reprogramar_motivo', motivoReprogramacion.value);
    
    // Redirigir a ReservarCita
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

// Filtro por tipo de mercancía (Secos, Fruver, Perecederos)
const filtroTipoMercancia = ref('todos'); // 'todos', 'secos', 'fruver', 'perecederos'

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

const pestanaCitas = ref('activas'); // 'activas', 'finalizadas'

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

// Escuchar evento de notificaciones para buscar orden
const handleBuscarDesdeNotif = (e) => {
    numeroOrden.value = e.detail;
    buscarOrden();
};
let refreshInterval = null;

onMounted(() => {
    window.addEventListener('buscar-orden', handleBuscarDesdeNotif);
    window.addEventListener('actualizacion-tiempo-real', cargarCitas);
    cargarCitas();
    
    // Polling independiente cada 60 segundos (1 minuto) para garantizar actualización sin saturar
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
    const t = busquedaProducto.value.toLowerCase();
    return productosOrden.value.filter(p =>
        (p.codigo && p.codigo.toLowerCase().includes(t)) ||
        (p.producto && p.producto.toLowerCase().includes(t))
    );
});

const categoriaPorFactor = (factor) => {
    const c = {
        1: { texto: 'Grande', color: 'bg-emerald-100 text-emerald-700', icono: '📦' },
        2: { texto: 'Mediano', color: 'bg-blue-100 text-blue-700', icono: '📋' },
        3: { texto: 'Pequeño', color: 'bg-amber-100 text-amber-700', icono: '📎' },
        4: { texto: 'Micro', color: 'bg-indigo-100 text-indigo-700', icono: '🔸' },
    };
    return c[factor] || { texto: 'N/A', color: 'bg-slate-100 text-slate-600', icono: '❓' };
};

const totalBultos = computed(() => productosOrden.value.reduce((s, p) => s + (Number(p.bultos) || 0), 0));

const formatFecha = (f) => {
    if (!f) return '—';
    const d = new Date(f);
    return d.toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatHora = (f) => {
    if (!f) return '—';
    return new Date(f).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true });
};

const formatRangoHora = (fechaInicio, duracionMinutos) => {
    if (!fechaInicio) return '—';
    const inicio = new Date(fechaInicio);
    const fin = new Date(inicio.getTime() + (duracionMinutos * 60000));
    const strInicio = inicio.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true });
    const strFin = fin.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true });
    return `${strInicio} - ${strFin}`;
};

const statusColor = (s) => {
    const m = { DWT: 'bg-amber-100 text-amber-700', DPE: 'bg-blue-100 text-blue-700', DCO: 'bg-emerald-100 text-emerald-700', DAN: 'bg-indigo-100 text-indigo-700' };
    return m[s] || 'bg-slate-100 text-slate-600';
};

const citaStatusColor = (s) => {
    const m = { 
        programada: 'bg-amber-100 text-amber-700', 
        'en muelle': 'bg-blue-100 text-blue-700', 
        finalizada: 'bg-emerald-100 text-emerald-700', 
        cancelada: 'bg-indigo-100 text-indigo-700' 
    };
    return m[s] || 'bg-slate-100 text-slate-600';
};

const formatMinutos = (m) => {
    if (!m) return '—';
    if (m < 60) return `${m} minutos`;
    const h = Math.floor(m / 60);
    const r = m % 60;
    const hText = h === 1 ? 'hora' : 'horas';
    return r > 0 ? `${h} ${hText} ${r} min` : `${h} ${hText}`;
};

const imprimirTicket = () => {
    window.print();
};

const getVehiculoNombre = (codigo) => {
    const map = {
        'minivan': { nombre: 'Minivan / Super Carry', cap: 'Hasta 950 kg | 2.5 - 4.5 m³', frio: false },
        'camioneta_panel': { nombre: 'Camioneta Carga / Panel', cap: '700 kg - 1.5 Ton | 3 - 6 m³', frio: false },
        'cava_pequena': { nombre: 'Camión 350 / Cava Pequeña', cap: '2.5 - 3.5 Ton | 10 - 15 m³', frio: true },
        'camion_liviano': { nombre: 'Camión Liviano (NPR / FVR 71)', cap: '4.5 - 5.5 Ton | 20 - 25 m³', frio: false },
        'camion_mediano': { nombre: 'Camión Mediano (NQR / Cargo 815)', cap: '6.5 - 8 Ton | 30 - 35 m³', frio: false },
        'camion_sencillo': { nombre: 'Camión Sencillo (Rígido Pesado)', cap: '8 - 12 Ton | 40 - 45 m³', frio: false },
        'camion_toronto': { nombre: 'Camión Toronto / Balancín', cap: '14 - 18 Ton | 45 - 55 m³', frio: false },
        'gandola_furgon': { nombre: 'Gandola con Furgón (48-53 pies)', cap: '24 - 30 Ton | 90 - 110 m³', frio: false },
        'gandola_sider': { nombre: 'Gandola Sider (Cortina Lateral)', cap: '24 - 28 Ton | 90 - 105 m³', frio: false },
        // Legacy values (citas antiguas)
        'turbo': { nombre: 'Camión 350 / Cava Pequeña (Turbo)', cap: '2.5 - 3.5 Ton | 10 - 15 m³', frio: false },
        'sencillo': { nombre: 'Camión Sencillo (Rígido Pesado)', cap: '8 - 12 Ton | 40 - 45 m³', frio: false },
        'gandola': { nombre: 'Gandola con Furgón (48-53 pies)', cap: '24 - 30 Ton | 90 - 110 m³', frio: false },
    };
    return map[codigo] || { nombre: codigo || 'No especificado', cap: '', frio: false };
};

const getSucursalNombre = (codigo) => {
    const map = {
        '0101': 'Tu Empresa',
        '0102': 'Depósito General',
        '0111': 'Producción',
        '0115': 'Insumos',
        '0161': 'Andinka',
        '01': 'Galpón Central',
        '02': 'Galpón Central',
        '03': 'Galpón Central',
        '04': 'Galpón Central'
    };
    return map[codigo] || codigo;
};
</script>

<template>
    <Head title="Panel de Logística" />
    <AuthenticatedLayout class="print:hidden">
        <template #header>
            <div class="flex items-center justify-between print:hidden">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    <span v-if="$page.props.auth.user.role === 'proveedor'">Portal del Proveedor <span class="text-indigo-600">| Mis Citas</span></span>
                    <span v-else>Módulo de Recepción <span class="text-indigo-600">| Control de Citas</span></span>
                </h2>
                <div class="text-sm font-medium text-slate-500 bg-slate-100 px-4 py-1 rounded-full border border-slate-200">
                    Sincronizado con ERP
                </div>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen print:hidden">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- BÚSQUEDA -->
                <div v-if="$page.props.auth.user.role !== 'proveedor'" class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl p-8 mb-8 border border-slate-100">
                    <div class="max-w-xl">
                        <h3 class="text-sm font-bold text-indigo-600 uppercase tracking-widest mb-2">Búsqueda rápida</h3>
                        <p class="text-slate-500 mb-6">Ingrese el número de la Orden de Compra para calcular tiempos y ciclos de descarga.</p>
                        <div class="flex gap-3">
                            <div class="relative flex-grow">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </span>
                                <input v-model="numeroOrden" @keyup.enter="buscarOrden" type="text" placeholder="Ej: E00001167"
                                    class="block w-full pl-11 pr-4 py-3 border-slate-200 focus:border-indigo-600 focus:ring-indigo-600/20 rounded-2xl transition-all shadow-sm">
                            </div>
                            <button @click="buscarOrden" :disabled="cargando"
                                class="bg-indigo-600 text-white px-8 py-3 rounded-2xl font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-600/20 active:scale-95 disabled:opacity-50">
                                <span v-if="cargando">Buscando...</span>
                                <span v-else>BUSCAR</span>
                            </button>
                        </div>
                        <p v-if="errorMensaje" class="text-indigo-500 font-medium mt-3 text-sm">⚠️ {{ errorMensaje }}</p>
                    </div>
                </div>

                <!-- PANEL DE CITAS AGENDADAS (siempre visible) -->
                <div class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100 mb-8">
                    <div class="bg-slate-900 px-8 py-5 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="text-white font-bold text-base">
                                    <span v-if="$page.props.auth.user.role === 'proveedor'">🚚 Mis Citas Agendadas</span>
                                    <span v-else>📋 Citas Agendadas</span>
                                </h3>
                                <a v-if="$page.props.auth.user.role === 'proveedor'" href="/reservar-cita" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-lg shadow-indigo-500/30 transition-all flex items-center gap-1 ml-4">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    AGENDAR NUEVA CITA
                                </a>
                            </div>
                            <p class="text-slate-400 text-xs mt-0.5">Recepciones activas y programadas</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="bg-white/10 text-white text-xs font-bold px-3 py-1 rounded-full">
                                {{ citasProgramadas.length }} cita{{ citasProgramadas.length !== 1 ? 's' : '' }}
                            </span>
                            <button @click="cargarCitas" class="text-slate-400 hover:text-white transition-colors p-1" title="Actualizar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Tabs de Filtro de Citas -->
                    <div class="flex border-b border-slate-100 bg-slate-50 px-8 py-2 gap-4">
                        <button @click="cambiarPestanaCitas('activas')" 
                            class="text-xs font-bold uppercase tracking-wider py-2 border-b-2 transition-all font-mono"
                            :class="pestanaCitas === 'activas' ? 'border-indigo-600 text-indigo-600 font-extrabold' : 'border-transparent text-slate-400 hover:text-slate-600'">
                            Activas
                        </button>
                        <button @click="cambiarPestanaCitas('finalizadas')" 
                            class="text-xs font-bold uppercase tracking-wider py-2 border-b-2 transition-all font-mono"
                            :class="pestanaCitas === 'finalizadas' ? 'border-indigo-600 text-indigo-600 font-extrabold' : 'border-transparent text-slate-400 hover:text-slate-600'">
                            Finalizadas (Historial)
                        </button>
                    </div>

                    <!-- Filtro de Categorías (Secos, Fruver, Perecederos) para Administración y Recepción -->
                    <div v-if="$page.props.auth.user.role !== 'proveedor'" class="flex flex-wrap items-center gap-2 bg-slate-50 px-8 py-3 border-b border-slate-100">
                        <span class="text-[11px] font-black text-slate-400 uppercase tracking-wider mr-2">Filtrar por:</span>
                        
                        <button @click="filtroTipoMercancia = 'todos'"
                            class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                            :class="filtroTipoMercancia === 'todos' ? 'bg-slate-950 text-white border-slate-950 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                            Todos
                        </button>
                        
                        <button @click="filtroTipoMercancia = 'secos'"
                            class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                            :class="filtroTipoMercancia === 'secos' ? 'bg-orange-600 text-white border-orange-600 shadow-sm shadow-orange-500/20' : 'bg-white text-slate-600 border-slate-200 hover:bg-orange-50 hover:text-orange-700 hover:border-orange-200'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            Secos
                        </button>
                        
                        <button @click="filtroTipoMercancia = 'fruver'"
                            class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                            :class="filtroTipoMercancia === 'fruver' ? 'bg-lime-600 text-white border-lime-600 shadow-sm shadow-lime-500/20' : 'bg-white text-slate-600 border-slate-200 hover:bg-lime-50 hover:text-lime-700 hover:border-lime-200'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3c-1.2 0-2.4.4-3.4 1.2A8 8 0 004 11c0 4.4 3.6 8 8 8s8-3.6 8-8-3.6-8-8-8zm0 16V9"></path></svg>
                            Fruver
                        </button>
                        
                        <button @click="filtroTipoMercancia = 'perecederos'"
                            class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all border"
                            :class="filtroTipoMercancia === 'perecederos' ? 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-500/20' : 'bg-white text-slate-600 border-slate-200 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v18m9-9H3m15.364-6.364L5.636 18.364m12.728 0L5.636 5.636"></path></svg>
                            Perecederos
                        </button>
                    </div>

                    <!-- Lista de citas -->
                    <div v-if="citasFiltradas.length > 0" class="divide-y divide-slate-100">
                        <div v-for="cita in citasFiltradas" :key="cita.id"
                            class="px-4 sm:px-8 py-5 flex flex-col md:flex-row items-start md:items-center justify-between hover:bg-slate-50/70 transition-colors group gap-4 md:gap-0">
                            <div class="flex items-start sm:items-center gap-4 sm:gap-5 w-full md:w-auto">
                                <!-- Bloque Fecha grande -->
                                <div class="w-16 h-16 flex-shrink-0 bg-indigo-50 rounded-2xl flex flex-col items-center justify-center border border-indigo-100">
                                    <span class="text-[10px] font-bold text-indigo-400 uppercase">{{ new Date(cita.fecha_cita).toLocaleDateString('es-VE', { month: 'short' }) }}</span>
                                    <span class="text-2xl font-black text-indigo-600 -mt-0.5">{{ new Date(cita.fecha_cita).getDate() }}</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <p class="font-black text-slate-800 font-mono text-base truncate">{{ cita.numero_oc }}</p>
                                        <span :class="citaStatusColor(cita.estatus)" class="text-[10px] font-black px-2.5 py-0.5 rounded-full uppercase">{{ cita.estatus }}</span>
                                        <span v-if="cita.tipo_mercancia" class="text-[10px] font-black px-2.5 py-0.5 rounded-full uppercase border"
                                            :class="{
                                                'bg-orange-50 text-orange-700 border-orange-200': obtenerTipoCita(cita.tipo_mercancia) === 'secos',
                                                'bg-lime-50 text-lime-700 border-lime-200': obtenerTipoCita(cita.tipo_mercancia) === 'fruver',
                                                'bg-rose-50 text-rose-700 border-rose-200': obtenerTipoCita(cita.tipo_mercancia) === 'perecederos'
                                            }">
                                            {{ obtenerTipoCita(cita.tipo_mercancia) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-slate-600 font-semibold truncate">{{ cita.proveedor }}</p>
                                    <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-slate-400">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            {{ formatRangoHora(cita.fecha_cita, cita.duracion_minutos || 0) }}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            {{ new Date(cita.fecha_cita).toLocaleDateString('es-VE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                            Sucursal {{ getSucursalNombre(cita.muelle_asignado) }}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 sm:gap-4">
                                        <div v-if="cita.vendedor_nombre" class="bg-slate-100 px-3 py-1.5 rounded-lg inline-block">
                                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Vendedor</p>
                                            <p class="text-xs font-bold text-slate-800">{{ cita.vendedor_nombre }} <span v-if="cita.vendedor_telefono" class="font-normal text-slate-500">| {{ cita.vendedor_telefono }}</span></p>
                                        </div>
                                        <div v-if="cita.registrado_por_nombre" class="bg-blue-50 px-3 py-1.5 rounded-lg inline-block border border-blue-100">
                                            <p class="text-[10px] text-blue-500 font-bold uppercase tracking-widest">Atendido por</p>
                                            <p class="text-xs font-bold text-blue-800">{{ cita.registrado_por_nombre }}</p>
                                        </div>
                                        <div v-if="cita.numero_factura && $page.props.auth.user.role !== 'proveedor'" class="bg-emerald-50 px-3 py-1.5 rounded-lg flex items-center gap-3 border border-emerald-100">
                                            <div>
                                                <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest">Factura Proveedor</p>
                                                <p class="text-xs font-bold text-emerald-900 font-mono">{{ cita.numero_factura }}</p>
                                            </div>
                                            <a v-if="cita.factura_url" :href="cita.factura_url" target="_blank"
                                                class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-700 transition-colors shadow-sm text-[10px] font-bold flex items-center gap-1.5" title="Ver / Descargar Factura">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                Ver Factura
                                            </a>
                                            <span v-else class="text-[10px] text-emerald-500 italic">Sin archivo</span>
                                        </div>
                                        <div v-if="cita.tipo_vehiculo && $page.props.auth.user.role !== 'proveedor'" class="px-3 py-1.5 rounded-lg inline-flex items-center gap-2 border" :class="getVehiculoNombre(cita.tipo_vehiculo).frio ? 'bg-cyan-50 border-cyan-200' : 'bg-violet-50 border-violet-100'">
                                            <svg class="w-4 h-4 flex-shrink-0" :class="getVehiculoNombre(cita.tipo_vehiculo).frio ? 'text-cyan-600' : 'text-violet-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-widest" :class="getVehiculoNombre(cita.tipo_vehiculo).frio ? 'text-cyan-600' : 'text-violet-500'">Vehículo</p>
                                                <p class="text-xs font-bold" :class="getVehiculoNombre(cita.tipo_vehiculo).frio ? 'text-cyan-900' : 'text-violet-800'">
                                                    {{ getVehiculoNombre(cita.tipo_vehiculo).nombre }}
                                                    <span class="font-normal text-slate-500">| {{ getVehiculoNombre(cita.tipo_vehiculo).cap }}</span>
                                                </p>
                                            </div>
                                            <span v-if="getVehiculoNombre(cita.tipo_vehiculo).frio" class="text-[9px] font-black bg-cyan-600 text-white px-2 py-0.5 rounded-full uppercase whitespace-nowrap">❄️ Thermo</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="$page.props.auth.user.role !== 'proveedor'" class="flex flex-wrap sm:flex-nowrap gap-2 w-full md:w-auto mt-4 md:mt-0">
                                <button v-if="cita.estatus === 'programada' && ['admin', 'receptor'].includes($page.props.auth.user.role)" @click="abrirModalFinalizar(cita)"
                                    class="w-full sm:w-auto justify-center bg-emerald-600 text-white px-5 py-3 md:py-2.5 rounded-xl text-xs font-bold hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-600/20 opacity-100 md:opacity-70 group-hover:opacity-100 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    Completar
                                </button>
                                <button @click="abrirModalReprogramar(cita)"
                                    class="w-full sm:w-auto justify-center bg-slate-200 text-slate-700 px-5 py-3 md:py-2.5 rounded-xl text-xs font-bold hover:bg-slate-300 transition-all opacity-100 md:opacity-70 group-hover:opacity-100 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Reprogramar
                                </button>
                                <button @click="numeroOrden = cita.numero_oc; buscarOrden()"
                                    class="w-full sm:w-auto justify-center bg-indigo-600 text-white px-5 py-3 md:py-2.5 rounded-xl text-xs font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-600/20 opacity-100 md:opacity-70 group-hover:opacity-100 flex items-center gap-1">
                                    Ver Detalle
                                </button>
                            </div>
                            <div v-if="$page.props.auth.user.role === 'proveedor' && cita.estatus === 'programada'" class="flex flex-wrap sm:flex-nowrap gap-2 w-full md:w-auto mt-4 md:mt-0">
                                <button @click="iniciarReprogramacion(cita)"
                                    class="w-full sm:w-auto justify-center bg-orange-50 text-orange-600 border border-orange-200 px-5 py-3 md:py-2.5 rounded-xl text-xs font-bold hover:bg-orange-100 transition-all opacity-100 md:opacity-70 group-hover:opacity-100 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Reprogramar
                                </button>
                                <button @click="abrirModalCancelar(cita)"
                                    class="w-full sm:w-auto justify-center bg-indigo-50 text-indigo-600 border border-indigo-200 px-5 py-3 md:py-2.5 rounded-xl text-xs font-bold hover:bg-indigo-100 transition-all opacity-100 md:opacity-70 group-hover:opacity-100 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    Cancelar Cita
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sin citas -->
                    <div v-else class="px-8 py-12 text-center">
                        <div class="text-4xl mb-3">📭</div>
                        <p class="text-slate-400 font-semibold">No hay citas agendadas</p>
                        <p class="text-slate-300 text-sm mt-1">Las recepciones aparecerán aquí cuando un proveedor reserve una cita</p>
                    </div>
                </div>

                <!-- INFO PRINCIPAL -->
                <div v-if="datosOrden && $page.props.auth.user.role !== 'proveedor'" class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100">
                        <!-- Header proveedor -->
                        <div class="bg-slate-900 p-8 text-white flex justify-between items-start">
                            <div>
                                <span class="bg-indigo-600 text-[10px] font-black px-2 py-1 rounded uppercase tracking-tighter mb-2 inline-block">Proveedor Verificado</span>
                                <h3 class="text-3xl font-black">{{ datosOrden.Nombre_Proveedor }}</h3>
                                <p class="text-slate-400 font-medium mt-1">RIF: {{ datosOrden.Codigo_Proveedor }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Documento</p>
                                <p class="text-2xl font-mono font-bold text-indigo-500">{{ datosOrden.Numero_OC }}</p>
                                <span v-if="datosExtra" :class="statusColor(datosExtra.status_orden)"
                                    class="inline-block mt-2 text-[10px] font-black px-2.5 py-1 rounded-full uppercase">
                                    {{ datosExtra.status_texto }}
                                </span>
                            </div>
                        </div>

                        <!-- Datos principales -->
                        <div class="p-8 grid grid-cols-1 md:grid-cols-4 gap-8">
                            <!-- Contacto + Factura -->
                            <div class="space-y-4">
                                <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Información de Contacto</h4>
                                <div>
                                    <p class="text-xs text-slate-500 font-bold">Teléfono Principal</p>
                                    <p class="text-slate-800 font-bold">{{ datosOrden.Telefono_Proveedor || 'No registrado' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-bold">Comprador Asignado</p>
                                    <p class="text-slate-800 font-bold">{{ datosOrden.Comprador_Interno || 'General' }}</p>
                                </div>
                                <div v-if="datosExtra">
                                    <p class="text-xs text-slate-500 font-bold">Factura Proveedor</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <p class="text-slate-800 font-bold font-mono">{{ datosExtra.factura || 'Por facturar' }}</p>
                                        <a v-if="datosExtra.factura_url" :href="datosExtra.factura_url" target="_blank"
                                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded transition-all text-[10px] font-bold flex items-center gap-1 shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Ver Factura
                                        </a>
                                    </div>
                                </div>
                                <div v-if="datosExtra && datosExtra.tipo_vehiculo" class="p-3 rounded-xl border mt-2" :class="getVehiculoNombre(datosExtra.tipo_vehiculo).frio ? 'bg-cyan-50 border-cyan-200' : 'bg-violet-50 border-violet-200'">
                                    <p class="text-[10px] font-black uppercase tracking-widest mb-1 flex items-center gap-1.5" :class="getVehiculoNombre(datosExtra.tipo_vehiculo).frio ? 'text-cyan-600' : 'text-violet-500'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                        Vehículo del Transporte
                                    </p>
                                    <p class="text-sm font-black" :class="getVehiculoNombre(datosExtra.tipo_vehiculo).frio ? 'text-cyan-900' : 'text-violet-900'">{{ getVehiculoNombre(datosExtra.tipo_vehiculo).nombre }}</p>
                                    <p class="text-[10px] text-slate-500 font-bold mt-0.5">Capacidad: {{ getVehiculoNombre(datosExtra.tipo_vehiculo).cap }}</p>
                                    <span v-if="getVehiculoNombre(datosExtra.tipo_vehiculo).frio" class="mt-1.5 inline-flex items-center gap-1 text-[9px] font-black bg-cyan-600 text-white px-2.5 py-1 rounded-full uppercase">❄️ Thermo King / Cadena de Frío</span>
                                </div>
                                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200 mt-4">
                                    <p class="text-[10px] text-yellow-800 font-black uppercase tracking-widest mb-1 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Observación ERP
                                    </p>
                                    <p class="text-xs text-yellow-900 font-medium italic">
                                        {{ datosOrden.Observacion && datosOrden.Observacion.trim() !== '' && datosOrden.Observacion !== 'Ninguna' ? datosOrden.Observacion : 'Sin observaciones en el ERP.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Mercancía + Fechas -->
                            <div class="space-y-4">

                                <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Detalle de Mercancía</h4>
                                <div class="flex items-center gap-3 cursor-pointer group hover:bg-slate-50 -mx-2 px-2 py-1 rounded-xl transition-all" @click="abrirModalSKU">
                                    <div class="h-10 w-10 bg-slate-100 rounded-lg flex items-center justify-center text-slate-600 font-bold group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-colors">{{ datosOrden.Total_SKUs }}</div>
                                    <div>
                                        <p class="text-sm text-slate-600 font-bold uppercase">Variedad de Productos</p>
                                        <p class="text-[10px] text-indigo-500 font-semibold opacity-0 group-hover:opacity-100 transition-opacity">Clic para ver detalle →</p>
                                    </div>
                                </div>
                                <div v-if="datosOrden.Total_Cajas_Fisicas > 0" class="flex items-center gap-3">
                                    <div class="h-10 w-10 bg-slate-100 rounded-lg flex items-center justify-center text-slate-700 font-bold">{{ Math.round(datosOrden.Total_Cajas_Fisicas) }}</div>
                                    <p class="text-sm text-slate-600 font-bold uppercase">Cajas (Secos)</p>
                                </div>
                                <div v-if="datosOrden.Total_KG_Carnes > 0 || datosOrden.Total_UND_Carnes > 0" class="flex items-center gap-3 group/item">
                                    <div class="h-10 w-10 bg-rose-50 rounded-lg flex flex-col items-center justify-center text-rose-700 font-bold border border-rose-100">
                                        <span class="text-[10px] leading-tight">{{ datosOrden.Total_KG_Carnes > 0 ? Math.round(datosOrden.Total_KG_Carnes) : Math.round(datosOrden.Total_UND_Carnes) }}</span>
                                        <span class="text-[8px] uppercase">{{ datosOrden.Total_KG_Carnes > 0 ? 'KG' : 'UND' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase leading-tight">Carnes / Aves</p>
                                </div>

                                <div v-if="datosOrden.Total_KG_Charcuteria > 0 || datosOrden.Total_UND_Charcuteria > 0" class="flex items-center gap-3 group/item">
                                    <div class="h-10 w-10 bg-amber-50 rounded-lg flex flex-col items-center justify-center text-amber-700 font-bold border border-amber-100">
                                        <span class="text-[10px] leading-tight">{{ datosOrden.Total_KG_Charcuteria > 0 ? Math.round(datosOrden.Total_KG_Charcuteria) : Math.round(datosOrden.Total_UND_Charcuteria) }}</span>
                                        <span class="text-[8px] uppercase">{{ datosOrden.Total_KG_Charcuteria > 0 ? 'KG' : 'UND' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase leading-tight">Charcutería / Lácteos</p>
                                </div>

                                <div v-if="datosOrden.Total_KG_Pescaderia > 0 || datosOrden.Total_UND_Pescaderia > 0" class="flex items-center gap-3 group/item">
                                    <div class="h-10 w-10 bg-blue-50 rounded-lg flex flex-col items-center justify-center text-blue-700 font-bold border border-blue-100">
                                        <span class="text-[10px] leading-tight">{{ datosOrden.Total_KG_Pescaderia > 0 ? Math.round(datosOrden.Total_KG_Pescaderia) : Math.round(datosOrden.Total_UND_Pescaderia) }}</span>
                                        <span class="text-[8px] uppercase">{{ datosOrden.Total_KG_Pescaderia > 0 ? 'KG' : 'UND' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase leading-tight">Pescadería</p>
                                </div>

                                <div v-if="datosOrden.Total_KG_Congelados > 0 || datosOrden.Total_UND_Congelados > 0" class="flex items-center gap-3 group/item">
                                    <div class="h-10 w-10 bg-cyan-50 rounded-lg flex flex-col items-center justify-center text-cyan-700 font-bold border border-cyan-100">
                                        <span class="text-[10px] leading-tight">{{ datosOrden.Total_KG_Congelados > 0 ? Math.round(datosOrden.Total_KG_Congelados) : Math.round(datosOrden.Total_UND_Congelados) }}</span>
                                        <span class="text-[8px] uppercase">{{ datosOrden.Total_KG_Congelados > 0 ? 'KG' : 'UND' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase leading-tight">Congelados</p>
                                </div>
                                <div v-if="datosOrden.Total_KG_Hortalizas > 0 || datosOrden.Total_UND_Hortalizas > 0" class="flex items-center gap-3 group/item">
                                    <div class="h-10 w-10 bg-lime-50 rounded-lg flex flex-col items-center justify-center text-lime-700 font-bold border border-lime-100">
                                        <span class="text-[10px] leading-tight">{{ datosOrden.Total_KG_Hortalizas > 0 ? Math.round(datosOrden.Total_KG_Hortalizas) : Math.round(datosOrden.Total_UND_Hortalizas) }}</span>
                                        <span class="text-[8px] uppercase">{{ datosOrden.Total_KG_Hortalizas > 0 ? 'KG' : 'UND' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase leading-tight">Hortalizas</p>
                                </div>

                                <div v-if="datosOrden.Total_KG_Frutas > 0 || datosOrden.Total_UND_Frutas > 0" class="flex items-center gap-3 group/item">
                                    <div class="h-10 w-10 bg-orange-50 rounded-lg flex flex-col items-center justify-center text-orange-700 font-bold border border-orange-100">
                                        <span class="text-[10px] leading-tight">{{ datosOrden.Total_KG_Frutas > 0 ? Math.round(datosOrden.Total_KG_Frutas) : Math.round(datosOrden.Total_UND_Frutas) }}</span>
                                        <span class="text-[8px] uppercase">{{ datosOrden.Total_KG_Frutas > 0 ? 'KG' : 'UND' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase leading-tight">Frutas</p>
                                </div>

                                <div v-if="datosOrden.Total_KG_Verduras > 0 || datosOrden.Total_UND_Verduras > 0" class="flex items-center gap-3 group/item">
                                    <div class="h-10 w-10 bg-emerald-50 rounded-lg flex flex-col items-center justify-center text-emerald-700 font-bold border border-emerald-100">
                                        <span class="text-[10px] leading-tight">{{ datosOrden.Total_KG_Verduras > 0 ? Math.round(datosOrden.Total_KG_Verduras) : Math.round(datosOrden.Total_UND_Verduras) }}</span>
                                        <span class="text-[8px] uppercase">{{ datosOrden.Total_KG_Verduras > 0 ? 'KG' : 'UND' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase leading-tight">Verduras</p>
                                </div>
                                <button @click="abrirModalSKU"
                                    class="w-full mt-2 flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-indigo-600 transition-all duration-300 shadow-md hover:shadow-lg active:scale-95">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                    Ver detalle Productos
                                </button>
                            </div>

                            <!-- Ciclos y Muelle -->
                            <div class="md:col-span-2 space-y-4">
                                <!-- Fechas -->
                                <div v-if="datosExtra" class="bg-blue-50 rounded-2xl p-4 border border-blue-100 grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-[10px] text-blue-500 font-black uppercase">Fecha Orden</p>
                                        <p class="text-sm font-bold text-blue-800">{{ formatFecha(datosExtra.fecha_orden) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-blue-500 font-black uppercase">Fecha Recepción</p>
                                        <p class="text-sm font-bold text-blue-800">{{ formatFecha(datosExtra.fecha_recepcion) }}</p>
                                    </div>
                                </div>

                                <div class="bg-slate-50 rounded-3xl p-6 border border-slate-200 flex items-center justify-around">
                                    <div class="text-center">
                                        <p class="text-xs text-slate-500 font-black uppercase mb-1">Ciclos de Descarga</p>
                                        <p class="text-5xl font-black text-slate-900">{{ Math.round(datosOrden.Ciclos_Necesarios) }}</p>
                                        <p class="text-[10px] text-indigo-600 font-bold mt-1 uppercase">Viajes estimados</p>
                                    </div>
                                    <div class="h-12 w-[1px] bg-slate-200"></div>
                                    <div class="text-center">
                                        <p class="text-xs text-slate-500 font-black uppercase mb-1">Sucursal Sugerida</p>
                                        <p class="text-3xl font-black text-indigo-600 truncate max-w-[150px] mx-auto" :title="getSucursalNombre(datosOrden.Muelle_Destino)">{{ getSucursalNombre(datosOrden.Muelle_Destino) }}</p>
                                        <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Destino de Recepción</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tiempo Óptimo con selector de operarios -->
                        <div v-if="tiempoOptimo" class="mx-8 mb-8 bg-gradient-to-r from-slate-900 to-slate-800 rounded-2xl p-6 text-white">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Tiempo Óptimo de Descarga</p>
                                    <p class="text-4xl font-black">
                                        {{ formatMinutos(tiempoOptimo.tiempo_optimo_minutos) }}
                                        <span class="text-lg text-slate-400 font-medium ml-2">con {{ tiempoOptimo.operarios_usados }} equipo{{ tiempoOptimo.operarios_usados !== 1 ? 's' : '' }}</span>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1">Tiempo base sin optimizar: {{ formatMinutos(tiempoOptimo.tiempo_base_minutos) }}</p>
                                </div>
                                <div class="flex items-center gap-4 bg-slate-800 rounded-xl p-4 border border-slate-700">
                                    <div class="text-center">
                                        <p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Equipos operativos</p>
                                        <div class="flex items-center gap-2">
                                            <button @click="operariosSeleccionados = Math.max(1, operariosSeleccionados - 1); recalcularTiempo()"
                                                class="w-8 h-8 bg-slate-700 rounded-lg flex items-center justify-center hover:bg-indigo-600 transition-colors font-bold">−</button>
                                            <span class="text-2xl font-black w-8 text-center" title="1 Equipo = 1 Receptor + 1 Carga">{{ operariosSeleccionados }}</span>
                                            <button @click="operariosSeleccionados++; recalcularTiempo()"
                                                class="w-8 h-8 bg-slate-700 rounded-lg flex items-center justify-center hover:bg-indigo-600 transition-colors font-bold">+</button>
                                        </div>
                                    </div>
                                    <div class="h-10 w-[1px] bg-slate-600"></div>
                                    <div class="text-xs space-y-1">
                                        <p class="text-slate-400"><span class="text-emerald-400 font-bold">{{ tiempoOptimo.receptores_disponibles }}</span> receptores disp.</p>
                                        <p class="text-slate-400"><span class="text-blue-400 font-bold">{{ tiempoOptimo.carga_disponibles }}</span> carga disp.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 border-t border-slate-100 px-8 py-4 flex justify-between items-center">
                            <p class="text-[10px] text-slate-400 font-bold uppercase italic">Los cálculos están basados en una capacidad de 96 cajas por ciclo.</p>
                            <button @click="imprimirTicket" class="text-indigo-600 font-bold text-xs hover:underline uppercase tracking-widest">Imprimir Ticket de Recepción</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL DE PRODUCTOS -->
        <Modal :show="mostrarModalSKU" max-width="2xl" @close="cerrarModalSKU">
            <div class="bg-white rounded-2xl overflow-hidden">
                <div class="bg-slate-900 px-8 py-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-white tracking-tight">Detalle de Productos</h3>
                        <p class="text-slate-400 text-sm font-medium mt-0.5">Orden <span class="text-indigo-500 font-bold font-mono">{{ datosOrden?.Numero_OC }}</span> — {{ productosOrden.length }} Producto{{ productosOrden.length !== 1 ? 's' : '' }}</p>
                    </div>
                    <button @click="cerrarModalSKU" class="text-slate-400 hover:text-white hover:bg-slate-700 p-2 rounded-xl transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="px-8 py-4 border-b border-slate-100 bg-slate-50">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></span>
                        <input v-model="busquedaProducto" type="text" placeholder="Buscar por código o nombre..."
                            class="block w-full pl-10 pr-4 py-2.5 text-sm border-slate-200 focus:border-indigo-600 focus:ring-indigo-600/20 rounded-xl transition-all">
                    </div>
                </div>
                <div class="max-h-[400px] overflow-y-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-8 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">#</th>
                                <th class="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Código</th>
                                <th class="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Producto</th>
                                <th class="px-4 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Medida</th>
                                <th class="px-8 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Volumen Físico</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(producto, index) in productosFiltrados" :key="index" class="hover:bg-indigo-50/40 transition-colors group">
                                <td class="px-8 py-3.5"><span class="text-xs font-bold text-slate-300">{{ index + 1 }}</span></td>
                                <td class="px-4 py-3.5"><span class="font-mono text-sm font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded group-hover:bg-indigo-100 group-hover:text-indigo-700 transition-colors">{{ producto.codigo }}</span></td>
                                <td class="px-4 py-3.5"><p class="text-sm text-slate-700 font-semibold leading-tight">{{ producto.producto || 'Sin descripción' }}</p></td>
                                <td class="px-4 py-3.5 text-center">
                                    <div v-if="producto.c_departamento === '14'">
                                        <div class="flex flex-col items-center">
                                            <span class="text-[9px] font-black uppercase tracking-tighter mb-0.5" 
                                                :class="producto.categoria_fruver === 'Hortaliza' ? 'text-lime-600' : (producto.categoria_fruver === 'Fruta' ? 'text-orange-600' : 'text-emerald-600')">
                                                {{ producto.categoria_fruver }}
                                            </span>
                                            <span v-if="producto.c_presenta?.trim() === 'KG'" class="text-xs font-black text-slate-700 bg-slate-100 px-2 py-0.5 rounded shadow-sm border border-slate-200">
                                                {{ parseFloat(producto.cantidad_unidades).toFixed(2) }} kg
                                            </span>
                                            <div v-else class="flex flex-col items-center">
                                                <span class="text-xs font-black text-slate-700 bg-slate-100 px-2 py-0.5 rounded shadow-sm border border-slate-200">
                                                    {{ Math.round(producto.cantidad_unidades) }} und
                                                </span>
                                                <span v-if="producto.unidades_por_caja > 1" class="text-[9px] text-slate-400 font-bold mt-0.5">
                                                    ({{ Math.round(producto.bultos) }} cajas de {{ Math.round(producto.unidades_por_caja) }})
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="['10', '11', '12', '13', '15', '21', '23'].includes(producto.c_departamento?.trim())">
                                        <div class="flex flex-col items-center">
                                            <span class="text-[9px] font-black uppercase tracking-tighter mb-0.5" 
                                                :class="producto.categoria_perecedero === 'Carnes' ? 'text-rose-600' : (producto.categoria_perecedero === 'Charcutería' ? 'text-amber-600' : (producto.categoria_perecedero === 'Pescadería' ? 'text-blue-600' : 'text-cyan-600'))">
                                                {{ producto.categoria_perecedero }}
                                            </span>
                                            <span v-if="producto.c_presenta?.trim() === 'KG'" class="text-xs font-black text-slate-700 bg-slate-100 px-2 py-1 rounded shadow-sm border border-slate-200">
                                                {{ parseFloat(producto.cantidad_unidades).toFixed(2) }} kg
                                            </span>
                                            <span v-else class="text-xs font-black text-slate-700 bg-slate-100 px-2 py-1 rounded shadow-sm border border-slate-200">
                                                {{ Math.round(producto.cantidad_unidades) }} und
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else class="flex flex-col items-center">
                                        <span v-if="producto.c_presenta?.trim() === 'KG'" class="text-xs font-black text-slate-700 bg-slate-100 px-2 py-0.5 rounded shadow-sm border border-slate-200">
                                            {{ parseFloat(producto.cantidad_unidades).toFixed(2) }} kg
                                        </span>
                                        <template v-else>
                                            <span class="text-xs font-black text-slate-700 bg-slate-100 px-2 py-0.5 rounded shadow-sm border border-slate-200">
                                                {{ Math.round(producto.cantidad_unidades) }} und
                                            </span>
                                            <span v-if="producto.unidades_por_caja > 1" class="text-[9px] text-slate-400 font-bold mt-0.5">
                                                ({{ Math.round(producto.bultos) }} bultos/cajas de {{ Math.round(producto.unidades_por_caja) }})
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="px-8 py-3.5 text-center"><span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full" :class="categoriaPorFactor(producto.factor_carrito).color">{{ categoriaPorFactor(producto.factor_carrito).icono }} {{ categoriaPorFactor(producto.factor_carrito).texto }}</span></td>
                            </tr>
                            <tr v-if="productosFiltrados.length === 0"><td colspan="5" class="px-8 py-10 text-center"><p class="text-slate-400 text-sm font-medium">No se encontraron productos.</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="bg-slate-50 border-t border-slate-200 px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2"><span class="text-[10px] font-black text-slate-400 uppercase">Total Productos:</span><span class="text-sm font-black text-slate-800">{{ productosOrden.length }}</span></div>
                        <div class="h-4 w-[1px] bg-slate-300"></div>
                        <div class="flex items-center gap-2"><span class="text-[10px] font-black text-slate-400 uppercase">Total Bultos:</span><span class="text-sm font-black text-indigo-600">{{ totalBultos }}</span></div>
                    </div>
                    <button @click="cerrarModalSKU" class="px-5 py-2 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-indigo-600 transition-all duration-300 active:scale-95">Cerrar</button>
                </div>
            </div>
        </Modal>
        <!-- Modal de Reprogramación -->
        <Modal :show="mostrarModalReprogramar" @close="mostrarModalReprogramar = false">
            <div class="p-6">
                <h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Reprogramar Cita
                </h3>
                
                <div v-if="citaReprogramar" class="mb-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="font-bold text-slate-800 font-mono">{{ citaReprogramar.numero_oc }}</p>
                    <p class="text-sm text-slate-600">{{ citaReprogramar.proveedor }}</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nueva Fecha y Hora</label>
                        <input v-model="formReprogramar.fecha_cita" type="datetime-local" class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sucursal Asignada</label>
                        <select v-model="formReprogramar.muelle_asignado" class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="0101">0101 (Hiper)</option>
                            <option value="0102">0102 (Dep Gral)</option>
                            <option value="0111">0111 (Producción)</option>
                            <option value="0115">0115 (Insumos)</option>
                            <option value="0161">0161 (Andinka)</option>
                            <option value="01">01 (Galpón)</option>
                            <option value="02">02 (Galpón)</option>
                            <option value="03">03 (Galpón)</option>
                            <option value="04">04 (Galpón)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Motivo de Reprogramación (Obligatorio)</label>
                        <textarea v-model="formReprogramar.motivo" rows="3" placeholder="Explique brevemente el motivo de este cambio..." class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    
                    <p v-if="errorReprogramacion" class="text-indigo-500 text-sm font-medium">{{ errorReprogramacion }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button @click="mostrarModalReprogramar = false" class="px-5 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 transition-colors">
                        Cancelar
                    </button>
                    <button @click="guardarReprogramacion" :disabled="procesandoReprogramacion"
                        class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        {{ procesandoReprogramacion ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal Cancelar Cita -->
        <Modal :show="mostrarModalCancelar" @close="mostrarModalCancelar = false" max-width="md">
            <div class="bg-white rounded-2xl overflow-hidden">
                <div class="bg-indigo-600 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-black text-white">Cancelar Cita</h3>
                    <button @click="mostrarModalCancelar = false" class="text-indigo-200 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 mb-4">¿Está seguro que desea cancelar esta cita? Esta acción notificará al equipo de Compras y Recepción.</p>
                    
                    <label class="block text-sm font-bold text-slate-700 mb-2">Motivo de Cancelación (Obligatorio)</label>
                    <textarea v-model="motivoCancelacion" rows="3" placeholder="Explique brevemente el motivo de la cancelación..." 
                        class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                    
                    <p v-if="errorCancelacion" class="text-indigo-500 text-xs font-bold mt-2">{{ errorCancelacion }}</p>
                    
                    <div class="mt-6 flex justify-end gap-3">
                        <button @click="mostrarModalCancelar = false" class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                            Volver
                        </button>
                        <button @click="guardarCancelacion" :disabled="procesandoCancelacion || motivoCancelacion.length < 5"
                            class="bg-indigo-600 text-white px-4 py-2 text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            {{ procesandoCancelacion ? 'Cancelando...' : 'Confirmar Cancelación' }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal Finalizar Cita -->
        <Modal :show="mostrarModalFinalizar" @close="mostrarModalFinalizar = false" max-width="md">
            <div class="bg-white rounded-2xl overflow-hidden">
                <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-black text-white">Completar Recepción</h3>
                    <button @click="mostrarModalFinalizar = false" class="text-emerald-200 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 mb-2">
                        ¿Está seguro que desea marcar la Orden de Compra <span class="font-bold text-slate-800 font-mono">{{ citaFinalizar?.numero_oc }}</span> como recibida y completada?
                    </p>
                    <p class="text-xs text-slate-500 mb-4 bg-slate-50 p-3 rounded-lg border border-slate-100">
                        Esta acción registrará la orden como descargada y validada en el sistema, finalizando la cita correspondiente.
                    </p>
                    
                    <p v-if="errorFinalizar" class="text-indigo-500 text-xs font-bold mt-2">⚠️ {{ errorFinalizar }}</p>
                    
                    <div class="mt-6 flex justify-end gap-3">
                        <button @click="mostrarModalFinalizar = false" class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                            Cancelar
                        </button>
                        <button @click="confirmarFinalizar" :disabled="procesandoFinalizar"
                            class="bg-emerald-600 text-white px-5 py-2 text-sm font-bold rounded-xl hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-600/20 disabled:opacity-50 flex items-center gap-1">
                            <span v-if="procesandoFinalizar">Procesando...</span>
                            <span v-else class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                Sí, Marcar Completada
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal Reprogramar Cita -->
        <Modal :show="mostrarModalReprogramarProveedor" @close="mostrarModalReprogramarProveedor = false" max-width="md">
            <div class="bg-white rounded-2xl overflow-hidden">
                <div class="bg-orange-500 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-black text-white">Reprogramar Cita</h3>
                    <button @click="mostrarModalReprogramarProveedor = false" class="text-orange-200 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 mb-4">Para reprogramar la cita debes justificar el motivo. Al continuar serás llevado al calendario para elegir una nueva fecha y hora. Esta acción notificará al equipo de Compras y Recepción.</p>
                    
                    <label class="block text-sm font-bold text-slate-700 mb-2">Motivo de Reprogramación (Obligatorio)</label>
                    <textarea v-model="motivoReprogramacion" rows="3" placeholder="Explique brevemente el motivo..." 
                        class="w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500 text-sm"></textarea>
                    
                    <p v-if="errorReprogramacion" class="text-indigo-500 text-xs font-bold mt-2">{{ errorReprogramacion }}</p>
                    
                    <div class="mt-6 flex justify-end gap-3">
                        <button @click="mostrarModalReprogramarProveedor = false" class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                            Volver
                        </button>
                        <button @click="continuarReprogramacion" :disabled="motivoReprogramacion.length < 5"
                            class="bg-orange-500 text-white px-4 py-2 text-sm font-bold rounded-xl hover:bg-orange-600 transition-colors disabled:opacity-50">
                            Ir al Calendario
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Ticket de Impresión Oculto -->
        <div class="hidden print:block absolute inset-0 bg-white z-[99999] p-8 text-black font-sans" v-if="datosOrden">
            <div class="max-w-sm mx-auto border-2 border-black p-6 rounded-xl">
                <div class="text-center mb-6">
                    <h1 class="text-2xl font-black uppercase tracking-widest">TICKET DE RECEPCIÓN</h1>
                    <p class="text-sm font-bold mt-1">LOGÍSTICA Empresa Base</p>
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
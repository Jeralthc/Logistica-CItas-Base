<template>
    <Head title="Control de Garita y Patio (YMS)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-slate-100 flex items-center gap-2">
                        <span class="text-3xl">🛡️</span> Control de Garita y Patio (YMS)
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Control de acceso vehicular, escaneo de pases QR y trazabilidad en tiempo real.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input 
                        type="date" 
                        v-model="fecha" 
                        @change="filtrarPorFecha"
                        class="bg-slate-800 text-slate-200 border border-slate-700 text-sm rounded-lg px-3 py-2 focus:ring-sky-500 focus:border-sky-500"
                    />
                    <button 
                        @click="abrirScanner"
                        class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-sm font-semibold flex items-center gap-2 shadow-lg shadow-sky-600/30 transition"
                    >
                        <span>📷</span> Escanear QR
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Tarjetas de Métricas de Garita -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-2xl">
                        🚚
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 font-medium">Total Citas Hoy</div>
                        <div class="text-2xl font-bold text-white">{{ metricas.total_hoy }}</div>
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-2xl">
                        ⏳
                    </div>
                    <div>
                        <div class="text-xs text-amber-400 font-medium">Esperando en Patio</div>
                        <div class="text-2xl font-bold text-amber-300">{{ metricas.en_patio }}</div>
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-2xl">
                        🏭
                    </div>
                    <div>
                        <div class="text-xs text-blue-400 font-medium">En Muelle (Descarga)</div>
                        <div class="text-2xl font-bold text-blue-300">{{ metricas.en_muelle }}</div>
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-2xl">
                        ✅
                    </div>
                    <div>
                        <div class="text-xs text-emerald-400 font-medium">Completadas / Salida</div>
                        <div class="text-2xl font-bold text-emerald-300">{{ metricas.finalizadas }}</div>
                    </div>
                </div>
            </div>

            <!-- Barra de Búsqueda Rápida y Filtros de Estado -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 space-y-4">
                <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
                    <div class="relative w-full sm:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">🔍</span>
                        <input 
                            v-model="busqueda"
                            type="text"
                            placeholder="Buscar por ODC, chofer, placa o proveedor..."
                            class="w-full pl-9 pr-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:ring-sky-500 focus:border-sky-500"
                        />
                    </div>

                    <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pb-1">
                        <button 
                            v-for="pestana in ['todas', 'programada', 'en_patio', 'en_muelle', 'finalizada']"
                            :key="pestana"
                            @click="filtroEstado = pestana"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition',
                                filtroEstado === pestana 
                                    ? 'bg-sky-600 text-white shadow' 
                                    : 'bg-slate-800 text-slate-400 hover:text-slate-200'
                            ]"
                        >
                            {{ labelPestana(pestana) }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Listado de Camiones y Citas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                    v-for="cita in citasFiltradas" 
                    :key="cita.id"
                    class="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition space-y-4 shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-xs font-mono font-bold text-sky-400 bg-sky-950/60 border border-sky-800/40 px-2 py-0.5 rounded">
                                {{ cita.numero_oc }}
                            </span>
                            <h3 class="font-bold text-base text-slate-100 mt-1 truncate max-w-[200px]" :title="cita.proveedor">
                                {{ cita.proveedor }}
                            </h3>
                            <div class="text-xs text-slate-400 font-mono">{{ cita.rif_proveedor || 'Sin RIF' }}</div>
                        </div>
                        <span :class="badgeEstado(cita.estado_patio || cita.estatus)">
                            {{ textoEstado(cita.estado_patio || cita.estatus) }}
                        </span>
                    </div>

                    <div class="text-xs space-y-1 text-slate-300 bg-slate-950/50 p-3 rounded-lg border border-slate-800/60">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Hora Cita:</span>
                            <span class="font-semibold text-slate-200">{{ formatHora(cita.fecha_cita) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Muelle:</span>
                            <span class="font-semibold text-amber-400">{{ cita.muelle_asignado || 'Por asignar' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Chofer:</span>
                            <span class="font-medium text-slate-200">{{ cita.chofer_nombre || 'No registrado' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Placa:</span>
                            <span class="font-mono font-bold text-sky-300">{{ cita.placa_vehiculo || 'S/P' }}</span>
                        </div>
                    </div>

                    <!-- Botones de Acción de Garita -->
                    <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between gap-2">
                        <button 
                            v-if="!cita.fecha_llegada_garita"
                            @click="abrirModalCheckin(cita)"
                            class="flex-1 py-1.5 px-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg flex items-center justify-center gap-1.5 transition"
                        >
                            <span>🚪</span> Check-in Garita
                        </button>

                        <button 
                            v-else-if="cita.estado_patio === 'en_patio'"
                            @click="abrirModalMuelle(cita)"
                            class="flex-1 py-1.5 px-3 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg flex items-center justify-center gap-1.5 transition"
                        >
                            <span>📢</span> Llamar a Muelle
                        </button>

                        <button 
                            v-else-if="cita.estado_patio === 'en_muelle' || cita.estatus === 'finalizada'"
                            @click="registrarSalida(cita)"
                            class="flex-1 py-1.5 px-3 bg-slate-700 hover:bg-slate-600 text-white text-xs font-semibold rounded-lg flex items-center justify-center gap-1.5 transition"
                        >
                            <span>🏁</span> Dar Salida
                        </button>

                        <button 
                            @click="verPase(cita)"
                            class="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs"
                            title="Ver Pase QR"
                        >
                            📱
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="citasFiltradas.length === 0" class="text-center py-12 text-slate-500 text-sm">
                No hay camiones ni citas registradas para este filtro.
            </div>
        </div>

        <!-- Modal Check-in Garita -->
        <div v-if="modalCheckin" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    <span>🚪</span> Registro de Llegada a Garita
                </h3>
                <p class="text-xs text-slate-400">
                    Orden: <strong class="text-sky-400">{{ citaSeleccionada.numero_oc }}</strong> - {{ citaSeleccionada.proveedor }}
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-slate-300">Nombre del Chofer</label>
                        <input v-model="formCheckin.chofer_nombre" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ej: Carlos Pérez" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-slate-300">Cédula / DNI</label>
                            <input v-model="formCheckin.chofer_cedula" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ej: V-18234567" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-300">Placa Vehículo</label>
                            <input v-model="formCheckin.placa_vehiculo" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ej: A12BC3D" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-300">Teléfono Chofer</label>
                        <input v-model="formCheckin.chofer_telefono" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ej: 0414-1234567" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-300">Observaciones Garita (Precintos, etc.)</label>
                        <textarea v-model="formCheckin.garita_observaciones" rows="2" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Notas de seguridad..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                    <button @click="modalCheckin = false" class="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white">Cancelar</button>
                    <button @click="confirmarCheckin" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition">Confirmar Entrada</button>
                </div>
            </div>
        </div>

        <!-- Modal Llamar a Muelle -->
        <div v-if="modalMuelle" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    <span>🏭</span> Asignar y Llamar a Muelle
                </h3>
                <p class="text-xs text-slate-400">
                    Indica a qué muelle debe dirigirse el camión <strong class="text-sky-400">{{ citaSeleccionada.placa_vehiculo || citaSeleccionada.numero_oc }}</strong>.
                </p>

                <div class="space-y-2">
                    <label class="text-xs font-medium text-slate-300">Selecciona Muelle</label>
                    <select v-model="muelleSeleccionado" class="w-full bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2">
                        <option value="Muelle 1">Muelle 1 (Carga Seca)</option>
                        <option value="Muelle 2">Muelle 2 (Carga Seca)</option>
                        <option value="Muelle 3">Muelle 3 (Refrigerados / Lácteos)</option>
                        <option value="Muelle 4">Muelle 4 (Express / Pequeños)</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                    <button @click="modalMuelle = false" class="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white">Cancelar</button>
                    <button @click="confirmarLlamadoMuelle" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition">Llamar Ahora</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    citas: Array,
    metricas: Object,
    fechaFiltro: String,
});

const fecha = ref(props.fechaFiltro);
const busqueda = ref('');
const filtroEstado = ref('todas');

const modalCheckin = ref(false);
const modalMuelle = ref(false);
const citaSeleccionada = ref({});
const muelleSeleccionado = ref('Muelle 1');

const formCheckin = ref({
    chofer_nombre: '',
    chofer_cedula: '',
    chofer_telefono: '',
    placa_vehiculo: '',
    garita_observaciones: '',
});

const labelPestana = (p) => {
    const labels = {
        todas: 'Todos',
        programada: 'Esperando Llegada',
        en_patio: 'En Patio',
        en_muelle: 'En Muelle',
        finalizada: 'Completados'
    };
    return labels[p] || p;
};

const badgeEstado = (e) => {
    if (e === 'en_patio' || e === 'en_garita') return 'px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950/60 text-amber-300 border border-amber-800/40';
    if (e === 'en_muelle') return 'px-2 py-0.5 rounded text-[10px] font-bold bg-blue-950/60 text-blue-300 border border-blue-800/40';
    if (e === 'finalizada' || e === 'salida') return 'px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950/60 text-emerald-300 border border-emerald-800/40';
    return 'px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700';
};

const textoEstado = (e) => {
    if (e === 'en_patio' || e === 'en_garita') return '⏳ En Patio';
    if (e === 'en_muelle') return '🏭 En Muelle';
    if (e === 'finalizada') return '✅ Descargado';
    if (e === 'salida') return '🏁 Salida';
    return '📅 Esperando';
};

const formatHora = (d) => {
    if (!d) return '--:--';
    const date = new Date(d);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const citasFiltradas = computed(() => {
    return props.citas.filter(c => {
        // Filtro de pestaña
        if (filtroEstado.value !== 'todas') {
            const estadoActual = c.estado_patio || c.estatus;
            if (filtroEstado.value === 'en_patio' && !['en_patio', 'en_garita'].includes(estadoActual)) return false;
            if (filtroEstado.value === 'en_muelle' && estadoActual !== 'en_muelle') return false;
            if (filtroEstado.value === 'finalizada' && !['finalizada', 'salida'].includes(estadoActual)) return false;
            if (filtroEstado.value === 'programada' && estadoActual !== 'programada') return false;
        }

        // Filtro de texto
        if (!busqueda.value) return true;
        const b = busqueda.value.toLowerCase();
        return (
            (c.numero_oc && c.numero_oc.toLowerCase().includes(b)) ||
            (c.proveedor && c.proveedor.toLowerCase().includes(b)) ||
            (c.chofer_nombre && c.chofer_nombre.toLowerCase().includes(b)) ||
            (c.placa_vehiculo && c.placa_vehiculo.toLowerCase().includes(b))
        );
    });
});

const filtrarPorFecha = () => {
    router.get('/garita', { fecha: fecha.value }, { preserveState: true });
};

const abrirModalCheckin = (cita) => {
    citaSeleccionada.value = cita;
    formCheckin.value = {
        chofer_nombre: cita.chofer_nombre || '',
        chofer_cedula: cita.chofer_cedula || '',
        chofer_telefono: cita.chofer_telefono || '',
        placa_vehiculo: cita.placa_vehiculo || '',
        garita_observaciones: cita.garita_observaciones || '',
    };
    modalCheckin.value = true;
};

const confirmarCheckin = async () => {
    try {
        await axios.post(`/api/garita/citas/${citaSeleccionada.value.id}/checkin`, formCheckin.value);
        modalCheckin.value = false;
        filtrarPorFecha();
    } catch (e) {
        alert('Error al registrar check-in: ' + (e.response?.data?.error || e.message));
    }
};

const abrirModalMuelle = (cita) => {
    citaSeleccionada.value = cita;
    muelleSeleccionado.value = cita.muelle_asignado || 'Muelle 1';
    modalMuelle.value = true;
};

const confirmarLlamadoMuelle = async () => {
    try {
        await axios.post(`/api/garita/citas/${citaSeleccionada.value.id}/llamar-muelle`, {
            muelle: muelleSeleccionado.value
        });
        modalMuelle.value = false;
        filtrarPorFecha();
    } catch (e) {
        alert('Error al llamar a muelle: ' + (e.response?.data?.error || e.message));
    }
};

const registrarSalida = async (cita) => {
    if (!confirm(`¿Confirmar salida del camión (${cita.placa_vehiculo || cita.numero_oc})?`)) return;
    try {
        await axios.post(`/api/garita/citas/${cita.id}/salida`);
        filtrarPorFecha();
    } catch (e) {
        alert('Error al registrar salida: ' + (e.response?.data?.error || e.message));
    }
};

const abrirScanner = () => {
    const codigo = prompt('Ingresa o escanea el Código QR / Número de ODC:');
    if (!codigo) return;
    busqueda.value = codigo;
};

const verPase = (cita) => {
    alert(`Pase de Entrada Cita #${cita.id}\nODC: ${cita.numero_oc}\nProveedor: ${cita.proveedor}\nChofer: ${cita.chofer_nombre || 'N/A'}\nPlaca: ${cita.placa_vehiculo || 'N/A'}`);
};
</script>
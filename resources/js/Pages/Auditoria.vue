<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

const logs = ref([]);
const pagination = ref({});
const cargando = ref(true);

const filtroModulo = ref('');
const busqueda = ref('');

const modulos = ['Citas', 'Usuarios', 'Autenticación', 'Operarios', 'Configuración'];

const cargarLogs = async (page = 1) => {
    cargando.value = true;
    try {
        const resp = await axios.get(`/api/auditoria/logs`, {
            params: {
                page: page,
                module: filtroModulo.value,
                search: busqueda.value
            }
        });
        logs.value = resp.data.data;
        pagination.value = {
            current_page: resp.data.current_page,
            last_page: resp.data.last_page,
            total: resp.data.total
        };
    } catch (error) {
        console.error('Error cargando bitácora:', error);
    } finally {
        cargando.value = false;
    }
};

onMounted(() => {
    cargarLogs();
});

// Buscar cuando se escribe (con un pequeño debounce simulado o en change)
let searchTimeout;
watch([busqueda, filtroModulo], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        cargarLogs(1);
    }, 500);
});

const formatFecha = (d) => {
    return new Date(d).toLocaleString('es-VE', { 
        day: '2-digit', month: 'short', year: 'numeric', 
        hour: '2-digit', minute: '2-digit', hour12: true 
    });
};

const formatValores = (val) => {
    if (!val) return '—';
    try {
        const obj = typeof val === 'string' ? JSON.parse(val) : val;
        return Object.entries(obj).map(([k, v]) => `${k}: ${v}`).join(', ');
    } catch (e) {
        return String(val);
    }
};
</script>

<template>
    <Head title="Centro de Auditoría" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                Centro de Auditoría <span class="text-red-600">| Bitácora Global</span>
            </h2>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filtros -->
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 mb-6 flex flex-col sm:flex-row gap-4 items-end">
                    <div class="w-full sm:w-1/3">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Búsqueda General</label>
                        <input v-model="busqueda" type="text" placeholder="Usuario, acción, motivo, ID..." class="w-full rounded-xl border-slate-200 focus:border-red-500 focus:ring-red-500 text-sm">
                    </div>
                    <div class="w-full sm:w-1/4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Filtrar por Módulo</label>
                        <select v-model="filtroModulo" class="w-full rounded-xl border-slate-200 focus:border-red-500 focus:ring-red-500 text-sm">
                            <option value="">Todos los módulos</option>
                            <option v-for="m in modulos" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </div>
                    <div>
                        <button @click="cargarLogs(1)" class="bg-slate-800 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-slate-900 transition-colors text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            Actualizar
                        </button>
                    </div>
                </div>

                <!-- Tabla de Bitácora -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-900 text-white font-medium uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-6 py-4">Fecha / Hora</th>
                                    <th class="px-6 py-4">Usuario</th>
                                    <th class="px-6 py-4">Módulo / Acción</th>
                                    <th class="px-6 py-4">Motivo / Justificación</th>
                                    <th class="px-6 py-4">Detalles (Antes -> Después)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="cargando">
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500">Cargando registros de auditoría...</td>
                                </tr>
                                <tr v-else-if="logs.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500">No se encontraron registros de auditoría que coincidan con los filtros.</td>
                                </tr>
                                <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-800">{{ formatFecha(log.created_at) }}</div>
                                        <div class="text-xs text-slate-400 mt-1">IP: {{ log.ip_address || 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800 flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[10px] text-slate-600 font-bold">
                                                {{ log.user_name ? log.user_name.charAt(0) : 'S' }}
                                            </div>
                                            {{ log.user_name || 'Sistema' }}
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1">{{ log.user_role || 'Automático' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200 mb-1">
                                            {{ log.module }}
                                        </span>
                                        <div class="font-bold text-slate-700 text-xs">{{ log.action }}</div>
                                        <div class="text-[10px] text-slate-400 mt-1 font-mono">Ref: {{ log.auditable_id || 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-slate-600 whitespace-normal min-w-[200px] italic">
                                            "{{ log.motive || 'Sin motivo especificado' }}"
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="log.old_values || log.new_values" class="text-xs space-y-1 font-mono">
                                            <div v-if="log.old_values" class="text-red-600 bg-red-50 px-2 py-1 rounded max-w-xs truncate" :title="formatValores(log.old_values)">
                                                - {{ formatValores(log.old_values) }}
                                            </div>
                                            <div v-if="log.new_values" class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded max-w-xs truncate" :title="formatValores(log.new_values)">
                                                + {{ formatValores(log.new_values) }}
                                            </div>
                                        </div>
                                        <span v-else class="text-slate-300 text-xs">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <div v-if="pagination.last_page > 1" class="bg-slate-50 px-6 py-4 flex items-center justify-between border-t border-slate-100">
                        <span class="text-sm text-slate-500">Página {{ pagination.current_page }} de {{ pagination.last_page }} ({{ pagination.total }} registros)</span>
                        <div class="flex gap-2">
                            <button @click="cargarLogs(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm font-medium hover:bg-white disabled:opacity-50">Anterior</button>
                            <button @click="cargarLogs(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm font-medium hover:bg-white disabled:opacity-50">Siguiente</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

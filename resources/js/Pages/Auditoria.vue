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

const getActionIcon = (action) => {
    const a = (action || '').toLowerCase();
    if (a.includes('crear') || a.includes('registr')) return { icon: 'plus', color: 'text-emerald-600 bg-emerald-50 border-emerald-200' };
    if (a.includes('eliminar') || a.includes('cancel') || a.includes('borr')) return { icon: 'trash', color: 'text-rose-600 bg-rose-50 border-rose-200' };
    if (a.includes('actualiz') || a.includes('editar') || a.includes('modif') || a.includes('reprogram')) return { icon: 'edit', color: 'text-amber-600 bg-amber-50 border-amber-200' };
    if (a.includes('login') || a.includes('inicio') || a.includes('sesión') || a.includes('autent')) return { icon: 'login', color: 'text-indigo-600 bg-indigo-50 border-indigo-200' };
    return { icon: 'default', color: 'text-[#6c7263] bg-[#f5f6f2] border-[#eef0eb]' };
};

const expandedLogId = ref(null);
const toggleExpand = (id) => {
    expandedLogId.value = expandedLogId.value === id ? null : id;
};
</script>

<template>
    <Head title="Centro de Auditoría" />
    <AuthenticatedLayout>
        
        <div class="space-y-6 max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#eef0eb] pb-5">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c] flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-slate-700 to-zinc-900 flex items-center justify-center shadow-lg shadow-zinc-800/20">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        </div>
                        Centro de Auditoría
                    </h1>
                    <p class="text-sm text-[#6c7263] mt-1 ml-[52px]">Bitácora completa de acciones, cambios y eventos del sistema.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] text-[#6c7263] font-bold bg-[#fcfbf8] border border-[#eef0eb] px-3 py-1.5 rounded-full flex items-center gap-1.5">
                        <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ pagination.total || 0 }} registros totales
                    </span>
                </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-5">
                
                <!-- Filtros Premium -->
                <div class="bg-white p-5 rounded-2xl border border-[#eef0eb] shadow-sm">
                    <div class="flex flex-col sm:flex-row gap-4 items-end">
                        <div class="w-full sm:flex-1">
                            <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                Búsqueda General
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-[#888c80]">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </span>
                                <input v-model="busqueda" type="text" placeholder="Usuario, acción, motivo, ID..." class="w-full rounded-xl border-[#eef0eb] bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 text-xs pl-10 pr-3.5 py-3 transition-all">
                            </div>
                        </div>
                        <div class="w-full sm:w-56">
                            <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                Módulo
                            </label>
                            <select v-model="filtroModulo" class="w-full rounded-xl border-[#eef0eb] bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 text-[#6c7263] text-xs px-3.5 py-3 transition-all">
                                <option value="">Todos los módulos</option>
                                <option v-for="m in modulos" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <button @click="cargarLogs(1)" class="bg-[#1c1c1c] text-white px-5 py-3 rounded-xl font-bold hover:bg-zinc-800 transition-all text-xs flex items-center gap-2 shadow-sm active:scale-95">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Actualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Timeline Style Logs -->
                <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                    
                    <!-- Table Header -->
                    <div class="bg-gradient-to-r from-[#fcfbf8] to-white px-6 py-4 border-b border-[#eef0eb] flex items-center justify-between">
                        <h3 class="text-sm font-bold text-[#1c1c1c] flex items-center gap-2">
                            <span class="flex h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                            Bitácora de Eventos
                        </h3>
                        <span class="text-[9px] font-bold text-[#888c80] uppercase tracking-wider">Últimas acciones del sistema</span>
                    </div>

                    <!-- Loading -->
                    <div v-if="cargando" class="p-8 space-y-4">
                        <div v-for="i in 5" :key="i" class="flex gap-4 animate-pulse">
                            <div class="w-10 h-10 bg-[#f5f6f2] rounded-xl shrink-0"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3.5 bg-[#f5f6f2] rounded-lg w-1/3"></div>
                                <div class="h-3 bg-[#f5f6f2] rounded-lg w-2/3"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="logs.length === 0" class="px-6 py-16 text-center text-[#888c80]">
                        <div class="text-4xl mb-3">📋</div>
                        <p class="text-sm font-bold">Sin registros de auditoría</p>
                        <p class="text-xs mt-1">No se encontraron eventos que coincidan con los filtros aplicados.</p>
                    </div>

                    <!-- Log Timeline Items -->
                    <div v-else class="divide-y divide-[#eef0eb]">
                        <div v-for="(log, idx) in logs" :key="log.id" 
                            class="px-6 py-4 hover:bg-[#fcfbf8]/40 transition-all duration-200 cursor-pointer"
                            @click="toggleExpand(log.id)">
                            
                            <div class="flex items-start gap-4">
                                <!-- Action Icon -->
                                <div class="w-10 h-10 rounded-xl border flex items-center justify-center shrink-0 transition-transform duration-200"
                                    :class="[getActionIcon(log.action).color, expandedLogId === log.id ? 'scale-110' : '']">
                                    <!-- Plus icon -->
                                    <svg v-if="getActionIcon(log.action).icon === 'plus'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                    <!-- Trash icon -->
                                    <svg v-else-if="getActionIcon(log.action).icon === 'trash'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    <!-- Edit icon -->
                                    <svg v-else-if="getActionIcon(log.action).icon === 'edit'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    <!-- Login icon -->
                                    <svg v-else-if="getActionIcon(log.action).icon === 'login'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                                    <!-- Default icon -->
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[9px] font-bold bg-primary/10 text-primary border border-primary/20 uppercase tracking-wider">
                                            {{ log.module }}
                                        </span>
                                        <span class="text-xs font-bold text-[#1c1c1c]">{{ log.action }}</span>
                                    </div>
                                    
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-[11px] text-[#888c80]">
                                        <span class="flex items-center gap-1.5 font-semibold">
                                            <div class="w-4 h-4 rounded-md bg-primary/10 border border-primary/20 flex items-center justify-center text-[8px] text-primary font-mono font-bold shrink-0">
                                                {{ log.user_name ? log.user_name.charAt(0) : 'S' }}
                                            </div>
                                            {{ log.user_name || 'Sistema' }}
                                            <span class="text-[9px] text-[#888c80] uppercase font-bold">({{ log.user_role || 'Automático' }})</span>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            {{ formatFecha(log.created_at) }}
                                        </span>
                                        <span class="font-mono text-[10px]">IP: {{ log.ip_address || 'N/A' }}</span>
                                    </div>

                                    <!-- Motivo -->
                                    <p v-if="log.motive" class="mt-2 text-xs text-[#6c7263] italic bg-[#fcfbf8] border border-[#eef0eb] px-3 py-1.5 rounded-xl inline-block">
                                        "{{ log.motive }}"
                                    </p>

                                    <!-- Expanded Details: Before/After diff -->
                                    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-1 max-h-0" enter-to-class="opacity-100 translate-y-0 max-h-40" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 max-h-40" leave-to-class="opacity-0 max-h-0">
                                        <div v-if="expandedLogId === log.id && (log.old_values || log.new_values)" class="mt-3 space-y-1.5 overflow-hidden">
                                            <span class="text-[9px] font-bold text-[#888c80] uppercase tracking-wider">Detalles del cambio — Ref #{{ log.auditable_id || 'N/A' }}</span>
                                            <div v-if="log.old_values" class="text-[11px] text-rose-600 bg-rose-50 border border-rose-100/60 px-3 py-1.5 rounded-xl font-mono truncate" :title="formatValores(log.old_values)">
                                                <span class="font-bold mr-1">−</span> {{ formatValores(log.old_values) }}
                                            </div>
                                            <div v-if="log.new_values" class="text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-100/60 px-3 py-1.5 rounded-xl font-mono truncate" :title="formatValores(log.new_values)">
                                                <span class="font-bold mr-1">+</span> {{ formatValores(log.new_values) }}
                                            </div>
                                        </div>
                                    </Transition>
                                </div>

                                <!-- Expand Arrow -->
                                <div class="shrink-0 mt-1">
                                    <svg class="w-4 h-4 text-[#888c80] transition-transform duration-200" :class="{ 'rotate-180': expandedLogId === log.id }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paginación Premium -->
                    <div v-if="pagination.last_page > 1" class="bg-gradient-to-r from-[#fcfbf8] to-white px-6 py-4 flex items-center justify-between border-t border-[#eef0eb]">
                        <span class="text-xs text-[#6c7263] font-semibold">
                            Página <span class="font-bold text-[#1c1c1c]">{{ pagination.current_page }}</span> de <span class="font-bold text-[#1c1c1c]">{{ pagination.last_page }}</span> 
                            <span class="text-[#888c80]">({{ pagination.total }} registros)</span>
                        </span>
                        <div class="flex gap-2">
                            <button @click="cargarLogs(pagination.current_page - 1)" :disabled="pagination.current_page === 1" 
                                class="px-4 py-2 rounded-xl border border-[#eef0eb] text-xs font-bold text-[#6c7263] bg-white hover:bg-[#fcfbf8] transition-all disabled:opacity-40 flex items-center gap-1.5 active:scale-95">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                                Anterior
                            </button>
                            <button @click="cargarLogs(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" 
                                class="px-4 py-2 rounded-xl border border-[#eef0eb] text-xs font-bold text-[#6c7263] bg-white hover:bg-[#fcfbf8] transition-all disabled:opacity-40 flex items-center gap-1.5 active:scale-95">
                                Siguiente
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

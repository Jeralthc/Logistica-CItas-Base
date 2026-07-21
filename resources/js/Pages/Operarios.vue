<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const operarios = ref([]);
const resumen = ref({ receptores_total: 0, receptores_disponibles: 0, carga_total: 0, carga_disponibles: 0 });
const cargando = ref(false);
const mostrarModal = ref(false);
const editando = ref(null);
const error = ref('');

const form = ref({ nombre: '', cedula: '', tipo: 'receptor', turno: 'diurno' });

const cargarOperarios = async () => {
    cargando.value = true;
    try {
        const resp = await axios.get('/api/operarios');
        operarios.value = resp.data.operarios;
        resumen.value = resp.data.resumen;
    } catch (e) { console.error(e); }
    finally { cargando.value = false; }
};

const abrirCrear = () => {
    editando.value = null;
    form.value = { nombre: '', cedula: '', tipo: 'receptor', turno: 'diurno' };
    error.value = '';
    mostrarModal.value = true;
};

const abrirEditar = (op) => {
    editando.value = op;
    form.value = { nombre: op.nombre, cedula: op.cedula, tipo: op.tipo, turno: op.turno };
    error.value = '';
    mostrarModal.value = true;
};

const guardar = async () => {
    error.value = '';
    try {
        if (editando.value) {
            await axios.put(`/api/operarios/${editando.value.id}`, form.value);
        } else {
            await axios.post('/api/operarios', form.value);
        }
        mostrarModal.value = false;
        cargarOperarios();
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al guardar.';
    }
};

const toggleDisponible = async (op) => {
    try {
        await axios.post(`/api/operarios/${op.id}/toggle`);
        op.disponible = !op.disponible;
        cargarOperarios();
    } catch (e) { console.error(e); }
};

const eliminar = async (op) => {
    if (!confirm(`¿Eliminar a ${op.nombre}?`)) return;
    try {
        await axios.delete(`/api/operarios/${op.id}`);
        cargarOperarios();
    } catch (e) { console.error(e); }
};

const receptores = computed(() => operarios.value.filter(o => o.tipo === 'receptor'));
const cargadores = computed(() => operarios.value.filter(o => o.tipo === 'carga'));

onMounted(cargarOperarios);
</script>

<template>
    <Head title="Personal de Muelle" />
    <AuthenticatedLayout>
        
        <div class="space-y-6 max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header and Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#eef0eb] pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c]">
                        Personal de Muelle
                    </h1>
                    <p class="text-xs text-[#6c7263] mt-1 font-medium">Administración del personal de descarga y receptores asignados a andenes.</p>
                </div>
                <button @click="abrirCrear"
                    class="bg-primary hover:bg-[#4f46e5] text-white px-4 py-2.5 rounded-xl font-bold text-xs shadow-sm transition-all active:scale-95 flex items-center gap-1.5 self-start sm:self-auto uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    Nuevo Operario
                </button>
            </div>

            <!-- Content Area -->
            <div class="space-y-6">
                <!-- Resumen Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl p-5 border border-[#eef0eb] shadow-sm hover:shadow-md transition-all duration-300">
                        <p class="text-[9px] text-[#888c80] font-black uppercase tracking-wider">Receptores</p>
                        <p class="text-2xl font-extrabold text-[#1c1c1c] mt-1">{{ resumen.receptores_total }}</p>
                        <p class="text-[10px] text-primary font-bold mt-1 bg-primary/10 border border-primary/20 rounded-full px-2 py-0.5 w-fit">{{ resumen.receptores_disponibles }} activos</p>
                    </div>
                    
                    <div class="bg-white rounded-2xl p-5 border border-[#eef0eb] shadow-sm hover:shadow-md transition-all duration-300">
                        <p class="text-[9px] text-[#888c80] font-black uppercase tracking-wider">Carga / Depósito</p>
                        <p class="text-2xl font-extrabold text-[#1c1c1c] mt-1">{{ resumen.carga_total }}</p>
                        <p class="text-[10px] text-indigo-600 font-bold mt-1 bg-indigo-50 border border-indigo-100 rounded-full px-2 py-0.5 w-fit">{{ resumen.carga_disponibles }} activos</p>
                    </div>
                    
                    <div class="bg-white rounded-2xl p-5 border border-[#eef0eb] shadow-sm hover:shadow-md transition-all duration-300">
                        <p class="text-[9px] text-[#888c80] font-black uppercase tracking-wider">Total Personal</p>
                        <p class="text-2xl font-extrabold text-[#1c1c1c] mt-1">{{ operarios.length }}</p>
                        <p class="text-[10px] text-[#6c7263] font-bold mt-1">Registrados</p>
                    </div>
                    
                    <div class="bg-[#1c1c1c] rounded-2xl p-5 text-white shadow-md border border-zinc-800">
                        <p class="text-[9px] text-zinc-400 font-black uppercase tracking-wider">Disponibles Ahora</p>
                        <p class="text-2xl font-extrabold mt-1 text-white">{{ resumen.receptores_disponibles + resumen.carga_disponibles }}</p>
                        <p class="text-[10px] text-emerald-400 font-bold mt-1 flex items-center gap-1">
                            <span class="h-1.5 w-1.5 bg-emerald-400 rounded-full animate-ping"></span>
                            Operativos
                        </p>
                    </div>
                </div>

                <!-- List Containers -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Tabla de Receptores -->
                    <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                        <div class="bg-[#faf9f6] border-b border-[#eef0eb] px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-[#1c1c1c] font-bold text-sm leading-none flex items-center gap-1.5">
                                    <span>🚛</span> Receptores de Muelle
                                </h3>
                                <p class="text-[#6c7263] text-[10px] mt-1 font-semibold">Personal encargado de recibir mercancía en andén</p>
                            </div>
                        </div>
                        <div class="divide-y divide-[#eef0eb]">
                            <div v-for="op in receptores" :key="op.id"
                                class="px-6 py-4 flex items-center justify-between hover:bg-[#faf9f6]/40 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs shrink-0"
                                        :class="op.disponible ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-zinc-50 text-zinc-400 border border-zinc-200'">
                                        {{ op.nombre.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#1c1c1c] text-xs leading-snug">{{ op.nombre }}</p>
                                        <p class="text-[10px] text-[#888c80] mt-0.5 font-bold">C.I: {{ op.cedula }} · Turno {{ op.turno }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button @click="toggleDisponible(op)"
                                        :class="op.disponible ? 'bg-primary/10 text-primary border border-primary/20 hover:bg-primary/20' : 'bg-zinc-50 text-zinc-500 border border-zinc-200 hover:bg-zinc-100'"
                                        class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1.5 rounded-lg transition-all">
                                        {{ op.disponible ? 'Disponible' : 'Inactivo' }}
                                    </button>
                                    <button @click="abrirEditar(op)" class="text-[#888c80] hover:text-[#1c1c1c] p-1.5 hover:bg-[#faf9f6] rounded-lg transition-all" title="Editar">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="eliminar(op)" class="text-[#888c80] hover:text-rose-600 p-1.5 hover:bg-rose-50 rounded-lg transition-all" title="Eliminar">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                            <div v-if="receptores.length === 0" class="px-6 py-8 text-center text-[#888c80] text-xs font-bold">No hay receptores registrados.</div>
                        </div>
                    </div>

                    <!-- Tabla de Carga -->
                    <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                        <div class="bg-[#faf9f6] border-b border-[#eef0eb] px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-[#1c1c1c] font-bold text-sm leading-none flex items-center gap-1.5">
                                    <span>📦</span> Operarios de Carga / Depósito
                                </h3>
                                <p class="text-[#6c7263] text-[10px] mt-1 font-semibold">Personal encargado del manejo de mercancía en depósito</p>
                            </div>
                        </div>
                        <div class="divide-y divide-[#eef0eb]">
                            <div v-for="op in cargadores" :key="op.id"
                                class="px-6 py-4 flex items-center justify-between hover:bg-[#faf9f6]/40 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs shrink-0"
                                        :class="op.disponible ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-zinc-50 text-zinc-400 border border-zinc-200'">
                                        {{ op.nombre.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#1c1c1c] text-xs leading-snug">{{ op.nombre }}</p>
                                        <p class="text-[10px] text-[#888c80] mt-0.5 font-bold">C.I: {{ op.cedula }} · Turno {{ op.turno }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button @click="toggleDisponible(op)"
                                        :class="op.disponible ? 'bg-indigo-50 text-indigo-700 border border-indigo-100 hover:bg-indigo-100/50' : 'bg-zinc-50 text-zinc-500 border border-zinc-200 hover:bg-zinc-100'"
                                        class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1.5 rounded-lg transition-all">
                                        {{ op.disponible ? 'Disponible' : 'Inactivo' }}
                                    </button>
                                    <button @click="abrirEditar(op)" class="text-[#888c80] hover:text-[#1c1c1c] p-1.5 hover:bg-[#faf9f6] rounded-lg transition-all" title="Editar">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="eliminar(op)" class="text-[#888c80] hover:text-rose-600 p-1.5 hover:bg-rose-50 rounded-lg transition-all" title="Eliminar">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                            <div v-if="cargadores.length === 0" class="px-6 py-8 text-center text-[#888c80] text-xs font-bold">No hay operarios de carga registrados.</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <Modal :show="mostrarModal" max-width="md" @close="mostrarModal = false">
            <div class="bg-white rounded-2xl overflow-hidden shadow-2xl border border-[#eef0eb]">
                <div class="bg-[#fcfbf8] border-b border-[#eef0eb] px-6 py-4 flex items-center justify-between">
                    <h3 class="text-sm font-black text-[#1c1c1c]">{{ editando ? 'Editar Operario' : 'Nuevo Operario' }}</h3>
                    <button @click="mostrarModal = false" class="text-[#888c80] hover:text-[#1c1c1c]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="text-[10px] font-bold text-[#888c80] uppercase tracking-wider">Nombre Completo</label>
                        <input v-model="form.nombre" type="text" placeholder="Ej. Juan Pérez" class="mt-1 block w-full text-xs border-[#eef0eb] rounded-xl focus:border-primary focus:ring-primary/20">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-[#888c80] uppercase tracking-wider">Cédula de Identidad</label>
                        <input v-model="form.cedula" type="text" placeholder="Ej. 12345678" class="mt-1 block w-full text-xs border-[#eef0eb] rounded-xl focus:border-primary focus:ring-primary/20">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-[#888c80] uppercase tracking-wider">Tipo</label>
                            <select v-model="form.tipo" class="mt-1 block w-full text-xs border-[#eef0eb] rounded-xl focus:border-primary focus:ring-primary/20">
                                <option value="receptor">Receptor de Muelle</option>
                                <option value="carga">Operario de Carga</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-[#888c80] uppercase tracking-wider">Turno</label>
                            <select v-model="form.turno" class="mt-1 block w-full text-xs border-[#eef0eb] rounded-xl focus:border-primary focus:ring-primary/20">
                                <option value="diurno">Diurno</option>
                                <option value="nocturno">Nocturno</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="error" class="text-rose-600 text-xs font-bold bg-rose-50 border border-rose-100 px-3 py-2 rounded-xl">⚠️ {{ error }}</p>
                </div>
                <div class="bg-[#fcfbf8] border-t border-[#eef0eb] px-6 py-4 flex justify-end gap-2.5">
                    <button @click="mostrarModal = false" class="px-4 py-2 text-xs font-bold text-[#6c7263] hover:text-[#1c1c1c] transition-colors">Cancelar</button>
                    <button @click="guardar" class="px-5 py-2 bg-primary hover:bg-[#4f46e5] text-white text-xs font-bold rounded-xl transition-all active:scale-95 shadow-sm">
                        {{ editando ? 'Guardar Cambios' : 'Registrar Operario' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

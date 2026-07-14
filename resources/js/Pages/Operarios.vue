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
    <Head title="Gestión de Operarios" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    Gestión de Operarios <span class="text-indigo-600">| Personal de Descarga</span>
                </h2>
                <button @click="abrirCrear"
                    class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-600/20 active:scale-95 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Nuevo Operario
                </button>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Resumen -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Receptores</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ resumen.receptores_total }}</p>
                        <p class="text-xs text-emerald-600 font-bold mt-1">{{ resumen.receptores_disponibles }} disponibles</p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Carga/Depósito</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ resumen.carga_total }}</p>
                        <p class="text-xs text-blue-600 font-bold mt-1">{{ resumen.carga_disponibles }} disponibles</p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Total Personal</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ operarios.length }}</p>
                    </div>
                    <div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-2xl p-5 text-white shadow-sm">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Disponibles Ahora</p>
                        <p class="text-3xl font-black mt-1">{{ resumen.receptores_disponibles + resumen.carga_disponibles }}</p>
                        <p class="text-xs text-emerald-400 font-bold mt-1">Operativos</p>
                    </div>
                </div>

                <!-- Tabla de Receptores -->
                <div class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100 mb-6">
                    <div class="bg-slate-900 px-8 py-5 flex items-center justify-between">
                        <div>
                            <h3 class="text-white font-bold text-base">🚛 Receptores de Muelle</h3>
                            <p class="text-slate-400 text-xs mt-0.5">Personal encargado de recibir mercancía en andén</p>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="op in receptores" :key="op.id"
                            class="px-4 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm shrink-0"
                                    :class="op.disponible ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'">
                                    {{ op.nombre.charAt(0) }}
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">{{ op.nombre }}</p>
                                    <p class="text-xs text-slate-400">C.I: {{ op.cedula }} · Turno {{ op.turno }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 self-end sm:self-auto">
                                <button @click="toggleDisponible(op)"
                                    :class="op.disponible ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                                    class="text-xs font-bold px-3 py-1.5 rounded-full transition-colors">
                                    {{ op.disponible ? 'Disponible' : 'No disponible' }}
                                </button>
                                <button @click="abrirEditar(op)" class="text-slate-400 hover:text-blue-600 transition-colors p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button @click="eliminar(op)" class="text-slate-400 hover:text-indigo-600 transition-colors p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="receptores.length === 0" class="px-8 py-8 text-center text-slate-400 text-sm">No hay receptores registrados.</div>
                    </div>
                </div>

                <!-- Tabla de Carga -->
                <div class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100">
                    <div class="bg-slate-900 px-8 py-5">
                        <h3 class="text-white font-bold text-base">📦 Operarios de Carga / Depósito</h3>
                        <p class="text-slate-400 text-xs mt-0.5">Personal encargado del manejo de mercancía en depósito</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="op in cargadores" :key="op.id"
                            class="px-4 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm shrink-0"
                                    :class="op.disponible ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-400'">
                                    {{ op.nombre.charAt(0) }}
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">{{ op.nombre }}</p>
                                    <p class="text-xs text-slate-400">C.I: {{ op.cedula }} · Turno {{ op.turno }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 self-end sm:self-auto">
                                <button @click="toggleDisponible(op)"
                                    :class="op.disponible ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-400'"
                                    class="text-xs font-bold px-3 py-1.5 rounded-full transition-colors">
                                    {{ op.disponible ? 'Disponible' : 'No disponible' }}
                                </button>
                                <button @click="abrirEditar(op)" class="text-slate-400 hover:text-blue-600 transition-colors p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button @click="eliminar(op)" class="text-slate-400 hover:text-indigo-600 transition-colors p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="cargadores.length === 0" class="px-8 py-8 text-center text-slate-400 text-sm">No hay operarios de carga registrados.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <Modal :show="mostrarModal" max-width="md" @close="mostrarModal = false">
            <div class="bg-white rounded-2xl overflow-hidden">
                <div class="bg-slate-900 px-8 py-6">
                    <h3 class="text-lg font-black text-white">{{ editando ? 'Editar Operario' : 'Nuevo Operario' }}</h3>
                </div>
                <div class="p-8 space-y-5">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Nombre Completo</label>
                        <input v-model="form.nombre" type="text" class="mt-1 block w-full border-slate-200 rounded-xl focus:border-indigo-600 focus:ring-indigo-600/20">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Cédula</label>
                        <input v-model="form.cedula" type="text" class="mt-1 block w-full border-slate-200 rounded-xl focus:border-indigo-600 focus:ring-indigo-600/20">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Tipo</label>
                            <select v-model="form.tipo" class="mt-1 block w-full border-slate-200 rounded-xl focus:border-indigo-600 focus:ring-indigo-600/20">
                                <option value="receptor">Receptor de Muelle</option>
                                <option value="carga">Operario de Carga</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Turno</label>
                            <select v-model="form.turno" class="mt-1 block w-full border-slate-200 rounded-xl focus:border-indigo-600 focus:ring-indigo-600/20">
                                <option value="diurno">Diurno</option>
                                <option value="nocturno">Nocturno</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="error" class="text-indigo-500 text-sm font-medium">⚠️ {{ error }}</p>
                </div>
                <div class="bg-slate-50 border-t border-slate-200 px-8 py-4 flex justify-end gap-3">
                    <button @click="mostrarModal = false" class="px-5 py-2 text-sm font-bold text-slate-600 hover:text-slate-800 transition-colors">Cancelar</button>
                    <button @click="guardar" class="px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all active:scale-95">
                        {{ editando ? 'Guardar Cambios' : 'Registrar' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

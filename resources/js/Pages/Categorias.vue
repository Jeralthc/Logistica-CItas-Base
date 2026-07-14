<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const categorias = ref([]);
const cargando = ref(true);
const guardando = ref(null); // ID of the category being saved
const mensajeExito = ref('');
const mensajeError = ref('');

const cargarCategorias = async () => {
    cargando.value = true;
    mensajeError.value = '';
    try {
        const resp = await axios.get('/api/categorias-rendimiento');
        categorias.value = resp.data.categorias;
    } catch (e) {
        mensajeError.value = 'Error al cargar las categorías. Verifica tu conexión.';
    } finally {
        cargando.value = false;
    }
};

const guardarCategoria = async (categoria) => {
    guardando.value = categoria.id;
    mensajeExito.value = '';
    mensajeError.value = '';

    try {
        const resp = await axios.put(`/api/categorias-rendimiento/${categoria.id}`, {
            tiempo_fijo: categoria.tiempo_fijo,
            velocidad_descarga: categoria.velocidad_descarga
        });
        
        mensajeExito.value = `Categoría "${categoria.nombre}" actualizada correctamente.`;
        
        // Hide success message after 3 seconds
        setTimeout(() => {
            if (mensajeExito.value.includes(categoria.nombre)) {
                mensajeExito.value = '';
            }
        }, 3000);

    } catch (e) {
        mensajeError.value = e.response?.data?.message || 'Error al guardar la categoría.';
    } finally {
        guardando.value = null;
    }
};

onMounted(() => {
    cargarCategorias();
});
</script>

<template>
    <Head title="Configuración de Categorías" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    Módulo de <span class="text-indigo-600">Categorías de Carga y Tiempos</span>
                </h2>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Mensajes -->
                <div v-if="mensajeExito" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <p class="font-bold text-sm">{{ mensajeExito }}</p>
                </div>

                <div v-if="mensajeError" class="mb-6 p-4 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-2xl flex items-center gap-3">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="font-bold text-sm">{{ mensajeError }}</p>
                </div>

                <!-- Contenido -->
                <div class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100">
                    <div class="bg-slate-900 px-8 py-5 flex items-center justify-between">
                        <div>
                            <h3 class="text-white font-bold text-base flex items-center gap-2">
                                Parámetros Logísticos
                            </h3>
                            <p class="text-slate-400 text-xs mt-0.5">Ajusta los tiempos fijos y coeficientes de descarga para las diferentes categorías.</p>
                        </div>
                    </div>

                    <div v-if="cargando" class="p-12 text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
                        <p class="text-slate-500 font-medium">Cargando categorías...</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest w-1/3">Categoría de Carga</th>
                                    <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Tiempo Fijo (min)</th>
                                    <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Velocidad de Descarga (Coeficiente)</th>
                                    <th class="px-6 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-widest w-32">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="categoria in categorias" :key="categoria.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 border border-indigo-100">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-800">{{ categoria.nombre }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="relative max-w-[120px]">
                                            <input v-model="categoria.tiempo_fijo" type="number" step="1" min="0"
                                                class="block w-full pl-3 pr-10 py-2.5 text-sm font-bold text-slate-800 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-600/20 focus:border-indigo-600 transition-all shadow-sm">
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-xs text-slate-400 font-bold">min</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="relative max-w-[150px]">
                                            <input v-model="categoria.velocidad_descarga" type="number" step="0.01" min="0"
                                                class="block w-full pl-3 pr-12 py-2.5 text-sm font-bold text-slate-800 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-600/20 focus:border-indigo-600 transition-all shadow-sm">
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-xs text-slate-400 font-bold">ton/min</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <button @click="guardarCategoria(categoria)" :disabled="guardando === categoria.id"
                                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-indigo-600 transition-all duration-300 shadow-md hover:shadow-lg active:scale-95 disabled:opacity-50 w-full">
                                            <svg v-if="guardando === categoria.id" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <span v-if="guardando === categoria.id">Guardando</span>
                                            <span v-else>Guardar</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-8 bg-blue-50 border border-blue-100 rounded-3xl p-6 flex items-start gap-4">
                    <div class="h-10 w-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-blue-900 mb-1">¿Cómo funciona el cálculo de tiempo?</h4>
                        <p class="text-sm text-blue-800/80 leading-relaxed">
                            El tiempo estimado de descarga se calcula de forma automática y en tiempo real combinando dos valores:<br>
                            <strong>Tiempo Fijo</strong>: Minutos base requeridos independiente del peso.<br>
                            <strong>Velocidad de Descarga</strong>: Minutos por cada tonelada de peso (o unidad) de esa categoría.<br><br>
                            <em>Fórmula: Duración = Tiempo Fijo + (Peso Real ÷ Velocidad de Descarga)</em>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

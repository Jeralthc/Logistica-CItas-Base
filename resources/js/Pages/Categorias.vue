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
    <Head title="Variables Logísticas" />

    <AuthenticatedLayout>
        
        <div class="space-y-6 max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#eef0eb] pb-5">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c] flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-primary to-indigo-600 flex items-center justify-center shadow-lg shadow-primary/20">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                        </div>
                        Variables Logísticas
                    </h1>
                    <p class="text-sm text-[#6c7263] mt-1 ml-[52px]">Configuración de tiempos base y coeficientes de descarga por departamento.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] text-[#6c7263] font-bold bg-[#fcfbf8] border border-[#eef0eb] px-3 py-1.5 rounded-full flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :class="cargando ? 'bg-amber-400 animate-pulse' : 'bg-emerald-500'"></span>
                        {{ cargando ? 'Sincronizando...' : categorias.length + ' categorías activas' }}
                    </span>
                </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-6">

                <!-- Mensajes -->
                <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="mensajeExito" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-3 text-xs font-semibold shadow-sm">
                        <div class="w-8 h-8 bg-emerald-100 rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <p>{{ mensajeExito }}</p>
                    </div>
                </Transition>

                <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="mensajeError" class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl flex items-center gap-3 text-xs font-semibold shadow-sm">
                        <div class="w-8 h-8 bg-rose-100 rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <p>{{ mensajeError }}</p>
                    </div>
                </Transition>

                <!-- Loading Skeleton -->
                <div v-if="cargando" class="space-y-4">
                    <div v-for="i in 4" :key="i" class="bg-white border border-[#eef0eb] rounded-2xl p-6 animate-pulse">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 bg-[#f5f6f2] rounded-2xl"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-[#f5f6f2] rounded-lg w-1/3"></div>
                                <div class="h-3 bg-[#f5f6f2] rounded-lg w-1/2"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categorías como Cards Premium -->
                <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div v-for="(categoria, idx) in categorias" :key="categoria.id" 
                        class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden hover:shadow-md hover:border-primary/20 transition-all duration-300 group"
                        :style="{ animationDelay: idx * 80 + 'ms' }">
                        
                        <!-- Card Header -->
                        <div class="px-6 py-4 border-b border-[#eef0eb] bg-gradient-to-r from-[#fcfbf8] to-white flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-2xl flex items-center justify-center text-white shadow-lg transition-transform group-hover:scale-110 duration-300"
                                    :class="[
                                        idx === 0 ? 'bg-gradient-to-br from-primary to-indigo-600 shadow-primary/25' :
                                        idx === 1 ? 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/25' :
                                        idx === 2 ? 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/25' :
                                        'bg-gradient-to-br from-rose-500 to-pink-600 shadow-rose-500/25'
                                    ]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-[#1c1c1c] text-sm">{{ categoria.nombre }}</p>
                                    <p class="text-[10px] text-[#888c80] font-semibold uppercase tracking-wider">Categoría #{{ categoria.id }}</p>
                                </div>
                            </div>
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span class="text-[9px] font-bold text-primary bg-primary/10 border border-primary/20 px-2 py-0.5 rounded-full">Editable</span>
                            </div>
                        </div>

                        <!-- Card Body - Parameters -->
                        <div class="p-6 space-y-5">
                            <!-- Tiempo Fijo -->
                            <div>
                                <label class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold text-[#888c80] uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-[#888c80]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Tiempo Fijo Base
                                    </span>
                                    <span class="text-xs font-bold text-[#1c1c1c] bg-[#f5f6f2] px-2 py-0.5 rounded-lg border border-[#eef0eb]">{{ categoria.tiempo_fijo }} min</span>
                                </label>
                                <div class="relative">
                                    <input v-model="categoria.tiempo_fijo" type="number" step="1" min="0"
                                        class="block w-full pl-4 pr-14 py-3 border-[#eef0eb] rounded-xl text-sm font-bold text-[#1c1c1c] bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 transition-all">
                                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                        <span class="text-[10px] text-[#888c80] font-black uppercase bg-white px-2 py-0.5 rounded-lg border border-[#eef0eb]">min</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Velocidad Descarga -->
                            <div>
                                <label class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold text-[#888c80] uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-[#888c80]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        Tasa de Descarga
                                    </span>
                                    <span class="text-xs font-bold text-[#1c1c1c] bg-[#f5f6f2] px-2 py-0.5 rounded-lg border border-[#eef0eb]">{{ categoria.velocidad_descarga }} t/min</span>
                                </label>
                                <div class="relative">
                                    <input v-model="categoria.velocidad_descarga" type="number" step="0.01" min="0"
                                        class="block w-full pl-4 pr-14 py-3 border-[#eef0eb] rounded-xl text-sm font-bold text-[#1c1c1c] bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 transition-all">
                                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                        <span class="text-[10px] text-[#888c80] font-black uppercase bg-white px-2 py-0.5 rounded-lg border border-[#eef0eb]">t/min</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Button -->
                            <button @click="guardarCategoria(categoria)" :disabled="guardando === categoria.id"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-[#1c1c1c] hover:bg-zinc-800 text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-sm active:scale-[0.98] disabled:opacity-50 group/btn">
                                <svg v-if="guardando === categoria.id" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <svg v-else class="w-3.5 h-3.5 transition-transform group-hover/btn:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span>{{ guardando === categoria.id ? 'Guardando cambios...' : 'Guardar Parámetros' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-gradient-to-r from-primary/5 to-indigo-50/50 border border-primary/10 rounded-2xl p-6 flex items-start gap-4">
                    <div class="h-10 w-10 bg-primary/10 text-primary border border-primary/20 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="text-xs leading-relaxed">
                        <h4 class="text-sm font-bold text-[#1c1c1c] mb-1.5">¿Cómo funciona el cálculo de tiempo?</h4>
                        <p class="text-[#6c7263]">
                            El tiempo estimado de descarga se calcula de forma automática y en tiempo real combinando dos valores:<br>
                            <span class="font-bold text-[#1c1c1c]">• Tiempo Fijo:</span> Minutos base requeridos independiente del peso.<br>
                            <span class="font-bold text-[#1c1c1c]">• Tasa de Descarga:</span> Minutos por cada tonelada de peso (o unidad) de esa categoría.<br><br>
                            <span class="inline-flex items-center gap-1.5 bg-white border border-primary/20 px-3 py-1.5 rounded-xl">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                <em class="font-semibold text-primary not-italic">Fórmula: Duración = Tiempo Fijo + (Peso Real ÷ Tasa de Descarga)</em>
                            </span>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const form = ref({
    mode: 'database',
    api_url: '',
    api_token: '',
    host: '',
    database: '',
    username: '',
    password: ''
});

const cargando = ref(true);
const procesando = ref(false);
const mensajeExito = ref('');
const errorMensaje = ref('');

onMounted(async () => {
    try {
        const resp = await axios.get('/api/configuracion-erp');
        form.value.mode = resp.data.mode || 'database';
        form.value.api_url = resp.data.api_url || '';
        form.value.api_token = resp.data.api_token || '';
        form.value.host = resp.data.host || '';
        form.value.database = resp.data.database || '';
        form.value.username = resp.data.username || '';
        // La contraseña no se trae por seguridad
    } catch (e) {
        errorMensaje.value = 'No se pudo cargar la configuración actual.';
    } finally {
        cargando.value = false;
    }
});

const guardarConfiguracion = async () => {
    procesando.value = true;
    errorMensaje.value = '';
    mensajeExito.value = '';
    
    try {
        await axios.post('/api/configuracion-erp', form.value);
        mensajeExito.value = '¡Configuración actualizada correctamente! El sistema ahora utilizará esta conexión.';
        form.value.password = ''; // Limpiar el campo de contraseña después de guardar
    } catch (e) {
        errorMensaje.value = e.response?.data?.error || 'Ocurrió un error al guardar la configuración.';
    } finally {
        procesando.value = false;
    }
};
</script>

<template>
    <Head title="Configuración ERP" />
    <AuthenticatedLayout>
        
        <div class="space-y-6 max-w-4xl mx-auto py-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#eef0eb] pb-5">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c] flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-cyan-600 to-blue-700 flex items-center justify-center shadow-lg shadow-cyan-600/20">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                        </div>
                        Configuración ERP
                    </h1>
                    <p class="text-sm text-[#6c7263] mt-1 ml-[52px]">Gestión de la conexión y sincronización de datos con el sistema de administración.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold px-3 py-1.5 rounded-full border flex items-center gap-1.5"
                        :class="form.mode === 'database' ? 'text-cyan-700 bg-cyan-50 border-cyan-200' : 'text-lime-700 bg-lime-50 border-lime-200'">
                        <span class="w-2 h-2 rounded-full animate-pulse" :class="form.mode === 'database' ? 'bg-cyan-500' : 'bg-lime-500'"></span>
                        {{ form.mode === 'database' ? 'Modo Directo' : 'Modo API' }}
                    </span>
                </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-6">
                
                <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                    
                    <!-- Loading -->
                    <div v-if="cargando" class="p-12 text-center">
                        <svg class="animate-spin h-8 w-8 mx-auto mb-3 text-primary" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-xs text-[#6c7263] font-bold">Obteniendo configuración actual...</p>
                    </div>

                    <form v-else @submit.prevent="guardarConfiguracion" class="divide-y divide-[#eef0eb]">
                        
                        <!-- Section 1: Connection Mode -->
                        <div class="p-6 md:p-8">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                <h3 class="text-sm font-bold text-[#1c1c1c]">Modo de Conexión ERP</h3>
                            </div>
                            <p class="text-xs text-[#6c7263] mb-6 ml-7">
                                Selecciona cómo se conectará el sistema al ERP. Base de datos directa para servidores locales, o Agente API para servidores en la nube.
                            </p>

                            <!-- Capsule Toggle -->
                            <div class="grid grid-cols-2 gap-2 bg-[#f5f6f2] p-1.5 rounded-2xl border border-[#eef0eb]">
                                <button type="button" @click="form.mode = 'database'" 
                                    :class="['py-3.5 rounded-xl text-xs font-bold transition-all duration-300 flex items-center justify-center gap-2.5', 
                                        form.mode === 'database' ? 'bg-white shadow-md text-cyan-700 border border-[#eef0eb]' : 'text-[#6c7263] hover:text-[#1c1c1c]']">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center transition-all duration-300"
                                        :class="form.mode === 'database' ? 'bg-cyan-50 border border-cyan-200' : 'bg-transparent'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                    </div>
                                    Base de Datos Local
                                </button>
                                <button type="button" @click="form.mode = 'api'" 
                                    :class="['py-3.5 rounded-xl text-xs font-bold transition-all duration-300 flex items-center justify-center gap-2.5', 
                                        form.mode === 'api' ? 'bg-white shadow-md text-lime-700 border border-[#eef0eb]' : 'text-[#6c7263] hover:text-[#1c1c1c]']">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center transition-all duration-300"
                                        :class="form.mode === 'api' ? 'bg-lime-50 border border-lime-200' : 'bg-transparent'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    Agente API Nube
                                </button>
                            </div>
                        </div>

                        <!-- Section 2: Alerts -->
                        <div v-if="mensajeExito || errorMensaje" class="px-6 md:px-8 pt-5">
                            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                                <div v-if="mensajeExito" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-3 text-xs font-semibold">
                                    <div class="w-8 h-8 bg-emerald-100 rounded-xl flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <p>{{ mensajeExito }}</p>
                                </div>
                            </Transition>

                            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                                <div v-if="errorMensaje" class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl flex items-center gap-3 text-xs font-semibold">
                                    <div class="w-8 h-8 bg-rose-100 rounded-xl flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <p>{{ errorMensaje }}</p>
                                </div>
                            </Transition>
                        </div>

                        <!-- Section 3: Database Fields -->
                        <div v-if="form.mode === 'database'" class="p-6 md:p-8 space-y-5 animate-fade-in">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-8 h-8 rounded-xl bg-cyan-50 border border-cyan-200 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                                </div>
                                <h4 class="text-xs font-bold text-[#1c1c1c]">Parámetros de Conexión Directa</h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs">
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                                        IP o Nombre del Equipo (Host)
                                    </label>
                                    <input v-model="form.host" type="text" :required="form.mode === 'database'" class="block w-full border-[#eef0eb] rounded-xl text-sm px-4 py-3 bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 transition-all font-medium" placeholder="Ej: 192.168.1.100 o SERVIDOR-DB">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2">Nombre de Base de Datos</label>
                                    <input v-model="form.database" type="text" :required="form.mode === 'database'" class="block w-full border-[#eef0eb] rounded-xl text-sm px-4 py-3 bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 transition-all font-medium" placeholder="Ej: VAD10">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2">Usuario SQL</label>
                                    <input v-model="form.username" type="text" :required="form.mode === 'database'" class="block w-full border-[#eef0eb] rounded-xl text-sm px-4 py-3 bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 transition-all font-medium" placeholder="Ej: sa">
                                </div>

                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2">
                                        Contraseña SQL <span class="text-[9px] text-[#888c80] font-normal normal-case">(Dejar en blanco si no desea cambiarla)</span>
                                    </label>
                                    <div class="relative">
                                        <input v-model="form.password" type="password" class="block w-full border-[#eef0eb] rounded-xl text-sm px-4 py-3 bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 transition-all font-medium" placeholder="********">
                                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                            <svg class="w-4 h-4 text-[#888c80]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3b: API Fields -->
                        <div v-if="form.mode === 'api'" class="p-6 md:p-8 space-y-5 animate-fade-in">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-8 h-8 rounded-xl bg-lime-50 border border-lime-200 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-lime-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <h4 class="text-xs font-bold text-[#1c1c1c]">Configuración del Agente API</h4>
                            </div>

                            <div class="bg-lime-50/30 p-5 rounded-2xl border border-lime-100 space-y-5 text-xs">
                                <div>
                                    <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2">URL Pública del Agente (Cloudflare Tunnel)</label>
                                    <input v-model="form.api_url" type="url" :required="form.mode === 'api'" class="block w-full border-lime-200 rounded-xl text-sm px-4 py-3 bg-white focus:border-lime-500 focus:ring-lime-500/20 transition-all font-medium" placeholder="Ej: https://agente.empresa.net/api/erp">
                                    <p class="mt-1.5 text-[10px] text-lime-700 font-semibold flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Debe incluir el "/api/erp" al final.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2">Token de Seguridad Secreto</label>
                                    <input v-model="form.api_token" type="text" class="block w-full border-lime-200 rounded-xl text-sm px-4 py-3 bg-white focus:border-lime-500 focus:ring-lime-500/20 transition-all font-medium" placeholder="Token secreto opcional para proteger tu agente">
                                </div>
                            </div>
                        </div>

                        <!-- Submit Section -->
                        <div class="p-6 md:p-8 bg-gradient-to-r from-[#fcfbf8] to-white flex flex-col sm:flex-row items-center justify-between gap-4">
                            <p class="text-[11px] text-[#888c80] font-medium flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                Los cambios se aplican inmediatamente al guardar.
                            </p>
                            <button type="submit" :disabled="procesando" class="bg-[#1c1c1c] hover:bg-zinc-800 text-white px-8 py-3 rounded-xl text-xs font-bold transition-all shadow-md active:scale-95 disabled:opacity-50 flex items-center gap-2 uppercase tracking-wider">
                                <svg v-if="procesando" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                {{ procesando ? 'Guardando...' : 'Guardar Configuración' }}
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

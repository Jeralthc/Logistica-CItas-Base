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
        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                Configuración ERP <span class="text-blue-600 font-normal">| Conexión a Base de Datos</span>
            </h2>
        </template>

        <div class="py-12 bg-slate-50 min-h-screen">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100">
                    <div class="p-8 md:p-12">
                        
                        <div class="mb-8 border-b border-slate-100 pb-6">
                            <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                Modo de Conexión ERP
                            </h3>
                            <p class="mt-2 text-sm text-slate-500">
                                Selecciona cómo se conectará el sistema al ERP. Para servidores públicos se recomienda usar el Agente API local con un túnel seguro (Cloudflare).
                            </p>
                        </div>

                        <div v-if="cargando" class="text-center py-10 text-slate-400">
                            Cargando configuración actual...
                        </div>

                        <form v-else @submit.prevent="guardarConfiguracion" class="space-y-6">
                            
                            <!-- Mensajes de Alerta -->
                            <div v-if="mensajeExito" class="bg-green-50 text-green-700 p-4 rounded-xl font-bold text-sm border border-green-200 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                {{ mensajeExito }}
                            </div>
                            <div v-if="errorMensaje" class="bg-indigo-50 text-indigo-700 p-4 rounded-xl font-bold text-sm border border-indigo-200 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ errorMensaje }}
                            </div>

                            <!-- Selector de Modo -->
                            <div class="grid grid-cols-2 gap-4 bg-slate-100 p-1.5 rounded-2xl mb-8">
                                <button type="button" @click="form.mode = 'database'" :class="['py-3 px-4 rounded-xl text-sm font-bold transition-all duration-200 flex items-center justify-center gap-2', form.mode === 'database' ? 'bg-white shadow-sm text-blue-700' : 'text-slate-500 hover:text-slate-700']">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                    Base de Datos Local
                                </button>
                                <button type="button" @click="form.mode = 'api'" :class="['py-3 px-4 rounded-xl text-sm font-bold transition-all duration-200 flex items-center justify-center gap-2', form.mode === 'api' ? 'bg-white shadow-sm text-emerald-700' : 'text-slate-500 hover:text-slate-700']">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                    Agente API Nube
                                </button>
                            </div>

                            <!-- Campos: Modo Base de Datos -->
                            <div v-if="form.mode === 'database'" class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-sm font-bold text-slate-700 mb-1">IP o Nombre del Equipo (Host)</label>
                                    <input v-model="form.host" type="text" :required="form.mode === 'database'" class="block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm bg-slate-50" placeholder="Ej: 192.168.1.100 o SERVIDOR-DB">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Nombre de Base de Datos</label>
                                    <input v-model="form.database" type="text" :required="form.mode === 'database'" class="block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm bg-slate-50" placeholder="Ej: VAD10">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Usuario SQL</label>
                                    <input v-model="form.username" type="text" :required="form.mode === 'database'" class="block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm bg-slate-50" placeholder="Ej: sa">
                                </div>

                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Contraseña SQL <span class="text-xs text-slate-400 font-normal">(Dejar en blanco para no cambiarla)</span></label>
                                    <input v-model="form.password" type="password" class="block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-xl shadow-sm bg-slate-50" placeholder="********">
                                </div>
                            </div>

                            <!-- Campos: Modo API Agente -->
                            <div v-if="form.mode === 'api'" class="grid grid-cols-1 gap-6 animate-fade-in bg-emerald-50/50 p-6 rounded-2xl border border-emerald-100">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">URL Pública del Agente (Cloudflare Tunnel)</label>
                                    <input v-model="form.api_url" type="url" :required="form.mode === 'api'" class="block w-full border-emerald-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm bg-white" placeholder="Ej: https://agente.Empresa Base.net/api/erp">
                                    <p class="mt-1 text-xs text-emerald-600 font-medium">Debe incluir el /api/erp al final.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Token de Seguridad Secreto</label>
                                    <input v-model="form.api_token" type="text" class="block w-full border-emerald-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm bg-white" placeholder="Token secreto opcional para proteger tu agente">
                                </div>
                            </div>

                            <div class="pt-6 flex justify-end">
                                <button type="submit" :disabled="procesando" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors shadow-lg shadow-blue-600/20 active:scale-95 disabled:opacity-50 flex items-center gap-2">
                                    <svg v-if="procesando" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ procesando ? 'Guardando...' : 'Guardar Configuración' }}
                                </button>
                            </div>

                        </form>
                    </div>
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

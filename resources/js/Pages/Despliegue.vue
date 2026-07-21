<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const info = ref(null);
const cargando = ref(true);
const cargandoAccion = ref(false);
const terminalLogs = ref('Consola lista. Ejecute alguna acción para ver su salida...\n');
const arrastrandoZip = ref(false);
const zipFile = ref(null);
const errorMensaje = ref('');
const exitoMensaje = ref('');

// Campos de mantenimiento
const maintenanceEndTime = ref('');
const changingMaintenance = ref(false);

const cargarInfo = async () => {
    cargando.value = true;
    errorMensaje.value = '';
    try {
        const resp = await axios.get('/api/despliegue/info');
        info.value = resp.data;
        if (resp.data.migrations) {
            terminalLogs.value += `[Info] Conexión DB activa. Estado de migraciones cargado.\n`;
        }
    } catch (e) {
        errorMensaje.value = 'Error al cargar la información del servidor.';
        terminalLogs.value += `[Error] No se pudo obtener información del servidor.\n`;
    } finally {
        cargando.value = false;
    }
};

onMounted(() => {
    cargarInfo();
});

const ejecutarAccion = async (endpoint, label) => {
    cargandoAccion.value = true;
    errorMensaje.value = '';
    exitoMensaje.value = '';
    terminalLogs.value += `[Ejecutando] ${label}...\n`;
    
    try {
        const resp = await axios.post(endpoint);
        if (resp.data.success) {
            exitoMensaje.value = `¡Acción "${label}" completada correctamente!`;
            terminalLogs.value += `----------------------------------------\n[Éxito] ${label}\n----------------------------------------\n${resp.data.output}\n`;
        } else {
            errorMensaje.value = resp.data.message || 'La acción no pudo completarse.';
            terminalLogs.value += `[Fallo] ${resp.data.output || ''}\n`;
        }
    } catch (e) {
        const errOut = e.response?.data?.output || e.response?.data?.message || e.message;
        errorMensaje.value = `Error al ejecutar "${label}".`;
        terminalLogs.value += `[Error] ${errOut}\n`;
    } finally {
        cargandoAccion.value = false;
        await cargarInfo();
    }
};

const crearBackup = async () => {
    cargandoAccion.value = true;
    errorMensaje.value = '';
    exitoMensaje.value = '';
    terminalLogs.value += `[Ejecutando] Creando copia de seguridad del código...\n`;
    
    try {
        const resp = await axios.post('/api/despliegue/crear-backup');
        if (resp.data.success) {
            exitoMensaje.value = resp.data.message;
            terminalLogs.value += `[Éxito] Backup creado correctamente.\n`;
        }
    } catch (e) {
        errorMensaje.value = e.response?.data?.message || 'Error al crear la copia de seguridad.';
        terminalLogs.value += `[Error] ${errorMensaje.value}\n`;
    } finally {
        cargandoAccion.value = false;
        await cargarInfo();
    }
};

const restaurarBackup = async (filename) => {
    if (!confirm(`¿ESTÁS ABSOLUTAMENTE SEGURO de restaurar el backup: ${filename}?\n\nEsto reemplazará el código actual de la aplicación. Se creará un backup automático de seguridad antes del proceso.`)) {
        return;
    }

    cargandoAccion.value = true;
    errorMensaje.value = '';
    exitoMensaje.value = '';
    terminalLogs.value += `[Restauración] Iniciando restauración del backup: ${filename}...\n`;
    
    try {
        const resp = await axios.post('/api/despliegue/restaurar-backup', { filename });
        if (resp.data.success) {
            exitoMensaje.value = resp.data.message;
            terminalLogs.value += `----------------------------------------\n[Éxito] Restauración Completa\n----------------------------------------\nArchivos extraídos: ${resp.data.details.extracted_count}\nErrores: ${resp.data.details.errors.length}\n`;
            if (resp.data.details.errors.length > 0) {
                terminalLogs.value += `Detalle de errores:\n` + resp.data.details.errors.join('\n') + `\n`;
            }
        }
    } catch (e) {
        errorMensaje.value = e.response?.data?.message || 'Error durante la restauración.';
        terminalLogs.value += `[Error] ${errorMensaje.value}\n`;
    } finally {
        cargandoActionComplete();
    }
};

const cargandoActionComplete = async () => {
    cargandoAccion.value = false;
    await cargarInfo();
};

const alternarMantenimiento = async () => {
    changingMaintenance.value = true;
    errorMensaje.value = '';
    exitoMensaje.value = '';
    
    try {
        if (info.value.maintenance) {
            // Desactivar
            await axios.post('/api/mantenimiento/desactivar');
            exitoMensaje.value = 'Modo de mantenimiento desactivado con éxito.';
            terminalLogs.value += `[Mantenimiento] Modo de mantenimiento desactivado. El sitio ahora es público.\n`;
        } else {
            // Activar
            if (!maintenanceEndTime.value) {
                errorMensaje.value = 'Debe definir una hora de regreso estimada.';
                changingMaintenance.value = false;
                return;
            }
            const resp = await axios.post('/api/mantenimiento/activar', { end_time: maintenanceEndTime.value });
            exitoMensaje.value = 'Modo de mantenimiento activado.';
            terminalLogs.value += `[Mantenimiento] Modo de mantenimiento activado. Bypass URL: ${resp.data.secret_url}\n`;
        }
    } catch (e) {
        errorMensaje.value = e.response?.data?.message || 'Error al cambiar el estado del mantenimiento.';
        terminalLogs.value += `[Mantenimiento] Error: ${errorMensaje.value}\n`;
    } finally {
        changingMaintenance.value = false;
        await cargarInfo();
    }
};

// Drag & Drop
const onDragOver = (e) => {
    e.preventDefault();
    arrastrandoZip.value = true;
};

const onDragLeave = () => {
    arrastrandoZip.value = false;
};

const onDrop = (e) => {
    e.preventDefault();
    arrastrandoZip.value = false;
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        validarZip(files[0]);
    }
};

const onFileSelected = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        validarZip(files[0]);
    }
};

const validarZip = (file) => {
    if (file.type !== 'application/zip' && !file.name.endsWith('.zip')) {
        errorMensaje.value = 'Solo se permiten archivos comprimidos (.zip).';
        return;
    }
    zipFile.value = file;
    exitoMensaje.value = `Parche "${file.name}" cargado. Listo para aplicar.`;
};

const subirYAplicarZip = async () => {
    if (!zipFile.value) return;
    
    cargandoAccion.value = true;
    errorMensaje.value = '';
    exitoMensaje.value = '';
    terminalLogs.value += `[Despliegue] Subiendo y procesando ZIP: ${zipFile.value.name}...\n`;
    
    const formData = new FormData();
    formData.append('zip_file', zipFile.value);
    
    try {
        const resp = await axios.post('/api/despliegue/subir-zip', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        if (resp.data.success) {
            exitoMensaje.value = resp.data.message;
            terminalLogs.value += `----------------------------------------\n[Éxito] Actualización Aplicada\n----------------------------------------\n`;
            terminalLogs.value += `Backup creado: ${resp.data.backup_created}\n`;
            terminalLogs.value += `Archivos extraídos: ${resp.data.extraction.extracted_count}\n`;
            terminalLogs.value += `Archivos omitidos: ${resp.data.extraction.skipped_count}\n`;
            terminalLogs.value += `Salida de Migración:\n${resp.data.migration_output}\n`;
            
            if (resp.data.extraction.errors.length > 0) {
                terminalLogs.value += `Errores de extracción:\n` + resp.data.extraction.errors.join('\n') + `\n`;
            }
            
            zipFile.value = null;
        }
    } catch (e) {
        const errOut = e.response?.data?.message || e.message;
        errorMensaje.value = 'Fallo durante el despliegue del parche ZIP.';
        terminalLogs.value += `[Error Despliegue] ${errOut}\n`;
    } finally {
        cargandoAccion.value = false;
        await cargarInfo();
    }
};
</script>

<template>
    <Head title="Centro de Despliegue" />
    <AuthenticatedLayout>
        
        <div class="space-y-6 max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#eef0eb] pb-5">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c] flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-violet-600 to-purple-700 flex items-center justify-center shadow-lg shadow-violet-600/20">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        Centro de Despliegue
                    </h1>
                    <p class="text-sm text-[#6c7263] mt-1 ml-[52px]">Herramientas de despliegue, copias de seguridad y optimización del servidor.</p>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span>
                        Consola del Servidor Activa
                    </span>
                </div>
            </div>

            <!-- Content Area -->
            <div>
                <!-- Alertas -->
                <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                    <div v-if="exitoMensaje" class="mb-5 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-3 text-xs font-semibold shadow-sm">
                        <div class="w-8 h-8 bg-emerald-100 rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <p>{{ exitoMensaje }}</p>
                    </div>
                </Transition>
                
                <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                    <div v-if="errorMensaje" class="mb-5 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl flex items-center gap-3 text-xs font-semibold shadow-sm">
                        <div class="w-8 h-8 bg-rose-100 rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <p>{{ errorMensaje }}</p>
                    </div>
                </Transition>

                <!-- Loading -->
                <div v-if="cargando" class="text-center py-20">
                    <svg class="animate-spin h-8 w-8 mx-auto mb-3 text-primary" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-xs text-[#6c7263] font-bold">Cargando información del servidor y diagnóstico...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Fila 1: Diagnóstico General y Mantenimiento -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        
                        <!-- Panel de Diagnóstico -->
                        <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm lg:col-span-2 overflow-hidden">
                            <div class="px-6 py-4 border-b border-[#eef0eb] bg-gradient-to-r from-[#fcfbf8] to-white">
                                <h3 class="text-sm font-bold text-[#1c1c1c] flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-violet-50 border border-violet-200 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    </div>
                                    Diagnóstico de Entorno
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3.5 text-xs">
                                    <div class="flex justify-between items-center border-b border-[#eef0eb] pb-2.5">
                                        <span class="text-[#6c7263] font-semibold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                            Versión Laravel
                                        </span>
                                        <span class="text-[#1c1c1c] font-bold font-mono bg-[#f5f6f2] px-2 py-0.5 rounded-lg border border-[#eef0eb]">{{ info.laravel_version }}</span>
                                    </div>
                                    <div class="flex justify-between items-center border-b border-[#eef0eb] pb-2.5">
                                        <span class="text-[#6c7263] font-semibold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                            Versión PHP
                                        </span>
                                        <span class="text-[#1c1c1c] font-bold font-mono bg-[#f5f6f2] px-2 py-0.5 rounded-lg border border-[#eef0eb]">{{ info.php_version }}</span>
                                    </div>
                                    <div class="flex justify-between items-center border-b border-[#eef0eb] pb-2.5">
                                        <span class="text-[#6c7263] font-semibold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Entorno (APP_ENV)
                                        </span>
                                        <span class="text-[#1c1c1c] font-bold capitalize bg-[#f5f6f2] px-2 py-0.5 rounded-lg border border-[#eef0eb]">{{ info.environment }}</span>
                                    </div>
                                    <div class="flex justify-between items-center border-b border-[#eef0eb] pb-2.5">
                                        <span class="text-[#6c7263] font-semibold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="info.debug_mode.includes('Activado') ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                                            Modo Debug
                                        </span>
                                        <span class="font-bold px-2 py-0.5 rounded-lg border text-xs" :class="info.debug_mode.includes('Activado') ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-[#1c1c1c] bg-[#f5f6f2] border-[#eef0eb]'">{{ info.debug_mode }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-[#6c7263] font-semibold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-500"></span>
                                            Carpeta Pública
                                        </span>
                                        <span class="text-[#1c1c1c] font-bold font-mono text-[10px] bg-[#f5f6f2] px-2 py-0.5 rounded-lg border border-[#eef0eb]">{{ info.public_dir }}</span>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="bg-[#fcfbf8] p-4 rounded-2xl border border-[#eef0eb]">
                                        <h4 class="text-xs font-bold text-[#1c1c1c] flex items-center gap-1.5 mb-3">
                                            <span class="w-2 h-2 rounded-full animate-pulse" :class="info.db.connected ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                            Conexión a Base de Datos
                                        </h4>
                                        <div v-if="info.db.connected" class="space-y-2 text-[11px]">
                                            <div class="flex justify-between">
                                                <span class="text-[#6c7263] font-medium">Driver:</span>
                                                <span class="text-[#1c1c1c] font-bold uppercase">{{ info.db.driver }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-[#6c7263] font-medium">Base de Datos:</span>
                                                <span class="text-[#1c1c1c] font-bold font-mono truncate max-w-[120px]">{{ info.db.name }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-[#6c7263] font-medium">Host:</span>
                                                <span class="text-[#1c1c1c] font-bold font-mono">{{ info.db.host }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-[11px] text-rose-600 font-bold">
                                            Desconectado. Error: {{ info.db.error }}
                                        </div>
                                    </div>

                                    <div class="bg-[#fcfbf8] p-4 rounded-2xl border border-[#eef0eb]">
                                        <h4 class="text-xs font-bold text-[#1c1c1c] flex items-center gap-1.5 mb-2">
                                            <span class="w-2 h-2 rounded-full animate-pulse" :class="info.manifest.exists ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                            Vite Manifest (.json)
                                        </h4>
                                        <div class="text-[11px]">
                                            <div v-if="info.manifest.exists" class="text-[#6c7263]">
                                                Encontrado en <span class="font-bold">{{ info.public_dir }}/build/</span>
                                                <p class="text-[9px] text-[#888c80] mt-1">Modificado: {{ info.manifest.last_modified }}</p>
                                            </div>
                                            <div v-else class="text-amber-600 font-bold">
                                                FALTA en {{ info.public_dir }}/build/manifest.json
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel de Mantenimiento Búnker -->
                        <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden flex flex-col">
                            <div class="px-6 py-4 border-b border-[#eef0eb] bg-gradient-to-r from-[#fcfbf8] to-white">
                                <h3 class="text-sm font-bold text-[#1c1c1c] flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center" :class="info.maintenance ? 'bg-primary/10 border border-primary/20' : 'bg-emerald-50 border border-emerald-200'">
                                        <svg class="w-3.5 h-3.5" :class="info.maintenance ? 'text-primary' : 'text-emerald-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    </div>
                                    Modo de Mantenimiento
                                </h3>
                            </div>
                            
                            <div class="p-6 flex-1 flex flex-col justify-between">
                                <div>
                                    <p class="text-[11px] text-[#6c7263] mb-5">Bloquea temporalmente el acceso público al sistema mientras realizas modificaciones críticas.</p>
                                    
                                    <div class="p-4 rounded-2xl mb-5 text-center border font-bold text-xs transition-all duration-300" :class="info.maintenance ? 'bg-gradient-to-r from-primary/10 to-indigo-50 border-primary/20 text-primary' : 'bg-gradient-to-r from-emerald-50 to-teal-50/30 border-emerald-200 text-emerald-800'">
                                        <div class="text-lg mb-1">{{ info.maintenance ? '🔒' : '🟢' }}</div>
                                        {{ info.maintenance ? '¡Sistema en mantenimiento!' : 'Sistema operativo y público' }}
                                    </div>

                                    <div v-if="!info.maintenance" class="mb-5">
                                        <label class="block text-[10px] font-bold text-[#888c80] uppercase tracking-wider mb-2">Hora estimada de regreso</label>
                                        <input type="datetime-local" v-model="maintenanceEndTime" class="w-full text-xs rounded-xl border-[#eef0eb] bg-[#faf9f6] focus:bg-white focus:border-primary focus:ring-primary/20 px-3.5 py-3 transition-all" />
                                    </div>
                                </div>

                                <button 
                                    @click="alternarMantenimiento" 
                                    :disabled="changingMaintenance"
                                    :class="info.maintenance ? 'bg-[#1c1c1c] hover:bg-zinc-800' : 'bg-primary hover:bg-[#4f46e5]'"
                                    class="w-full text-white py-3 rounded-xl font-bold transition-all text-xs shadow-sm active:scale-[0.98] flex items-center justify-center gap-2"
                                >
                                    <span v-if="changingMaintenance" class="animate-spin w-3 h-3 border-2 border-white border-t-transparent rounded-full inline-block"></span>
                                    {{ info.maintenance ? 'Desactivar Mantenimiento' : 'Activar Mantenimiento' }}
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Fila 2: Herramientas del Sistema y Consola -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        
                        <!-- Panel de Acciones -->
                        <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden flex flex-col">
                            <div class="px-6 py-4 border-b border-[#eef0eb] bg-gradient-to-r from-[#fcfbf8] to-white">
                                <h3 class="text-sm font-bold text-[#1c1c1c] flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </div>
                                    Acciones de Optimización
                                </h3>
                            </div>
                            
                            <div class="p-6 flex-1 flex flex-col justify-between">
                                <div class="space-y-2.5">
                                    <button 
                                        @click="ejecutarAccion('/api/despliegue/limpiar-cache', 'Limpieza de Caché')"
                                        :disabled="cargandoAccion"
                                        class="w-full bg-[#faf9f6] hover:bg-[#f5f6f2] border border-[#eef0eb] text-[#1c1c1c] text-left px-4 py-3.5 rounded-xl text-xs font-bold transition-all flex items-center justify-between gap-2 active:scale-[0.98] group"
                                    >
                                        <span class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                                <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </div>
                                            Limpiar Caché Completa
                                        </span>
                                        <svg class="w-4 h-4 text-[#888c80] group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
 
                                    <button 
                                        @click="ejecutarAccion('/api/despliegue/optimizar', 'Optimización')"
                                        :disabled="cargandoAccion"
                                        class="w-full bg-[#faf9f6] hover:bg-[#f5f6f2] border border-[#eef0eb] text-[#1c1c1c] text-left px-4 py-3.5 rounded-xl text-xs font-bold transition-all flex items-center justify-between gap-2 active:scale-[0.98] group"
                                    >
                                        <span class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            </div>
                                            Optimizar Framework
                                        </span>
                                        <svg class="w-4 h-4 text-[#888c80] group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
 
                                    <button 
                                        @click="ejecutarAccion('/api/despliegue/ejecutar-migracion', 'Ejecución de Migraciones')"
                                        :disabled="cargandoAccion || !info.db.connected"
                                        class="w-full bg-primary/5 hover:bg-primary/10 border border-primary/20 text-primary text-left px-4 py-3.5 rounded-xl text-xs font-bold transition-all flex items-center justify-between gap-2 active:scale-[0.98] disabled:opacity-50 group"
                                    >
                                        <span class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.2M7 9a7 7 0 0111.79 3.179M7 9h6"></path></svg>
                                            </div>
                                            Ejecutar Migraciones
                                        </span>
                                        <svg class="w-4 h-4 text-primary/50 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                </div>
 
                                <div class="pt-5 border-t border-[#eef0eb] mt-5">
                                    <button 
                                        @click="crearBackup"
                                        :disabled="cargandoAccion"
                                        class="w-full bg-[#1c1c1c] hover:bg-zinc-800 text-white text-center py-3 rounded-xl text-xs font-bold transition-all active:scale-[0.98] flex items-center justify-center gap-2"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                        Crear Respaldo del Código
                                    </button>
                                </div>
                            </div>
                        </div>
 
                        <!-- Consola Terminal Interactiva -->
                        <div class="bg-[#0a0a0f] rounded-2xl shadow-xl border border-zinc-800/50 lg:col-span-2 overflow-hidden flex flex-col">
                            <div class="px-5 py-3 border-b border-zinc-800/60 flex items-center justify-between bg-[#111118]">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 bg-rose-500 rounded-full"></span>
                                    <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
                                    <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                                    <span class="ml-2 text-[10px] text-zinc-500 font-mono font-bold">output_server_terminal.log</span>
                                </div>
                                <button @click="terminalLogs = 'Consola limpia.\n'" class="text-[9px] text-zinc-600 hover:text-zinc-400 font-mono font-bold transition-colors uppercase tracking-wider">Limpiar</button>
                            </div>
                            
                            <div class="p-5 flex-1 min-h-[250px] max-h-[320px]">
                                <div class="bg-black/30 p-4 rounded-xl font-mono text-[11px] text-lime-400 h-full overflow-y-auto border border-zinc-800/40 whitespace-pre-wrap leading-relaxed scrollbar-thin scrollbar-thumb-zinc-700 scrollbar-track-transparent">{{ terminalLogs }}</div>
                            </div>
                            
                            <div class="px-5 py-2.5 flex justify-between items-center text-[9px] text-zinc-600 font-mono border-t border-zinc-800/40 bg-[#111118]">
                                <span>PAGER=cat | UTF-8 | {{ new Date().toLocaleDateString('es-VE') }}</span>
                                <span class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                    conectado
                                </span>
                            </div>
                        </div>
 
                    </div>
 
                    <!-- Fila 3: Carga de Archivos ZIP y Historial de Backups -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        
                        <!-- Panel de Subida de ZIP -->
                        <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-[#eef0eb] bg-gradient-to-r from-[#fcfbf8] to-white">
                                <h3 class="text-sm font-bold text-[#1c1c1c] flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-cyan-50 border border-cyan-200 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    </div>
                                    Despliegue ZIP
                                </h3>
                            </div>
                            
                            <div class="p-6">
                                <p class="text-[11px] text-[#6c7263] mb-5">Sube o arrastra un archivo de actualización comprimido en formato ZIP.</p>
                                
                                <!-- Dropzone -->
                                <div 
                                    @dragover="onDragOver"
                                    @dragleave="onDragLeave"
                                    @drop="onDrop"
                                    :class="arrastrandoZip ? 'border-primary bg-primary/5 scale-[1.02]' : 'border-[#eef0eb] hover:border-[#888c80] hover:bg-[#faf9f6]'"
                                    class="border-2 border-dashed p-8 rounded-2xl text-center cursor-pointer transition-all duration-300"
                                    @click="$refs.fileInput.click()"
                                >
                                    <input type="file" ref="fileInput" class="hidden" accept=".zip" @change="onFileSelected" />
                                    <div class="w-12 h-12 mx-auto mb-3 bg-[#f5f6f2] rounded-2xl flex items-center justify-center border border-[#eef0eb]">
                                        <svg class="w-6 h-6 text-[#888c80]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    </div>
                                    <p class="text-xs font-bold text-[#1c1c1c]">Arrastra tu parche .zip</p>
                                    <p class="text-[10px] text-[#888c80] mt-1">o haz clic para explorar archivos</p>
                                </div>
 
                                <!-- Confirmación del ZIP -->
                                <div v-if="zipFile" class="mt-4 p-4 bg-amber-50/60 border border-amber-200 rounded-2xl text-xs animate-fade-in">
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <span class="font-bold text-amber-800 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Parche listo
                                        </span>
                                        <span class="text-[#6c7263] font-mono truncate text-[10px] max-w-[120px]">{{ zipFile.name }}</span>
                                    </div>
                                    <p class="text-[10px] text-amber-700 mb-3">
                                        ⚠️ Se creará un backup de la versión actual antes de extraer el parche y migrar la base de datos.
                                    </p>
                                    <button 
                                        @click="subirYAplicarZip"
                                        :disabled="cargandoAccion"
                                        class="w-full bg-primary hover:bg-[#4f46e5] text-white font-bold text-xs py-3 rounded-xl transition-all flex items-center justify-center gap-2 shadow-sm active:scale-[0.98]"
                                    >
                                        <span v-if="cargandoAccion" class="animate-spin w-3 h-3 border-2 border-white border-t-transparent rounded-full"></span>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                        Desplegar Parche Ahora
                                    </button>
                                </div>
                            </div>
                        </div>
 
                        <!-- Historial de Backups -->
                        <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden lg:col-span-2">
                            <div class="px-6 py-4 border-b border-[#eef0eb] bg-gradient-to-r from-[#fcfbf8] to-white flex items-center justify-between">
                                <h3 class="text-sm font-bold text-[#1c1c1c] flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    Historial de Respaldos
                                </h3>
                                <span class="text-[9px] font-bold text-[#888c80] uppercase tracking-wider">{{ info.backups?.length || 0 }} copias</span>
                            </div>
                            
                            <div class="p-5 max-h-[380px] overflow-y-auto space-y-2.5">
                                <div v-for="bk in info.backups" :key="bk.filename" 
                                    class="p-4 rounded-xl border border-[#eef0eb] bg-[#faf9f6] hover:bg-[#fcfbf8] transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs group">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span class="font-bold text-[#1c1c1c] block text-xs break-all leading-tight font-mono">{{ bk.filename }}</span>
                                            <span v-if="bk.source === 'root'" class="bg-primary/10 text-primary text-[9px] font-bold px-1.5 py-0.5 rounded-lg border border-primary/20">PARCHE</span>
                                            <span v-else class="bg-[#f5f6f2] text-[#6c7263] text-[9px] font-bold px-1.5 py-0.5 rounded-lg border border-[#eef0eb]">MANUAL</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-[10px] text-[#888c80] font-semibold mt-1">
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                {{ bk.created_at }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
                                                {{ bk.size }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 self-end sm:self-center shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
                                        <a 
                                            :href="route('despliegue.descargar-backup', bk.filename)"
                                            class="bg-white hover:bg-[#fcfbf8] border border-[#eef0eb] text-[#6c7263] hover:text-[#1c1c1c] text-[10px] font-bold px-3.5 py-2 rounded-xl transition-all shadow-sm flex items-center gap-1.5"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                            Descargar
                                        </a>
                                        <button 
                                            @click="restaurarBackup(bk.filename)"
                                            :disabled="cargandoAccion"
                                            class="bg-[#1c1c1c] hover:bg-zinc-800 text-white text-[10px] font-bold px-3.5 py-2 rounded-xl transition-all active:scale-95 disabled:opacity-50 flex items-center gap-1.5"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            Restaurar
                                        </button>
                                    </div>
                                </div>
                                <div v-if="info.backups.length === 0" class="text-center py-10 text-[#888c80]">
                                    <div class="text-3xl mb-2">📦</div>
                                    <p class="font-bold text-xs">Sin copias de seguridad</p>
                                    <p class="text-[10px] mt-0.5">Crea tu primera copia desde las acciones de optimización.</p>
                                </div>
                            </div>
                        </div>
 
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

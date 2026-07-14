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
    <Head title="Control de Cambios y Despliegue" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    Centro de Control de Cambios <span class="text-red-600 font-normal">| Despliegue y Recuperación</span>
                </h2>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-semibold bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-ping"></span>
                        Consola del Servidor Activa
                    </span>
                </div>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Alertas -->
                <div v-if="exitoMensaje" class="mb-6 bg-green-50 border border-green-200 text-green-800 p-4 rounded-2xl flex items-start gap-3">
                    <svg class="w-5 h-5 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <p class="font-bold text-sm">Operación Exitosa</p>
                        <p class="text-xs mt-0.5 text-green-700">{{ exitoMensaje }}</p>
                    </div>
                </div>
                
                <div v-if="errorMensaje" class="mb-6 bg-red-50 border border-red-200 text-red-800 p-4 rounded-2xl flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <p class="font-bold text-sm">Fallo en Operación</p>
                        <p class="text-xs mt-0.5 text-red-700">{{ errorMensaje }}</p>
                    </div>
                </div>

                <div v-if="cargando" class="text-center py-20 text-slate-500">
                    <div class="animate-spin w-10 h-10 border-4 border-red-600 border-t-transparent rounded-full mx-auto mb-4"></div>
                    Cargando información del servidor y diagnóstico...
                </div>

                <div v-else class="space-y-6">
                    <!-- Fila 1: Diagnóstico General y Mantenimiento -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Panel de Diagnóstico -->
                        <div class="bg-white p-6 rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 lg:col-span-2">
                            <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                Diagnóstico de Entorno
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <div class="flex justify-between border-b border-slate-100 pb-2">
                                        <span class="text-sm text-slate-500 font-medium">Versión Laravel:</span>
                                        <span class="text-sm text-slate-800 font-bold font-mono">{{ info.laravel_version }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-2">
                                        <span class="text-sm text-slate-500 font-medium">Versión PHP:</span>
                                        <span class="text-sm text-slate-800 font-bold font-mono">{{ info.php_version }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-2">
                                        <span class="text-sm text-slate-500 font-medium">Entorno (APP_ENV):</span>
                                        <span class="text-sm text-slate-800 font-bold capitalize">{{ info.environment }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-2">
                                        <span class="text-sm text-slate-500 font-medium">Modo Debug:</span>
                                        <span class="text-sm font-bold" :class="info.debug_mode.includes('Activado') ? 'text-amber-600' : 'text-slate-800'">{{ info.debug_mode }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-2">
                                        <span class="text-sm text-slate-500 font-medium">Carpeta Pública Activa:</span>
                                        <span class="text-sm text-slate-800 font-bold font-mono">{{ info.public_dir }}</span>
                                    </div>
                                </div>

                                <div class="space-y-4 bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                    <h4 class="text-sm font-bold text-slate-700 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full" :class="info.db.connected ? 'bg-green-500' : 'bg-red-500'"></span>
                                        Conexión a Base de Datos
                                    </h4>
                                    <div v-if="info.db.connected" class="space-y-2 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-slate-500 font-medium">Driver:</span>
                                            <span class="text-slate-800 font-bold uppercase">{{ info.db.driver }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500 font-medium">Base de Datos:</span>
                                            <span class="text-slate-800 font-bold font-mono">{{ info.db.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500 font-medium">Host:</span>
                                            <span class="text-slate-800 font-bold font-mono">{{ info.db.host }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-red-600 font-bold">
                                        Desconectado. Error: {{ info.db.error }}
                                    </div>

                                    <h4 class="text-sm font-bold text-slate-700 pt-2 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full" :class="info.manifest.exists ? 'bg-green-500' : 'bg-amber-500'"></span>
                                        Vite Manifest (.json)
                                    </h4>
                                    <div class="text-xs">
                                        <div v-if="info.manifest.exists" class="text-slate-600">
                                            Encontrado en <span class="font-bold">{{ info.public_dir }}/build/</span>
                                            <p class="text-[10px] text-slate-400 mt-1">Modificado: {{ info.manifest.last_modified }}</p>
                                        </div>
                                        <div v-else class="text-amber-600 font-bold">
                                            FALTA en {{ info.public_dir }}/build/manifest.json. El frontend Vue no cargará.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel de Mantenimiento Búnker -->
                        <div class="bg-white p-6 rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-black text-slate-800 mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    Mantenimiento Búnker
                                </h3>
                                <p class="text-xs text-slate-500 mb-6">Activa el bloqueo a nivel de núcleo. Evita el acceso a usuarios mientras actualizas la web.</p>
                                
                                <div class="p-4 rounded-2xl mb-4 text-center border font-bold" :class="info.maintenance ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800'">
                                    {{ info.maintenance ? '¡El sistema está en mantenimiento!' : 'El sistema está en línea' }}
                                </div>

                                <div v-if="!info.maintenance" class="mb-4">
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Hora estimada de regreso</label>
                                    <input type="datetime-local" v-model="maintenanceEndTime" class="w-full text-xs rounded-xl border-slate-300 focus:border-red-500 focus:ring-red-500" />
                                </div>
                            </div>

                            <button 
                                @click="alternarMantenimiento" 
                                :disabled="changingMaintenance"
                                :class="info.maintenance ? 'bg-slate-800 hover:bg-slate-700 shadow-slate-800/10' : 'bg-red-600 hover:bg-red-500 shadow-red-600/20'"
                                class="w-full text-white py-3 rounded-xl font-bold transition-all shadow-lg text-sm"
                            >
                                <span v-if="changingMaintenance" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full inline-block mr-2"></span>
                                {{ info.maintenance ? 'Apagar Mantenimiento (Sitio Público)' : 'Activar Mantenimiento Seguro' }}
                            </button>
                        </div>

                    </div>

                    <!-- Fila 2: Herramientas del Sistema y Consola de Salida -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Panel de Acciones -->
                        <div class="bg-white p-6 rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-black text-slate-800 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Acciones de Optimización
                                </h3>
                                <p class="text-xs text-slate-500 mb-6">Ejecuta comandos de limpieza en producción sin necesidad de entrar a la consola SSH o cPanel.</p>
                                
                                <div class="space-y-3">
                                    <button 
                                        @click="ejecutarAccion('/api/despliegue/limpiar-cache', 'Limpieza de Caché')"
                                        :disabled="cargandoAccion"
                                        class="w-full bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 text-left px-4 py-3 rounded-2xl text-xs font-bold transition-colors flex items-center justify-between"
                                    >
                                        Limpiar Caché (Config, Vistas, Rutas)
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>

                                    <button 
                                        @click="ejecutarAccion('/api/despliegue/optimizar', 'Optimización')"
                                        :disabled="cargandoAccion"
                                        class="w-full bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 text-left px-4 py-3 rounded-2xl text-xs font-bold transition-colors flex items-center justify-between"
                                    >
                                        Optimizar Framework (artisan optimize)
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>

                                    <button 
                                        @click="ejecutarAccion('/api/despliegue/ejecutar-migracion', 'Ejecución de Migraciones')"
                                        :disabled="cargandoAccion || !info.db.connected"
                                        class="w-full bg-red-50 hover:bg-red-100 border border-red-100 text-red-800 text-left px-4 py-3 rounded-2xl text-xs font-bold transition-colors flex items-center justify-between"
                                    >
                                        Ejecutar Migraciones Pendientes
                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.2M7 9a7 7 0 0111.79 3.179M7 9h6"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-slate-100 mt-6">
                                <button 
                                    @click="crearBackup"
                                    :disabled="cargandoAccion"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-3 rounded-xl text-sm font-bold transition-colors shadow-lg shadow-blue-600/10"
                                >
                                    Crear Copia de Seguridad del Código
                                </button>
                            </div>
                        </div>

                        <!-- Consola Terminal Interactiva -->
                        <div class="bg-slate-900 p-6 rounded-3xl shadow-xl shadow-slate-950 border border-slate-800 lg:col-span-2 flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-400 mb-4 font-mono flex items-center gap-2">
                                    <span class="w-3 h-3 bg-red-500 rounded-full inline-block"></span>
                                    <span class="w-3 h-3 bg-yellow-500 rounded-full inline-block"></span>
                                    <span class="w-3 h-3 bg-green-500 rounded-full inline-block"></span>
                                    terminal_output.log
                                </h3>
                                
                                <div class="bg-slate-950 p-4 rounded-2xl font-mono text-xs text-blue-400 h-[220px] overflow-y-auto border border-slate-850 whitespace-pre-wrap leading-relaxed">
                                    {{ terminalLogs }}
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center text-[10px] text-slate-500 font-mono mt-3">
                                <span>PAGER=cat</span>
                                <button @click="terminalLogs = 'Consola limpia.\n'" class="hover:text-slate-300 font-bold">Limpiar logs</button>
                            </div>
                        </div>

                    </div>

                    <!-- Fila 3: Carga de Archivos ZIP y Historial de Backups -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Panel de Subida de ZIP de Parche -->
                        <div class="bg-white p-6 rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 lg:col-span-1">
                            <h3 class="text-lg font-black text-slate-800 mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                Despliegue de Cambios ZIP
                            </h3>
                            <p class="text-xs text-slate-500 mb-6">Arrastra o selecciona el ZIP generado por tu sistema de cambios local. Se instalará automáticamente.</p>
                            
                            <!-- Dropzone -->
                            <div 
                                @dragover="onDragOver"
                                @dragleave="onDragLeave"
                                @drop="onDrop"
                                :class="arrastrandoZip ? 'border-red-500 bg-red-50/50' : 'border-slate-200 hover:border-slate-300'"
                                class="border-2 border-dashed p-8 rounded-2xl text-center cursor-pointer transition-all bg-slate-50/50"
                                @click="$refs.fileInput.click()"
                            >
                                <input type="file" ref="fileInput" class="hidden" accept=".zip" @change="onFileSelected" />
                                <div class="text-3xl mb-2">📦</div>
                                <p class="text-xs font-bold text-slate-700">Arrastra tu archivo .zip aquí</p>
                                <p class="text-[10px] text-slate-400 mt-1">o haz clic para seleccionar del ordenador</p>
                            </div>

                            <!-- Alerta de seguridad y confirmación -->
                            <div v-if="zipFile" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-2xl">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-amber-800">Listo para aplicar:</span>
                                    <span class="text-xs text-slate-600 font-mono">{{ zipFile.name }} ({{ (zipFile.size / 1024 / 1024).toFixed(2) }} MB)</span>
                                </div>
                                <p class="text-[10px] text-amber-700 mt-2">
                                    ⚠️ Al hacer clic en desplegar se hará un backup de tu código actual y se extraerá el ZIP, aplicando las migraciones automáticamente.
                                </p>
                                <button 
                                    @click="subirYAplicarZip"
                                    :disabled="cargandoAccion"
                                    class="w-full bg-red-600 hover:bg-red-500 text-white font-bold text-xs py-3 rounded-xl mt-4 transition-colors flex items-center justify-center gap-1.5"
                                >
                                    <span v-if="cargandoAccion" class="animate-spin w-3 h-3 border-2 border-white border-t-transparent rounded-full"></span>
                                    Desplegar Parche Ahora
                                </button>
                            </div>
                        </div>

                        <!-- Historial de Backups -->
                        <div class="bg-white p-6 rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 lg:col-span-2">
                            <h3 class="text-lg font-black text-slate-800 mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Historial de Copias de Seguridad (Backups)
                            </h3>
                            <p class="text-xs text-slate-500 mb-6">Listado de copias generadas automáticamente o de forma manual. Puedes restaurar el sistema a cualquier estado anterior.</p>
                            
                            <div class="max-h-[350px] overflow-y-auto space-y-3 pr-2 font-mono">
                                <div v-for="bk in info.backups" :key="bk.filename" 
                                    class="p-4 rounded-2xl border border-slate-100 hover:border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span class="font-bold text-slate-800 block text-xs break-all">{{ bk.filename }}</span>
                                            <span v-if="bk.source === 'root'" class="bg-red-50 text-red-600 text-[9px] font-black px-1.5 py-0.5 rounded uppercase">Parche</span>
                                            <span v-else class="bg-slate-100 text-slate-600 text-[9px] font-black px-1.5 py-0.5 rounded uppercase">Respaldo</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-[10px] text-slate-400 font-semibold mt-1">
                                            <span>📅 {{ bk.created_at }}</span>
                                            <span>💾 {{ bk.size }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                                        <a 
                                            :href="route('despliegue.descargar-backup', bk.filename)"
                                            class="bg-white hover:bg-slate-100 border border-slate-200 text-slate-800 text-[10px] font-bold px-3 py-2 rounded-xl transition-all shadow-sm"
                                        >
                                            Descargar
                                        </a>
                                        <button 
                                            @click="restaurarBackup(bk.filename)"
                                            :disabled="cargandoAccion"
                                            class="bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold px-3 py-2 rounded-xl transition-all shadow-md shadow-red-600/15 active:scale-95 disabled:opacity-50"
                                        >
                                            Restaurar
                                        </button>
                                    </div>
                                </div>
                                <div v-if="info.backups.length === 0" class="text-center py-8 text-slate-400 font-medium italic">
                                    No se encontraron copias de seguridad de código.
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
</style>

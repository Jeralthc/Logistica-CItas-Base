<template>
    <Head title="Configuración de Empresa y Marca" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-slate-100 flex items-center gap-2">
                        <span class="text-3xl">🏢</span> Configuración de Empresa y Marca (White-Label)
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Personaliza el nombre de tu empresa, colores institucionales, logo y centros de distribución.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Formulario de Identidad Corporativa -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🎨</span> Identidad Corporativa
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-slate-400 font-medium">Nombre de la Empresa</label>
                        <input v-model="form.nombre_empresa" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ej: Supermercados Plaza C.A." />
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 font-medium">RIF / Identificación Fiscal</label>
                        <input v-model="form.rif_empresa" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ej: J-12345678-9" />
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 font-medium">Color Primario de Marca</label>
                        <div class="flex items-center gap-2 mt-1">
                            <input v-model="form.color_primario" type="color" class="w-10 h-10 rounded-lg bg-transparent border border-slate-700 cursor-pointer" />
                            <input v-model="form.color_primario" type="text" class="flex-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2 font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 font-medium">Zona Horaria</label>
                        <select v-model="form.zona_horaria" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2">
                            <option value="America/Caracas">America/Caracas (GMT-4)</option>
                            <option value="America/Bogota">America/Bogota (GMT-5)</option>
                            <option value="America/Mexico_City">America/Mexico_City (GMT-6)</option>
                            <option value="America/Santiago">America/Santiago (GMT-4)</option>
                            <option value="America/New_York">America/New_York (EST)</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 font-medium">Correo Electrónico de Notificaciones</label>
                        <input v-model="form.email_contacto" type="email" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="logistica@empresa.com" />
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 font-medium">Teléfono de Soporte</label>
                        <input v-model="form.telefono_contacto" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="+58 212-1234567" />
                    </div>
                </div>

                <div class="flex justify-end pt-3">
                    <button @click="guardarAjustes" class="px-6 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-xs font-bold transition">
                        Guardar Configuración
                    </button>
                </div>
            </div>

            <!-- Centros de Distribución / Almacenes -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>📍</span> Centros de Distribución y Almacenes
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Define los galpones y la cantidad de muelles disponibles en cada sede.</p>
                    </div>
                    <button @click="modalAlmacen = true" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-lg transition">
                        + Agregar Almacén
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div v-for="alm in almacenes" :key="alm.id" class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-white text-sm">{{ alm.nombre }}</h4>
                            <span class="font-mono text-xs text-sky-400 bg-sky-950/60 border border-sky-800/40 px-2 py-0.5 rounded">{{ alm.codigo }}</span>
                        </div>
                        <p class="text-xs text-slate-400">{{ alm.direccion || 'Sin dirección registrada' }}</p>
                        <div class="text-xs font-semibold text-amber-400 pt-2 border-t border-slate-800">
                            {{ alm.muelles_totales }} Muelles Activos
                        </div>
                    </div>

                    <div v-if="almacenes.length === 0" class="col-span-3 text-center py-6 text-slate-500 text-xs">
                        No hay almacenes personalizados registrados. Se utiliza la configuración por defecto.
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Nuevo Almacén -->
        <div v-if="modalAlmacen" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    <span>📍</span> Nuevo Centro de Distribución
                </h3>

                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-slate-400 font-medium">Nombre de Sede / Almacén</label>
                        <input v-model="formAlmacen.nombre" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ej: CD Principal Caracas" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 font-medium">Código Único</label>
                            <input v-model="formAlmacen.codigo" type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2 uppercase" placeholder="CD-CCS" />
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 font-medium">Muelles Totales</label>
                            <input v-model="formAlmacen.muelles_totales" type="number" min="1" max="50" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 font-medium">Dirección Física</label>
                        <textarea v-model="formAlmacen.direccion" rows="2" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="Ubicación..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                    <button @click="modalAlmacen = false" class="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white">Cancelar</button>
                    <button @click="guardarAlmacen" class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-xs font-bold transition">Crear Almacén</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    settings: Object,
    almacenes: Array,
});

const form = ref({
    nombre_empresa: props.settings?.nombre_empresa || 'Sistema de Gestión Logística',
    rif_empresa: props.settings?.rif_empresa || '',
    color_primario: props.settings?.color_primario || '#0284c7',
    zona_horaria: props.settings?.zona_horaria || 'America/Caracas',
    moneda: props.settings?.moneda || 'USD',
    email_contacto: props.settings?.email_contacto || '',
    telefono_contacto: props.settings?.telefono_contacto || '',
});

const modalAlmacen = ref(false);
const formAlmacen = ref({
    nombre: '',
    codigo: '',
    muelles_totales: 4,
    direccion: '',
});

const guardarAjustes = async () => {
    try {
        await axios.post('/api/configuracion-empresa', form.value);
        alert('Configuración guardada exitosamente.');
    } catch (e) {
        alert('Error al guardar: ' + (e.response?.data?.message || e.message));
    }
};

const guardarAlmacen = async () => {
    try {
        await axios.post('/api/configuracion-empresa/almacenes', formAlmacen.value);
        modalAlmacen.value = false;
        router.reload();
    } catch (e) {
        alert('Error al crear almacén: ' + (e.response?.data?.message || e.message));
    }
};
</script>
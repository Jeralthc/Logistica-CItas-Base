<template>
    <Head title="Conectores ERP Universales" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-slate-100 flex items-center gap-2">
                        <span class="text-3xl">🔌</span> Conectores ERP Universales
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Conecta cualquier ERP (SAP, Odoo, Profit Plus, Excel o API REST) para sincronización automática de compras.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Pestañas de Métodos de Integración -->
            <div class="flex border-b border-slate-800 gap-6">
                <button 
                    @click="tab = 'excel'"
                    :class="['pb-3 text-sm font-semibold flex items-center gap-2 transition border-b-2', tab === 'excel' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <span>📊</span> Importador Masivo (Excel / CSV)
                </button>
                <button 
                    @click="tab = 'api'"
                    :class="['pb-3 text-sm font-semibold flex items-center gap-2 transition border-b-2', tab === 'api' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <span>🌐</span> API REST Abierta (SAP, Odoo, Dynamics)
                </button>
                <button 
                    @click="tab = 'db'"
                    :class="['pb-3 text-sm font-semibold flex items-center gap-2 transition border-b-2', tab === 'db' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <span>🗄️</span> Conector Directo SQL (Profit / Saint / Custom)
                </button>
            </div>

            <!-- TAB 1: IMPORTADOR EXCEL / CSV -->
            <div v-if="tab === 'excel'" class="space-y-6">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <span>📥</span> Carga Rápida de Órdenes de Compra
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Arrastra o selecciona tu archivo CSV con las órdenes de compra emitidas hoy para habilitar citas a tus proveedores.
                            </p>
                        </div>
                        <a 
                            href="/api/conectores-erp/plantilla" 
                            download
                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-sky-400 text-xs font-semibold rounded-lg border border-slate-700 flex items-center gap-1.5 transition"
                        >
                            <span>📥</span> Descargar Plantilla Modelo (.csv)
                        </a>
                    </div>

                    <!-- Dropzone -->
                    <div 
                        @dragover.prevent
                        @drop.prevent="manejarDrop"
                        class="border-2 border-dashed border-slate-700 hover:border-sky-500 rounded-2xl p-8 text-center transition cursor-pointer bg-slate-950/40"
                        @click="$refs.fileInput.click()"
                    >
                        <input type="file" ref="fileInput" @change="manejarArchivo" accept=".csv,.txt" class="hidden" />
                        <div class="text-4xl mb-2">📁</div>
                        <p class="text-sm font-semibold text-slate-200">
                            {{ archivoSeleccionado ? archivoSeleccionado.name : 'Haz clic o arrastra aquí tu archivo CSV' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Formato soportado: .CSV delimitado por comas o punto y coma (Máx 10 MB)</p>
                    </div>

                    <div v-if="archivoSeleccionado" class="flex justify-end">
                        <button 
                            @click="subirArchivo"
                            :disabled="cargando"
                            class="px-6 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-xs font-bold transition flex items-center gap-2 disabled:opacity-50"
                        >
                            <span v-if="cargando">⏳ Procesando...</span>
                            <span v-else>🚀 Procesar e Importar ODCs</span>
                        </button>
                    </div>

                    <!-- Resultado de Importación -->
                    <div v-if="resultadoImportacion" class="p-4 rounded-xl bg-emerald-950/50 border border-emerald-800/60 text-emerald-300 text-xs space-y-1">
                        <div class="font-bold text-sm">✅ {{ resultadoImportacion.message }}</div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: API REST ABIERTA -->
            <div v-if="tab === 'api'" class="space-y-6">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <span>🔑</span> Claves de Acceso API (API Keys)
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Genera tokens seguros para que tus sistemas externos envíen órdenes en tiempo real mediante HTTP POST.
                            </p>
                        </div>
                        <button 
                            @click="nuevaApiKey"
                            class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5"
                        >
                            <span>+</span> Nueva API Key
                        </button>
                    </div>

                    <!-- Lista de API Keys -->
                    <div class="border border-slate-800 rounded-xl overflow-hidden">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950/80 text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="p-3">Sistema / Cliente</th>
                                    <th class="p-3">API Key</th>
                                    <th class="p-3">Último Uso</th>
                                    <th class="p-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <tr v-for="key in apiKeys" :key="key.id" class="hover:bg-slate-800/30">
                                    <td class="p-3 font-semibold text-white">{{ key.nombre_cliente }}</td>
                                    <td class="p-3 font-mono text-sky-400">{{ key.api_key }}</td>
                                    <td class="p-3 text-slate-400">{{ key.ultimo_uso || 'Nunca' }}</td>
                                    <td class="p-3 text-right">
                                        <button @click="eliminarKey(key.id)" class="text-red-400 hover:text-red-300 font-bold">Revocar</button>
                                    </td>
                                </tr>
                                <tr v-if="apiKeys.length === 0">
                                    <td colspan="4" class="p-6 text-center text-slate-500">No hay API Keys generadas. Crea una para integrar SAP u Odoo.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Documentación Interactiva de la API -->
                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-mono font-bold text-emerald-400">POST /api/v1/erp/ordenes</span>
                            <span class="text-[10px] text-slate-500 font-mono">Header: X-ERP-API-KEY</span>
                        </div>
                        <p class="text-xs text-slate-400">Ejemplo de Payload JSON para enviar desde SAP / Odoo / Python:</p>
                        <pre class="bg-slate-900 p-3 rounded-lg text-[11px] font-mono text-slate-300 overflow-x-auto border border-slate-800">
{
  "ordenes": [
    {
      "numero_oc": "ODC-98765",
      "proveedor": "Distribuidora Internacional S.A.",
      "rif_proveedor": "J-12345678-0",
      "fecha_emision": "2026-09-22",
      "monto_total": 4500.00,
      "tipo_mercancia": "viveres"
    }
  ]
}</pre>
                    </div>
                </div>
            </div>

            <!-- TAB 3: CONECTOR SQL DIRECTO -->
            <div v-if="tab === 'db'" class="space-y-6">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🗄️</span> Conexión Directa a Base de Datos ERP
                    </h3>
                    <p class="text-xs text-slate-400">
                        Configura la conexión directa por red local o VPN hacia tu servidor SQL Server, MySQL o PostgreSQL.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs text-slate-400 font-medium">Motor de BD</label>
                            <select class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2">
                                <option value="sqlsrv">Microsoft SQL Server (Profit Plus / SAP B1)</option>
                                <option value="mysql">MySQL / MariaDB (Saint / Odoo)</option>
                                <option value="pgsql">PostgreSQL</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 font-medium">Host / Servidor IP</label>
                            <input type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="192.168.1.50" />
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 font-medium">Nombre de Base de Datos</label>
                            <input type="text" class="w-full mt-1 bg-slate-800 border-slate-700 rounded-lg text-sm text-white px-3 py-2" placeholder="PROFIT_ADMIN" />
                        </div>
                    </div>

                    <div class="flex justify-end pt-3">
                        <button class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-xs font-bold transition">
                            Probar Conexión y Guardar
                        </button>
                    </div>
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
    configuraciones: Array,
    apiKeys: Array,
    totalSincronizadas: Number,
});

const tab = ref('excel');
const archivoSeleccionado = ref(null);
const cargando = ref(false);
const resultadoImportacion = ref(null);

const manejarArchivo = (e) => {
    archivoSeleccionado.value = e.target.files[0];
};

const manejarDrop = (e) => {
    if (e.dataTransfer.files.length) {
        archivoSeleccionado.value = e.dataTransfer.files[0];
    }
};

const subirArchivo = async () => {
    if (!archivoSeleccionado.value) return;
    cargando.value = true;
    resultadoImportacion.value = null;

    const formData = new FormData();
    formData.append('archivo', archivoSeleccionado.value);

    try {
        const res = await axios.post('/api/conectores-erp/importar-excel', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        resultadoImportacion.value = res.data;
        archivoSeleccionado.value = null;
    } catch (e) {
        alert('Error al importar: ' + (e.response?.data?.error || e.message));
    } finally {
        cargando.value = false;
    }
};

const nuevaApiKey = async () => {
    const nombre = prompt('Nombre identificador para esta clave (ej: "SAP Producción", "Script Compras"):');
    if (!nombre) return;
    try {
        const res = await axios.post('/api/conectores-erp/api-keys', { nombre_cliente: nombre });
        alert(`API Key Generada con éxito:\n\n${res.data.api_key}\n\nGuárdala en un lugar seguro.`);
        router.reload();
    } catch (e) {
        alert('Error al generar API Key');
    }
};

const eliminarKey = async (id) => {
    if (!confirm('¿Estás seguro de revocar esta API Key? Los sistemas que la utilicen perderán acceso.')) return;
    try {
        await axios.delete(`/api/conectores-erp/api-keys/${id}`);
        router.reload();
    } catch (e) {
        alert('Error al revocar API Key');
    }
};
</script>
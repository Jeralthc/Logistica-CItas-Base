<template>
    <Head title="Dashboard Ejecutivo de KPIs Logísticos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-slate-100 flex items-center gap-2">
                        <span class="text-3xl">📊</span> Dashboard Ejecutivo de KPIs Logísticos
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Métricas de desempeño de muelle, tiempos de estadía (Dwell Time), OTIF y Scorecard de proveedores.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <select 
                        v-model="rango" 
                        @change="cambiarRango"
                        class="bg-slate-800 text-slate-200 border border-slate-700 text-sm rounded-lg px-3 py-2 focus:ring-sky-500 focus:border-sky-500"
                    >
                        <option value="hoy">Hoy</option>
                        <option value="semana">Esta Semana</option>
                        <option value="mes">Este Mes</option>
                        <option value="todo">Histórico Completo</option>
                    </select>
                    <button 
                        @click="imprimirReporte"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-semibold flex items-center gap-2 transition"
                    >
                        <span>🖨️</span> Imprimir Reporte
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- KPIs Principales -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- OTIF -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cumplimiento OTIF</span>
                        <span class="text-xs font-bold text-emerald-400 bg-emerald-950/60 border border-emerald-800/40 px-2 py-0.5 rounded">
                            Objetivo: 95%
                        </span>
                    </div>
                    <div class="text-3xl font-extrabold text-white">
                        {{ metricas.otif_porcentaje }}%
                    </div>
                    <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div 
                            class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                            :style="{ width: `${metricas.otif_porcentaje}%` }"
                        ></div>
                    </div>
                    <p class="text-[11px] text-slate-500">Citas completadas en tiempo y forma.</p>
                </div>

                <!-- Dwell Time -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Dwell Time Promedio</span>
                        <span class="text-xs font-bold text-sky-400 bg-sky-950/60 border border-sky-800/40 px-2 py-0.5 rounded">
                            Muelle
                        </span>
                    </div>
                    <div class="text-3xl font-extrabold text-white">
                        {{ metricas.dwell_time_promedio }} <span class="text-lg font-normal text-slate-400">min</span>
                    </div>
                    <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div class="bg-sky-500 h-full rounded-full w-3/4"></div>
                    </div>
                    <p class="text-[11px] text-slate-500">Tiempo medio de descarga por camión.</p>
                </div>

                <!-- Citas Totales -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Citas</span>
                        <span class="text-2xl">📦</span>
                    </div>
                    <div class="text-3xl font-extrabold text-white">
                        {{ metricas.total_citas }}
                    </div>
                    <div class="flex gap-2 text-[11px] text-slate-400 pt-1">
                        <span class="text-emerald-400 font-bold">✅ {{ metricas.completadas }} listos</span>
                        <span>·</span>
                        <span class="text-amber-400 font-bold">⏳ {{ metricas.pendientes }} pend.</span>
                    </div>
                </div>

                <!-- No Shows / Canceladas -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Canceladas / Fallidas</span>
                        <span class="text-2xl">⚠️</span>
                    </div>
                    <div class="text-3xl font-extrabold text-red-400">
                        {{ metricas.canceladas }}
                    </div>
                    <p class="text-[11px] text-slate-500">Citas no presentadas o canceladas por proveedor.</p>
                </div>
            </div>

            <!-- Gráfico de Ocupación por Muelle y Scorecard -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Ocupación de Muelles -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🏭</span> Ocupación por Muelle
                    </h3>
                    <div class="space-y-3">
                        <div v-for="m in ocupacionMuelles" :key="m.muelle" class="space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-slate-300">{{ m.muelle }}</span>
                                <span class="text-sky-400">{{ m.total }} camiones</span>
                            </div>
                            <div class="w-full bg-slate-800 h-2.5 rounded-full overflow-hidden flex">
                                <div 
                                    class="bg-sky-500 h-full rounded-full"
                                    :style="{ width: `${metricas.total_citas > 0 ? (m.total / metricas.total_citas) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>
                        <div v-if="ocupacionMuelles.length === 0" class="text-center text-xs text-slate-500 py-6">
                            No hay datos de muelles en este período.
                        </div>
                    </div>
                </div>

                <!-- Scorecard / Ranking de Proveedores -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>⭐</span> Scorecard / Ranking de Proveedores
                        </h3>
                        <span class="text-xs text-slate-400">Top 15 por volumen</span>
                    </div>

                    <div class="border border-slate-800 rounded-xl overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950/80 text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="p-3">Proveedor</th>
                                    <th class="p-3">Citas</th>
                                    <th class="p-3">Completadas</th>
                                    <th class="p-3">% Efectividad</th>
                                    <th class="p-3 text-right">Calificación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <tr v-for="p in proveedoresRanking" :key="p.proveedor" class="hover:bg-slate-800/30">
                                    <td class="p-3">
                                        <div class="font-bold text-slate-100">{{ p.proveedor }}</div>
                                        <div class="text-[10px] font-mono text-slate-500">{{ p.rif }}</div>
                                    </td>
                                    <td class="p-3 font-semibold text-white">{{ p.citas_totales }}</td>
                                    <td class="p-3 text-emerald-400 font-semibold">{{ p.citas_completadas }}</td>
                                    <td class="p-3">
                                        <span :class="p.tasa_efectividad >= 80 ? 'text-emerald-400' : 'text-amber-400'" class="font-bold">
                                            {{ p.tasa_efectividad }}%
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <span class="text-amber-400">
                                            {{ '⭐'.repeat(p.calificacion) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="proveedoresRanking.length === 0">
                                    <td colspan="5" class="p-6 text-center text-slate-500">No hay datos de proveedores para este período.</td>
                                </tr>
                            </tbody>
                        </table>
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

const props = defineProps({
    metricas: Object,
    proveedoresRanking: Array,
    ocupacionMuelles: Array,
    rangoSeleccionado: String,
});

const rango = ref(props.rangoSeleccionado);

const cambiarRango = () => {
    router.get('/kpis-logistica', { rango: rango.value }, { preserveState: true });
};

const imprimirReporte = () => {
    window.print();
};
</script>
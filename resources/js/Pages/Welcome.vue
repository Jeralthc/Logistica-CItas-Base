<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Bienvenido al Sistema de Logística" />

    <div class="bg-red-600 text-white text-sm md:text-base py-3 px-8 flex flex-col md:flex-row justify-between items-center z-20 relative font-medium fixed top-0 w-full shadow-md gap-2 md:gap-0">
        <div class="flex-1 text-left">
            <span>📞 0424-7170326</span>
        </div>
        <div class="flex-1 text-center">
            <span>✉️ contacto@tuempresa.com</span>
        </div>
        <div class="flex-1 text-right">
            <span>🕒 Horario: 8:00 a.m. a 6:00 p.m.</span>
        </div>
    </div>

    <div class="min-h-screen bg-slate-950 flex flex-col justify-between items-center relative overflow-hidden text-white pt-24">
        <div class="absolute top-0 left-0 w-full h-full z-0 opacity-10">
            <div class="absolute -top-1/4 -right-1/4 w-full h-full rounded-full bg-gradient-to-b from-red-600 to-transparent blur-3xl"></div>
            <div class="absolute -bottom-1/4 -left-1/4 w-full h-full rounded-full bg-gradient-to-t from-red-800 to-transparent blur-3xl"></div>
        </div>

        <div class="relative z-10 text-center px-6 sm:px-8 max-w-6xl mx-auto flex-grow flex flex-col items-center justify-center pb-12">
            
            <div class="mb-10 p-4 bg-slate-900 border-2 border-slate-800 rounded-3xl shadow-2xl shadow-slate-900/50 transform hover:scale-105 transition-transform duration-300 flex items-center justify-center gap-4">
                <div class="bg-white p-2 rounded-xl">
                    <img src="/images/logo.png" alt="Logo Empresa Base" class="w-16 h-16 object-contain" />
                </div>
                <div class="text-left pr-4">
                   <h2 class="text-4xl font-black tracking-tighter text-white uppercase">Empresa Base</h2>
                   <p class="text-[11px] uppercase font-black tracking-[0.3em] text-red-500">Logística</p>
                </div>
            </div>

            <h1 class="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-6 drop-shadow-lg">
                Portal de Citas y Recepción
            </h1>
            
            <p class="mt-4 text-lg md:text-xl text-slate-300 max-w-2xl mx-auto mb-12 leading-relaxed font-light">
                Bienvenido a tu plataforma de logística. Aquí podrás agendar tus entregas y mantener todo organizado de la forma más rápida y sencilla.
            </p>

            <div v-if="canLogin" class="flex flex-col sm:flex-row gap-5 justify-center mt-6">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="px-10 py-4 text-lg font-semibold text-white bg-red-600 rounded-xl shadow-lg shadow-red-500/30 hover:bg-red-500 hover:shadow-red-500/50 transition-all duration-300 hover:-translate-y-1"
                >
                    Ir al Panel de Control
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="px-10 py-4 text-lg font-semibold text-white bg-red-600 rounded-xl shadow-lg shadow-red-500/30 hover:bg-red-500 hover:shadow-red-500/50 transition-all duration-300 hover:-translate-y-1"
                    >
                        Iniciar Sesión
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="px-10 py-4 text-lg font-semibold text-red-500 bg-transparent border-2 border-red-600 rounded-xl hover:bg-red-950 hover:text-white transition-all duration-300 hover:-translate-y-1"
                    >
                        Registrar Usuario
                    </Link>
                </template>
            </div>
        </div>

        <!-- Corporate Footer -->
        <footer class="w-full bg-slate-900 text-slate-400 py-10 mt-auto border-t-[4px] border-red-600 z-10">
            <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-white font-bold text-lg mb-4 flex items-center gap-2">
                        <img src="/images/logo.png" alt="Logo" class="w-6 h-6 grayscale brightness-200" />
                        Empresa Base LOGÍSTICA
                    </h3>
                    <p class="text-sm leading-relaxed max-w-sm">
                        Sistema integral para la gestión de citas, recepción de mercancía y optimización de tiempos en andén.
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase text-sm tracking-wider">Soporte Técnico</h4>
                    <ul class="space-y-2 text-sm">
                        <li>📞 0424-7475109</li>
                        <li>✉️ soporte@tuempresa.com</li>
                        <li>🕒 Lunes a Lunes: 7:00 am - 10:00 pm</li>
                    </ul>
                </div>
            </div>
            <div class="max-w-6xl mx-auto px-6 mt-10 pt-6 border-t border-slate-800 text-xs text-center text-slate-500">
                &copy; {{ new Date().getFullYear() }} Portal Logístico Empresa Base. Todos los derechos reservados. <br>
                Desarrollado por el Departamento de Sistemas.
            </div>
        </footer>
    </div>
</template>
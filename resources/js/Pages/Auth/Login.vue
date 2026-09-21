<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión - Portal Logístico" />

    <div class="bg-red-600 text-white text-sm md:text-base py-3 px-8 flex flex-col md:flex-row justify-between items-center z-20 relative font-medium fixed top-0 w-full shadow-md gap-2 md:gap-0">
        <div class="flex-1 text-left">
            <span>📞 0424-7170326</span>
        </div>
        <div class="flex-1 text-center">
            <span>✉️ hipersurakica@gmail.com</span>
        </div>
        <div class="flex-1 text-right">
            <span>🕒 Horario: 8:00 a.m. a 6:00 p.m.</span>
        </div>
    </div>

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 pt-24 flex flex-col items-center justify-between relative overflow-hidden">
        
        <!-- Decoración Corporativa Suave -->
        <div class="absolute top-0 left-0 w-full h-[40vh] bg-slate-900 z-0" style="clip-path: polygon(0 0, 100% 0, 100% 100%, 0 80%);"></div>

        <div class="w-full flex-grow flex flex-col items-center justify-center z-10 px-4 pb-12">
            <!-- Header del Formulario -->
            <div class="text-center mb-8 flex flex-col items-center">
                <div class="p-4 bg-white rounded-3xl shadow-lg flex items-center justify-center gap-3 border border-slate-100">
                    <img src="/images/logo_suraki.ico" alt="Logo Suraki" class="w-12 h-12 object-contain" />
                    <div class="text-left pr-4">
                       <h2 class="text-3xl font-black tracking-tighter text-slate-800 uppercase">SURAKI</h2>
                       <p class="text-[9px] uppercase font-black tracking-[0.3em] text-red-600">Logística</p>
                    </div>
                </div>
                <h2 class="text-3xl font-bold text-white mt-6 tracking-tight drop-shadow-md">
                    Portal de Citas y Recepción
                </h2>
                <p class="text-slate-300 mt-2 text-lg font-medium">Acceso Seguro</p>
            </div>
        <div class="w-full max-w-md bg-white p-10 border border-slate-200/60 rounded-3xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.15)] mb-10">
            <form @submit.prevent="submit">
                
                <div>
                    <InputLabel for="username" value="Usuario / RIF" class="font-semibold text-slate-800" />
                    <TextInput
                        id="username"
                        type="text"
                        class="mt-2 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/20 rounded-xl shadow-sm px-4 py-3 bg-white/50 transition-all hover:bg-white"
                        v-model="form.username"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="Ej: Recepcion.Romulo o J-12345678-9"
                    />
                    <InputError class="mt-2" :message="form.errors.username" />
                </div>

                <div class="mt-6">
                    <InputLabel for="password" value="Contraseña" class="font-semibold text-slate-800" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-2 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/20 rounded-xl shadow-sm px-4 py-3 bg-white/50 transition-all hover:bg-white"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="block mt-6 flex items-center justify-between">
                    <label class="flex items-center">
                        <Checkbox name="remember" v-model:checked="form.remember" class="border-slate-300 text-red-600 focus:ring-red-600" />
                        <span class="ms-2 text-sm text-slate-600">Recordarme</span>
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm text-red-600 hover:text-red-700 font-medium"
                    >
                        ¿Olvidaste tu contraseña?
                    </Link>
                </div>

                <div class="mt-8 flex flex-col gap-4 items-center">
                    <PrimaryButton 
                        class="w-full justify-center text-lg py-4 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 rounded-2xl shadow-lg shadow-red-600/30 transition-all duration-300 hover:shadow-red-600/50 hover:-translate-y-0.5"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        INGRESAR
                    </PrimaryButton>

                    <Link :href="route('register')" class="text-sm text-slate-600 hover:text-red-600 font-medium">
                        ¿No tienes una cuenta? <span class="text-red-600">Regístrate</span>
                    </Link>

                    <!-- Acceso y Descarga de Manual de Usuario para Proveedores -->
                    <div class="w-full pt-4 border-t border-slate-100 flex flex-col items-center mt-2">
                        <p class="text-xs text-slate-500 mb-2 font-medium">¿Eres proveedor y necesitas orientación?</p>
                        <a 
                            href="/Manual_Usuario_Portal_Proveedor_CITSUR.pdf" 
                            target="_blank"
                            class="w-full flex items-center justify-center gap-2.5 py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white rounded-2xl text-xs font-bold transition-all shadow-md hover:shadow-lg active:scale-98 group"
                            title="Descargar Manual de Usuario Oficial en PDF"
                        >
                            <svg class="w-4 h-4 text-red-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span>Descargar Manual de Usuario (PDF)</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
        </div>

        <!-- Corporate Footer -->
        <footer class="w-full bg-slate-900 text-slate-400 py-10 mt-auto border-t-[4px] border-red-600 z-10">
            <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-white font-bold text-lg mb-4 flex items-center gap-2">
                        <img src="/images/logo_suraki.ico" alt="Logo" class="w-6 h-6 grayscale brightness-200" />
                        SURAKI LOGÍSTICA
                    </h3>
                    <p class="text-sm leading-relaxed max-w-sm">
                        Sistema integral para la gestión de citas, recepción de mercancía y optimización de tiempos en andén.
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase text-sm tracking-wider">Soporte Técnico</h4>
                    <ul class="space-y-2 text-sm">
                        <li>📞 0424-7475109</li>
                        <li>✉️ sistemassuraki@gmail.com</li>
                        <li>🕒 Lunes a Lunes: 7:00 am - 10:00 pm</li>
                    </ul>
                </div>
            </div>
            <div class="max-w-6xl mx-auto px-6 mt-10 pt-6 border-t border-slate-800 text-xs text-center text-slate-500">
                &copy; {{ new Date().getFullYear() }} Portal Logístico Suraki. Todos los derechos reservados. <br>
                Desarrollado por el Departamento de Sistemas de Suraki.
            </div>
        </footer>
    </div>
</template>
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    rif: String,
    email: String,
    name: String,
});

const form = useForm({
    name: props.name || 'Proveedor Externo',
    username: props.rif || '',
    email: props.email || '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('proveedor.setup'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Activación de Cuenta - Portal Logístico" />

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

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 pt-24 flex flex-col items-center justify-between relative overflow-hidden">
        
        <!-- Decoración Corporativa Suave -->
        <div class="absolute top-0 left-0 w-full h-[40vh] bg-slate-900 z-0" style="clip-path: polygon(0 0, 100% 0, 100% 100%, 0 80%);"></div>

        <div class="w-full flex-grow flex flex-col items-center justify-center z-10 px-4 pb-12">
            <!-- Header del Formulario -->
            <div class="text-center mb-8 flex flex-col items-center">
                <div class="p-4 bg-white rounded-3xl shadow-lg flex items-center justify-center gap-3 border border-slate-100">
                    <img src="/images/logo.png" alt="Logo Empresa Base" class="w-12 h-12 object-contain" />
                    <div class="text-left pr-4">
                       <h2 class="text-3xl font-black tracking-tighter text-slate-800 uppercase">Empresa Base</h2>
                       <p class="text-[9px] uppercase font-black tracking-[0.3em] text-red-600">Logística</p>
                    </div>
                </div>
                <h2 class="text-3xl font-bold text-white mt-6 tracking-tight drop-shadow-md">
                    Activación de Cuenta
                </h2>
                <p class="text-slate-300 mt-2 text-lg font-medium">Establece tu contraseña de acceso</p>
            </div>
            <div class="w-full max-w-md bg-white p-10 border border-slate-200/60 rounded-3xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.15)] mb-10">
            <form @submit.prevent="submit">
                
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6">
                    <p class="text-sm text-slate-500 mb-1">Empresa / RIF Asignado:</p>
                    <p class="text-lg font-bold text-slate-800">{{ form.username || 'No especificado' }}</p>
                    <p class="text-sm text-slate-500 mb-1 mt-3">Correo Electrónico:</p>
                    <p class="text-md font-medium text-slate-700">{{ form.email || 'No especificado' }}</p>
                    <p class="text-xs text-slate-400 mt-2">Este será tu usuario para iniciar sesión.</p>
                </div>

                <!-- Hidden inputs para enviar al controlador -->
                <input type="hidden" v-model="form.email" />
                <input type="hidden" v-model="form.name" />
                <input type="hidden" v-model="form.username" />

                <div class="mt-5">
                    <InputLabel for="password" value="Contraseña" class="font-semibold text-slate-800" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/30 rounded-lg shadow-sm"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mt-5">
                    <InputLabel for="password_confirmation" value="Confirmar Contraseña" class="font-semibold text-slate-800" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/30 rounded-lg shadow-sm"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Repita la contraseña"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div class="mt-8 flex flex-col gap-5 items-center">
                    <PrimaryButton 
                        class="w-full justify-center text-lg py-4 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 rounded-2xl shadow-lg shadow-red-600/30 transition-all duration-300 hover:shadow-red-600/50 hover:-translate-y-0.5"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        REGISTRAR USUARIO
                    </PrimaryButton>

                    <Link
                        :href="route('login')"
                        class="text-sm text-slate-600 hover:text-red-600 font-medium transition-colors"
                    >
                        ¿Ya tienes una cuenta? <span class="text-red-600 font-bold underline decoration-2 underline-offset-2">Inicia Sesión</span>
                    </Link>
                </div>
            </form>
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
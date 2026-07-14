<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Restablecer Contraseña - Portal Logístico" />

    <div class="bg-indigo-600 text-white text-sm md:text-base py-3 px-8 flex flex-col md:flex-row justify-between items-center z-20 relative font-medium fixed top-0 w-full shadow-md gap-2 md:gap-0">
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
                       <p class="text-[9px] uppercase font-black tracking-[0.3em] text-indigo-600">Logística</p>
                    </div>
                </div>
                <h2 class="text-3xl font-bold text-white mt-6 tracking-tight drop-shadow-md">
                    Restablecer Contraseña
                </h2>
                <p class="text-slate-300 mt-2 text-lg font-medium">Crea una nueva contraseña segura</p>
            </div>

            <div class="w-full max-w-md bg-white p-10 border border-slate-200/60 rounded-3xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.15)] mb-10">
                <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Correo Electrónico" class="font-semibold text-slate-800" />

                        <TextInput
                            id="email"
                            type="email"
                            class="mt-2 block w-full border-slate-200 focus:border-indigo-600 focus:ring-indigo-600/20 rounded-xl shadow-sm px-4 py-3 bg-slate-100 text-slate-500"
                            v-model="form.email"
                            required
                            readonly
                            autocomplete="username"
                        />

                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="password" value="Nueva Contraseña" class="font-semibold text-slate-800" />

                        <TextInput
                            id="password"
                            type="password"
                            class="mt-2 block w-full border-slate-200 focus:border-indigo-600 focus:ring-indigo-600/20 rounded-xl shadow-sm px-4 py-3 bg-white/50 transition-all hover:bg-white"
                            v-model="form.password"
                            required
                            autofocus
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                            title="La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas, números y símbolos especiales."
                            autocomplete="new-password"
                            placeholder="Mínimo 8 caracteres, números y símbolos"
                        />

                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="mt-6">
                        <InputLabel
                            for="password_confirmation"
                            value="Confirmar Contraseña"
                            class="font-semibold text-slate-800"
                        />

                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-2 block w-full border-slate-200 focus:border-indigo-600 focus:ring-indigo-600/20 rounded-xl shadow-sm px-4 py-3 bg-white/50 transition-all hover:bg-white"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />

                        <InputError
                            class="mt-2"
                            :message="form.errors.password_confirmation"
                        />
                    </div>

                    <div class="mt-8">
                        <PrimaryButton
                            class="w-full justify-center text-lg py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 rounded-2xl shadow-lg shadow-indigo-600/30 transition-all duration-300 hover:shadow-indigo-600/50 hover:-translate-y-0.5"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            RESTABLECER CONTRASEÑA
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Corporate Footer -->
        <footer class="w-full bg-slate-900 text-slate-400 py-10 mt-auto border-t-[4px] border-indigo-600 z-10">
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

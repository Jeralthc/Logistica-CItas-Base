<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    name: '',
    username: '',
    email: '',
    cargo: '',
    password: '',
    password_confirmation: '',
});

const showPasswordRules = ref(false);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Registro - Portal Logístico" />

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
                <p class="text-slate-300 mt-2 text-lg font-medium">Crear una Cuenta Nueva</p>
            </div>
            <div class="w-full max-w-md bg-white p-10 border border-slate-200/60 rounded-3xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.15)] mb-10">
            <form @submit.prevent="submit">
                
                <div>
                    <InputLabel for="name" value="Nombre Completo" class="font-semibold text-slate-800" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/30 rounded-lg shadow-sm"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Ej: Juan Pérez"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-5">
                    <InputLabel for="username" value="Usuario / RIF" class="font-semibold text-slate-800" />
                    <TextInput
                        id="username"
                        type="text"
                        class="mt-1 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/30 rounded-lg shadow-sm"
                        v-model="form.username"
                        required
                        autocomplete="username"
                        placeholder="Ej: Recepcion.Romulo o J-12345678-9"
                    />
                    <InputError class="mt-2" :message="form.errors.username" />
                </div>

                <div class="mt-5">
                    <InputLabel for="email" value="Correo Electrónico (Opcional)" class="font-semibold text-slate-800" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/30 rounded-lg shadow-sm"
                        v-model="form.email"
                        autocomplete="email"
                        placeholder="ejemplo@correo.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
                <div class="mt-5">
                    <InputLabel for="cargo" value="Cargo en la Empresa" class="font-semibold text-slate-800" />
                    <select
                        id="cargo"
                        v-model="form.cargo"
                        class="mt-1 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/30 rounded-lg shadow-sm"
                        required
                    >
                        <option value="" disabled>Seleccione su perfil / cargo...</option>
                        <option value="comprador">Departamento de Compras</option>
                        <option value="proveedor">Proveedor Externo</option>
                        <option value="receptor">Personal de Recepción (Muelle)</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.cargo" />
                </div>

                <div class="mt-5 relative">
                    <InputLabel for="password" value="Contraseña" class="font-semibold text-slate-800" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full border-slate-200 focus:border-red-600 focus:ring-red-600/30 rounded-lg shadow-sm"
                        v-model="form.password"
                        required
                        pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres, números y símbolos"
                        @focus="showPasswordRules = true"
                        @blur="showPasswordRules = false"
                    />
                    <!-- Tooltip interactivo de reglas -->
                    <div v-show="showPasswordRules" class="absolute z-20 w-64 p-4 mt-2 text-sm text-slate-700 bg-white border border-slate-200 rounded-xl shadow-2xl right-0 top-full transition-opacity duration-300">
                        <div class="absolute -top-2 right-6 w-4 h-4 bg-white border-t border-l border-slate-200 transform rotate-45"></div>
                        <p class="font-bold mb-3 text-slate-800 border-b pb-2">Tu contraseña debe tener:</p>
                        <ul class="space-y-2">
                            <li class="flex items-center gap-2" :class="form.password.length >= 8 ? 'text-green-600' : 'text-slate-500'">
                                <span v-if="form.password.length >= 8">✅</span><span v-else>❌</span> Al menos 8 caracteres
                            </li>
                            <li class="flex items-center gap-2" :class="/[A-Z]/.test(form.password) ? 'text-green-600' : 'text-slate-500'">
                                <span v-if="/[A-Z]/.test(form.password)">✅</span><span v-else>❌</span> Al menos una mayúscula
                            </li>
                            <li class="flex items-center gap-2" :class="/[a-z]/.test(form.password) ? 'text-green-600' : 'text-slate-500'">
                                <span v-if="/[a-z]/.test(form.password)">✅</span><span v-else>❌</span> Al menos una minúscula
                            </li>
                            <li class="flex items-center gap-2" :class="/\d/.test(form.password) ? 'text-green-600' : 'text-slate-500'">
                                <span v-if="/\d/.test(form.password)">✅</span><span v-else>❌</span> Al menos un número
                            </li>
                            <li class="flex items-center gap-2" :class="/[\W_]/.test(form.password) ? 'text-green-600' : 'text-slate-500'">
                                <span v-if="/[\W_]/.test(form.password)">✅</span><span v-else>❌</span> Al menos un símbolo
                            </li>
                        </ul>
                    </div>
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
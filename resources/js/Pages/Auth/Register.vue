<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const form = useForm({
    name: '',
    username: '',
    email: '',
    cargo: '',
    password: '',
    password_confirmation: '',
});

const isLoaded = ref(false);

onMounted(() => {
    setTimeout(() => {
        isLoaded.value = true;
    }, 100);
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Registro - Portal Logístico" />

    <div class="min-h-screen bg-[#faf9f6] text-[#1c1c1c] font-sans relative overflow-hidden flex flex-col justify-between selection:bg-primary selection:text-white">
        
        <!-- Ambient Background Gradients -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[5%] w-[45%] h-[45%] rounded-full bg-primary/5 blur-[120px]"></div>
            <div class="absolute top-[35%] -right-[10%] w-[35%] h-[50%] rounded-full bg-primary/5 blur-[120px]"></div>
        </div>

        <!-- Header Info Bar -->
        <div class="w-full z-20 transition-all duration-700 ease-out"
             :class="{ 'opacity-100 translate-y-0': isLoaded, 'opacity-0 -translate-y-10': !isLoaded }">
            <div class="mx-auto max-w-7xl px-6 pt-4">
                <div class="flex flex-col sm:flex-row items-center justify-between rounded-2xl bg-white/70 backdrop-blur-xl border border-[#eef0eb] px-6 py-3 shadow-sm gap-3 sm:gap-0">
                    <Link href="/" class="flex items-center gap-2.5 group">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-white shadow-sm transition-transform group-hover:scale-105">
                            <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.656 48.656 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M1.5 12a48.655 48.655 0 00.138 3.662 4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M1.5 12l-3 3m3-3l3 3" />
                            </svg>
                        </div>
                        <span class="text-sm font-extrabold tracking-tight text-[#1c1c1c]">LogiSync</span>
                    </Link>
                    <div class="flex flex-wrap justify-center items-center gap-4 text-[10px] text-[#6c7263] font-bold">
                        <div class="flex items-center gap-1"><span class="text-primary">📞</span> 0424-7170326</div>
                        <div class="flex items-center gap-1"><span class="text-primary">✉️</span> contacto@logisync.com</div>
                        <div class="flex items-center gap-1"><span class="text-primary">🕒</span> Lun - Sáb: 8:00 AM - 6:00 PM</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Center Register Card -->
        <div class="w-full flex-grow flex flex-col items-center justify-center z-10 px-4 py-10">
            
            <div class="w-full max-w-[440px] transition-all duration-1000 ease-out"
                 :class="{ 'opacity-100 scale-100': isLoaded, 'opacity-0 scale-95': !isLoaded }">
                
                <!-- Logo & Subtitle -->
                <div class="text-center mb-6 flex flex-col items-center">
                    <div class="p-3 bg-white border border-[#eef0eb] rounded-2xl shadow-sm flex items-center justify-center gap-3 backdrop-blur-md mb-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-white shadow-sm shrink-0">
                            <!-- LogiSync SVG Icon -->
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.656 48.656 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M1.5 12a48.655 48.655 0 00.138 3.662 4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M1.5 12l-3 3m3-3l3 3" />
                            </svg>
                        </div>
                        <div class="text-left pr-3">
                           <h2 class="text-base font-extrabold tracking-tight text-[#1c1c1c] uppercase leading-none">Empresa Base</h2>
                           <p class="text-[8px] uppercase font-black tracking-wider text-primary mt-1">LOGÍSTICA</p>
                        </div>
                    </div>
                    <h1 class="text-2xl font-extrabold text-[#1c1c1c] tracking-tight">
                        Crear una Cuenta
                    </h1>
                    <p class="text-[#6c7263] mt-1 text-xs font-semibold">Regístrate para solicitar tus citas logísticas</p>
                </div>

                <!-- Form Card -->
                <div class="bg-white border border-[#eef0eb] p-6 sm:p-8 rounded-2xl shadow-sm relative overflow-hidden">
                    
                    <form @submit.prevent="submit" class="space-y-4">
                        
                        <div class="space-y-1">
                            <InputLabel for="name" value="Nombre Completo" class="font-bold text-[#1c1c1c] text-xs" />
                            <TextInput
                                id="name"
                                type="text"
                                class="block w-full border-[#eef0eb] rounded-xl text-xs px-3.5 py-2.5 bg-white focus:border-primary focus:ring-primary/20 placeholder-zinc-400"
                                v-model="form.name"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Ej: Juan Pérez"
                            />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="username" value="Usuario / RIF" class="font-bold text-[#1c1c1c] text-xs" />
                            <TextInput
                                id="username"
                                type="text"
                                class="block w-full border-[#eef0eb] rounded-xl text-xs px-3.5 py-2.5 bg-white focus:border-primary focus:ring-primary/20 placeholder-zinc-400"
                                v-model="form.username"
                                required
                                autocomplete="username"
                                placeholder="Ej: Recepcion.Romulo o J-12345678-9"
                            />
                            <InputError class="mt-1" :message="form.errors.username" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="email" value="Correo Electrónico (Opcional)" class="font-bold text-[#1c1c1c] text-xs" />
                            <TextInput
                                id="email"
                                type="email"
                                class="block w-full border-[#eef0eb] rounded-xl text-xs px-3.5 py-2.5 bg-white focus:border-primary focus:ring-primary/20 placeholder-zinc-400"
                                v-model="form.email"
                                autocomplete="email"
                                placeholder="ejemplo@correo.com"
                            />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="cargo" value="Cargo en la Empresa" class="font-bold text-[#1c1c1c] text-xs" />
                            <select
                                id="cargo"
                                v-model="form.cargo"
                                class="block w-full border border-[#eef0eb] focus:border-primary focus:ring-primary/20 rounded-xl text-xs px-3.5 py-2.5 bg-white text-[#1c1c1c] transition-all focus:outline-none"
                                required
                            >
                                <option value="" disabled class="text-zinc-400">Seleccione su perfil / cargo...</option>
                                <option value="comprador">Departamento de Compras</option>
                                <option value="proveedor">Proveedor Externo</option>
                                <option value="receptor">Personal de Recepción (Muelle)</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.cargo" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <InputLabel for="password" value="Contraseña" class="font-bold text-[#1c1c1c] text-xs" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="block w-full border-[#eef0eb] rounded-xl text-xs px-3.5 py-2.5 bg-white focus:border-primary focus:ring-primary/20 placeholder-zinc-400"
                                    v-model="form.password"
                                    required
                                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                                    title="La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas, números y símbolos especiales."
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                            </div>
                            <div class="space-y-1">
                                <InputLabel for="password_confirmation" value="Confirmar" class="font-bold text-[#1c1c1c] text-xs" />
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    class="block w-full border-[#eef0eb] rounded-xl text-xs px-3.5 py-2.5 bg-white focus:border-primary focus:ring-primary/20 placeholder-zinc-400"
                                    v-model="form.password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 col-span-2">
                            <InputError :message="form.errors.password" />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>

                        <div class="pt-2 space-y-4">
                            <PrimaryButton 
                                class="w-full justify-center py-2.5 bg-primary hover:bg-[#4f46e5] text-white rounded-xl text-xs font-bold shadow-sm transition-all active:scale-95 uppercase tracking-wider"
                                :class="{ 'opacity-50': form.processing }"
                                :disabled="form.processing"
                            >
                                REGISTRAR USUARIO
                            </PrimaryButton>

                            <div class="text-center text-xs">
                                <span class="text-[#6c7263] font-semibold">¿Ya tienes una cuenta? </span>
                                <Link :href="route('login')" class="text-primary hover:underline font-extrabold">
                                    Inicia Sesión aquí
                                </Link>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="relative z-10 border-t border-[#eef0eb] bg-white py-6 text-xs text-[#888c80]">
            <div class="mx-auto max-w-7xl px-6 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 font-semibold">
                    <div class="h-7 w-7 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20 shrink-0">
                        <svg class="h-4 w-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.656 48.656 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M1.5 12a48.655 48.655 0 00.138 3.662 4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M1.5 12l-3 3m3-3l3 3" />
                        </svg>
                    </div>
                    <span>&copy; {{ new Date().getFullYear() }} LogiSync. Todos los derechos reservados.</span>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-[10px] text-[#6c7263] justify-center font-bold">
                    <span>Soporte: 📞 0424-7475109</span>
                    <span>✉️ soporte@logisync.com</span>
                    <span>🕒 Soporte Técnico: 7:00 AM - 10:00 PM</span>
                </div>
            </div>
        </footer>
    </div>
</template>
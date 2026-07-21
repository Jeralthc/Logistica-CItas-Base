<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const isLoaded = ref(false);

onMounted(() => {
    // Pequeño retraso para activar las animaciones de entrada fluidas
    setTimeout(() => {
        isLoaded.value = true;
    }, 100);
});
</script>

<template>
    <div class="min-h-screen bg-[#faf9f6] text-[#1c1c1c] font-sans relative overflow-hidden selection:bg-primary selection:text-white">
        <Head title="LogiSync - Portal Premium" />

        <!-- Background Ambient Blobs -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[5%] w-[45%] h-[45%] rounded-full bg-primary/5 blur-[120px]"></div>
            <div class="absolute top-[30%] -right-[10%] w-[35%] h-[50%] rounded-full bg-primary/5 blur-[120px]"></div>
        </div>

        <!-- Glass Navbar -->
        <nav class="fixed top-0 w-full z-50 transition-all duration-700 ease-out" 
             :class="{ 'opacity-100 translate-y-0': isLoaded, 'opacity-0 -translate-y-10': !isLoaded }">
            <div class="mx-auto max-w-7xl px-6 py-4">
                <div class="flex items-center justify-between rounded-2xl bg-white/70 backdrop-blur-xl border border-[#eef0eb] px-6 py-3 shadow-sm">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-white shadow-sm">
                            <!-- LogiSync Icon SVG -->
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.656 48.656 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M1.5 12a48.655 48.655 0 00.138 3.662 4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M1.5 12l-3 3m3-3l3 3" />
                            </svg>
                        </div>
                        <span class="text-base font-extrabold tracking-tight text-[#1c1c1c]">LogiSync</span>
                    </div>
                    
                    <div class="hidden md:flex items-center gap-6 text-xs font-bold text-[#6c7263]">
                        <div class="flex items-center gap-1.5 hover:text-[#1c1c1c] transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                            <span>0424-7170326</span>
                        </div>
                        <div class="flex items-center gap-1.5 hover:text-[#1c1c1c] transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <span>contacto@logisync.com</span>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Hero Content -->
        <main class="relative z-10 flex min-h-screen flex-col items-center justify-center px-6 pt-28 text-center">
            
            <div class="max-w-4xl space-y-6 transition-all duration-1000 ease-out" 
                 :class="{ 'opacity-100 translate-y-0': isLoaded, 'opacity-0 translate-y-10': !isLoaded }">
                
                <!-- Floating Badge -->
                <div class="mx-auto flex max-w-fit items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-4 py-1.5 text-xs font-bold text-primary shadow-sm backdrop-blur-md mb-3">
                    <span class="flex h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>
                    Portal Logístico Inteligente
                </div>

                <h1 class="text-4xl font-extrabold tracking-tight text-[#1c1c1c] sm:text-5xl md:text-6xl leading-[1.1]">
                    Simplifica tu recepción de <br />
                    <span class="text-primary drop-shadow-sm">Mercancía y Citas</span>
                </h1>

                <p class="mx-auto max-w-xl text-sm leading-relaxed text-[#6c7263] sm:text-base font-medium">
                    Agenda citas, optimiza tus tiempos en muelle y controla todo el flujo de entrega desde un único panel inteligente, ágil y moderno.
                </p>

                <!-- Actions -->
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <div v-if="canLogin" class="flex flex-col sm:flex-row w-full sm:w-auto gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="group inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary hover:bg-[#4f46e5] px-6 py-3 text-xs font-bold text-white shadow-md transition-all active:scale-95"
                        >
                            Ir al Panel de Control
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="group inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#1c1c1c] hover:bg-zinc-800 px-6 py-3 text-xs font-bold text-white shadow-md transition-all active:scale-95"
                            >
                                Iniciar Sesión
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex items-center justify-center rounded-xl border border-[#eef0eb] bg-white hover:bg-[#fcfbf8] px-6 py-3 text-xs font-bold text-[#1c1c1c] shadow-sm transition-all active:scale-95"
                            >
                                Registrar Empresa
                            </Link>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Decorative App Preview / Floating Card -->
            <div class="mt-14 relative w-full max-w-4xl transition-all duration-1000 ease-out delay-300" 
                 :class="{ 'opacity-100 translate-y-0': isLoaded, 'opacity-0 translate-y-20': !isLoaded }">
                <div class="absolute inset-0 -top-4 rounded-3xl bg-primary/5 blur-2xl"></div>
                <div class="relative rounded-2xl border border-[#eef0eb] bg-white p-2.5 shadow-xl">
                    <div class="rounded-xl border border-[#eef0eb] bg-[#faf9f6] overflow-hidden">
                        <!-- Mockup Header -->
                        <div class="flex items-center gap-2 border-b border-[#eef0eb] bg-[#fcfbf8] px-4 py-2.5">
                            <div class="flex gap-1.5">
                                <div class="h-2 w-2 rounded-full bg-rose-400"></div>
                                <div class="h-2 w-2 rounded-full bg-amber-400"></div>
                                <div class="h-2 w-2 rounded-full bg-emerald-400"></div>
                            </div>
                            <div class="mx-auto flex h-5 w-60 items-center justify-center rounded bg-white text-[9px] text-[#888c80] border border-[#eef0eb] font-mono">
                                logisync.empresa.com
                            </div>
                        </div>
                        <!-- Mockup Body (Warm Light Tone) -->
                        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5 opacity-55">
                            <div class="col-span-1 space-y-3">
                                <div class="h-20 rounded-xl bg-white border border-[#eef0eb]"></div>
                                <div class="h-32 rounded-xl bg-white border border-[#eef0eb]"></div>
                            </div>
                            <div class="col-span-2 space-y-3">
                                <div class="h-10 rounded-xl bg-white border border-[#eef0eb]"></div>
                                <div class="h-42 rounded-xl bg-white border border-[#eef0eb]"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- Footer -->
        <footer class="relative z-10 border-t border-[#eef0eb] bg-white py-6 mt-16 text-xs text-[#888c80]">
            <div class="mx-auto max-w-7xl px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 font-semibold">
                    <div class="h-7 w-7 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20 shrink-0">
                        <svg class="h-4 w-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.656 48.656 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M1.5 12a48.655 48.655 0 00.138 3.662 4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M1.5 12l-3 3m3-3l3 3" />
                        </svg>
                    </div>
                    <span>&copy; {{ new Date().getFullYear() }} LogiSync. Todos los derechos reservados.</span>
                </div>
                <div class="flex gap-5 font-bold">
                    <a href="#" class="hover:text-[#1c1c1c] transition-colors">Términos</a>
                    <a href="#" class="hover:text-[#1c1c1c] transition-colors">Privacidad</a>
                    <a href="#" class="hover:text-[#1c1c1c] transition-colors">Soporte</a>
                </div>
            </div>
        </footer>
    </div>
</template>
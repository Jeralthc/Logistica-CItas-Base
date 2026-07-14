<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col">
        <!-- Barra superior -->
        <nav class="bg-indigo-600 text-white text-sm py-3 px-4 sm:px-8 flex flex-col sm:flex-row justify-between items-center shadow-md gap-2 sm:gap-0 sticky top-0 z-50">
            <div class="flex items-center gap-3">
                <img src="/images/logo.png" alt="Logo Empresa Base" class="w-8 h-8 object-contain bg-white rounded-full p-1" />
                <span class="font-black uppercase tracking-widest">Empresa Base Logística</span>
            </div>
            <div class="flex gap-4 items-center">
                <Link :href="route('login')" class="text-white hover:text-indigo-200 font-bold transition-colors">Iniciar Sesión</Link>
            </div>
        </nav>

        <!-- Contenido de la página -->
        <main class="flex-grow">
            <slot />
        </main>
        
        <!-- Footer simple -->
        <footer class="bg-slate-900 text-slate-500 py-6 text-center text-xs mt-auto">
            &copy; {{ new Date().getFullYear() }} Portal Logístico Empresa Base.
        </footer>
    </div>
</template>

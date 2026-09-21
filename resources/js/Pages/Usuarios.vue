<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const esSuperAdmin = computed(() => currentUser.value && (currentUser.value.id === 1 || currentUser.value.username === 'Sistemas.Jeralthc'));

const MODULOS_SISTEMA = [
    { key: 'recepcion', label: 'Recepción (Andén / Citas)', icon: '📥', desc: 'Acceso a la recepción de mercancía y tablero de citas' },
    { key: 'monitor_odc', label: 'Monitor ODC', icon: '📋', desc: 'Consulta y seguimiento de órdenes de compra' },
    { key: 'operarios', label: 'Operarios', icon: '👷', desc: 'Gestión y asignación de operarios de almacén' },
    { key: 'reservar_cita', label: 'Reservar Cita', icon: '📅', desc: 'Agendamiento y reprogramación de citas' },
    { key: 'monitoreo', label: 'Monitoreo & Auditoría', icon: '📊', desc: 'Trazabilidad, logs y reportes del sistema' },
    { key: 'configuracion_erp', label: 'Configuración ERP', icon: '⚙️', desc: 'Conexión y parámetros del ERP Profit Plus' },
    { key: 'despliegue', label: 'Control de Cambios', icon: '🚀', desc: 'Despliegue de parches, mantenimiento y respaldos' },
    { key: 'usuarios', label: 'Gestión de Usuarios', icon: '👥', desc: 'Administración de usuarios, roles y cuentas' },
    { key: 'categorias', label: 'Categorías', icon: '🏷️', desc: 'Parámetros de rendimiento y cálculo de descarga' },
];

const usuarios = ref([]);
const metrics = ref({
    total: 0,
    activos: 0,
    inactivos: 0,
    admin: 0,
    receptor: 0,
    comprador: 0,
    proveedor: 0
});

const cargando = ref(true);
const busqueda = ref('');
const filtroRol = ref('todos');
const filtroEstado = ref('todos');

// Modales
const mostrarModalCrear = ref(false);
const mostrarModalEditar = ref(false);
const mostrarModalClave = ref(false);
const mostrarModalToggle = ref(false);
const mostrarModalModulos = ref(false);

const usuarioSeleccionado = ref(null);
const errores = ref({});
const procesando = ref(false);
const mensajeExito = ref('');

// Formulario Crear
const formCrear = ref({
    name: '',
    username: '',
    email: '',
    role: 'receptor',
    es_galpon: false,
    password: '',
    password_confirmation: '',
    contactos: []
});

// Formulario Editar
const formEditar = ref({
    id: null,
    name: '',
    username: '',
    email: '',
    role: 'receptor',
    es_galpon: false,
    contactos: [],
    modulos_permitidos: []
});

// Formulario Módulos (Modal Rápido)
const formModulos = ref({
    id: null,
    name: '',
    username: '',
    role: '',
    modulos_permitidos: []
});

// Formulario Clave
const formClave = ref({
    id: null,
    password: '',
    password_confirmation: ''
});

const cargarUsuarios = async () => {
    cargando.value = true;
    try {
        const resp = await axios.get('/api/usuarios');
        if (resp.data.status === 'Exitoso') {
            usuarios.value = resp.data.users || [];
            metrics.value = resp.data.metrics || metrics.value;
        }
    } catch (e) {
        console.error('Error al cargar usuarios:', e);
    } finally {
        cargando.value = false;
    }
};

onMounted(() => {
    cargarUsuarios();
});

const usuariosFiltrados = computed(() => {
    let list = usuarios.value;

    if (filtroRol.value !== 'todos') {
        list = list.filter(u => u.role === filtroRol.value);
    }

    if (filtroEstado.value === 'activos') {
        list = list.filter(u => u.activo);
    } else if (filtroEstado.value === 'inactivos') {
        list = list.filter(u => !u.activo);
    }

    if (busqueda.value.trim() !== '') {
        const q = busqueda.value.toLowerCase().trim();
        list = list.filter(u =>
            (u.name && u.name.toLowerCase().includes(q)) ||
            (u.username && u.username.toLowerCase().includes(q)) ||
            (u.email && u.email.toLowerCase().includes(q)) ||
            (u.contactos && u.contactos.some(c => (c.email && c.email.toLowerCase().includes(q)) || (c.nombre && c.nombre.toLowerCase().includes(q))))
        );
    }

    return list;
});

// Reset Formularios y Errores
const resetErrores = () => { errores.value = {}; mensajeExito.value = ''; };

const abrirModalCrear = () => {
    resetErrores();
    formCrear.value = {
        name: '',
        username: '',
        email: '',
        role: 'receptor',
        es_galpon: false,
        password: '',
        password_confirmation: '',
        contactos: []
    };
    mostrarModalCrear.value = true;
};

const agregarContactoCrear = () => {
    if (!formCrear.value.contactos) formCrear.value.contactos = [];
    formCrear.value.contactos.push({ nombre: '', email: '', telefono: '' });
};

const eliminarContactoCrear = (index) => {
    formCrear.value.contactos.splice(index, 1);
};

const guardarNuevoUsuario = async () => {
    resetErrores();
    procesando.value = true;
    try {
        const resp = await axios.post('/api/usuarios', formCrear.value);
        if (resp.data.status === 'Exitoso') {
            mostrarModalCrear.value = false;
            mensajeExito.value = resp.data.mensaje;
            cargarUsuarios();
        }
    } catch (e) {
        if (e.response?.data?.errors) {
            errores.value = e.response.data.errors;
        } else if (e.response?.data?.message) {
            errores.value = { general: e.response.data.message };
        }
    } finally {
        procesando.value = false;
    }
};

const abrirModalEditar = (u) => {
    resetErrores();
    usuarioSeleccionado.value = u;

    const primEmail = (u.email || '').toLowerCase().trim();
    const cts = (u.contactos || [])
        .filter(c => c && c.email && c.email.toLowerCase().trim() !== primEmail)
        .map(c => ({
            id: c.id,
            nombre: c.nombre || '',
            email: c.email || '',
            telefono: c.telefono || ''
        }));

    let mods = [];
    if (Array.isArray(u.modulos_permitidos)) {
        mods = [...u.modulos_permitidos];
    } else {
        if (u.role === 'admin') {
            mods = MODULOS_SISTEMA.map(m => m.key);
        } else if (u.role === 'receptor') {
            mods = ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita'];
        } else if (u.role === 'comprador') {
            mods = ['recepcion', 'monitor_odc', 'reservar_cita', 'monitoreo'];
        } else if (u.role === 'proveedor') {
            mods = ['recepcion', 'reservar_cita'];
        }
    }

    formEditar.value = {
        id: u.id,
        name: u.name,
        username: u.username,
        email: u.email,
        role: u.role,
        es_galpon: !!u.es_galpon,
        contactos: cts,
        modulos_permitidos: mods
    };
    mostrarModalEditar.value = true;
};

// Métodos para asignación de módulos
const abrirModalModulos = (u) => {
    resetErrores();
    usuarioSeleccionado.value = u;
    let mods = [];
    if (Array.isArray(u.modulos_permitidos) && u.modulos_permitidos.length > 0) {
        mods = [...u.modulos_permitidos];
    } else {
        if (u.role === 'admin') {
            mods = MODULOS_SISTEMA.map(m => m.key);
        } else if (u.role === 'receptor') {
            mods = ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita'];
        } else if (u.role === 'comprador') {
            mods = ['recepcion', 'monitor_odc', 'reservar_cita', 'monitoreo'];
        } else if (u.role === 'proveedor') {
            mods = ['recepcion', 'reservar_cita'];
        }
    }

    formModulos.value = {
        id: u.id,
        name: u.name,
        username: u.username,
        role: u.role,
        modulos_permitidos: mods
    };
    mostrarModalModulos.value = true;
};

const toggleModuloEditar = (key) => {
    const idx = formEditar.value.modulos_permitidos.indexOf(key);
    if (idx > -1) {
        formEditar.value.modulos_permitidos.splice(idx, 1);
    } else {
        formEditar.value.modulos_permitidos.push(key);
    }
};

const toggleModuloRapido = (key) => {
    const idx = formModulos.value.modulos_permitidos.indexOf(key);
    if (idx > -1) {
        formModulos.value.modulos_permitidos.splice(idx, 1);
    } else {
        formModulos.value.modulos_permitidos.push(key);
    }
};

const marcarTodosModulosEditar = () => {
    formEditar.value.modulos_permitidos = MODULOS_SISTEMA.map(m => m.key);
};

const desmarcarTodosModulosEditar = () => {
    formEditar.value.modulos_permitidos = [];
};

const restaurarModulosPorRolEditar = () => {
    const r = formEditar.value.role;
    if (r === 'admin') formEditar.value.modulos_permitidos = MODULOS_SISTEMA.map(m => m.key);
    else if (r === 'receptor') formEditar.value.modulos_permitidos = ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita'];
    else if (r === 'comprador') formEditar.value.modulos_permitidos = ['recepcion', 'monitor_odc', 'reservar_cita', 'monitoreo'];
    else if (r === 'proveedor') formEditar.value.modulos_permitidos = ['recepcion', 'reservar_cita'];
};

const marcarTodosModulosRapido = () => {
    formModulos.value.modulos_permitidos = MODULOS_SISTEMA.map(m => m.key);
};

const desmarcarTodosModulosRapido = () => {
    formModulos.value.modulos_permitidos = [];
};

const restaurarModulosPorRolRapido = () => {
    const r = formModulos.value.role;
    if (r === 'admin') formModulos.value.modulos_permitidos = MODULOS_SISTEMA.map(m => m.key);
    else if (r === 'receptor') formModulos.value.modulos_permitidos = ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita'];
    else if (r === 'comprador') formModulos.value.modulos_permitidos = ['recepcion', 'monitor_odc', 'reservar_cita', 'monitoreo'];
    else if (r === 'proveedor') formModulos.value.modulos_permitidos = ['recepcion', 'reservar_cita'];
};

const guardarModulosUsuario = async () => {
    resetErrores();
    procesando.value = true;
    try {
        const u = usuarioSeleccionado.value;
        const resp = await axios.put(`/api/usuarios/${formModulos.value.id}`, {
            name: u.name,
            username: u.username,
            email: u.email,
            role: u.role,
            es_galpon: u.es_galpon,
            contactos: u.contactos,
            modulos_permitidos: formModulos.value.modulos_permitidos
        });
        if (resp.data.status === 'Exitoso') {
            mostrarModalModulos.value = false;
            mensajeExito.value = `Permisos de módulos actualizados exitosamente para ${u.name}.`;
            cargarUsuarios();
        }
    } catch (e) {
        if (e.response?.data?.message) {
            errores.value = { general: e.response.data.message };
        }
    } finally {
        procesando.value = false;
    }
};

const agregarContactoEditar = () => {
    if (!formEditar.value.contactos) formEditar.value.contactos = [];
    formEditar.value.contactos.push({ nombre: '', email: '', telefono: '' });
};

const eliminarContactoEditar = (index) => {
    formEditar.value.contactos.splice(index, 1);
};

const actualizarUsuario = async () => {
    resetErrores();
    procesando.value = true;
    try {
        const resp = await axios.put(`/api/usuarios/${formEditar.value.id}`, formEditar.value);
        if (resp.data.status === 'Exitoso') {
            mostrarModalEditar.value = false;
            mensajeExito.value = resp.data.mensaje;
            cargarUsuarios();
        }
    } catch (e) {
        if (e.response?.data?.errors) {
            errores.value = e.response.data.errors;
        }
    } finally {
        procesando.value = false;
    }
};

const abrirModalClave = (u) => {
    resetErrores();
    usuarioSeleccionado.value = u;
    formClave.value = {
        id: u.id,
        password: '',
        password_confirmation: ''
    };
    mostrarModalClave.value = true;
};

const generarClaveAleatoria = () => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789#@!';
    let pass = '';
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    formClave.value.password = pass;
    formClave.value.password_confirmation = pass;
};

const actualizarClave = async () => {
    resetErrores();
    procesando.value = true;
    try {
        const resp = await axios.post(`/api/usuarios/${formClave.value.id}/password`, formClave.value);
        if (resp.data.status === 'Exitoso') {
            mostrarModalClave.value = false;
            mensajeExito.value = resp.data.mensaje;
        }
    } catch (e) {
        if (e.response?.data?.errors) {
            errores.value = e.response.data.errors;
        }
    } finally {
        procesando.value = false;
    }
};

const abrirModalToggleStatus = (u) => {
    resetErrores();
    usuarioSeleccionado.value = u;
    mostrarModalToggle.value = true;
};

const toggleStatusUsuario = async () => {
    if (!usuarioSeleccionado.value) return;
    procesando.value = true;
    try {
        const resp = await axios.post(`/api/usuarios/${usuarioSeleccionado.value.id}/toggle-activo`);
        if (resp.data.status === 'Exitoso') {
            mostrarModalToggle.value = false;
            mensajeExito.value = resp.data.mensaje;
            cargarUsuarios();
        }
    } catch (e) {
        if (e.response?.data?.mensaje) {
            errores.value = { general: e.response.data.mensaje };
        }
    } finally {
        procesando.value = false;
    }
};

const badgeRol = (role) => {
    const r = {
        admin: { label: 'Administrador', class: 'bg-rose-100 text-rose-800 border-rose-200', icon: '👑' },
        receptor: { label: 'Receptor', class: 'bg-blue-100 text-blue-800 border-blue-200', icon: '📦' },
        comprador: { label: 'Comprador', class: 'bg-emerald-100 text-emerald-800 border-emerald-200', icon: '🛒' },
        proveedor: { label: 'Proveedor', class: 'bg-amber-100 text-amber-800 border-amber-200', icon: '🏢' },
    };
    return r[role] || { label: role, class: 'bg-slate-100 text-slate-700 border-slate-200', icon: '👤' };
};

const formatFecha = (f) => {
    if (!f) return '—';
    return new Date(f).toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Gestión de Usuarios" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight flex items-center gap-2">
                        👥 Gestión de Usuarios
                    </h2>
                    <p class="text-xs text-slate-500 font-medium mt-1">
                        Control centralizado de cuentas, asignación de roles, permisos y contraseñas.
                    </p>
                </div>
                
                <button @click="abrirModalCrear"
                    class="flex items-center justify-center gap-2 text-sm font-bold text-white bg-red-600 px-5 py-2.5 rounded-xl hover:bg-red-700 transition-all shadow-lg shadow-red-600/20 active:scale-95 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    <span>Nuevo Usuario</span>
                </button>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- MENSAJE DE ÉXITO -->
                <div v-if="mensajeExito" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl flex items-center justify-between shadow-sm animate-in fade-in">
                    <div class="flex items-center gap-3 font-semibold text-sm">
                        <span class="text-lg">✅</span>
                        <span>{{ mensajeExito }}</span>
                    </div>
                    <button @click="mensajeExito = ''" class="text-emerald-600 hover:text-emerald-900 font-bold text-xs uppercase tracking-wider">Cerrar</button>
                </div>

                <!-- TARJETAS DE ESTADÍSTICAS -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total</span>
                        <span class="text-2xl font-black text-slate-800 mt-1">{{ metrics.total }}</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-emerald-200/80 shadow-sm flex flex-col items-center justify-center text-center bg-emerald-50/20">
                        <span class="text-xs font-bold text-emerald-600 uppercase tracking-widest">Activos</span>
                        <span class="text-2xl font-black text-emerald-700 mt-1">{{ metrics.activos }}</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Inactivos</span>
                        <span class="text-2xl font-black text-rose-600 mt-1">{{ metrics.inactivos }}</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-rose-100 shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="text-xs font-bold text-rose-600 uppercase tracking-widest">Admins</span>
                        <span class="text-2xl font-black text-rose-700 mt-1">{{ metrics.admin }}</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-blue-100 shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest">Receptores</span>
                        <span class="text-2xl font-black text-blue-700 mt-1">{{ metrics.receptor }}</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-emerald-100 shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="text-xs font-bold text-emerald-600 uppercase tracking-widest">Compradores</span>
                        <span class="text-2xl font-black text-emerald-700 mt-1">{{ metrics.comprador }}</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-amber-100 shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="text-xs font-bold text-amber-600 uppercase tracking-widest">Proveedores</span>
                        <span class="text-2xl font-black text-amber-700 mt-1">{{ metrics.proveedor }}</span>
                    </div>
                </div>

                <!-- FILTROS Y BUSCADOR -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="relative w-full sm:w-80">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input v-model="busqueda" type="text" placeholder="Buscar por RIF, usuario, nombre..."
                            class="block w-full pl-10 pr-4 py-2 text-sm border-slate-200 focus:border-red-600 focus:ring-red-600/20 rounded-xl transition-all">
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                        <select v-model="filtroRol" class="py-2 px-3 border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:border-red-600 focus:ring-red-600/20">
                            <option value="todos">Todos los Roles</option>
                            <option value="admin">Administradores</option>
                            <option value="receptor">Receptores</option>
                            <option value="comprador">Compradores</option>
                            <option value="proveedor">Proveedores</option>
                        </select>

                        <select v-model="filtroEstado" class="py-2 px-3 border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:border-red-600 focus:ring-red-600/20">
                            <option value="todos">Todos los Estados</option>
                            <option value="activos">Sólo Activos</option>
                            <option value="inactivos">Sólo Inactivos</option>
                        </select>
                    </div>
                </div>

                <!-- TABLA DE USUARIOS -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">#</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Usuario / RIF</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nombre Completo</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Correo Electrónico</th>
                                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Rol</th>
                                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Registro</th>
                                    <th class="px-6 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(u, index) in usuariosFiltrados" :key="u.id" 
                                    :class="!u.activo ? 'bg-slate-50/70 text-slate-400' : 'hover:bg-slate-50/50 transition-colors'">
                                    
                                    <td class="px-6 py-4 text-xs font-bold" :class="!u.activo ? 'text-slate-300' : 'text-slate-400'">
                                        {{ index + 1 }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg border transition-colors"
                                            :class="!u.activo ? 'bg-slate-100 text-slate-400 border-slate-200' : 'bg-slate-100 text-slate-800 border-slate-200/80'">
                                            {{ u.username }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-bold" :class="!u.activo ? 'text-slate-500 line-through' : 'text-slate-800'">
                                                {{ u.name }}
                                            </p>
                                            <span v-if="u.id === currentUser.id" class="text-[9px] font-black uppercase bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Tú</span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-xs font-semibold" :class="!u.activo ? 'text-slate-400' : 'text-slate-700'">
                                                {{ u.email }}
                                            </span>
                                            <div v-if="u.contactos && u.contactos.filter(c => c.email && c.email.toLowerCase() !== u.email.toLowerCase()).length > 0" class="flex flex-wrap items-center gap-1 mt-0.5">
                                                <span v-for="c in u.contactos.filter(c => c.email && c.email.toLowerCase() !== u.email.toLowerCase())" :key="c.id" 
                                                    class="inline-flex items-center gap-1 text-[10px] font-medium bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md border border-slate-200"
                                                    :title="(c.nombre ? c.nombre + ': ' : '') + c.email">
                                                    <span class="text-slate-400">✉️</span>
                                                    <span>{{ c.email }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <div class="inline-flex flex-col items-center gap-1">
                                            <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-full border shadow-sm" :class="badgeRol(u.role).class">
                                                <span>{{ badgeRol(u.role).icon }}</span>
                                                <span>{{ badgeRol(u.role).label }}</span>
                                            </span>
                                            <span v-if="u.es_galpon" class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200">
                                                📦 Galpón / Traslados
                                            </span>
                                            <span v-if="esSuperAdmin && Array.isArray(u.modulos_permitidos)" 
                                                class="text-[9px] font-mono text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 font-bold" 
                                                :title="'Módulos asignados: ' + u.modulos_permitidos.join(', ')">
                                                🔐 {{ u.modulos_permitidos.length }}/9 módulos
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <button @click="abrirModalToggleStatus(u)"
                                            :disabled="u.id === currentUser.id"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                            :class="u.activo ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-200 text-slate-600 hover:bg-slate-300'">
                                            <span class="w-2 h-2 rounded-full" :class="u.activo ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                                            <span>{{ u.activo ? 'Activo' : 'Inactivo' }}</span>
                                        </button>
                                    </td>

                                    <td class="px-6 py-4 text-center text-xs font-medium text-slate-400">
                                        {{ formatFecha(u.created_at) }}
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- BOTÓN ASIGNAR MÓDULOS (Exclusivo Superadmin Jeralth) -->
                                            <button v-if="esSuperAdmin" @click="abrirModalModulos(u)"
                                                class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all" 
                                                title="Configurar Módulos Permitidos (Checks)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            </button>

                                            <!-- BOTÓN EDITAR -->
                                            <button @click="abrirModalEditar(u)" 
                                                class="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all" title="Editar Usuario">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </button>

                                            <!-- BOTÓN CAMBIAR CLAVE -->
                                            <button @click="abrirModalClave(u)" 
                                                class="p-2 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-xl transition-all" title="Cambiar / Restablecer Contraseña">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                            </button>

                                            <!-- BOTÓN CAMBIAR ESTADO -->
                                            <button @click="abrirModalToggleStatus(u)"
                                                :disabled="u.id === currentUser.id"
                                                class="p-2 rounded-xl transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                                                :class="u.activo ? 'text-slate-400 hover:text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50'"
                                                :title="u.activo ? 'Desactivar Cuenta' : 'Activar Cuenta'">
                                                <svg v-if="u.activo" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="usuariosFiltrados.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                        <p class="text-sm font-medium">No se encontraron usuarios que coincidan con la búsqueda.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL CREAR USUARIO -->
        <Modal :show="mostrarModalCrear" @close="mostrarModalCrear = false">
            <div class="p-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span>👤</span> Registrar Nuevo Usuario
                    </h3>
                    <button @click="mostrarModalCrear = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div v-if="errores.general" class="mb-4 p-3 bg-red-50 text-red-700 rounded-xl text-xs font-bold">
                    {{ errores.general }}
                </div>

                <form @submit.prevent="guardarNuevoUsuario" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Nombre Completo</label>
                        <input v-model="formCrear.name" type="text" placeholder="Ej: Juan Pérez" required
                            class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm">
                        <InputError :message="errores.name?.[0]" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">RIF / Usuario</label>
                            <input v-model="formCrear.username" type="text" placeholder="Ej: J-123456789" required
                                class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm font-mono">
                            <InputError :message="errores.username?.[0]" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Rol de Acceso</label>
                            <select v-model="formCrear.role" required
                                class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm font-semibold">
                                <option value="receptor">Receptor (Almacén / Recepción)</option>
                                <option value="comprador">Comprador Interno</option>
                                <option value="proveedor">Proveedor Externo</option>
                                <option value="admin">Administrador General</option>
                            </select>
                            <InputError :message="errores.role?.[0]" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Correo Electrónico (Principal)</label>
                        <input v-model="formCrear.email" type="email" placeholder="usuario@empresa.com" required
                            class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm">
                        <InputError :message="errores.email?.[0]" class="mt-1" />
                    </div>

                    <!-- Sección de Correos Adicionales si es Proveedor -->
                    <div v-if="formCrear.role === 'proveedor'" class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                    <span>📧 Correos y Contactos Adicionales de la Empresa</span>
                                </h4>
                                <p class="text-[11px] text-slate-500">Recibirán copia de habilitación de ODC, credenciales y confirmaciones de citas.</p>
                            </div>
                            <button type="button" @click="agregarContactoCrear" 
                                class="text-xs font-bold text-red-600 hover:text-red-700 bg-white hover:bg-red-50 px-3 py-1.5 rounded-xl border border-red-200 shadow-sm transition-all flex items-center gap-1 self-start sm:self-auto">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                Agregar otro correo
                            </button>
                        </div>

                        <div v-if="formCrear.contactos && formCrear.contactos.length > 0" class="space-y-2">
                            <div v-for="(ct, idx) in formCrear.contactos" :key="idx" class="flex flex-col sm:flex-row items-center gap-2 p-2.5 bg-white rounded-xl border border-slate-200/80 shadow-2xs">
                                <input v-model="ct.nombre" type="text" placeholder="Nombre / Vendedor (Opcional)"
                                    class="w-full sm:w-1/3 rounded-lg border-slate-200 text-xs py-1.5 px-2.5 focus:border-red-500 focus:ring-red-500">
                                <input v-model="ct.email" type="email" placeholder="correo.vendedor@empresa.com" required
                                    class="w-full sm:flex-1 rounded-lg border-slate-200 text-xs py-1.5 px-2.5 focus:border-red-500 focus:ring-red-500">
                                <input v-model="ct.telefono" type="text" placeholder="Teléfono"
                                    class="w-full sm:w-28 rounded-lg border-slate-200 text-xs py-1.5 px-2.5 focus:border-red-500 focus:ring-red-500">
                                <button type="button" @click="eliminarContactoCrear(idx)" 
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors self-end sm:self-auto" title="Quitar correo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center py-2 text-xs text-slate-400 italic">
                            No hay correos adicionales agregados (solo se notificará al principal).
                        </div>
                    </div>

                    <div class="flex items-center gap-2 p-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                        <input type="checkbox" id="es_galpon_crear" v-model="formCrear.es_galpon" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
                        <label for="es_galpon_crear" class="text-xs font-bold text-indigo-900 cursor-pointer">
                            ¿Es cuenta de Galpón / Traslado Interno?
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Contraseña</label>
                            <input v-model="formCrear.password" type="password" placeholder="••••••••" required
                                class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm">
                            <InputError :message="errores.password?.[0]" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Confirmar Contraseña</label>
                            <input v-model="formCrear.password_confirmation" type="password" placeholder="••••••••" required
                                class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="mostrarModalCrear = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200">Cancelar</button>
                        <button type="submit" :disabled="procesando"
                            class="px-5 py-2 bg-red-600 text-white rounded-xl text-xs font-bold hover:bg-red-700 transition-all shadow-md active:scale-95 disabled:opacity-50">
                            {{ procesando ? 'Guardando...' : 'Crear Usuario' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL EDITAR USUARIO -->
        <Modal :show="mostrarModalEditar" @close="mostrarModalEditar = false">
            <div class="p-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span>✏️</span> Editar Datos de Usuario
                    </h3>
                    <button @click="mostrarModalEditar = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form @submit.prevent="actualizarUsuario" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Nombre Completo</label>
                        <input v-model="formEditar.name" type="text" required
                            class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm">
                        <InputError :message="errores.name?.[0]" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">RIF / Usuario</label>
                            <input v-model="formEditar.username" type="text" required
                                class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm font-mono">
                            <InputError :message="errores.username?.[0]" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Rol de Acceso</label>
                            <select v-model="formEditar.role" required
                                class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm font-semibold">
                                <option value="receptor">Receptor (Almacén / Recepción)</option>
                                <option value="comprador">Comprador Interno</option>
                                <option value="proveedor">Proveedor Externo</option>
                                <option value="admin">Administrador General</option>
                            </select>
                            <InputError :message="errores.role?.[0]" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Correo Electrónico (Principal)</label>
                        <input v-model="formEditar.email" type="email" required
                            class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm">
                        <InputError :message="errores.email?.[0]" class="mt-1" />
                    </div>

                    <!-- Sección de Correos Adicionales si es Proveedor -->
                    <div v-if="formEditar.role === 'proveedor'" class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                    <span>📧 Correos y Contactos Adicionales de la Empresa</span>
                                </h4>
                                <p class="text-[11px] text-slate-500">Recibirán copia de habilitación de ODC, credenciales y confirmaciones de citas.</p>
                            </div>
                            <button type="button" @click="agregarContactoEditar" 
                                class="text-xs font-bold text-red-600 hover:text-red-700 bg-white hover:bg-red-50 px-3 py-1.5 rounded-xl border border-red-200 shadow-sm transition-all flex items-center gap-1 self-start sm:self-auto">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                Agregar otro correo
                            </button>
                        </div>

                        <div v-if="formEditar.contactos && formEditar.contactos.length > 0" class="space-y-2">
                            <div v-for="(ct, idx) in formEditar.contactos" :key="idx" class="flex flex-col sm:flex-row items-center gap-2 p-2.5 bg-white rounded-xl border border-slate-200/80 shadow-2xs">
                                <input v-model="ct.nombre" type="text" placeholder="Nombre / Vendedor (Opcional)"
                                    class="w-full sm:w-1/3 rounded-lg border-slate-200 text-xs py-1.5 px-2.5 focus:border-red-500 focus:ring-red-500">
                                <input v-model="ct.email" type="email" placeholder="correo.vendedor@empresa.com" required
                                    class="w-full sm:flex-1 rounded-lg border-slate-200 text-xs py-1.5 px-2.5 focus:border-red-500 focus:ring-red-500">
                                <input v-model="ct.telefono" type="text" placeholder="Teléfono"
                                    class="w-full sm:w-28 rounded-lg border-slate-200 text-xs py-1.5 px-2.5 focus:border-red-500 focus:ring-red-500">
                                <button type="button" @click="eliminarContactoEditar(idx)" 
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors self-end sm:self-auto" title="Quitar correo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center py-2 text-xs text-slate-400 italic">
                            No hay correos adicionales registrados. (Solo se enviará al correo principal).
                        </div>
                    </div>

                    <div class="flex items-center gap-2 p-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                        <input type="checkbox" id="es_galpon_editar" v-model="formEditar.es_galpon" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
                        <label for="es_galpon_editar" class="text-xs font-bold text-indigo-900 cursor-pointer">
                            ¿Es cuenta de Galpón / Traslado Interno?
                        </label>
                    </div>

                    <!-- SECCIÓN PERMISOS DE MÓDULOS EN EDICIÓN (Exclusivo Superadmin Jeralth) -->
                    <div v-if="esSuperAdmin" class="p-4 bg-slate-900 text-white rounded-2xl border border-slate-800 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800 pb-3">
                            <div>
                                <h4 class="text-xs font-black uppercase tracking-wider text-red-400 flex items-center gap-1.5">
                                    <span>🔐 Módulos Habilitados (Solo Superadministrador)</span>
                                </h4>
                                <p class="text-[11px] text-slate-400">
                                    Marca o desmarca los módulos a los que este usuario podrá ingresar (incluso si es Administrador).
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button type="button" @click="marcarTodosModulosEditar" class="text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-emerald-400 px-2.5 py-1 rounded-lg border border-emerald-500/30 transition-all">
                                    ✓ Todos
                                </button>
                                <button type="button" @click="desmarcarTodosModulosEditar" class="text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-red-400 px-2.5 py-1 rounded-lg border border-red-500/30 transition-all">
                                    ✕ Ninguno
                                </button>
                                <button type="button" @click="restaurarModulosPorRolEditar" class="text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-blue-400 px-2.5 py-1 rounded-lg border border-blue-500/30 transition-all">
                                    ↺ Por Rol
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 max-h-60 overflow-y-auto pr-1">
                            <label 
                                v-for="mod in MODULOS_SISTEMA" 
                                :key="mod.key" 
                                class="flex items-start gap-2.5 p-2 rounded-xl border transition-all cursor-pointer select-none"
                                :class="formEditar.modulos_permitidos.includes(mod.key) ? 'bg-red-950/40 border-red-500/50 text-white' : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:bg-slate-800'"
                            >
                                <input 
                                    type="checkbox" 
                                    :checked="formEditar.modulos_permitidos.includes(mod.key)"
                                    @change="toggleModuloEditar(mod.key)"
                                    class="rounded border-slate-600 text-red-600 focus:ring-red-500 mt-0.5 w-4 h-4 bg-slate-700"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm">{{ mod.icon }}</span>
                                        <span class="text-xs font-bold" :class="formEditar.modulos_permitidos.includes(mod.key) ? 'text-white' : 'text-slate-300'">{{ mod.label }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">{{ mod.desc }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="mostrarModalEditar = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200">Cancelar</button>
                        <button type="submit" :disabled="procesando"
                            class="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-all shadow-md active:scale-95 disabled:opacity-50">
                            {{ procesando ? 'Guardando...' : 'Guardar Cambios' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL RÁPIDO ASIGNAR MÓDULOS (Exclusivo Superadmin Jeralth) -->
        <Modal :show="mostrarModalModulos" @close="mostrarModalModulos = false">
            <div class="p-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span>🔐</span> Permisos de Módulos
                    </h3>
                    <button @click="mostrarModalModulos = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div v-if="usuarioSeleccionado" class="mb-4 p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Usuario Seleccionado</p>
                        <p class="text-sm font-bold text-slate-800">{{ usuarioSeleccionado.name }} <span class="font-mono text-slate-500">({{ usuarioSeleccionado.username }})</span></p>
                    </div>
                    <span class="inline-flex items-center gap-1 text-xs font-black px-2.5 py-1 rounded-full border" :class="badgeRol(usuarioSeleccionado.role).class">
                        <span>{{ badgeRol(usuarioSeleccionado.role).icon }}</span>
                        <span>{{ badgeRol(usuarioSeleccionado.role).label }}</span>
                    </span>
                </div>

                <div class="p-4 bg-slate-900 text-white rounded-2xl border border-slate-800 space-y-3 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800 pb-3">
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-red-400">
                                Módulos con Acceso Habilitado
                            </h4>
                            <p class="text-[11px] text-slate-400">
                                Los módulos desmarcados quedarán completamente inaccesibles para este usuario.
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button type="button" @click="marcarTodosModulosRapido" class="text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-emerald-400 px-2.5 py-1 rounded-lg border border-emerald-500/30 transition-all">
                                ✓ Todos
                            </button>
                            <button type="button" @click="desmarcarTodosModulosRapido" class="text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-red-400 px-2.5 py-1 rounded-lg border border-red-500/30 transition-all">
                                ✕ Ninguno
                            </button>
                            <button type="button" @click="restaurarModulosPorRolRapido" class="text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-blue-400 px-2.5 py-1 rounded-lg border border-blue-500/30 transition-all">
                                ↺ Por Rol
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 max-h-72 overflow-y-auto pr-1">
                        <label 
                            v-for="mod in MODULOS_SISTEMA" 
                            :key="mod.key" 
                            class="flex items-start gap-2.5 p-2.5 rounded-xl border transition-all cursor-pointer select-none"
                            :class="formModulos.modulos_permitidos.includes(mod.key) ? 'bg-red-950/40 border-red-500/50 text-white shadow-xs' : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:bg-slate-800'"
                        >
                            <input 
                                type="checkbox" 
                                :checked="formModulos.modulos_permitidos.includes(mod.key)"
                                @change="toggleModuloRapido(mod.key)"
                                class="rounded border-slate-600 text-red-600 focus:ring-red-500 mt-0.5 w-4 h-4 bg-slate-700"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm">{{ mod.icon }}</span>
                                    <span class="text-xs font-bold" :class="formModulos.modulos_permitidos.includes(mod.key) ? 'text-white' : 'text-slate-300'">{{ mod.label }}</span>
                                </div>
                                <p class="text-[10px] text-slate-400 leading-tight mt-0.5">{{ mod.desc }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="mostrarModalModulos = false" 
                        class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200">Cancelar</button>
                    <button type="button" @click="guardarModulosUsuario" :disabled="procesando"
                        class="px-5 py-2 bg-red-600 text-white rounded-xl text-xs font-bold hover:bg-red-700 transition-all shadow-md active:scale-95 disabled:opacity-50 flex items-center gap-1.5">
                        <span>{{ procesando ? 'Guardando...' : 'Aplicar Permisos' }}</span>
                    </button>
                </div>
            </div>
        </Modal>

        <!-- MODAL RESTABLECER CONTRASEÑA -->
        <Modal :show="mostrarModalClave" @close="mostrarModalClave = false">
            <div class="p-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span>🔑</span> Cambiar / Restablecer Contraseña
                    </h3>
                    <button @click="mostrarModalClave = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div v-if="usuarioSeleccionado" class="mb-4 p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                    <p class="text-xs text-slate-400 font-bold uppercase">Usuario Seleccionado</p>
                    <p class="text-sm font-bold text-slate-800">{{ usuarioSeleccionado.name }} <span class="font-mono text-slate-500">({{ usuarioSeleccionado.username }})</span></p>
                </div>

                <form @submit.prevent="actualizarClave" class="space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-xs font-bold uppercase text-slate-600">Nueva Contraseña</label>
                        <button type="button" @click="generarClaveAleatoria" 
                            class="text-[10px] font-bold text-blue-600 hover:underline uppercase">Generar Clave Aleatoria</button>
                    </div>

                    <input v-model="formClave.password" type="text" placeholder="Nueva clave..." required
                        class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm font-mono">
                    <InputError :message="errores.password?.[0]" class="mt-1" />

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Confirmar Nueva Contraseña</label>
                        <input v-model="formClave.password_confirmation" type="text" placeholder="Repetir clave..." required
                            class="w-full rounded-xl border-slate-200 focus:border-red-600 focus:ring-red-600/20 text-sm font-mono">
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="mostrarModalClave = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200">Cancelar</button>
                        <button type="submit" :disabled="procesando"
                            class="px-5 py-2 bg-amber-600 text-white rounded-xl text-xs font-bold hover:bg-amber-700 transition-all shadow-md active:scale-95 disabled:opacity-50">
                            {{ procesando ? 'Actualizando...' : 'Restablecer Contraseña' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL CONFIRMACIÓN TOGGLE ESTADO (ACTIVAR / DESACTIVAR) -->
        <Modal :show="mostrarModalToggle" @close="mostrarModalToggle = false">
            <div class="p-6">
                <div class="text-center space-y-3">
                    <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center text-2xl"
                        :class="usuarioSeleccionado?.activo ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'">
                        {{ usuarioSeleccionado?.activo ? '⚠️' : '✅' }}
                    </div>

                    <h3 class="text-lg font-bold text-slate-800">
                        {{ usuarioSeleccionado?.activo ? '¿Desactivar esta cuenta de usuario?' : '¿Activar esta cuenta de usuario?' }}
                    </h3>

                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        <span v-if="usuarioSeleccionado?.activo">
                            El usuario <strong class="text-slate-800">{{ usuarioSeleccionado?.name }}</strong> no podrá iniciar sesión en la plataforma mientras su cuenta permanezca desactivada.
                        </span>
                        <span v-else>
                            El usuario <strong class="text-slate-800">{{ usuarioSeleccionado?.name }}</strong> podrá volver a iniciar sesión con sus credenciales habituales.
                        </span>
                    </p>

                    <div v-if="errores.general" class="p-3 bg-red-50 text-red-700 rounded-xl text-xs font-bold">
                        {{ errores.general }}
                    </div>

                    <div class="flex justify-center gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="mostrarModalToggle = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200">Cancelar</button>
                        <button type="button" @click="toggleStatusUsuario" :disabled="procesando"
                            class="px-5 py-2 text-white rounded-xl text-xs font-bold transition-all shadow-md active:scale-95 disabled:opacity-50"
                            :class="usuarioSeleccionado?.activo ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700'">
                            {{ procesando ? 'Procesando...' : (usuarioSeleccionado?.activo ? 'Sí, Desactivar' : 'Sí, Activar') }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

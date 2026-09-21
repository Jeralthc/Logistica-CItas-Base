<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuestLayout from '@/Layouts/GuestFullLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

// Estado
const paso = ref(1); // 1=buscar OC, 2=seleccionar fecha/hora, 3=confirmación
const cargando = ref(false);
const error = ref('');

// Reprogramar Estado
const isReprogramar = ref(false);
const reprogramarCitaId = ref(null);
const reprogramarMotivo = ref('');

// Paso 1: Buscar Orden
const numeroOrden = ref('');
const datosOrden = ref(null);
const duracionEstimada = ref(60);

// Paso 2: Seleccionar fecha y hora
const fechasDisponibles = ref([]);
const fechaSeleccionada = ref('');
const slotsDisponibles = ref([]);
const slotSeleccionado = ref(null);
const muelleSeleccionado = ref('');
const observaciones = ref('');
const cargandoSlots = ref(false);

// Paso 3: Confirmación y Registro
const citaConfirmada = ref(null);
const registroCompletado = ref(false);
const proveedorYaRegistrado = ref(false);

const formRegistro = ref({
    rif: '',
    password_base: '', // Debe ser la que el comprador le asignó
    email: '',
    telefono: '',
    asesor: '',
    cita_id: null,
    contacto_id: null,
    processing: false,
});

const contactosExistentes = ref([]);
const agregarNuevoContacto = ref(false);

// Citas existentes
const citasProgramadas = ref([]);
const tabActual = ref('activas');
const countActivas = ref(0);
const countFinalizadas = ref(0);
const filtroComprador = ref('Todos');
const compradoresActivos = ref([]);

const citasFiltradas = computed(() => {
    if (filtroComprador.value === 'Todos') {
        return citasProgramadas.value;
    }
    return citasProgramadas.value.filter(c => c.registrado_por_nombre === filtroComprador.value);
});

const esCitaExistente = ref(false);

// Modales y Reprogramación
const modalCancelar = ref(false);
const modalReprogramar = ref(false);
const citaSeleccionada = ref(null);
const motivoCancelacion = ref('');
const motivoReprogramacion = ref('');
const repFechaSeleccionada = ref('');
const repSlotsDisponibles = ref([]);
const repFechasDisponibles = ref([]);
const repSlotSeleccionado = ref(null);
const repMuelleSeleccionado = ref('');
const cargandoReprogramacion = ref(false);
const procesandoModal = ref(false);
const errorModal = ref('');

// Modal para configurar correos del proveedor al habilitar ODC (Comprador / Admin)
const showEmailModal = ref(false);
const modalEmailInput = ref('');
const modalEmailError = ref('');
const esErrorAutenticacion = ref(false);
const editarCorreoComprador = ref(false);
const modalEmailsAdicionales = ref([]);
const tempProveedorData = ref(null);
const contactosExistentesModal = ref([]);
const contactoModalSeleccionadoId = ref(null);

const agregarEmailAdicionalModal = (email = '') => {
    if (modalEmailsAdicionales.value.length < 2) {
        modalEmailsAdicionales.value.push(email);
    }
};

const eliminarEmailAdicionalModal = (index) => {
    modalEmailsAdicionales.value.splice(index, 1);
};

const correosErpSugeridosModal = computed(() => {
    const detectados = datosOrden.value?.emails_detectados_erp || [];
    const principal = (modalEmailInput.value || '').toLowerCase().trim();
    const adicionales = modalEmailsAdicionales.value.map(e => (e || '').toLowerCase().trim());
    return detectados.filter(em => {
        const mailLower = (em || '').toLowerCase().trim();
        return mailLower !== '' && mailLower !== principal && !adicionales.includes(mailLower);
    });
});

const usarEmailSugeridoModal = (email) => {
    if (!modalEmailInput.value || modalEmailInput.value.trim() === '') {
        modalEmailInput.value = email;
        return;
    }
    agregarEmailAdicionalModal(email);
};

const establecerComoPrincipalModal = (nuevoPrincipal, desdeIndiceAdicional = null) => {
    if (!nuevoPrincipal) return;
    const anteriorPrincipal = (modalEmailInput.value || '').trim();
    
    modalEmailInput.value = nuevoPrincipal;
    editarCorreoComprador.value = true;

    if (desdeIndiceAdicional !== null) {
        if (anteriorPrincipal !== '') {
            modalEmailsAdicionales.value[desdeIndiceAdicional] = anteriorPrincipal;
        } else {
            modalEmailsAdicionales.value.splice(desdeIndiceAdicional, 1);
        }
    } else {
        if (anteriorPrincipal !== '' && anteriorPrincipal.toLowerCase() !== nuevoPrincipal.toLowerCase()) {
            if (modalEmailsAdicionales.value.length < 2 && !modalEmailsAdicionales.value.map(e => (e || '').toLowerCase()).includes(anteriorPrincipal.toLowerCase())) {
                modalEmailsAdicionales.value.unshift(anteriorPrincipal);
            }
        }
    }
};

const onCambioContactoModal = () => {
    if (contactoModalSeleccionadoId.value && contactoModalSeleccionadoId.value !== 'nuevo') {
        const c = contactosExistentesModal.value.find(x => x.id === contactoModalSeleccionadoId.value);
        if (c) {
            modalEmailInput.value = c.email || modalEmailInput.value;
            editarCorreoComprador.value = false;
        }
    }
};

const abrirModalComprador = (data) => {
    const provEmail = data?.resumen?.Email_Proveedor || data?.Email_Proveedor || data?.proveedor_email || '';
    modalEmailInput.value = provEmail;
    editarCorreoComprador.value = !provEmail;
    modalEmailsAdicionales.value = [];
    modalEmailError.value = '';
    esErrorAutenticacion.value = false;
    tempProveedorData.value = {
        nombre: data.nombre_proveedor || data?.resumen?.Nombre_Proveedor || data?.resumen?.proveedor || 'Sin nombre',
        rif: data?.resumen?.Codigo_Proveedor || data?.Codigo_Proveedor || '',
    };
    contactosExistentesModal.value = data?.contactos_registrados || data?.contactos || [];
    contactoModalSeleccionadoId.value = data?.contacto_id || (contactosExistentesModal.value[0]?.id ?? 'nuevo');
    
    showEmailModal.value = true;
};

const guardarYEnviarOdc = async () => {
    modalEmailError.value = '';
    esErrorAutenticacion.value = false;
    const email = modalEmailInput.value.trim();
    if (!email) {
        modalEmailError.value = 'El correo electrónico principal es requerido.';
        return;
    }
    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexEmail.test(email)) {
        modalEmailError.value = 'El correo electrónico principal no es válido.';
        return;
    }

    const adicionalesValidos = [];
    for (let i = 0; i < modalEmailsAdicionales.value.length; i++) {
        const ad = (modalEmailsAdicionales.value[i] || '').trim();
        if (ad) {
            if (!regexEmail.test(ad)) {
                modalEmailError.value = `El correo adicional #${i + 1} (${ad}) no tiene un formato válido.`;
                return;
            }
            if (ad.toLowerCase() === email.toLowerCase()) {
                modalEmailError.value = `El correo adicional #${i + 1} es idéntico al correo principal.`;
                return;
            }
            if (adicionalesValidos.map(e => e.toLowerCase()).includes(ad.toLowerCase())) {
                modalEmailError.value = `El correo adicional #${i + 1} está repetido.`;
                return;
            }
            adicionalesValidos.push(ad);
        }
    }

    try {
        cargando.value = true;
        const resp = await axios.post('/api/odc/habilitar', {
            numero_oc: numeroOrden.value,
            proveedor: tempProveedorData.value.nombre,
            rif: tempProveedorData.value.rif,
            contacto_id: (contactoModalSeleccionadoId.value !== 'nuevo' ? contactoModalSeleccionadoId.value : null),
            email: email,
            emails_adicionales: adicionalesValidos,
            telefono: '0000000000',
            asesor: 'Vendedor'
        });
        if (resp.data) {
            infoHabilitacionExitosa.value = {
                link_acceso: resp.data.link_acceso || '',
                email_destino: resp.data.email_destino || email || '',
                emails_adicionales: resp.data.emails_adicionales || adicionalesValidos || [],
                proveedor_registrado: !!resp.data.proveedor_registrado,
                copiado: false,
            };
        }
        showEmailModal.value = false;
        odcHabilitadaExitosa.value = true;
        paso.value = 3;
    } catch (err) {
        if (err.response?.status === 401 || err.response?.status === 419 || err.response?.data?.message === 'Unauthenticated.') {
            modalEmailError.value = 'Su sesión de Compras ha expirado o no ha iniciado sesión. Debe iniciar sesión con su cuenta de Compras para habilitar la orden.';
            esErrorAutenticacion.value = true;
        } else {
            esErrorAutenticacion.value = false;
            modalEmailError.value = err.response?.data?.error 
                || err.response?.data?.message 
                || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : null)
                || 'Error al habilitar ODC.';
        }
    } finally {
        cargando.value = false;
    }
};

const cancelarEmailModal = () => {
    showEmailModal.value = false;
    cargando.value = false;
};

const buscarOrden = async () => {
    if (!numeroOrden.value) return;
    
    // Verificar si ya existe en las citas programadas
    const ordenNumUpper = numeroOrden.value.toUpperCase();
    const citaExist = citasProgramadas.value.find(c => c.numero_oc.toUpperCase() === ordenNumUpper && (c.estatus === 'programada' || c.estatus === 'en muelle'));
    
    if (citaExist) {
        const d = new Date(citaExist.fecha_cita);
        const durMin = Math.min(240, Math.max(30, Number(citaExist.duracion_minutos) || 60));
        const dFin = new Date(d.getTime() + durMin * 60000);
        citaConfirmada.value = {
            numero_oc: citaExist.numero_oc,
            fecha: d.toLocaleDateString('es-VE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
            hora: d.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true }),
            hora_fin: dFin.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true }),
            muelle: citaExist.muelle_asignado
        };
        esCitaExistente.value = true;
        paso.value = 3;
        return;
    }

    cargando.value = true;
    error.value = '';
    datosOrden.value = null;
    esCitaExistente.value = false;

    try {
        const resp = await axios.get(`/api/orden-completa/${numeroOrden.value}`);
        if (resp.data.status === 'Exitoso') {
            datosOrden.value = resp.data;
            duracionEstimada.value = resp.data.tiempos?.tiempo_optimo_minutos || 60;
            
            if (['comprador', 'admin'].includes(userRole.value)) {
                abrirModalComprador(resp.data);
                cargando.value = false;
                return;
            }
            
            paso.value = 2;
            cargarSlots();
        }
    } catch (e) {
        console.error('🔍 [Inspeccionador de Elementos - Red/Consola] Detalle de error ODC:', e.response?.data || e);
        if (e.response && e.response.status === 401) {
            error.value = 'Su sesión ha caducado. Por favor recargue la página (F5) o vuelva a iniciar sesión.';
        } else {
            const rawErr = String(e.response?.data?.error || (e.response?.data?.message && e.response?.data?.message !== 'Unauthenticated.' ? e.response?.data?.message : '') || '');
            if (rawErr.includes('SQLSTATE') || rawErr.includes('Driver') || rawErr.includes('SELECT') || rawErr.includes('Connection:') || rawErr.includes('ODBC Driver')) {
                error.value = '⚠️ No se pudo establecer conexión con el servidor del ERP en este momento. Por favor intente más tarde o consulte con el departamento de sistemas.';
            } else {
                error.value = rawErr || 'La Orden de Compra no existe en el ERP o no ha sido sincronizada.';
            }
        }
    } finally {
        cargando.value = false;
    }
};

const maxIntentosBusqueda = ref(0);

const cargarSlots = async () => {
    cargandoSlots.value = true;
    try {
        const isTI = (modoReserva.value === 'traslado_interno');
        const resp = await axios.get('/api/citas/slots', {
            params: { 
                fecha: fechaSeleccionada.value, 
                duracion: isTI ? duracionTraslado.value : duracionEstimada.value,
                sucursal: isTI ? sucursalDestinoTraslado.value : (datosOrden.value?.sucursal_destino || '0101'),
                tipo_operacion: isTI ? 'traslado_interno' : 'proveedor'
            }
        });
        slotsDisponibles.value = resp.data.slots;
        fechasDisponibles.value = resp.data.fechas_disponibles;
        if (!fechaSeleccionada.value && resp.data.fechas_disponibles.length > 0) {
            fechaSeleccionada.value = resp.data.fechas_disponibles[0].fecha;
            return;
        }

        // Auto-avanzar si todo está full
        if (fechaSeleccionada.value && slotsDisponibles.value.length > 0) {
            const tieneLibre = slotsDisponibles.value.some(s => s.disponible);
            if (!tieneLibre && maxIntentosBusqueda.value < 14) {
                maxIntentosBusqueda.value++;
                const d = new Date(fechaSeleccionada.value + 'T00:00:00');
                d.setDate(d.getDate() + 1);
                fechaSeleccionada.value = d.toISOString().split('T')[0];
                return;
            }
        }
        
        maxIntentosBusqueda.value = 0;

    } catch (e) {
        console.error(e);
        maxIntentosBusqueda.value = 0;
    } finally {
        cargandoSlots.value = false;
    }
};

watch(fechaSeleccionada, () => {
    slotSeleccionado.value = null;
    muelleSeleccionado.value = '';
    if (fechaSeleccionada.value) cargarSlots();
});

const seleccionarSlot = (slot) => {
    if (!slot.disponible) return;
    slotSeleccionado.value = slot;
    muelleSeleccionado.value = slot.muelles[0] || '';
};

const reservar = async () => {
    if (!slotSeleccionado.value || !muelleSeleccionado.value) return;
    cargando.value = true;
    error.value = '';

    try {
        const resp = await axios.post('/api/citas/reservar', {
            numero_oc: numeroOrden.value,
            proveedor: datosOrden.value.nombre_proveedor || 'Sin nombre',
            rif_proveedor: datosOrden.value?.resumen?.Codigo_Proveedor || datosOrden.value?.Codigo_Proveedor || '',
            fecha_cita: `${fechaSeleccionada.value} ${slotSeleccionado.value.hora}:00`,
            muelle_asignado: muelleSeleccionado.value,
            duracion_minutos: duracionEstimada.value,
            observaciones: observaciones.value,
        });

        citaConfirmada.value = resp.data.cita;
        formRegistro.value.cita_id = resp.data.cita.id;
        formRegistro.value.rif = datosOrden.value?.resumen?.Codigo_Proveedor || datosOrden.value?.Codigo_Proveedor || ''; 
        
        if (resp.data.proveedor_registrado) {
            proveedorYaRegistrado.value = true;
            contactosExistentes.value = resp.data.contactos || [];
            if (contactosExistentes.value.length > 0) {
                // Preseleccionar el primer contacto
                formRegistro.value.contacto_id = contactosExistentes.value[0].id;
                agregarNuevoContacto.value = false;
            } else {
                agregarNuevoContacto.value = true;
            }
        }

        paso.value = 3;
    } catch (e) {
        error.value = e.response?.data?.error || 'Error al reservar.';
    } finally {
        cargando.value = false;
    }
};

const registrarProveedor = async () => {
    formRegistro.value.processing = true;
    error.value = '';
    
    try {
        await axios.post('/api/citas/registrar-proveedor', formRegistro.value);
        registroCompletado.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al registrar la cuenta.';
    } finally {
        formRegistro.value.processing = false;
    }
};

const nuevaReserva = () => {
    odcHabilitadaExitosa.value = false;
    infoHabilitacionExitosa.value = {
        link_acceso: '',
        email_destino: '',
        emails_adicionales: [],
        proveedor_registrado: false,
        copiado: false,
    };
    paso.value = 1;
    numeroOrden.value = '';
    datosOrden.value = null;
    citaConfirmada.value = null;
    slotsDisponibles.value = [];
    citasProgramadas.value = [];
    cargarOdcsPendientes();
    // Resetear formHabilitar
    formHabilitar.value = {
        numero_oc: '',
        proveedor: '',
        rif: '',
        email: '',
        emails_adicionales: [],
        telefono: '',
        asesor: '',
        contacto_id: null
    };
    if (typeof productosOrden !== 'undefined') productosOrden.value = [];
    if (typeof tiempoOptimo !== 'undefined') tiempoOptimo.value = null;
    if (typeof slots !== 'undefined') slots.value = [];
    fechaSeleccionada.value = '';
    slotSeleccionado.value = null;
    muelleSeleccionado.value = '';
    duracionEstimada.value = 60;
    
    // Refresh lists so scheduled orders disappear
    if (userRole.value === 'proveedor') {
        cargarOdcsPendientes();
    }
    cargarCitas();
    
    observaciones.value = '';
    citaConfirmada.value = null;
    esCitaExistente.value = false;
    registroCompletado.value = false;
    proveedorYaRegistrado.value = false;
    error.value = '';
    formRegistro.value = {
        cita_id: null,
        contacto_id: null,
        rif: '',
        email: '',
        telefono: '',
        asesor: '',
        password_base: '12345678',
        processing: false
    };
    contactosExistentes.value = [];
    agregarNuevoContacto.value = false;
    cargarCitas();
};

const cargarCitas = async () => {
    try {
        const resp = await axios.get(`/api/citas?status=${tabActual.value}`);
        citasProgramadas.value = resp.data.citas;
        if (resp.data.compradores) {
            compradoresActivos.value = resp.data.compradores;
        }
        if (resp.data.counts) {
            countActivas.value = resp.data.counts.activas || 0;
            countFinalizadas.value = resp.data.counts.finalizadas || 0;
        }
    } catch (e) { console.error(e); }
};

const abrirModalCancelar = (cita) => {
    citaSeleccionada.value = cita;
    motivoCancelacion.value = '';
    errorModal.value = '';
    modalCancelar.value = true;
};

const cerrarModalCancelar = () => {
    modalCancelar.value = false;
    citaSeleccionada.value = null;
};

const confirmarCancelacion = async () => {
    if (!motivoCancelacion.value || motivoCancelacion.value.length < 5) {
        errorModal.value = 'El motivo debe tener al menos 5 caracteres.';
        return;
    }
    procesandoModal.value = true;
    errorModal.value = '';
    try {
        await axios.post(`/api/citas/${citaSeleccionada.value.id}/cancelar`, { motivo: motivoCancelacion.value });
        cargarCitas();
        cerrarModalCancelar();
    } catch (e) { 
        errorModal.value = e.response?.data?.error || 'Error al cancelar la cita.';
    } finally {
        procesandoModal.value = false;
    }
};

const abrirModalReprogramar = async (cita) => {
    citaSeleccionada.value = cita;
    motivoReprogramacion.value = '';
    errorModal.value = '';
    repFechaSeleccionada.value = '';
    repSlotSeleccionado.value = null;
    repMuelleSeleccionado.value = '';
    modalReprogramar.value = true;
    
    // Usar la fecha actual de la cita por defecto si es posible, o recargar
    const d = new Date(cita.fecha_cita);
    repFechaSeleccionada.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    await cargarSlotsReprogramacion(cita);
};

const cerrarModalReprogramar = () => {
    modalReprogramar.value = false;
    citaSeleccionada.value = null;
};

const maxIntentosReprogramacion = ref(0);

const cargarSlotsReprogramacion = async (cita) => {
    cargandoReprogramacion.value = true;
    try {
        const isTI = cita.es_traslado_interno || (cita.numero_oc && String(cita.numero_oc).startsWith('TI-'));
        const resp = await axios.get('/api/citas/slots', {
            params: { 
                fecha: repFechaSeleccionada.value, 
                duracion: cita.duracion_minutos || 60,
                sucursal: cita.muelle_asignado ? cita.muelle_asignado.substring(0, 4) : '0101',
                tipo_operacion: isTI ? 'traslado_interno' : 'proveedor',
                cita_id: cita.id
            }
        });
        repSlotsDisponibles.value = resp.data.slots;
        repFechasDisponibles.value = resp.data.fechas_disponibles;
        if (!repFechaSeleccionada.value && resp.data.fechas_disponibles.length > 0) {
            repFechaSeleccionada.value = resp.data.fechas_disponibles[0].fecha;
            return;
        }

        // Auto-avanzar si todo está full
        if (repFechaSeleccionada.value && repSlotsDisponibles.value.length > 0) {
            const tieneLibre = repSlotsDisponibles.value.some(s => s.disponible);
            if (!tieneLibre && maxIntentosReprogramacion.value < 14) {
                maxIntentosReprogramacion.value++;
                const d = new Date(repFechaSeleccionada.value + 'T00:00:00');
                d.setDate(d.getDate() + 1);
                repFechaSeleccionada.value = d.toISOString().split('T')[0];
                return;
            }
        }

        maxIntentosReprogramacion.value = 0;

    } catch (e) {
        console.error(e);
        maxIntentosReprogramacion.value = 0;
    } finally {
        cargandoReprogramacion.value = false;
    }
};

watch(repFechaSeleccionada, () => {
    repSlotSeleccionado.value = null;
    repMuelleSeleccionado.value = '';
    if (repFechaSeleccionada.value && modalReprogramar.value && citaSeleccionada.value) {
        cargarSlotsReprogramacion(citaSeleccionada.value);
    }
});

const seleccionarSlotReprogramacion = (slot) => {
    if (!slot.disponible) return;
    repSlotSeleccionado.value = slot;
    repMuelleSeleccionado.value = slot.muelles[0] || '';
};

const confirmarReprogramacion = async () => {
    if (!repSlotSeleccionado.value || !repMuelleSeleccionado.value) {
        errorModal.value = 'Debe seleccionar un horario y un muelle.';
        return;
    }
    if (!motivoReprogramacion.value || motivoReprogramacion.value.length < 5) {
        errorModal.value = 'El motivo debe tener al menos 5 caracteres.';
        return;
    }
    procesandoModal.value = true;
    errorModal.value = '';

    try {
        await axios.post(`/api/citas/${citaSeleccionada.value.id}/reprogramar`, {
            fecha_cita: `${repFechaSeleccionada.value} ${repSlotSeleccionado.value.hora}:00`,
            muelle_asignado: repMuelleSeleccionado.value,
            motivo: motivoReprogramacion.value,
        });

        cargarCitas();
        cerrarModalReprogramar();
    } catch (e) {
        errorModal.value = e.response?.data?.error || 'Error al reprogramar la cita.';
    } finally {
        procesandoModal.value = false;
    }
};

const formatFecha = (f) => {
    if (!f) return '—';
    return new Date(f).toLocaleDateString('es-VE', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
};
const formatHora = (f) => {
    if (!f) return '—';
    return new Date(f).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true });
};

const formatFechaHora = (f) => {
    if (!f) return '—';
    const isSoloFecha = typeof f === 'string' && (f.includes('00:00:00') || !f.includes(':'));
    const d = new Date(f);
    if (isNaN(d.getTime())) return f;
    const fecha = d.toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric' });
    if (isSoloFecha) {
        return fecha;
    }
    const hora = d.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: true });
    return `${fecha} · ${hora}`;
};

const statusColor = (s) => {
    const m = { programada: 'bg-amber-100 text-amber-700', 'en muelle': 'bg-blue-100 text-blue-700', finalizada: 'bg-emerald-100 text-emerald-700', cancelada: 'bg-red-100 text-red-700' };
    return m[s] || 'bg-slate-100 text-slate-600';
};

const getSucursalNombre = (codigo) => {
    const map = {
        '0101': 'Hipersuraki',
        '0102': 'Depósito General',
        '0111': 'Producción',
        '0115': 'Insumos',
        '0140': 'Depósito Carnes',
        '0141': 'Depósito Perecederos',
        '0150': 'Depósito Perecederos',
        '0160': 'Andinka',
        '0161': 'Andinka',
        '0171': 'Depósito Sucursales',
        '0180': 'Galpón Central',
        '01993': 'CC Yuan Lin',
        '01': 'Galpón Central',
        '02': 'Galpón Central',
        '03': 'Galpón Central',
        '04': 'Galpón Central'
    };
    return map[codigo] || codigo;
};


// Nuevos flujos Comprador/Proveedor y Traslado Interno
const userRole = computed(() => usePage().props.auth.user?.role || 'guest');
const isGalponUser = computed(() => !!usePage().props.auth.user?.es_galpon || usePage().props.auth.user?.username === 'GALPON.ALFONSO');
const modoReserva = ref('proveedor'); // 'proveedor', 'traslado_interno'

// Formulario Mínimo de Traslado Interno
const galponOrigenSeleccionado = ref('GALPÓN ALFONSO');
const sucursalDestinoTraslado = ref('0101');
const duracionTraslado = ref(60); // 30, 60, 90, 120 (max 2h)
const numeroTrasladoManual = ref('');
const observacionesTraslado = ref('');

onMounted(() => {
    if (isGalponUser.value) {
        modoReserva.value = 'traslado_interno';
        cargarSlots();
    }
});

watch([duracionTraslado, sucursalDestinoTraslado, modoReserva], () => {
    if (modoReserva.value === 'traslado_interno') {
        slotSeleccionado.value = null;
        muelleSeleccionado.value = '';
        cargarSlots();
    }
});

const agendarTrasladoInterno = async () => {
    if (!slotSeleccionado.value || !muelleSeleccionado.value) {
        error.value = 'Debe seleccionar una fecha y un horario de descarga disponible.';
        return;
    }
    cargando.value = true;
    error.value = '';

    const origen = galponOrigenSeleccionado.value;
    const rifProv = (origen.includes('ALFONSO')) ? 'J-10715201' : 'J-000000000';
    
    let numDoc = (numeroTrasladoManual.value || '').trim();
    if (!numDoc) {
        const rnd = Math.random().toString(36).substring(2, 6).toUpperCase();
        numDoc = 'TI-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + rnd;
    } else if (!numDoc.toUpperCase().startsWith('TI-')) {
        numDoc = 'TI-' + numDoc.toUpperCase();
    }

    try {
        const resp = await axios.post('/api/citas/reservar', {
            es_traslado_interno: true,
            galpon_origen: origen,
            numero_oc: numDoc,
            proveedor: `${origen} (TRASLADO INTERNO)`,
            rif_proveedor: rifProv,
            fecha_cita: `${fechaSeleccionada.value} ${slotSeleccionado.value.hora}:00`,
            muelle_asignado: muelleSeleccionado.value,
            duracion_minutos: duracionTraslado.value,
            observaciones: observacionesTraslado.value || 'Traslado Interno de Galpón',
        });

        citaConfirmada.value = resp.data.cita;
        formRegistro.value.cita_id = resp.data.cita.id;
        registroCompletado.value = true;
        paso.value = 3;
    } catch (e) {
        error.value = e.response?.data?.error || e.response?.data?.message || 'Error al agendar el traslado interno.';
    } finally {
        cargando.value = false;
    }
};

const odcsPendientes = ref([]);

// Flujo Comprador
const formHabilitar = ref({
    numero_oc: '',
    proveedor: '',
    rif: '',
    email: '',
    emails_adicionales: [],
    telefono: '',
    asesor: '',
    password_base: '',
    contacto_id: null
});
const habilitando = ref(false);
const contactoSeleccionadoId = ref(null);

const agregarEmailAdicional = (email = '') => {
    if (!formHabilitar.value.emails_adicionales) {
        formHabilitar.value.emails_adicionales = [];
    }
    if (formHabilitar.value.emails_adicionales.length < 2) {
        formHabilitar.value.emails_adicionales.push(email);
    }
};

const eliminarEmailAdicional = (index) => {
    if (formHabilitar.value.emails_adicionales) {
        formHabilitar.value.emails_adicionales.splice(index, 1);
    }
};

const correosErpSugeridos = computed(() => {
    const detectados = datosOrden.value?.emails_detectados_erp || [];
    const actualPrincipal = (formHabilitar.value.email || '').toLowerCase().trim();
    const adicionales = (formHabilitar.value.emails_adicionales || []).map(e => (e || '').toLowerCase().trim());
    return detectados.filter(em => {
        const mailLower = (em || '').toLowerCase().trim();
        return mailLower !== '' && mailLower !== actualPrincipal && !adicionales.includes(mailLower);
    });
});

const usarEmailSugerido = (email) => {
    if (!formHabilitar.value.email) {
        formHabilitar.value.email = email;
        return;
    }
    agregarEmailAdicional(email);
};

const seleccionarContactoExistente = () => {
    if (contactoSeleccionadoId.value === 'nuevo') {
        formHabilitar.value.asesor = '';
        formHabilitar.value.email = '';
        formHabilitar.value.telefono = '';
        formHabilitar.value.contacto_id = null;
        return;
    }
    
    const c = contactosExistentes.value.find(item => item.id === contactoSeleccionadoId.value);
    if (c) {
        formHabilitar.value.asesor = c.nombre || '';
        formHabilitar.value.email = c.email || '';
        formHabilitar.value.telefono = c.telefono || '';
        formHabilitar.value.contacto_id = c.id;
    }
};

const prepararHabilitacion = () => {
    formHabilitar.value.numero_oc = numeroOrden.value;
    formHabilitar.value.proveedor = datosOrden.value.nombre_proveedor || 'Sin nombre';
    formHabilitar.value.rif = datosOrden.value?.resumen?.Codigo_Proveedor || datosOrden.value?.Codigo_Proveedor || '';
    formHabilitar.value.emails_adicionales = [];
    
    const rawContactos = datosOrden.value?.contactos_registrados || datosOrden.value?.contactos || [];
    const listContactos = rawContactos.filter(c => c && c.email && !c.email.toLowerCase().includes('@proveedor.suraki.net'));
    contactosExistentes.value = listContactos;

    if (listContactos.length > 0) {
        contactoSeleccionadoId.value = listContactos[listContactos.length - 1].id;
        seleccionarContactoExistente();
    } else {
        contactoSeleccionadoId.value = null;
        formHabilitar.value.asesor = datosOrden.value?.proveedor_asesor || '';
        let initEmail = datosOrden.value?.proveedor_email || '';
        if (initEmail.toLowerCase().includes('@proveedor.suraki.net')) {
            initEmail = (datosOrden.value?.emails_detectados_erp && datosOrden.value.emails_detectados_erp[0]) || '';
        }
        formHabilitar.value.email = initEmail;
        formHabilitar.value.telefono = datosOrden.value?.proveedor_telefono || '';
        formHabilitar.value.contacto_id = null;
    }

    if (formHabilitar.value.email && formHabilitar.value.email.toLowerCase().includes('@proveedor.suraki.net')) {
        formHabilitar.value.email = (datosOrden.value?.emails_detectados_erp && datosOrden.value.emails_detectados_erp[0]) || '';
    }

    paso.value = 2; // Mostrar form de habilitar
};

const odcHabilitadaExitosa = ref(false);
const infoHabilitacionExitosa = ref({
    link_acceso: '',
    email_destino: '',
    emails_adicionales: [],
    proveedor_registrado: false,
    copiado: false,
});

const copiarEnlaceWhatsApp = async () => {
    if (!infoHabilitacionExitosa.value.link_acceso) return;
    try {
        await navigator.clipboard.writeText(infoHabilitacionExitosa.value.link_acceso);
        infoHabilitacionExitosa.value.copiado = true;
        setTimeout(() => {
            infoHabilitacionExitosa.value.copiado = false;
        }, 4000);
    } catch (e) {
        const input = document.createElement('input');
        input.value = infoHabilitacionExitosa.value.link_acceso;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        infoHabilitacionExitosa.value.copiado = true;
        setTimeout(() => {
            infoHabilitacionExitosa.value.copiado = false;
        }, 4000);
    }
};

const habilitarOdc = async () => {
    habilitando.value = true;
    error.value = '';
    try {
        const resp = await axios.post('/api/odc/habilitar', formHabilitar.value);
        if (resp.status === 206) {
            error.value = resp.data.message;
        }
        if (resp.data) {
            infoHabilitacionExitosa.value = {
                link_acceso: resp.data.link_acceso || '',
                email_destino: resp.data.email_destino || formHabilitar.value.email || '',
                emails_adicionales: resp.data.emails_adicionales || formHabilitar.value.emails_adicionales || [],
                proveedor_registrado: !!resp.data.proveedor_registrado,
                copiado: false,
            };
        }
        odcHabilitadaExitosa.value = true;
        paso.value = 3; // Mostrar éxito
    } catch (e) {
        if (e.response?.status === 401 || e.response?.status === 419 || e.response?.data?.message === 'Unauthenticated.') {
            error.value = 'Su sesión de Compras ha expirado o no ha iniciado sesión. Por favor inicie sesión nuevamente para habilitar la orden.';
        } else {
            error.value = e.response?.data?.message || 'Error al habilitar ODC';
        }
    } finally {
        habilitando.value = false;
    }
};

// Flujo Proveedor
const modalInteligente = ref(false);
const editarCorreo = ref(false);
const odcActiva = ref(null);
const formProveedor = ref({
    numero_oc: '',
    proveedor: '',
    numero_factura: '',
    peso_factura_ton: 0,
    formato_carga: 'suelta',
    tipo_vehiculo: '',
    categoria_sugerida: '',
    tipo_mercancia: '',
    fecha_cita: '',
    muelle_asignado: '',
    email_contacto: ''
});
const facturaFile = ref(null);
const errorFormProveedor = ref('');

const handleFacturaUpload = (event) => {
    facturaFile.value = event.target.files[0] || null;
};

const quitarFacturaFile = () => {
    facturaFile.value = null;
    const input = document.getElementById('facturaFileInput');
    if (input) input.value = '';
};

const getNombreVehiculo = (val) => {
    const map = {
        'minivan': 'Minivan / Super Carry (Hasta 950 kg)',
        'camioneta_panel': 'Camioneta Panel (700 kg a 1.5 Ton)',
        'cava_pequena': 'Camión 350 / Cava Pequeña (2.5 a 3.5 Ton)',
        'camion_liviano': 'Camión Liviano NPR / FVR (4.5 a 5.5 Ton)',
        'camion_mediano': 'Camión Mediano NQR / Cargo (6.5 a 8 Ton)',
        'camion_sencillo': 'Camión Sencillo Pesado (8 a 12 Ton)',
        'camion_toronto': 'Camión Toronto / Balancín (14 a 18 Ton)',
        'gandola_furgon': 'Gandola con Furgón (24 a 30 Ton)',
        'gandola_sider': 'Gandola Sider (24 a 28 Ton)',
    };
    return map[val] || val || 'No especificado';
};

const duracionCalculada = ref(60);

const calcularDuracionReactiva = async () => {
    if (!formProveedor.value.peso_factura_ton || formProveedor.value.peso_factura_ton <= 0) return;
    try {
        const resp = await axios.post('/api/calcular-duracion', {
            categoria: formProveedor.value.categoria_sugerida || formProveedor.value.tipo_mercancia || 'Alimentos 1 (Viveres)',
            peso_ton: formProveedor.value.peso_factura_ton,
            formato_carga: formProveedor.value.formato_carga
        });
        duracionCalculada.value = resp.data.duracion_minutos;
        duracionEstimada.value = duracionCalculada.value;
    } catch (e) {
        duracionCalculada.value = 60;
    }
};

watch(() => formProveedor.value.peso_factura_ton, calcularDuracionReactiva);
watch(() => formProveedor.value.formato_carga, calcularDuracionReactiva);
watch(() => formProveedor.value.categoria_sugerida, (newVal) => {
    formProveedor.value.tipo_mercancia = newVal;
    calcularDuracionReactiva();
});
watch(() => formProveedor.value.tipo_mercancia, calcularDuracionReactiva);

const formatMinutos = (m) => {
    if (!m) return '—';
    if (m < 60) return `${m} minutos`;
    const h = Math.floor(m / 60);
    const r = m % 60;
    const hText = h === 1 ? 'hora' : 'horas';
    return r > 0 ? `${h} ${hText} ${r} min` : `${h} ${hText}`;
};

// Validación de cadena de frío
const vehiculoRequiereFrio = computed(() => {
    return formProveedor.value.tipo_vehiculo === 'cava_pequena';
});

const esMercanciaPerecedera = computed(() => {
    const cat = (formProveedor.value.categoria_sugerida || formProveedor.value.tipo_mercancia || '').toLowerCase();
    return cat.includes('perecedero') || cat.includes('charcuteria') || cat.includes('carniceria') || cat.includes('pescaderia') || cat.includes('frutas') || cat.includes('verduras');
});

const cargarOdcsPendientes = async () => {
    if (userRole.value !== 'proveedor') return;
    try {
        const resp = await axios.get('/api/odc/mis-pendientes');
        odcsPendientes.value = resp.data.ordenes;
    } catch (e) {}
};

const abrirModalProveedor = (odc) => {
    odcActiva.value = odc;
    formProveedor.value.numero_oc = odc.numero_oc;
    formProveedor.value.proveedor = odc.resumen?.nombre_proveedor || 'Proveedor';
    formProveedor.value.categoria_sugerida = odc.categoria_sugerida || 'Alimentos 1 (Viveres)';
    formProveedor.value.tipo_mercancia = odc.categoria_sugerida || 'Alimentos 1 (Viveres)';
    formProveedor.value.peso_factura_ton = odc.peso_estimado_ton || 0;
    formProveedor.value.email_contacto = usePage().props.auth.user.email;
    facturaFile.value = null;
    editarCorreo.value = false;
    errorFormProveedor.value = '';
    
    calcularDuracionReactiva();
    modalInteligente.value = true;
};

const restaurarValoresOdcOriginal = () => {
    if (!odcActiva.value) return;
    formProveedor.value.numero_factura = '';
    formProveedor.value.peso_factura_ton = odcActiva.value.peso_estimado_ton || 0;
    formProveedor.value.formato_carga = 'suelta';
    formProveedor.value.tipo_vehiculo = '';
    formProveedor.value.categoria_sugerida = odcActiva.value.categoria_sugerida || 'Alimentos 1 (Viveres)';
    formProveedor.value.tipo_mercancia = odcActiva.value.categoria_sugerida || 'Alimentos 1 (Viveres)';
    formProveedor.value.email_contacto = usePage().props.auth.user.email;
    editarCorreo.value = false;
    quitarFacturaFile();
    errorFormProveedor.value = '';
    calcularDuracionReactiva();
};

const continuarAHorarios = () => {
    errorFormProveedor.value = '';

    // Validar correo si se editó
    if (editarCorreo.value && formProveedor.value.email_contacto) {
        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regexEmail.test(formProveedor.value.email_contacto.trim())) {
            errorFormProveedor.value = 'El correo del asesor introducido no es válido.';
            return;
        }
    }

    // Validar vehículo primero
    if (!formProveedor.value.tipo_vehiculo) {
        errorFormProveedor.value = 'Por favor seleccione el Tipo de Vehículo que realizará el despacho.';
        return;
    }

    const capacidadesMaxPorVehiculo = {
        'minivan': 1.0,
        'camioneta_panel': 1.5,
        'cava_pequena': 3.5,
        'camion_350': 3.5,
        'camion_600': 5.0,
        'camion_750': 7.0,
        'camion_sencillo': 12.0,
        'camion_toronto': 18.0,
        'gandola_furgon': 32.0,
        'gandola_sider': 30.0,
    };

    let pesoNum = Number(formProveedor.value.peso_factura_ton);
    if (isNaN(pesoNum) || pesoNum <= 0) {
        errorFormProveedor.value = 'El peso real debe ser mayor a 0 Toneladas (Ejemplo: 0.050 para 50 Kg, o 2.5 Ton).';
        return;
    }

    const capMax = capacidadesMaxPorVehiculo[formProveedor.value.tipo_vehiculo] || 32.0;

    // Si colocó un peso que excede la capacidad del vehículo y es mayor o igual a 10 (ej: 50 kg para una cava 350 de 3.5 Ton)
    if (pesoNum > capMax && pesoNum >= 10) {
        // El usuario escribió kilogramos por confusión en lugar de toneladas
        const pesoEnTon = Number((pesoNum / 1000).toFixed(4));
        if (pesoEnTon <= capMax) {
            formProveedor.value.peso_factura_ton = pesoEnTon;
            pesoNum = pesoEnTon;
        } else {
            errorFormProveedor.value = `El peso indicado (${pesoNum} Toneladas) excede la capacidad máxima de este vehículo (${capMax} Ton). Si indicó el peso en Kilogramos (${pesoNum} Kg), debe escribir ${(pesoNum / 1000).toFixed(3)} Ton.`;
            return;
        }
    } else if (pesoNum > 35) {
        // En general, más de 35 Toneladas siempre son Kilogramos mal ingresados
        formProveedor.value.peso_factura_ton = Number((pesoNum / 1000).toFixed(4));
        pesoNum = formProveedor.value.peso_factura_ton;
    }

    if (pesoNum > 35) {
        errorFormProveedor.value = 'El peso indicado excede la capacidad máxima de recepción (35 Toneladas).';
        return;
    }

    if (!formProveedor.value.numero_factura || formProveedor.value.numero_factura.trim() === '') {
        formProveedor.value.numero_factura = 'Por facturar';
    }

    modalInteligente.value = false;
    numeroOrden.value = formProveedor.value.numero_oc;
    const sucursalDestino = odcActiva.value?.resumen?.sucursal_destino 
        || odcActiva.value?.resumen?.Muelle_Destino 
        || odcActiva.value?.destino 
        || '0101';
    datosOrden.value = { sucursal_destino: sucursalDestino };
    paso.value = 2; // Ir a slots
    cargarSlots();
};

const reservarComoProveedor = async () => {
    if (!slotSeleccionado.value || !muelleSeleccionado.value) return;
    cargando.value = true;
    error.value = '';
    try {
        formProveedor.value.fecha_cita = `${fechaSeleccionada.value} ${slotSeleccionado.value.hora}:00`;
        formProveedor.value.muelle_asignado = muelleSeleccionado.value;
        
        if (!formProveedor.value.numero_factura) formProveedor.value.numero_factura = 'Por facturar';
        if (!formProveedor.value.tipo_vehiculo) formProveedor.value.tipo_vehiculo = 'camioneta_panel';

        const data = new FormData();
        Object.keys(formProveedor.value).forEach(key => {
            data.append(key, formProveedor.value[key]);
        });
        if (facturaFile.value) {
            data.append('factura_file', facturaFile.value);
        }

        let resp;
        if (isReprogramar.value) {
            resp = await axios.post(`/api/citas/${reprogramarCitaId.value}/reprogramar`, {
                fecha_cita: formProveedor.value.fecha_cita,
                muelle_asignado: formProveedor.value.muelle_asignado,
                motivo: reprogramarMotivo.value
            });
            citaConfirmada.value = {
                numero_oc: formProveedor.value.numero_oc,
                proveedor: formProveedor.value.proveedor,
                fecha_cita: formProveedor.value.fecha_cita,
                muelle_asignado: formProveedor.value.muelle_asignado
            };
        } else {
            resp = await axios.post('/api/odc/agendar', data, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            });
            citaConfirmada.value = resp.data.cita;
        }
        
        // Refrescar órdenes para que ya no aparezca la que acabamos de agendar
        cargarOdcsPendientes();
        paso.value = 3;
    } catch (e) {
        if (e.response?.data?.errors) {
            error.value = Object.values(e.response.data.errors).flat().join(' | ');
        } else {
            error.value = e.response?.data?.error || e.response?.data?.message || 'Error al agendar/reprogramar';
        }
    } finally {
        cargando.value = false;
    }
};

onMounted(() => {
    const repCitaStr = localStorage.getItem('reprogramar_cita');
    if (repCitaStr) {
        try {
            const cita = JSON.parse(repCitaStr);
            isReprogramar.value = true;
            reprogramarCitaId.value = localStorage.getItem('reprogramar_cita_id');
            reprogramarMotivo.value = localStorage.getItem('reprogramar_motivo');
            
            formProveedor.value.numero_oc = cita.numero_oc;
            formProveedor.value.proveedor = cita.proveedor;
            formProveedor.value.tipo_mercancia = cita.categoria ?? 'Alimentos 1 (Viveres)';
            formProveedor.value.peso_factura_ton = cita.peso_toneladas ?? 1;
            formProveedor.value.formato_carga = cita.formato_carga ?? 'Paletizada';
            
            // Limpiar localStorage
            localStorage.removeItem('reprogramar_cita');
            localStorage.removeItem('reprogramar_cita_id');
            localStorage.removeItem('reprogramar_motivo');
            
            // Pasar a paso 2
            paso.value = 2;
            calcularDuracionReactiva();
            cargarSlots();
        } catch (e) {
            cargarOdcsPendientes();
        }
    } else {
        cargarOdcsPendientes();
    }
});

onMounted(cargarCitas);
</script>

<template>
    <Head title="Reservar Cita" />
    <component :is="$page.props.auth.user ? AuthenticatedLayout : GuestLayout">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('dashboard')" class="bg-white hover:bg-slate-100 text-slate-700 px-3 py-1.5 rounded-xl transition-all border border-slate-200 shadow-sm flex items-center gap-2 font-bold text-xs group" title="Volver al Panel Principal">
                        <svg class="w-4 h-4 text-slate-500 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        <span>Volver al Inicio</span>
                    </Link>
                    <h2 class="font-bold text-xl sm:text-2xl text-slate-800 leading-tight">
                        Reservar Cita <span class="text-red-600">| Programar Recepción</span>
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="/Manual_Usuario_Portal_Proveedor_CITSUR.pdf" target="_blank" class="bg-white hover:bg-slate-100 text-slate-700 px-3 py-1.5 rounded-xl transition-all border border-slate-200 shadow-sm flex items-center gap-1.5 font-bold text-xs" title="Abrir y Descargar Manual Oficial en PDF">
                        <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Manual PDF</span>
                    </a>
                    <div class="flex items-center gap-2 text-sm text-slate-500 bg-slate-100 px-4 py-1 rounded-full border border-slate-200 w-fit">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                        Horario: 8:00 AM - 7:00 PM
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8 sm:py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- ========== FLUJO ADMINISTRATIVO / RECEPTOR / COMPRADOR ========== -->
                <div v-if="userRole !== 'proveedor' && paso !== 3">

                <!-- Indicador de pasos -->
                <div class="flex items-center justify-center gap-4 mb-8">
                    <div v-for="p in 3" :key="p" class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-black transition-all"
                            :class="paso >= p ? 'bg-red-600 text-white' : 'bg-slate-200 text-slate-400'">{{ p }}</div>
                        <span class="text-sm font-bold hidden sm:inline" :class="paso >= p ? 'text-slate-800' : 'text-slate-400'">
                            {{ p === 1 ? 'Buscar Orden' : p === 2 ? 'Seleccionar Horario' : 'Confirmación' }}
                        </span>
                        <div v-if="p < 3" class="w-8 h-[2px]" :class="paso > p ? 'bg-red-600' : 'bg-slate-200'"></div>
                    </div>
                </div>

                <!-- ========== PASO 1: Buscar Orden ========== -->
                <div v-if="paso === 1" class="max-w-2xl mx-auto">
                    <!-- Búsqueda de ODC para proveedores -->
                    <div class="bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl p-8 border border-slate-100">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <h3 class="text-xl font-black text-slate-800">Programar Recepción de Mercancía</h3>
                            <p class="text-slate-500 text-sm mt-1">Ingrese el número de Orden de Compra para reservar una cita</p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-grow">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </span>
                                <input v-model="numeroOrden" @keyup.enter="buscarOrden" type="text" placeholder="Ej: E00001167"
                                    class="block w-full pl-11 pr-4 py-3.5 border-slate-200 focus:border-red-600 focus:ring-red-600/20 rounded-2xl transition-all shadow-sm text-lg font-mono">
                            </div>
                            <button @click="buscarOrden" :disabled="cargando"
                                class="bg-red-600 text-white px-8 py-3.5 rounded-2xl font-bold hover:bg-red-700 transition-all shadow-lg shadow-red-600/20 active:scale-95 disabled:opacity-50 w-full sm:w-auto">
                                {{ cargando ? 'Buscando...' : 'BUSCAR' }}
                            </button>
                        </div>
                        <p v-if="error" class="text-red-500 font-medium mt-3 text-sm">⚠️ {{ error }}</p>
                    </div>
                </div>

                    <!-- Pestañas y Filtros (Solo para Recepción/Admin/Comprador) -->
                    <div v-if="userRole !== 'proveedor'" class="mt-12 mb-6">
                        <div class="flex items-center gap-6 border-b border-slate-200 pb-2">
                            <button @click="tabActual = 'activas'; cargarCitas()" 
                                class="pb-2 font-black text-sm uppercase tracking-wider transition-colors border-b-2 flex items-center gap-1.5"
                                :class="tabActual === 'activas' ? 'text-red-600 border-red-600' : 'text-slate-400 border-transparent hover:text-slate-600'">
                                <span>ACTIVAS</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px]" :class="tabActual === 'activas' ? 'bg-red-100 text-red-700' : 'bg-slate-200 text-slate-600'">
                                    {{ countActivas }}
                                </span>
                            </button>
                            <button @click="tabActual = 'finalizadas'; cargarCitas()" 
                                class="pb-2 font-black text-sm uppercase tracking-wider transition-colors border-b-2 flex items-center gap-1.5"
                                :class="tabActual === 'finalizadas' ? 'text-slate-800 border-slate-800' : 'text-slate-400 border-transparent hover:text-slate-600'">
                                <span>FINALIZADAS (HISTORIAL)</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px]" :class="tabActual === 'finalizadas' ? 'bg-slate-200 text-slate-800' : 'bg-slate-100 text-slate-500'">
                                    {{ countFinalizadas }}
                                </span>
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-3 mt-4 overflow-x-auto pb-2 scrollbar-hide">
                            <span class="text-xs font-bold text-slate-400 uppercase mr-2 whitespace-nowrap">FILTRAR POR:</span>
                            <button @click="filtroComprador = 'Todos'"
                                class="px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all border flex items-center gap-1.5"
                                :class="filtroComprador === 'Todos' ? 'bg-slate-900 text-white border-slate-900 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                                Todos
                            </button>
                            <button v-for="comp in compradoresActivos" :key="comp.id"
                                @click="filtroComprador = comp.name"
                                class="px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all border flex items-center gap-1.5"
                                :class="filtroComprador === comp.name ? 'bg-slate-900 text-white border-slate-900 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                {{ comp.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Citas programadas -->
                    <div v-if="citasFiltradas.length > 0" class="mb-8 bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100">
                        <div class="bg-slate-900 px-8 py-5 flex items-center justify-between">
                            <div>
                                <h3 class="text-white font-bold text-base">📋 Citas {{ tabActual === 'activas' ? 'Programadas' : 'Finalizadas' }}</h3>
                                <p class="text-slate-400 text-xs mt-0.5">{{ tabActual === 'activas' ? 'Próximas recepciones agendadas' : 'Historial de recepciones completadas' }}</p>
                            </div>
                            <span v-if="filtroComprador !== 'Todos'" class="bg-slate-700 text-slate-200 text-xs px-3 py-1 rounded-full font-bold">Filtrado por: {{ filtroComprador }}</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="cita in citasFiltradas" :key="cita.id"
                                class="px-4 sm:px-8 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 sm:gap-0 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-slate-100 rounded-xl flex flex-col items-center justify-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase">{{ new Date(cita.fecha_cita).toLocaleDateString('es-VE', { month: 'short' }) }}</span>
                                        <span class="text-lg font-black text-slate-800 -mt-0.5">{{ new Date(cita.fecha_cita).getDate() }}</span>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                            <p class="font-bold text-slate-800 font-mono">{{ cita.numero_oc }}</p>
                                            <span v-if="cita.vendedor_nombre" class="text-[10px] font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded uppercase flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                VENDEDOR: {{ cita.vendedor_nombre }}
                                            </span>
                                            <span v-if="cita.registrado_por_nombre" class="text-[10px] font-bold bg-blue-50 text-blue-600 px-2 py-0.5 rounded uppercase flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                ATENDIDO POR: {{ cita.registrado_por_nombre }}
                                            </span>
                                            <span v-if="cita.fecha_envio_comprador || cita.fecha_emision || cita.fecha_odc" class="text-[10px] font-bold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded uppercase flex items-center gap-1 border border-indigo-200" title="Fecha y hora en que el comprador envió/habilitó la orden">
                                                📤 ENVÍO: {{ formatFechaHora(cita.fecha_envio_comprador || cita.fecha_emision || cita.fecha_odc) }}
                                            </span>
                                            <span v-if="cita.fecha_registro_cita || cita.created_at" class="text-[10px] font-bold bg-purple-50 text-purple-700 px-2 py-0.5 rounded uppercase flex items-center gap-1 border border-purple-200" title="Fecha y hora en que el proveedor registró la cita">
                                                ⏱️ REGISTRO: {{ formatFechaHora(cita.fecha_registro_cita || cita.created_at) }}
                                            </span>
                                            <span v-if="cita.estatus === 'finalizada'" class="text-[10px] font-bold bg-emerald-50 text-emerald-800 px-2 py-0.5 rounded uppercase flex items-center gap-1 border border-emerald-300" title="Fecha y hora en que fue completada la recepción en el muelle">
                                                ✅ COMPLETADA: {{ formatFechaHora(cita.fecha_completada || cita.updated_at) }}
                                                <span v-if="cita.completada_por_nombre" class="font-normal text-emerald-600">({{ cita.completada_por_nombre }})</span>
                                            </span>
                                            <span v-else-if="cita.estatus === 'programada'" class="text-[10px] font-bold bg-amber-50 text-amber-800 px-2 py-0.5 rounded uppercase flex items-center gap-1 border border-amber-200" title="Aún no completada en el muelle">
                                                ⏳ SIN COMPLETAR
                                            </span>
                                            <span v-if="cita.numero_factura" class="text-[10px] font-bold bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded uppercase flex items-center gap-1">
                                                📄 Factura: {{ cita.numero_factura }}
                                            </span>
                                            <a v-if="cita.factura_url" :href="cita.factura_url" target="_blank" 
                                                class="text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-0.5 rounded transition-colors flex items-center gap-1"
                                                title="Ver factura adjunta">
                                                👁️ Ver Factura
                                            </a>
                                        </div>
                                        <p class="text-xs text-slate-400">{{ cita.proveedor }} · {{ formatHora(cita.fecha_cita) }} · Sucursal {{ getSucursalNombre(cita.muelle_asignado) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                                    <span :class="statusColor(cita.estatus)" class="text-[10px] font-black px-2.5 py-1 rounded-full uppercase">{{ cita.estatus }}</span>
                                    <div v-if="cita.estatus === 'programada' && (!cita.bloqueado_para_comprador || $page.props.auth?.user?.role !== 'comprador')" class="flex items-center gap-1">
                                        <button @click="abrirModalReprogramar(cita)"
                                            class="text-slate-400 hover:text-blue-600 transition-colors p-1" title="Reprogramar cita">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button @click="abrirModalCancelar(cita)"
                                            class="text-slate-400 hover:text-red-600 transition-colors p-1" title="Cancelar cita">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                
                <!-- ========== PASO 2: Habilitar ODC (Comprador) o Agendar (Receptor/Legacy) ========== -->
                <div v-if="paso === 2 && userRole !== 'proveedor'" class="max-w-4xl mx-auto">
                    <!-- Flujo Habilitar ODC para Comprador/Admin -->
                    <div v-if="['comprador', 'admin'].includes(userRole)" class="bg-white overflow-hidden shadow-xl rounded-3xl p-5 sm:p-8 border border-slate-100 mb-8">
                        <div class="text-center mb-6">
                            <h3 class="text-xl font-black text-slate-800">Habilitar Orden de Compra</h3>
                            <p class="text-slate-500">Notifique al proveedor ingresando sus datos o seleccionando un asesor/vendedor registrado.</p>
                        </div>
                        
                        <!-- Banner de Estado de Habilitación & Trazabilidad de Correos -->
                        <div v-if="datosOrden?.estatus_habilitacion === 'habilitada' || (datosOrden?.email_logs && datosOrden.email_logs.length > 0)" class="mb-6 p-4 rounded-2xl border" :class="datosOrden?.estatus_habilitacion === 'habilitada' ? 'bg-emerald-50/80 border-emerald-200' : 'bg-slate-50 border-slate-200'">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span v-if="datosOrden?.estatus_habilitacion === 'habilitada'" class="bg-emerald-600 text-white text-xs font-black px-2.5 py-1 rounded-full uppercase tracking-wider">🟢 Habilitada Activa</span>
                                    <span v-else-if="datosOrden?.estatus_habilitacion === 'agendada'" class="bg-blue-600 text-white text-xs font-black px-2.5 py-1 rounded-full uppercase tracking-wider">🔵 Cita Agendada</span>
                                    <span v-else class="bg-amber-500 text-white text-xs font-black px-2.5 py-1 rounded-full uppercase tracking-wider">🟡 Pendiente</span>
                                    
                                    <span v-if="datosOrden?.habilitada_info" class="text-xs text-slate-600 font-medium">
                                        por <strong>{{ datosOrden.habilitada_info.nombre }}</strong> el {{ datosOrden.habilitada_info.fecha }}
                                    </span>
                                </div>
                            </div>
                            
                            <div v-if="datosOrden?.email_logs && datosOrden.email_logs.length > 0" class="mt-3 space-y-2">
                                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    Historial de Envíos de Correo para esta ODC:
                                </p>
                                <div v-for="log in datosOrden.email_logs" :key="log.id" class="bg-white p-3 rounded-xl border border-slate-200 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-sm">
                                    <div>
                                        <span class="font-bold text-slate-800">✉️ {{ log.email_destino }}</span>
                                        <span v-if="log.vendedor_nombre" class="text-slate-500 ml-2">({{ log.vendedor_nombre }})</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-slate-400 font-mono text-[11px]">{{ log.created_at }}</span>
                                        <span v-if="log.estatus === 'exitoso'" class="bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                                            ✓ Enviado Exitosamente
                                        </span>
                                        <span v-else class="bg-red-100 text-red-700 font-bold px-2 py-0.5 rounded-md flex items-center gap-1" :title="log.error_mensaje">
                                            ⚠️ Error de Entrega
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selector de contactos registrados para este RIF -->
                        <div v-if="contactosExistentes.length > 0" class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                            <label class="block text-sm font-bold text-slate-800 mb-1">📋 Vendedores / Contactos Registrados para RIF {{ formHabilitar.rif }}</label>
                            <p class="text-xs text-slate-500 mb-3">Seleccione la persona o correo que debe recibir la notificación para esta orden de compra:</p>
                            <select v-model="contactoSeleccionadoId" @change="seleccionarContactoExistente" class="w-full border-slate-300 rounded-xl text-sm font-medium shadow-sm focus:border-red-500 focus:ring-red-500 bg-white py-2.5">
                                <option v-for="c in contactosExistentes" :key="c.id" :value="c.id">
                                    👤 {{ c.nombre || 'Asesor' }} — ✉️ {{ c.email }} {{ c.telefono ? '— 📞 ' + c.telefono : '' }}
                                </option>
                                <option value="nuevo">➕ Registrar / Ingresar Nuevo Asesor o Correo...</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700">Nombre del Asesor o Vendedor Comercial</label>
                                <input v-model="formHabilitar.asesor" type="text" placeholder="Ej: Juan Pérez" class="mt-1 w-full border-slate-300 rounded-lg shadow-sm focus:border-red-500 focus:ring-red-500">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700">Correo Electrónico (Principal) *</label>
                                <input v-model="formHabilitar.email" type="email" placeholder="Para enviar la notificación" class="mt-1 w-full border-slate-300 rounded-lg shadow-sm focus:border-red-500 focus:ring-red-500">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700">Teléfono</label>
                                <input v-model="formHabilitar.telefono" type="text" placeholder="Ej: 0414-1234567" class="mt-1 w-full border-slate-300 rounded-lg shadow-sm focus:border-red-500 focus:ring-red-500">
                            </div>
                        </div>

                        <!-- Sección de Correos Adicionales (Hasta 2 adicionales = Total 3 correos) -->
                        <div class="mt-5 p-4 bg-slate-50/80 rounded-2xl border border-slate-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                        <span>📧 Correos Adicionales en Copia</span>
                                        <span class="text-xs font-medium text-slate-500">(Opcional · Máx. 2 adicionales · Total 3 correos)</span>
                                    </h4>
                                    <p class="text-xs text-slate-500">Recibirán la misma notificación de activación y detalles de la cita.</p>
                                </div>
                                <button 
                                    v-if="formHabilitar.emails_adicionales.length < 2"
                                    type="button" 
                                    @click="agregarEmailAdicional('')"
                                    class="text-xs font-bold text-red-600 hover:text-red-700 bg-white hover:bg-red-50 px-3 py-1.5 rounded-xl border border-red-200 shadow-sm transition-all flex items-center gap-1 self-start sm:self-auto">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                    Agregar otro correo
                                </button>
                            </div>

                            <!-- Lista de inputs para correos adicionales -->
                            <div v-if="formHabilitar.emails_adicionales.length > 0" class="space-y-2.5 mb-3">
                                <div v-for="(emAd, idx) in formHabilitar.emails_adicionales" :key="idx" class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-500 w-20 flex-shrink-0">Copia #{{ idx + 1 }}:</span>
                                    <input 
                                        v-model="formHabilitar.emails_adicionales[idx]" 
                                        type="email" 
                                        placeholder="correo.adicional@empresa.com" 
                                        class="flex-1 border-slate-300 rounded-lg shadow-sm focus:border-red-500 focus:ring-red-500 text-sm py-1.5 px-3">
                                    <button 
                                        type="button" 
                                        @click="eliminarEmailAdicional(idx)" 
                                        class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" 
                                        title="Quitar este correo">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Sugerencias de correos detectados en ERP si los hay -->
                            <div v-if="correosErpSugeridos.length > 0" class="pt-2.5 border-t border-slate-200/80 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-bold text-slate-600 flex items-center gap-1">
                                    <span>💡 Detectados en Ficha Proveedor (ERP):</span>
                                </span>
                                <button 
                                    v-for="erpMail in correosErpSugeridos" 
                                    :key="erpMail"
                                    type="button"
                                    @click="usarEmailSugerido(erpMail)"
                                    :disabled="formHabilitar.emails_adicionales.length >= 2 && formHabilitar.email"
                                    class="text-xs bg-white hover:bg-red-50 hover:border-red-300 text-slate-700 font-medium px-2.5 py-1 rounded-full border border-slate-300 shadow-sm transition-all flex items-center gap-1.5 disabled:opacity-40 disabled:hover:bg-white disabled:hover:border-slate-300"
                                    :title="'Click para usar ' + erpMail">
                                    <span>{{ erpMail }}</span>
                                    <span class="text-red-600 font-black">+</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col-reverse sm:flex-row justify-center gap-3">
                            <button @click="nuevaReserva" class="px-6 py-3 font-bold text-slate-500 hover:text-slate-800 transition-colors">CANCELAR</button>
                            <button @click="habilitarOdc" :disabled="habilitando"
                                class="bg-red-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-red-700 transition-all shadow-lg active:scale-95 disabled:opacity-50">
                                {{ habilitando ? 'PROCESANDO...' : (datosOrden?.estatus_habilitacion === 'habilitada' ? '📧 REENVIAR CORREO / ACTUALIZAR NOTIFICACIÓN' : 'HABILITAR ORDEN PARA EL PROVEEDOR') }}
                            </button>
                        </div>
                    </div>

                    <!-- Banner para visitantes / sesión expirada -->
                    <div v-if="userRole === 'guest'" class="bg-amber-50/90 border border-amber-200 rounded-3xl p-6 sm:p-8 text-center mb-8 shadow-sm">
                        <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl">
                            🔒
                        </div>
                        <h4 class="text-base font-black text-slate-800 mb-1">Módulo para Compradores y Administradores</h4>
                        <p class="text-xs text-slate-600 max-w-md mx-auto mb-4">
                            Para configurar correos de notificación y habilitar órdenes de compra para los proveedores, debe identificarse con su cuenta del departamento de Compras.
                        </p>
                        <a :href="route('login')" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-red-600/20 transition-all">
                            <span>🔑 Iniciar Sesión en Compras</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                    </div>

                    <div class="flex items-center gap-4 my-8">
                        <div class="h-px bg-slate-200 flex-grow"></div>
                        <span class="text-xs font-bold text-slate-400 uppercase">O Programar Manualmente</span>
                        <div class="h-px bg-slate-200 flex-grow"></div>
                    </div>

                    <!-- CALENDARIO VIEJO (Para agendar manualmente) -->
                    <div class="max-w-7xl mx-auto">

                    <!-- Info de la orden -->
                    <div class="bg-slate-900 rounded-2xl p-4 md:p-5 mb-6 text-white w-full shadow-lg relative overflow-hidden">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 md:gap-6 w-full">
                            
                            <!-- Grid para movil, flex para desktop -->
                            <div class="grid grid-cols-2 md:flex md:flex-row md:items-center gap-y-4 gap-x-2 w-full min-w-0">
                                
                                <div class="flex flex-col min-w-0">
                                    <p class="text-[10px] text-slate-400 font-black uppercase">Orden de Compra</p>
                                    <p class="text-lg md:text-xl font-black font-mono text-red-500 truncate">{{ numeroOrden }}</p>
                                </div>
                                
                                <div class="hidden md:block h-8 w-[1px] bg-slate-700 mx-2 lg:mx-4"></div>
                                
                                <div class="flex flex-col min-w-0">
                                    <p class="text-[10px] text-slate-400 font-black uppercase">Proveedor</p>
                                    <p class="text-sm font-bold truncate" :title="datosOrden?.nombre_proveedor">{{ datosOrden?.nombre_proveedor }}</p>
                                </div>
                                
                                <div class="hidden md:block h-8 w-[1px] bg-slate-700 mx-2 lg:mx-4"></div>
                                
                                <div class="flex flex-col min-w-0 col-span-1">
                                    <p class="text-[10px] text-slate-400 font-black uppercase">Destino</p>
                                    <p class="text-xs md:text-sm font-bold text-blue-400 leading-tight break-words whitespace-normal">{{ datosOrden?.sucursal_nombre }}</p>
                                </div>
                                
                                <div class="hidden md:block h-8 w-[1px] bg-slate-700 mx-2 lg:mx-4"></div>
                                
                                <div class="flex flex-col min-w-0 col-span-1">
                                    <p class="text-[10px] text-slate-400 font-black uppercase">Duración</p>
                                    <p class="text-sm font-bold">{{ duracionEstimada }} min</p>
                                </div>

                            </div>
                            
                            <!-- Boton cambiar orden -->
                            <div class="w-full md:w-auto flex-shrink-0 mt-2 md:mt-0">
                                <button @click="paso = 1" class="w-full md:w-auto py-3 md:py-2 px-4 border border-slate-700 md:border-transparent rounded-xl text-xs text-slate-300 hover:text-white hover:bg-slate-800 font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                    Cambiar orden
                                </button>
                            </div>

                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Selector de fecha -->
                        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100">
                                <h4 class="font-bold text-slate-800">📅 Seleccionar Fecha</h4>
                            </div>
                            <div class="p-4 space-y-2">
                                <button v-for="f in fechasDisponibles" :key="f.fecha"
                                    @click="fechaSeleccionada = f.fecha"
                                    class="w-full text-left px-4 py-3 rounded-xl transition-all flex items-center justify-between"
                                    :class="fechaSeleccionada === f.fecha ? 'bg-red-600 text-white shadow-lg shadow-red-600/20' : 'hover:bg-slate-50 text-slate-700'">
                                    <div>
                                        <p class="font-bold text-sm capitalize">{{ f.dia_largo }}</p>
                                        <span v-if="f.es_hoy" class="text-[10px] font-bold" :class="fechaSeleccionada === f.fecha ? 'text-red-200' : 'text-red-500'">HOY</span>
                                    </div>
                                    <svg v-if="fechaSeleccionada === f.fecha" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Selector de hora -->
                        <div class="lg:col-span-2 bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                                <h4 class="font-bold text-slate-800">🕐 Horarios Disponibles</h4>
                                <p class="text-xs text-slate-400 font-medium">Última reservación: 6:00 PM</p>
                            </div>

                            <div v-if="cargandoSlots" class="p-10 text-center text-slate-400">Cargando horarios...</div>

                            <div v-else>
                                <div v-if="fechaSeleccionada && new Date(fechaSeleccionada + 'T00:00:00').getDay() === 3" class="mx-4 mb-2 p-2.5 bg-amber-50 border border-amber-200/80 rounded-xl text-amber-800 text-xs flex items-center gap-2">
                                    <span>ℹ️</span>
                                    <span><strong>Miércoles:</strong> Recepción para proveedores externos disponible únicamente de 8:00 AM a 11:00 AM.</span>
                                </div>

                                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <button v-for="slot in slotsDisponibles" :key="slot.hora"
                                        @click="seleccionarSlot(slot)"
                                        :disabled="!slot.disponible"
                                        :title="slot.bloqueado_horario ? slot.motivo_bloqueo : (!slot.disponible ? 'Turno ocupado' : 'Turno disponible')"
                                        class="px-2 py-3 rounded-2xl text-center transition-all border-2 flex flex-col items-center justify-center gap-0.5"
                                        :class="slotSeleccionado?.hora === slot.hora
                                            ? 'border-red-600 bg-red-50 shadow-md ring-4 ring-red-600/10'
                                            : slot.disponible
                                                ? 'border-slate-100 hover:border-red-200 hover:bg-slate-50'
                                                : 'border-transparent bg-slate-50 opacity-40 cursor-not-allowed'">
                                        <p class="font-black text-sm" :class="slotSeleccionado?.hora === slot.hora ? 'text-red-700' : 'text-slate-800'">{{ slot.hora_formato }}</p>
                                        <div class="flex items-center gap-1">
                                            <div v-if="slot.disponible" class="flex gap-0.5">
                                                <span v-for="n in slot.muelles_libres" :key="n" class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            </div>
                                            <span class="text-[9px] font-black uppercase tracking-tighter" :class="slot.disponible ? 'text-emerald-600' : (slot.bloqueado_horario ? 'text-amber-600' : 'text-slate-400')">
                                                {{ slot.disponible ? 'Libre' : (slot.bloqueado_horario ? 'Bloqueado' : 'Full') }}
                                            </span>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Muelle + Observaciones (si hay slot seleccionado) -->
                            <div v-if="slotSeleccionado" class="px-6 py-5 border-t border-slate-100 bg-slate-50 space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Seleccionar Sucursal</label>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <button v-for="m in slotSeleccionado.muelles" :key="m"
                                                @click="muelleSeleccionado = m"
                                                class="px-4 py-2 rounded-xl border-2 font-bold text-xs transition-all shadow-sm"
                                                :class="muelleSeleccionado === m ? 'border-red-600 bg-red-600 text-white shadow-red-200' : 'border-slate-200 text-slate-500 hover:border-slate-300 bg-white'"
                                                :title="getSucursalNombre(m)">
                                                {{ getSucursalNombre(m) }}
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Horario Seleccionado</label>
                                        <p class="mt-1 py-2.5 px-3 bg-white rounded-xl border border-slate-200 text-sm font-bold text-slate-800">
                                            {{ slotSeleccionado.hora_formato }} — {{ slotSeleccionado.hora_fin }}
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Observaciones (opcional)</label>
                                    <textarea v-model="observaciones" rows="2" placeholder="Notas adicionales para la recepción..."
                                        class="mt-1 block w-full border-slate-200 rounded-xl focus:border-red-600 focus:ring-red-600/20 text-sm"></textarea>
                                </div>
                                <p v-if="error" class="text-red-500 font-medium text-sm">⚠️ {{ error }}</p>
                                <button @click="reservar" :disabled="cargando || !muelleSeleccionado"
                                    class="w-full bg-red-600 text-white py-3 rounded-xl font-bold text-sm uppercase tracking-wider hover:bg-red-700 transition-all shadow-lg shadow-red-600/20 active:scale-95 disabled:opacity-50">
                                    {{ cargando ? 'Reservando...' : 'CONFIRMAR RESERVACIÓN' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                </div> <!-- Cierre del Flujo Administrativo / Receptor / Comprador -->

                <!-- ========== PASO 3: Confirmación (Para cualquier rol: Galpón, Proveedor Comercial, Admin, Receptor) ========== -->
                <div v-if="paso === 3" class="max-w-4xl mx-auto px-4">
                    <div v-if="odcHabilitadaExitosa" class="bg-white overflow-hidden shadow-xl shadow-blue-200/50 sm:rounded-3xl p-8 border border-blue-100 text-center mb-8">
                        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6 text-blue-600">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h3 class="text-3xl font-black text-slate-800 mb-2">¡Orden Habilitada!</h3>
                        <p v-if="error" class="text-red-600 font-bold mb-6 max-w-md mx-auto text-lg">⚠️ {{ error }}</p>
                        <p v-else class="text-slate-500 mb-6 max-w-md mx-auto text-lg">La orden de compra ha sido habilitada y se ha enviado la notificación por correo electrónico.</p>

                        <!-- Tarjeta de Enlace Directo para WhatsApp / Respaldo -->
                        <div v-if="infoHabilitacionExitosa.link_acceso" class="bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl p-5 mb-8 text-left max-w-2xl mx-auto shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-500/20">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-extrabold text-slate-800 text-base flex items-center gap-2">
                                        <span>Enlace Directo de Agendamiento</span>
                                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-full">Respaldo WhatsApp</span>
                                    </h4>
                                    <p class="text-xs text-slate-600 mt-0.5">
                                        Si el proveedor utiliza un correo corporativo con filtros estrictos (Polar, Nestlé, etc.), copia este enlace y envíaselo directamente por WhatsApp para que agende su cita al instante:
                                    </p>
                                    <div class="mt-3 flex items-center gap-2 bg-white p-1.5 rounded-xl border border-emerald-200 shadow-inner">
                                        <input readonly :value="infoHabilitacionExitosa.link_acceso" class="bg-transparent px-2 text-xs text-slate-700 font-mono flex-1 outline-none truncate select-all" />
                                        <button @click="copiarEnlaceWhatsApp" type="button" class="shrink-0 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 shadow">
                                            <span v-if="infoHabilitacionExitosa.copiado" class="text-white font-black">✅ ¡Enlace Copiado!</span>
                                            <span v-else class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                                Copiar Enlace
                                            </span>
                                        </button>
                                    </div>
                                    <div class="mt-2 text-[11px] text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span>📧 Notificado a: <strong class="text-slate-700">{{ infoHabilitacionExitosa.email_destino }}</strong></span>
                                        <span v-if="infoHabilitacionExitosa.emails_adicionales?.length">
                                            y copias a: <strong class="text-slate-700">{{ infoHabilitacionExitosa.emails_adicionales.join(', ') }}</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row justify-center gap-4">
                            <Link :href="route('dashboard')" class="px-6 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-2xl shadow-lg transition-all inline-flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 001 1m-6 0h6"></path></svg>
                                Volver al Dashboard Principal
                            </Link>
                            <button @click="nuevaReserva" class="px-6 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl transition-all">Buscar Otra Orden</button>
                        </div>
                    </div>
                    <div v-else class="bg-white overflow-hidden shadow-xl shadow-emerald-200/50 sm:rounded-3xl p-8 border border-emerald-100 text-center mb-8">
                        <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6 text-emerald-600">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h3 class="text-3xl font-black text-slate-800 mb-2">
                            <span v-if="citaConfirmada?.es_traslado_interno === true || citaConfirmada?.numero_oc?.startsWith('TI-') || modoReserva === 'traslado_interno'">¡Traslado Interno Agendado!</span>
                            <span v-else-if="isReprogramar">¡Cita Reprogramada!</span>
                            <span v-else>¡Cita Agendada!</span>
                        </h3>
                        <p class="text-slate-500 mb-8 max-w-md mx-auto text-lg">
                            <span v-if="citaConfirmada?.es_traslado_interno === true || citaConfirmada?.numero_oc?.startsWith('TI-') || modoReserva === 'traslado_interno'">
                                La recepción para el traslado <strong>{{ citaConfirmada?.numero_oc }}</strong> ha sido reservada con éxito en el Muelle {{ citaConfirmada?.muelle }}.
                            </span>
                            <span v-else>
                                La cita para la ODC {{ citaConfirmada?.numero_oc }} ha sido <span v-if="isReprogramar">reprogramada</span><span v-else>reservada</span> con éxito. Se ha enviado la confirmación por correo.
                            </span>
                        </p>
                        
                        <div class="flex flex-col sm:flex-row justify-center gap-4">
                            <Link :href="route('dashboard')" class="px-6 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl shadow-lg shadow-emerald-600/20 transition-all inline-flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 001 1m-6 0h6"></path></svg>
                                Volver al Dashboard Principal
                            </Link>
                            <button @click="nuevaReserva" class="px-6 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl transition-all">
                                {{ userRole === 'proveedor' ? (isGalponUser ? 'Agendar Otro Traslado' : 'Volver a Mis Órdenes') : 'Agendar Otra Cita' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ========== FLUJO PROVEEDOR (Galpón o Comercial) ========== -->
                <div v-if="userRole === 'proveedor' && paso !== 3">
                    <!-- Vista exclusiva para usuario Galpón Alfonso / Traslado Interno (Nuevo Diseño Amplio y Moderno) -->
                    <div v-if="isGalponUser && paso === 1" class="max-w-4xl mx-auto">
                        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
                            <!-- Top banner / header con acento índigo -->
                            <div class="relative px-6 sm:px-10 pt-8 pb-6 border-b border-slate-100 bg-gradient-to-b from-indigo-50/50 via-white to-white">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div>
                                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100/80 border border-indigo-200/60 text-indigo-700 text-xs font-bold uppercase tracking-wider mb-2">
                                            <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                                            Portal Galpones · Traslado Interno
                                        </div>
                                        <h3 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                                            Programar Traslado a Muelle
                                        </h3>
                                        <p class="text-slate-500 text-sm mt-1 max-w-xl">
                                            Configure el muelle de destino y la franja de horario para la entrega interna de mercancía (máximo 2 horas de descarga).
                                        </p>
                                    </div>

                                    <!-- Ficha de Galpón Origen -->
                                    <div class="bg-white px-4 py-3 rounded-2xl border border-indigo-100 shadow-sm flex items-center gap-3 shrink-0">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                                            🏢
                                        </div>
                                        <div>
                                            <p class="text-xs font-black text-slate-900 leading-tight">Origen: Galpón Alfonso</p>
                                            <p class="text-[11px] text-slate-500 font-mono">RIF: J-10715201</p>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Despacho Directo Activo
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 sm:px-10 py-8 space-y-8">
                                <!-- 2 Columns Form -->
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                                    <!-- Left Column: Configuración de Destino y Carga (5 cols) -->
                                    <div class="lg:col-span-5 space-y-6">
                                        <!-- Muelle Destino -->
                                        <div>
                                            <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-2">
                                                1. Muelle / Sucursal Destino
                                            </label>
                                            <div class="relative">
                                                <select v-model="sucursalDestinoTraslado" class="w-full text-sm font-bold text-slate-800 rounded-2xl border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 py-3.5 pl-4 pr-10 shadow-sm transition-all bg-slate-50/50 hover:bg-white">
                                                    <option value="0101">🏪 0101 - Hiper Suraki (Muelle Principal)</option>
                                                    <option value="0102">📦 0102 - Depósito General</option>
                                                    <option value="0111">⚙️ 0111 - Área de Producción</option>
                                                    <option value="0140">🥩 0140 - Depósito de Carnes</option>
                                                    <option value="0150">🥦 0150 - Depósito Perecederos</option>
                                                    <option value="0180">🏭 0180 - Galpón Central</option>
                                                </select>
                                            </div>
                                            <p class="text-[11px] text-slate-400 mt-1.5">Los horarios disponibles se adaptan al muelle seleccionado.</p>
                                        </div>

                                        <!-- Duración Estimada de Descarga -->
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="block text-xs font-black uppercase tracking-wider text-slate-600">
                                                    2. Duración Estimada
                                                </label>
                                                <span class="text-[11px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100">
                                                    Máximo 2 Horas
                                                </span>
                                            </div>
                                            <div class="grid grid-cols-4 gap-2">
                                                <button v-for="mins in [30, 60, 90, 120]" :key="mins" type="button"
                                                    @click="duracionTraslado = mins"
                                                    class="py-3 text-center rounded-2xl text-xs font-black border transition-all flex flex-col items-center justify-center gap-0.5"
                                                    :class="duracionTraslado === mins ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-600/25 scale-[1.02]' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'">
                                                    <span class="text-sm font-black">{{ mins }}</span>
                                                    <span class="text-[10px] uppercase font-bold opacity-80">min</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Datos Adicionales / Opcionales -->
                                        <div class="pt-2 border-t border-slate-100 space-y-4">
                                            <p class="text-xs font-black uppercase tracking-wider text-slate-400">Datos Opcionales del Traslado</p>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1">Nº Guía / Nota de Entrega</label>
                                                <input v-model="numeroTrasladoManual" type="text" placeholder="Ej: TI-2026-0042 (Autogenerado si se omite)"
                                                    class="w-full text-xs font-mono rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/20 py-2.5 shadow-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1">Observaciones de la Carga</label>
                                                <textarea v-model="observacionesTraslado" rows="2" placeholder="Ej: 4 paletas de víveres secos, pallet filmado..."
                                                    class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/20 py-2 shadow-sm"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column: Fecha y Selección de Horarios (7 cols) -->
                                    <div class="lg:col-span-7 space-y-6">
                                        <!-- Fecha de Llegada -->
                                        <div>
                                            <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-2">
                                                3. Fecha de Llegada a Muelle
                                            </label>
                                            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
                                                <button v-for="f in fechasDisponibles" :key="f.fecha" type="button"
                                                    @click="fechaSeleccionada = f.fecha"
                                                    class="px-3.5 py-3 rounded-2xl border text-center whitespace-nowrap transition-all flex flex-col items-center justify-center min-w-[72px]"
                                                    :class="fechaSeleccionada === f.fecha ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-600/30 scale-105' : 'bg-white text-slate-700 border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/40'">
                                                    <span class="text-[10px] font-black uppercase tracking-wider opacity-85">{{ f.dia }}</span>
                                                    <span class="text-lg font-black mt-0.5 leading-none">{{ parseInt(f.fecha.split('-')[2]) }}</span>
                                                    <span class="text-[9px] uppercase font-bold opacity-75 mt-0.5">{{ f.dia_largo ? f.dia_largo.split(' ')[0] : '' }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Horario Disponible en Muelle -->
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="block text-xs font-black uppercase tracking-wider text-slate-600">
                                                    4. Horario Disponible en Muelle
                                                </label>
                                                <span v-if="slotsDisponibles.length > 0" class="text-[11px] text-slate-400">
                                                    {{ slotsDisponibles.filter(s => s.disponible).length }} franjas disponibles
                                                </span>
                                            </div>

                                            <div v-if="cargandoSlots" class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-100 text-slate-400 text-xs flex flex-col items-center gap-2">
                                                <div class="w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                                                <span>Consultando disponibilidad de muelles...</span>
                                            </div>
                                            <div v-else-if="slotsDisponibles.length > 0">
                                                <div v-if="fechaSeleccionada && new Date(fechaSeleccionada + 'T00:00:00').getDay() === 3" class="mb-2.5 px-3 py-2 bg-amber-50 border border-amber-200/80 rounded-xl text-amber-900 text-xs flex items-center gap-2">
                                                    <span>ℹ️</span>
                                                    <span><strong>Miércoles:</strong> Los traslados de galpones se reciben únicamente a partir de las 2:00 PM.</span>
                                                </div>
                                                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 max-h-56 overflow-y-auto p-1.5 bg-slate-50/70 rounded-2xl border border-slate-200/80">
                                                    <button v-for="slot in slotsDisponibles" :key="slot.hora" type="button"
                                                        :disabled="!slot.disponible"
                                                        :title="slot.bloqueado_horario ? slot.motivo_bloqueo : (!slot.disponible ? 'Turno ocupado' : 'Turno disponible')"
                                                        @click="seleccionarSlot(slot)"
                                                        class="p-2.5 rounded-xl text-center border text-xs transition-all flex flex-col items-center justify-center gap-0.5 relative"
                                                        :class="[
                                                            slotSeleccionado?.hora === slot.hora ? 'bg-indigo-600 text-white border-indigo-600 font-black shadow-md shadow-indigo-600/30 scale-[1.03] z-10' :
                                                            slot.disponible ? 'bg-white text-slate-800 hover:bg-indigo-50/70 hover:border-indigo-300 border-slate-200 font-bold shadow-sm' :
                                                            'bg-slate-100 text-slate-300 border-slate-200/60 cursor-not-allowed line-through opacity-50'
                                                        ]">
                                                        <span class="font-black text-xs">{{ slot.hora_formato }}</span>
                                                        <span class="text-[9px] font-bold" :class="slotSeleccionado?.hora === slot.hora ? 'text-indigo-100' : slot.disponible ? 'text-emerald-600' : (slot.bloqueado_horario ? 'text-amber-500' : 'text-slate-400')">
                                                            {{ slot.disponible ? 'Libre' : (slot.bloqueado_horario ? 'Bloqueado' : 'Ocupado') }}
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-else class="p-6 text-center bg-slate-50 rounded-2xl border border-slate-100 text-slate-400 text-xs">
                                                No hay turnos disponibles para la fecha seleccionada.
                                            </div>

                                            <!-- Resumen de Horario Seleccionado -->
                                            <div v-if="slotSeleccionado" class="mt-3 p-3.5 bg-indigo-50 border border-indigo-200/70 rounded-2xl flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">
                                                        ✓
                                                    </div>
                                                    <div>
                                                        <p class="text-xs font-black text-indigo-950">
                                                            {{ slotSeleccionado.hora_formato }} a {{ slotSeleccionado.hora_fin }}
                                                        </p>
                                                        <p class="text-[11px] text-indigo-700">
                                                            Muelle Asignado: <strong>Muelle {{ muelleSeleccionado }}</strong> · Duración: <strong>{{ duracionTraslado }} min</strong>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Alerta de Error si ocurre -->
                                <div v-if="error" class="p-3.5 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-xs font-bold flex items-center gap-2">
                                    <span>⚠️</span>
                                    <span>{{ error }}</span>
                                </div>

                                <!-- Submit Button -->
                                <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <p class="text-xs text-slate-400 text-center sm:text-left">
                                        Al confirmar, se registrará el traslado interno en el sistema de logística de Suraki.
                                    </p>
                                    <button type="button" @click="agendarTrasladoInterno" :disabled="cargando || !slotSeleccionado"
                                        class="w-full sm:w-auto px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black text-sm uppercase tracking-wider transition-all shadow-xl shadow-indigo-600/30 hover:shadow-indigo-600/40 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                        <span v-if="cargando" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                        <span>{{ cargando ? 'Agendando Traslado...' : 'Confirmar Traslado Interno' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Flujo comercial tradicional si NO es galpón -->
                    <div v-else-if="paso === 1" class="bg-white overflow-hidden shadow-xl sm:rounded-3xl p-8 mb-8">
                        <h3 class="text-xl font-black text-slate-800 mb-4">Mis Órdenes Habilitadas</h3>
                        <div v-if="odcsPendientes.length === 0" class="text-slate-500 text-center py-8">
                            No tiene órdenes pendientes por agendar.
                        </div>
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div v-for="odc in odcsPendientes" :key="odc.numero_oc" 
                                class="border border-slate-200 rounded-xl p-5 hover:border-red-500 transition-colors cursor-pointer bg-slate-50 shadow-sm"
                                @click="abrirModalProveedor(odc)">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-bold text-lg text-slate-800">{{ odc.numero_oc }}</span>
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded">Habilitada</span>
                                </div>
                                <p class="text-sm text-slate-600 mb-4">Click para agendar despacho</p>
                                <div class="flex flex-col gap-1 text-xs text-slate-500">
                                    <span>Cat: <strong>{{ odc.categoria_sugerida }}</strong></span>
                                    <span>Peso Est.: <strong>{{ odc.peso_estimado_ton }} Ton</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2: Calendario para proveedor -->
                    <div v-if="paso === 2" class="bg-white overflow-hidden shadow-xl sm:rounded-3xl p-8 mb-8">
                        <div v-if="isReprogramar" class="bg-orange-50 border-l-4 border-orange-500 p-4 mb-6 rounded-r-xl">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-orange-800">Reprogramando Cita</h3>
                                    <div class="mt-2 text-sm text-orange-700">
                                        <p>Selecciona la nueva fecha y horario para la orden <strong>{{ formProveedor.numero_oc }}</strong>.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TARJETA DE REVISIÓN Y CORRECCIÓN DE DATOS ANTES DE AGENDAR -->
                        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-6 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1 flex items-center gap-1.5">
                                        <span>📋 Datos de Despacho Registrados</span>
                                        <span class="text-slate-400 font-normal">(Verifique antes de seleccionar fecha y hora)</span>
                                    </p>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-700">
                                        <span>📄 Factura: <strong class="text-slate-900 font-mono">{{ formProveedor.numero_factura || 'Por facturar' }}</strong></span>
                                        <span>⚖️ Peso: <strong class="text-slate-900">{{ formProveedor.peso_factura_ton }} Ton</strong></span>
                                        <span>📦 Formato: <strong class="text-slate-900">{{ formProveedor.formato_carga === 'paletizada' ? 'Paletizada' : 'Suelta' }}</strong></span>
                                        <span>🚚 Vehículo: <strong class="text-slate-900">{{ getNombreVehiculo(formProveedor.tipo_vehiculo) }}</strong></span>
                                        <span>⏱️ Tiempo Estimado: <strong class="text-red-600 font-bold">{{ formatMinutos(duracionCalculada) }}</strong></span>
                                        <span v-if="facturaFile" class="text-emerald-700 font-bold flex items-center gap-1">
                                            📎 {{ facturaFile.name }}
                                        </span>
                                    </div>
                                </div>
                                <button @click="modalInteligente = true" type="button" class="px-3.5 py-2 bg-white hover:bg-blue-50 text-blue-600 hover:text-blue-800 font-bold text-xs rounded-xl border border-blue-200 shadow-sm transition-all flex items-center gap-1.5 flex-shrink-0 self-start md:self-auto">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    <span>¿Desea corregir un dato? Modificar aquí</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-4">
                            <h3 class="text-xl font-black text-slate-800">Seleccione Horario de Recepción</h3>
                            <button @click="nuevaReserva" class="text-xs font-bold text-slate-500 hover:text-red-600 transition-colors flex items-center gap-1.5 py-1 px-2.5 rounded-lg hover:bg-slate-50 border border-slate-250">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Volver Atrás
                            </button>
                        </div>
                        
                        <!-- Fechas (reusamos la lista de fechas) -->
                        <div class="flex gap-2 overflow-x-auto pb-4 mb-4 scrollbar-hide">
                            <button v-for="fd in fechasDisponibles" :key="fd.fecha" @click="fechaSeleccionada = fd.fecha"
                                class="flex flex-col items-center justify-center min-w-[80px] p-3 rounded-2xl transition-all border"
                                :class="fechaSeleccionada === fd.fecha ? 'bg-red-600 text-white border-red-600 shadow-md shadow-red-600/30' : 'bg-white text-slate-600 border-slate-200 hover:border-red-300 hover:bg-red-50'">
                                <span class="text-xs font-bold uppercase opacity-80">{{ new Date(fd.fecha + 'T00:00:00').toLocaleDateString('es-VE', { weekday: 'short' }) }}</span>
                                <span class="text-2xl font-black">{{ new Date(fd.fecha + 'T00:00:00').getDate() }}</span>
                                <span class="text-[10px] font-bold uppercase opacity-80">{{ new Date(fd.fecha + 'T00:00:00').toLocaleDateString('es-VE', { month: 'short' }) }}</span>
                            </button>
                        </div>

                        <div v-if="fechaSeleccionada && new Date(fechaSeleccionada + 'T00:00:00').getDay() === 3" class="mb-4 p-3 bg-amber-50 border border-amber-200/80 rounded-xl text-amber-900 text-xs flex items-center gap-2">
                            <span>ℹ️</span>
                            <span><strong>Horario especial de Miércoles:</strong> La recepción para proveedores externos es de 8:00 AM hasta las 11:00 AM. Turnos posteriores no disponibles.</span>
                        </div>

                        <!-- Slots (reusamos la lista de slots) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
                            <button v-for="slot in slotsDisponibles" :key="slot.hora"
                                @click="seleccionarSlot(slot)"
                                :disabled="!slot.disponible"
                                :title="slot.bloqueado_horario ? slot.motivo_bloqueo : (!slot.disponible ? 'Turno ocupado' : 'Turno disponible')"
                                class="py-3 px-2 rounded-xl text-center font-bold text-sm transition-all border border-transparent flex flex-col items-center justify-center gap-1 relative overflow-hidden"
                                :class="[
                                    !slot.disponible ? 'bg-slate-100 text-slate-400 cursor-not-allowed opacity-60' : 
                                    slotSeleccionado?.hora === slot.hora ? 'bg-red-600 text-white shadow-lg shadow-red-600/30 scale-105 z-10' : 'bg-red-50 text-red-900 hover:bg-red-100 cursor-pointer hover:-translate-y-0.5'
                                ]">
                                <span>{{ formatHora(`2000-01-01 ${slot.hora}:00`) }}</span>
                                <span class="text-[9px] font-bold" :class="slotSeleccionado?.hora === slot.hora ? 'text-red-100' : (slot.disponible ? 'text-emerald-700' : (slot.bloqueado_horario ? 'text-amber-600' : 'text-slate-400'))">
                                    {{ slot.disponible ? 'Libre' : (slot.bloqueado_horario ? 'Bloqueado' : 'Ocupado') }}
                                </span>
                            </button>
                        </div>
                        <div v-if="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 font-bold text-xs text-center shadow-sm">
                            ⚠️ {{ error }}
                        </div>
                        <div class="mt-8 flex justify-between items-center border-t border-slate-100 pt-6">
                            <button @click="nuevaReserva"
                                class="px-5 py-2.5 rounded-xl font-bold text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-800 border border-slate-200 transition-all">
                                ← Volver a Mis Órdenes
                            </button>
                            <button v-if="slotSeleccionado" @click="reservarComoProveedor" :disabled="cargando"
                                class="bg-red-600 text-white px-8 py-3.5 rounded-2xl font-bold hover:bg-red-700 transition-all shadow-lg active:scale-95">
                                <span v-if="isReprogramar">{{ cargando ? 'REPROGRAMANDO...' : 'CONFIRMAR NUEVO HORARIO' }}</span>
                                <span v-else>{{ cargando ? 'AGENDANDO...' : 'CONFIRMAR CITA' }}</span>
                            </button>
                        </div> <!-- Cierre de mt-8 flex justify-between -->
                    </div> <!-- Cierre de paso === 2 de proveedor comercial -->
                </div> <!-- Cierre del Flujo Proveedor -->

            </div> <!-- Cierre de max-w-7xl mx-auto -->
        </div> <!-- Cierre de py-8 sm:py-10 bg-slate-50 min-h-screen -->

        <Modal :show="modalInteligente" @close="modalInteligente = false">
                        <div class="p-4 sm:p-8">
                            <h2 class="text-xl sm:text-2xl font-bold mb-4 text-slate-800">Formulario de Despacho</h2>

                            <!-- Alerta de validación si algún dato es incorrecto -->
                            <div v-if="errorFormProveedor" class="mb-4 p-3.5 bg-red-50 border border-red-200 rounded-xl text-red-600 font-bold text-xs flex items-center gap-2 shadow-sm animate-pulse">
                                <span class="text-base">⚠️</span>
                                <span>{{ errorFormProveedor }}</span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2 border border-slate-200 rounded-xl p-4 bg-slate-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-sm font-bold text-slate-700">Correo del Asesor/Vendedor (Notificaciones)</label>
                                        <label class="flex items-center gap-2 cursor-pointer text-sm font-bold text-slate-600 hover:text-red-600 select-none bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm transition-colors" :class="editarCorreo ? 'border-red-300 bg-red-50 text-red-700' : ''">
                                            <input type="checkbox" v-model="editarCorreo" class="rounded border-slate-300 text-red-600 focus:ring-red-600 cursor-pointer">
                                            Editar Correo
                                        </label>
                                    </div>
                                    <input v-model="formProveedor.email_contacto" type="email" :readonly="!editarCorreo" :class="editarCorreo ? 'bg-white border-slate-300 focus:border-red-500 focus:ring-red-500 text-slate-900' : 'bg-slate-200 border-slate-200 text-slate-500 cursor-not-allowed'" class="mt-1 block w-full rounded-md transition-colors" placeholder="Ej: ventas@proveedor.com">
                                    <p class="text-xs text-slate-500 mt-2">Active la casilla "Editar Correo" si su vendedor o asesor comercial cambió y desea actualizar la dirección donde recibirá las notificaciones.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700">Nº de Factura</label>
                                    <input v-model="formProveedor.numero_factura" type="text" class="mt-1 block w-full rounded-md border-slate-300" placeholder="Ej: 12345">
                                </div>
                                <div>
                                    <div class="flex justify-between items-center">
                                        <label class="block text-sm font-bold text-slate-700">Peso Real (Toneladas)</label>
                                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">Nota: 1 Ton = 1.000 Kg</span>
                                    </div>
                                    <input v-model="formProveedor.peso_factura_ton" type="number" step="any" class="mt-1 block w-full rounded-md border-slate-300" placeholder="Ej: 0.050 para 50 Kg | 2.5 para 2.5 Ton">
                                    <div v-if="Number(formProveedor.peso_factura_ton) > 0" class="mt-1.5 text-xs">
                                        <p class="text-slate-500 font-medium">Equivale a: <strong class="text-slate-700">{{ (Number(formProveedor.peso_factura_ton) * 1000).toLocaleString('es-VE') }} Kg</strong></p>
                                        <div v-if="Number(formProveedor.peso_factura_ton) >= 10" class="mt-1.5 p-2 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] flex flex-wrap items-center justify-between gap-1">
                                            <span>⚠️ ¿Ingresó el peso en <strong>Kilos</strong>? ({{ formProveedor.peso_factura_ton }} Kg = {{ (Number(formProveedor.peso_factura_ton) / 1000).toFixed(3) }} Ton)</span>
                                            <button type="button" @click="formProveedor.peso_factura_ton = Number((Number(formProveedor.peso_factura_ton) / 1000).toFixed(3))" class="px-2 py-0.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded text-[10px] whitespace-nowrap shadow-sm">
                                                Corregir a {{ (Number(formProveedor.peso_factura_ton) / 1000).toFixed(3) }} Ton
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700">Formato de Carga</label>
                                    <select v-model="formProveedor.formato_carga" class="mt-1 block w-full rounded-md border-slate-300">
                                        <option value="suelta">Carga Suelta</option>
                                        <option value="paletizada">Carga Paletizada</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold text-slate-700">Tipo de Vehículo</label>
                                    <select v-model="formProveedor.tipo_vehiculo" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                        <option value="" disabled>— Seleccione el tipo de vehículo —</option>
                                        <optgroup label="🚐 Vehículos Livianos">
                                            <option value="minivan">Minivan / Super Carry — Hasta 950 kg | 2.5 - 4.5 m³</option>
                                            <option value="camioneta_panel">Camioneta Carga / Panel — 700 kg a 1.5 Ton | 3 - 6 m³</option>
                                        </optgroup>
                                        <optgroup label="🚚 Camiones Medianos">
                                            <option value="cava_pequena">Camión 350 / Cava Pequeña — 2.5 a 3.5 Ton | 10 - 15 m³</option>
                                            <option value="camion_liviano">Camión Liviano (NPR / FVR 71) — 4.5 a 5.5 Ton | 20 - 25 m³</option>
                                            <option value="camion_mediano">Camión Mediano (NQR / Cargo 815) — 6.5 a 8 Ton | 30 - 35 m³</option>
                                        </optgroup>
                                        <optgroup label="🚛 Camiones Pesados">
                                            <option value="camion_sencillo">Camión Sencillo (Rígido Pesado) — 8 a 12 Ton | 40 - 45 m³</option>
                                            <option value="camion_toronto">Camión Toronto / Balancín — 14 a 18 Ton | 45 - 55 m³</option>
                                        </optgroup>
                                        <optgroup label="🚛 Gandolas (Articulados)">
                                            <option value="gandola_furgon">Gandola con Furgón (48-53 pies) — 24 a 30 Ton | 90 - 110 m³</option>
                                            <option value="gandola_sider">Gandola Sider (Cortina Lateral) — 24 a 28 Ton | 90 - 105 m³</option>
                                        </optgroup>
                                    </select>
                                    <p v-if="vehiculoRequiereFrio && esMercanciaPerecedera" class="mt-2 text-xs text-emerald-700 font-bold bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 flex items-center gap-2">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        ✅ Vehículo con Thermo King / Cadena de frío — Apto para perecederos
                                    </p>
                                    <p v-if="!vehiculoRequiereFrio && esMercanciaPerecedera" class="mt-2 text-xs text-red-700 font-bold bg-red-50 border border-red-200 rounded-lg px-3 py-2 flex items-center gap-2">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        ⚠️ Alerta: Para mercancía perecedera se requiere vehículo con unidad de refrigeración (Thermo King). Seleccione "Camión 350 / Cava Pequeña" u otro con cadena de frío.
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700">Tipo de Mercancía Declarada</label>
                                    <select v-model="formProveedor.categoria_sugerida" class="mt-1 block w-full rounded-md border-slate-300">
                                        <option value="Alimentos 1 (Viveres)">Alimentos 1 (Viveres)</option>
                                        <option value="Alimentos 2 (Golosinas, Confites)">Alimentos 2 (Golosinas, Confites)</option>
                                        <option value="No Alimentos 1 (Cuidado del Hogar, Ropa)">No Alimentos 1 (Cuidado del Hogar, Ropa)</option>
                                        <option value="No Alimentos 2 (Cuidado Personal, Perfumeria)">No Alimentos 2 (Cuidado Personal, Perfumeria)</option>
                                        <option value="No Alimentos 3 (Desechables, Papel, Plasticos, Carton)">No Alimentos 3 (Desechables, Papel, Plasticos, Carton)</option>
                                        <option value="No Alimentos 4 (Papeleria, Jugueteria)">No Alimentos 4 (Papeleria, Jugueteria)</option>
                                        <option value="Perecederos 1 (Charcuteria)">Perecederos 1 (Charcuteria)</option>
                                        <option value="Perecederos 2 (Carniceria, Pescaderia)">Perecederos 2 (Carniceria, Pescaderia)</option>
                                        <option value="Perecederos 3 (Frutas y Verduras)">Perecederos 3 (Frutas y Verduras)</option>
                                        <option value="Licores">Licores</option>
                                        <option value="Farmacia">Farmacia</option>
                                        <option value="Electronicos">Electronicos</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700">Archivo de Factura (PDF/Imagen)</label>
                                    <input id="facturaFileInput" type="file" @change="handleFacturaUpload" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                                    <div v-if="facturaFile" class="mt-2 flex items-center justify-between p-2 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800">
                                        <span class="font-bold flex items-center gap-1.5 truncate">
                                            📎 {{ facturaFile.name }}
                                        </span>
                                        <button type="button" @click="quitarFacturaFile" class="text-red-600 hover:text-red-800 font-bold ml-2 underline text-[11px] flex-shrink-0">
                                            ✕ Quitar archivo
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6 bg-slate-100 p-4 rounded-lg">
                                <p class="text-sm text-slate-600 font-bold text-center">
                                    Duración de descarga estimada: <span class="text-red-600 text-lg">{{ formatMinutos(duracionCalculada) }}</span>
                                </p>
                            </div>
                            <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-slate-100">
                                <button 
                                    type="button" 
                                    @click="restaurarValoresOdcOriginal"
                                    class="text-xs font-bold text-slate-500 hover:text-slate-800 flex items-center gap-1 underline transition-colors"
                                    title="Restaurar los valores iniciales sugeridos por la Orden de Compra">
                                    <span>↺ Restaurar datos iniciales de la ODC</span>
                                </button>
                                <div class="flex items-center gap-2 w-full sm:w-auto">
                                    <button @click="modalInteligente = false" class="px-4 py-2.5 bg-slate-200 hover:bg-slate-300 rounded-xl font-bold w-full sm:w-auto text-slate-700 text-xs transition-colors">Cancelar</button>
                                    <button @click="continuarAHorarios" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold w-full sm:w-auto text-xs shadow-md shadow-red-600/20 transition-all flex items-center justify-center gap-1.5">
                                        <span>Seleccionar Fecha y Hora →</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Modal>

        <!-- MODAL CANCELAR -->
        <div v-if="modalCancelar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-100">
                <div class="p-6">
                    <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-800">Cancelar Cita</h3>
                    <p class="text-sm text-slate-500 mt-1">¿Estás seguro de cancelar la cita de la OC <span class="font-bold text-slate-700">{{ citaSeleccionada?.numero_oc }}</span>?</p>
                    
                    <div class="mt-4">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Motivo de Cancelación</label>
                        <textarea v-model="motivoCancelacion" rows="3" placeholder="Ej: Error del proveedor, falta de inventario..."
                            class="mt-1 block w-full border-slate-200 rounded-xl focus:border-red-600 focus:ring-red-600/20 text-sm"></textarea>
                    </div>
                    
                    <p v-if="errorModal" class="text-red-500 font-medium mt-2 text-sm">⚠️ {{ errorModal }}</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex flex-col-reverse sm:flex-row justify-end gap-3 border-t border-slate-100">
                    <button @click="cerrarModalCancelar" :disabled="procesandoModal"
                        class="px-5 py-3 sm:py-2.5 rounded-xl font-bold text-sm text-slate-500 hover:bg-slate-200 transition-colors w-full sm:w-auto">
                        Volver
                    </button>
                    <button @click="confirmarCancelacion" :disabled="procesandoModal || motivoCancelacion.length < 5"
                        class="px-5 py-3 sm:py-2.5 rounded-xl font-bold text-sm text-white bg-red-600 hover:bg-red-700 shadow-lg shadow-red-600/20 transition-all disabled:opacity-50 w-full sm:w-auto">
                        {{ procesandoModal ? 'Cancelando...' : 'Confirmar Cancelación' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL REPROGRAMAR -->
        <div v-if="modalReprogramar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden border border-slate-100 flex flex-col max-h-[90vh]">
                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-900 text-white">
                    <div>
                        <h3 class="text-lg font-black">Reprogramar Cita</h3>
                        <p class="text-xs text-slate-400 mt-0.5">OC: <span class="font-bold text-blue-400">{{ citaSeleccionada?.numero_oc }}</span></p>
                    </div>
                    <button @click="cerrarModalReprogramar" class="text-slate-400 hover:text-white p-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto bg-slate-50 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Columna de Fecha -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden h-fit">
                        <div class="px-5 py-3 border-b border-slate-100"><h4 class="font-bold text-slate-800 text-sm">📅 Nueva Fecha</h4></div>
                        <div class="p-3 space-y-1">
                            <button v-for="f in repFechasDisponibles" :key="f.fecha" @click="repFechaSeleccionada = f.fecha"
                                class="w-full text-left px-3 py-2 rounded-lg transition-all flex items-center justify-between"
                                :class="repFechaSeleccionada === f.fecha ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-50 text-slate-700'">
                                <span class="font-bold text-xs capitalize">{{ f.dia_largo }}</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Columna de Horario y Confirmación -->
                    <div class="md:col-span-2 space-y-4">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                            <div class="px-5 py-3 border-b border-slate-100"><h4 class="font-bold text-slate-800 text-sm">🕐 Nuevo Horario</h4></div>
                            <div v-if="cargandoReprogramacion" class="p-8 text-center text-slate-400 text-sm">Cargando horarios...</div>
                            <div v-else class="p-4 grid grid-cols-3 sm:grid-cols-4 gap-2">
                                <button v-for="slot in repSlotsDisponibles" :key="slot.hora"
                                    @click="seleccionarSlotReprogramacion(slot)" :disabled="!slot.disponible"
                                    class="px-2 py-2 rounded-xl text-center transition-all border-2 flex flex-col items-center gap-1"
                                    :class="repSlotSeleccionado?.hora === slot.hora ? 'border-blue-600 bg-blue-50' : slot.disponible ? 'border-slate-100 hover:border-blue-200' : 'border-transparent bg-slate-50 opacity-40 cursor-not-allowed'">
                                    <span class="font-black text-xs" :class="repSlotSeleccionado?.hora === slot.hora ? 'text-blue-700' : 'text-slate-800'">{{ slot.hora_formato }}</span>
                                    <span class="text-[9px] font-black uppercase tracking-tighter" :class="slot.disponible ? 'text-emerald-600' : 'text-slate-400'">{{ slot.disponible ? 'Libre' : 'Full' }}</span>
                                </button>
                            </div>
                            
                            <div v-if="repSlotSeleccionado" class="px-5 py-4 border-t border-slate-100 bg-slate-50">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Muelles Disponibles</label>
                                <div class="flex flex-wrap gap-2 mt-2 mb-4">
                                    <button v-for="m in repSlotSeleccionado.muelles" :key="m" @click="repMuelleSeleccionado = m"
                                        class="px-3 py-1.5 rounded-lg border-2 font-bold text-xs transition-all"
                                        :class="repMuelleSeleccionado === m ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 text-slate-500 bg-white'">
                                        {{ m }}
                                    </button>
                                </div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Motivo de Reprogramación</label>
                                <textarea v-model="motivoReprogramacion" rows="2" placeholder="Ej: Retraso en tránsito..."
                                    class="mt-1 block w-full border-slate-200 rounded-xl focus:border-blue-600 focus:ring-blue-600/20 text-sm"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white px-6 py-4 flex flex-col md:flex-row justify-between items-start md:items-center border-t border-slate-100 gap-4 md:gap-0">
                    <p class="text-red-500 font-medium text-sm w-full"><span v-if="errorModal">⚠️ {{ errorModal }}</span></p>
                    <div class="flex flex-col-reverse sm:flex-row w-full md:w-auto gap-3">
                        <button @click="cerrarModalReprogramar" :disabled="procesandoModal"
                            class="px-5 py-3 sm:py-2.5 rounded-xl font-bold text-sm text-slate-500 hover:bg-slate-100 transition-colors w-full sm:w-auto">Cancelar</button>
                        <button @click="confirmarReprogramacion" :disabled="procesandoModal || motivoReprogramacion.length < 5 || !repSlotSeleccionado || !repMuelleSeleccionado"
                            class="px-5 py-3 sm:py-2.5 rounded-xl font-bold text-sm text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition-all disabled:opacity-50 w-full sm:w-auto">
                            {{ procesandoModal ? 'Guardando...' : 'Confirmar Reprogramación' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL CONFIGURACIÓN Y HABILITACIÓN DE CORREOS PARA COMPRADOR -->
        <div v-if="showEmailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
            <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-100 transform transition-all duration-300 scale-100">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-black text-slate-800 leading-tight">Configurar Correo Electrónico</h3>
                                <p class="text-xs text-slate-500">ODC: <span class="font-bold text-slate-700">#{{ numeroOrden }}</span> · {{ tempProveedorData?.nombre }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a :href="route('monitoreo')" target="_blank" class="hidden sm:inline-flex text-[11px] text-blue-600 hover:text-blue-800 font-bold items-center gap-1 bg-blue-50 hover:bg-blue-100 px-2.5 py-1.5 rounded-xl border border-blue-200 transition-colors" title="Abrir Monitor de Correos y Trazabilidad Forense en nueva pestaña">
                                <span>📡 Monitor de Correos</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                            <button @click="cancelarEmailModal" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 mb-3">
                        Introduzca o confirme los correos donde el proveedor recibirá la notificación para agendar su cita:
                    </p>

                    <!-- Selector de Contactos Previos si existen para este RIF -->
                    <div v-if="contactosExistentesModal.length > 0" class="mb-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                            Contactos / Vendedores registrados:
                        </label>
                        <select v-model="contactoModalSeleccionadoId" @change="onCambioContactoModal" class="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2.5 font-medium text-slate-700">
                            <option v-for="c in contactosExistentesModal" :key="c.id" :value="c.id">
                                {{ c.nombre }} ({{ c.email }})
                            </option>
                            <option value="nuevo">-- Ingresar correo manualmente --</option>
                        </select>
                    </div>

                    <!-- Correo Principal -->
                    <div class="border border-slate-200 rounded-2xl p-3.5 bg-slate-50/70 mb-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                <span class="bg-amber-100 text-amber-800 text-[10px] font-black px-1.5 py-0.5 rounded">👑 PRINCIPAL</span>
                                <span>Correo del Proveedor / Vendedor *</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-600 hover:text-red-600 select-none bg-white px-2.5 py-1 rounded-lg border border-slate-200 shadow-sm transition-colors" :class="editarCorreoComprador ? 'border-red-300 bg-red-50 text-red-700' : ''">
                                <input type="checkbox" v-model="editarCorreoComprador" class="rounded border-slate-300 text-red-600 focus:ring-red-600 cursor-pointer w-3.5 h-3.5">
                                Editar Correo
                            </label>
                        </div>
                        <input 
                            type="email" 
                            v-model="modalEmailInput" 
                            :readonly="!editarCorreoComprador"
                            placeholder="ejemplo@proveedor.com"
                            class="block w-full rounded-xl text-xs px-3 py-2.5 font-mono transition-colors border"
                            :class="editarCorreoComprador ? 'bg-white border-slate-300 focus:border-red-600 focus:ring-red-600/20 text-slate-900' : 'bg-slate-200/80 border-slate-200 text-slate-600 cursor-not-allowed'"
                            @keyup.enter="guardarYEnviarOdc"
                        />
                    </div>

                    <!-- Correos Adicionales (Hasta 2 adicionales = Total 3) -->
                    <div class="border border-slate-200 rounded-2xl p-3.5 bg-slate-50/70 mb-3">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="text-xs font-bold text-slate-700 flex items-center gap-1">
                                    📧 Correos Adicionales en Copia (Opcional)
                                </span>
                                <span class="text-[11px] text-slate-400">Máximo 2 adicionales (Total 3 correos)</span>
                            </div>
                            <button 
                                v-if="modalEmailsAdicionales.length < 2"
                                type="button" 
                                @click="agregarEmailAdicionalModal('')"
                                class="text-xs font-bold text-red-600 hover:text-red-700 bg-white hover:bg-red-50 px-2.5 py-1 rounded-lg border border-red-200 shadow-sm transition-all flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                Agregar otro
                            </button>
                        </div>

                        <!-- Lista de correos adicionales con botón para intercambiar a Principal -->
                        <div v-if="modalEmailsAdicionales.length > 0" class="space-y-2 mb-2">
                            <div v-for="(emAd, idx) in modalEmailsAdicionales" :key="idx" class="flex items-center gap-1.5">
                                <span class="text-[11px] font-bold text-slate-500 w-16 flex-shrink-0">Copia #{{ idx + 1 }}:</span>
                                <input 
                                    v-model="modalEmailsAdicionales[idx]" 
                                    type="email" 
                                    placeholder="correo.adicional@empresa.com" 
                                    class="flex-1 bg-white border border-slate-300 rounded-lg text-xs py-1.5 px-3 font-mono focus:border-red-600 focus:ring-red-600/20">
                                
                                <button 
                                    type="button" 
                                    @click="establecerComoPrincipalModal(modalEmailsAdicionales[idx], idx)"
                                    :disabled="!modalEmailsAdicionales[idx]"
                                    class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-bold rounded-lg transition-colors flex items-center gap-1 border border-amber-200 disabled:opacity-40" 
                                    title="Intercambiar: hacer que este correo pase a ser el Principal">
                                    <span>👑</span> <span class="hidden sm:inline">Hacer Principal</span>
                                </button>

                                <button 
                                    type="button" 
                                    @click="eliminarEmailAdicionalModal(idx)" 
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" 
                                    title="Quitar este correo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Chips / Sugerencias rápidas del ERP con opciones de Principal o Copia -->
                        <div v-if="correosErpSugeridosModal.length > 0" class="pt-2.5 border-t border-slate-200 flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-bold text-slate-500">💡 Detectados en ERP:</span>
                            <div v-for="erpMail in correosErpSugeridosModal" :key="erpMail" class="inline-flex items-center rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden text-xs">
                                <span class="px-2.5 py-1 text-slate-700 font-mono text-[11px] font-medium select-all">{{ erpMail }}</span>
                                <button 
                                    type="button"
                                    @click="establecerComoPrincipalModal(erpMail)"
                                    class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold border-l border-slate-200 flex items-center gap-0.5 transition-colors text-[10px]"
                                    title="Poner como el correo principal a enviar">
                                    <span>👑 Principal</span>
                                </button>
                                <button 
                                    type="button"
                                    @click="agregarEmailAdicionalModal(erpMail)"
                                    :disabled="modalEmailsAdicionales.length >= 2"
                                    class="px-2 py-1 bg-slate-50 hover:bg-red-50 text-slate-600 hover:text-red-700 font-bold border-l border-slate-200 flex items-center gap-0.5 transition-colors text-[10px] disabled:opacity-40"
                                    title="Agregar como copia adicional">
                                    <span>+ Copia</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Alerta de error si ocurre -->
                    <div v-if="modalEmailError" class="mt-3 bg-red-50 border border-red-200 rounded-2xl p-3.5 shadow-sm">
                        <div class="flex items-start gap-2.5">
                            <span class="text-red-600 text-base leading-none">⚠️</span>
                            <div class="flex-1">
                                <p class="text-xs font-bold text-red-700 leading-snug">{{ modalEmailError }}</p>
                                <div v-if="esErrorAutenticacion" class="mt-2.5 flex flex-wrap items-center gap-2">
                                    <a :href="route('login')" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-md shadow-red-600/20 transition-all">
                                        <span>🔑 Iniciar Sesión en Compras</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                    </a>
                                    <button type="button" @click="guardarYEnviarOdc" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold border border-slate-200 shadow-sm transition-all">
                                        <span>🔄 Reintentar Envío</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer del modal -->
                <div class="bg-slate-50 px-6 py-4 flex flex-col-reverse sm:flex-row justify-end gap-2.5 border-t border-slate-100">
                    <button @click="cancelarEmailModal" :disabled="cargando"
                        class="px-5 py-2.5 rounded-xl font-bold text-xs text-slate-500 hover:bg-slate-200 transition-colors w-full sm:w-auto">
                        Cancelar
                    </button>
                    <button @click="guardarYEnviarOdc" :disabled="cargando"
                        class="px-6 py-2.5 rounded-xl font-bold text-xs text-white bg-red-600 hover:bg-red-700 shadow-lg shadow-red-600/20 transition-all flex items-center justify-center gap-2 w-full sm:w-auto disabled:opacity-50">
                        <svg v-if="cargando" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ cargando ? 'Guardando y Notificando...' : 'Guardar y Enviar' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </component>
</template>

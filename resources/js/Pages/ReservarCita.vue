<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuestLayout from '@/Layouts/GuestFullLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, usePage, useForm } from '@inertiajs/vue3';
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

// Modal para introducir correo del proveedor cuando falta
const showEmailModal = ref(false);
const modalEmailInput = ref('');
const modalEmailError = ref('');
const tempProveedorData = ref(null);

const guardarYEnviarOdc = async () => {
    modalEmailError.value = '';
    const email = modalEmailInput.value.trim();
    if (!email) {
        modalEmailError.value = 'El correo electrónico es requerido.';
        return;
    }
    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexEmail.test(email)) {
        modalEmailError.value = 'El correo electrónico introducido no es válido.';
        return;
    }

    try {
        cargando.value = true;
        showEmailModal.value = false;
        await axios.post('/api/odc/habilitar', {
            numero_oc: numeroOrden.value,
            proveedor: tempProveedorData.value.nombre,
            rif: tempProveedorData.value.rif,
            contacto_id: null,
            email: email,
            telefono: '0000000000',
            asesor: 'Vendedor'
        });
        odcHabilitadaExitosa.value = true;
        paso.value = 3;
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al habilitar ODC.';
    } finally {
        cargando.value = false;
    }
};

const cancelarEmailModal = () => {
    showEmailModal.value = false;
    error.value = 'Habilitación cancelada: se requiere un correo para notificar al proveedor.';
    cargando.value = false;
};

const buscarOrden = async () => {
    if (!numeroOrden.value) return;
    
    // Verificar si ya existe en las citas programadas
    const ordenNumUpper = numeroOrden.value.toUpperCase();
    const citaExist = citasProgramadas.value.find(c => c.numero_oc.toUpperCase() === ordenNumUpper && (c.estatus === 'programada' || c.estatus === 'en muelle'));
    
    if (citaExist) {
        const d = new Date(citaExist.fecha_cita);
        const dFin = new Date(d.getTime() + (citaExist.duracion_minutos || 60) * 60000);
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
                // Flujo Comprador automático
                const emailProveedor = resp.data.proveedor_email;
                const rifProveedor = resp.data.Codigo_Proveedor || resp.data.resumen?.Codigo_Proveedor || '';
                const nombreProveedor = resp.data.nombre_proveedor || 'Sin nombre';
                const telefonoProveedor = resp.data.proveedor_telefono || '0000000000';
                const asesorProveedor = resp.data.proveedor_asesor || 'Vendedor';
                const contactoId = resp.data.contacto_id || null;
                if (emailProveedor) {
                    modalEmailInput.value = emailProveedor;
                } else {
                    modalEmailInput.value = '';
                }
                
                tempProveedorData.value = {
                    nombre: nombreProveedor,
                    rif: rifProveedor,
                    contacto_id: contactoId,
                    telefono: telefonoProveedor,
                    asesor: asesorProveedor
                };
                
                modalEmailError.value = '';
                editarCorreoComprador.value = emailProveedor ? false : true;
                showEmailModal.value = true;
                cargando.value = false;
                return;
            }
            
            paso.value = 2;
            cargarSlots();
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Orden no encontrada en el ERP.';
    } finally {
        cargando.value = false;
    }
};

const maxIntentosBusqueda = ref(0);

const cargarSlots = async () => {
    cargandoSlots.value = true;
    try {
        const resp = await axios.get('/api/citas/slots', {
            params: { 
                fecha: fechaSeleccionada.value, 
                duracion: duracionEstimada.value,
                sucursal: datosOrden.value?.sucursal_destino
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
        const resp = await axios.get('/api/citas');
        citasProgramadas.value = resp.data.citas;
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
        const resp = await axios.get('/api/citas/slots', {
            params: { 
                fecha: repFechaSeleccionada.value, 
                duracion: cita.duracion_minutos || 60,
                sucursal: cita.muelle_asignado.substring(0, 4) || '0101'
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

const statusColor = (s) => {
    const m = { programada: 'bg-amber-100 text-amber-700', 'en muelle': 'bg-blue-100 text-blue-700', finalizada: 'bg-emerald-100 text-emerald-700', cancelada: 'bg-indigo-100 text-indigo-700' };
    return m[s] || 'bg-slate-100 text-slate-600';
};

const getSucursalNombre = (codigo) => {
    const map = {
        '0101': 'Tu Empresa',
        '0102': 'Depósito General',
        '0111': 'Producción',
        '0115': 'Insumos',
        '0161': 'Andinka',
        '01': 'Galpón Central',
        '02': 'Galpón Central',
        '03': 'Galpón Central',
        '04': 'Galpón Central'
    };
    return map[codigo] || codigo;
};


// Nuevos flujos Comprador/Proveedor
const userRole = computed(() => usePage().props.auth.user?.role || 'guest');
const odcsPendientes = ref([]);

// Flujo Comprador
const formHabilitar = ref({
    numero_oc: '',
    proveedor: '',
    rif: '',
    email: '',
    telefono: '',
    asesor: '',
    password_base: '',
    contacto_id: null
});
const habilitando = ref(false);

const prepararHabilitacion = () => {
    formHabilitar.value.numero_oc = numeroOrden.value;
    formHabilitar.value.proveedor = datosOrden.value.nombre_proveedor || 'Sin nombre';
    formHabilitar.value.rif = datosOrden.value?.resumen?.Codigo_Proveedor || datosOrden.value?.Codigo_Proveedor || '';
    
    // Si ya tiene contactos registrados, cargarlos
    if (datosOrden.value?.contactos && datosOrden.value.contactos.length > 0) {
        contactosExistentes.value = datosOrden.value.contactos;
        formHabilitar.value.contacto_id = contactosExistentes.value[0].id;
    } else {
        contactosExistentes.value = [];
        agregarNuevoContacto.value = true;
    }
    paso.value = 2; // Mostrar form de habilitar
};

const odcHabilitadaExitosa = ref(false);

const habilitarOdc = async () => {
    habilitando.value = true;
    error.value = '';
    try {
        await axios.post('/api/odc/habilitar', formHabilitar.value);
        odcHabilitadaExitosa.value = true;
        paso.value = 3; // Mostrar éxito
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al habilitar ODC';
    } finally {
        habilitando.value = false;
    }
};

// Flujo Proveedor
const modalInteligente = ref(false);
const editarCorreo = ref(false);
const editarCorreoComprador = ref(false);
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
const handleFacturaUpload = (event) => {
    facturaFile.value = event.target.files[0];
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
    } catch(e) {}
};

watch(() => formProveedor.value.peso_factura_ton, calcularDuracionReactiva);
watch(() => formProveedor.value.formato_carga, calcularDuracionReactiva);
watch(() => formProveedor.value.categoria_sugerida, (val) => {
    formProveedor.value.tipo_mercancia = val;
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
    
    calcularDuracionReactiva();
    modalInteligente.value = true;
};

const continuarAHorarios = () => {
    modalInteligente.value = false;
    numeroOrden.value = formProveedor.value.numero_oc;
    datosOrden.value = { sucursal_destino: odcActiva.value.resumen?.sucursal_destino || '0101' };
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
        error.value = e.response?.data?.error || 'Error al agendar/reprogramar';
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
    <Head title="Programar Citas" />
    <component :is="$page.props.auth.user ? AuthenticatedLayout : GuestLayout">
        
        <div class="space-y-6 max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#eef0eb] pb-5">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#1c1c1c]">Programación de Entregas</h1>
                    <p class="text-xs text-[#6c7263] mt-1 font-medium">Habilita órdenes de compra y gestiona citas de descarga en muelles de recepción.</p>
                </div>
                <div class="flex items-center gap-2 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-100/70 shadow-sm w-fit">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    Recepción Abierta: 8:00 AM - 7:00 PM
                </div>
            </div>

            <!-- FLOW FOR RECEPCIÓN / COMPRADOR / ADMIN -->
            <div v-if="userRole !== 'proveedor'" class="space-y-6">
                
                <!-- STEPPER INDICATOR (Sleek modern design) -->
                <div class="flex items-center justify-between bg-white border border-[#eef0eb] p-3 rounded-2xl shadow-sm max-w-lg mx-auto">
                    <div v-for="p in 3" :key="p" class="flex items-center gap-2 w-full justify-center">
                        <div class="w-7 h-7 rounded-xl flex items-center justify-center text-xs font-bold transition-all shrink-0"
                            :class="paso >= p ? 'bg-primary text-white shadow-sm' : 'bg-[#faf9f6] text-[#888c80] border border-[#eef0eb]'">
                            {{ p }}
                        </div>
                        <span class="text-xs font-bold" :class="paso >= p ? 'text-[#1c1c1c]' : 'text-[#888c80]'">
                            {{ p === 1 ? 'Buscar ODC' : p === 2 ? 'Configurar' : 'Confirmación' }}
                        </span>
                        <div v-if="p < 3" class="hidden sm:block h-[1px] w-6 bg-[#eef0eb] mx-1"></div>
                    </div>
                </div>

                <!-- PASO 1: Buscar Orden -->
                <div v-if="paso === 1" class="max-w-xl mx-auto space-y-6">
                    <div class="bg-white border border-[#eef0eb] rounded-2xl p-6 shadow-sm">
                        <div class="text-center mb-5">
                            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-3 border border-primary/20">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <h3 class="text-base font-bold text-[#1c1c1c]">Buscar Orden de Compra</h3>
                            <p class="text-xs text-[#6c7263] mt-1 font-medium">Ingresa el código ERP de la orden para iniciar el proceso</p>
                        </div>

                        <div class="flex gap-2">
                            <div class="relative flex-grow">
                                <input v-model="numeroOrden" @keyup.enter="buscarOrden" type="text" placeholder="Ej: E00001167"
                                    class="block w-full px-4 py-2.5 border-[#eef0eb] bg-[#faf9f6] focus:border-primary focus:ring-primary/20 rounded-xl transition-all text-xs font-bold font-mono uppercase">
                            </div>
                            <button @click="buscarOrden" :disabled="cargando"
                                class="bg-[#1c1c1c] text-white hover:bg-zinc-800 px-5 py-2.5 rounded-xl text-xs font-bold transition-all active:scale-95 disabled:opacity-50 uppercase tracking-wider shrink-0">
                                {{ cargando ? 'Buscando...' : 'BUSCAR' }}
                            </button>
                        </div>
                        <p v-if="error" class="text-rose-500 font-bold mt-2.5 text-xs text-center">⚠️ {{ error }}</p>
                    </div>

                    <!-- Citas Programadas List -->
                    <div v-if="citasProgramadas.length > 0" class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                        <div class="bg-[#fcfbf8] border-b border-[#eef0eb] px-5 py-3.5">
                            <h3 class="text-[#1c1c1c] font-bold text-xs uppercase tracking-wider">Citas Activas Registradas</h3>
                        </div>
                        <div class="divide-y divide-[#eef0eb]">
                            <div v-for="cita in citasProgramadas" :key="cita.id" class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between hover:bg-[#faf9f6] transition-colors gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[#faf9f6] rounded-xl flex flex-col items-center justify-center border border-[#eef0eb] shrink-0">
                                        <span class="text-[8px] font-bold text-[#888c80] uppercase leading-none">{{ new Date(cita.fecha_cita).toLocaleDateString('es-VE', { month: 'short' }) }}</span>
                                        <span class="text-base font-extrabold text-[#1c1c1c] mt-0.5 leading-none">{{ new Date(cita.fecha_cita).getDate() }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                            <p class="font-bold text-[#1c1c1c] font-mono text-xs">{{ cita.numero_oc }}</p>
                                            <span v-if="cita.numero_factura" class="text-[8px] font-bold bg-[#faf9f6] text-[#6c7263] border border-[#eef0eb] px-2 py-0.5 rounded-full uppercase">Fac: {{ cita.numero_factura }}</span>
                                        </div>
                                        <p class="text-xs text-[#6c7263] truncate font-medium max-w-[280px]">{{ cita.proveedor }}</p>
                                        <p class="text-[9px] text-[#888c80] mt-0.5">🕒 {{ formatHora(cita.fecha_cita) }} · {{ getSucursalNombre(cita.muelle_asignado) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between sm:justify-end gap-2.5">
                                    <span :class="statusColor(cita.estatus)" class="text-[8px] font-bold px-2 py-0.5 rounded-xl uppercase">{{ cita.estatus }}</span>
                                    <div v-if="cita.estatus === 'programada' && (!cita.bloqueado_para_comprador || $page.props.auth?.user?.role !== 'comprador')" class="flex items-center gap-1 shrink-0">
                                        <button @click="abrirModalReprogramar(cita)" class="p-1.5 border border-[#eef0eb] hover:bg-[#faf9f6] text-[#6c7263] rounded-xl transition-all" title="Reprogramar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </button>
                                        <button @click="abrirModalCancelar(cita)" class="p-1.5 border border-rose-100 hover:bg-rose-50 text-rose-600 rounded-xl transition-all" title="Cancelar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PASO 2: Habilitar ODC (Comprador) o Agendar (Receptor/Admin) -->
                <div v-if="paso === 2 && userRole !== 'proveedor'" class="space-y-6 max-w-4xl mx-auto">
                    
                    <!-- Habilitar ODC Card -->
                    <div v-if="['comprador', 'admin'].includes(userRole)" class="bg-white border border-[#eef0eb] rounded-2xl p-6 shadow-sm">
                        <div class="text-center mb-5">
                            <h3 class="text-base font-bold text-[#1c1c1c]">Habilitar Enlace del Proveedor</h3>
                            <p class="text-xs text-[#6c7263] mt-1 font-medium">Asigna y valida el contacto del proveedor para enviarle la invitación</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-[#6c7263] uppercase tracking-wider">Asesor Comercial</label>
                                <input v-model="formHabilitar.asesor" type="text" placeholder="Ej: Juan Pérez" class="w-full text-xs rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20 bg-white px-3.5 py-2.5">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-[#6c7263] uppercase tracking-wider">Correo Proveedor</label>
                                <input v-model="formHabilitar.email" type="email" placeholder="Enlace de acceso" class="w-full text-xs rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20 bg-white px-3.5 py-2.5">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-[#6c7263] uppercase tracking-wider">Teléfono de Contacto</label>
                                <input v-model="formHabilitar.telefono" type="text" placeholder="Ej: 0424-1234567" class="w-full text-xs rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20 bg-white px-3.5 py-2.5">
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse sm:flex-row justify-center gap-3">
                            <button @click="nuevaReserva" class="px-5 py-2 text-xs font-bold text-[#6c7263] uppercase tracking-wider">Cancelar</button>
                            <button @click="habilitarOdc" :disabled="habilitando"
                                class="bg-primary text-white hover:bg-[#4f46e5] px-5 py-2.5 rounded-xl text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-wider disabled:opacity-50">
                                {{ habilitando ? 'Procesando...' : 'Habilitar Órden' }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 my-4">
                        <div class="h-[1px] bg-[#eef0eb] flex-grow"></div>
                        <span class="text-[9px] font-black text-[#888c80] uppercase tracking-widest">O Agendar Manualmente</span>
                        <div class="h-[1px] bg-[#eef0eb] flex-grow"></div>
                    </div>

                    <!-- Programación Manual (Calendario) -->
                    <div class="space-y-6">
                        <!-- Header info de la orden -->
                        <div class="bg-[#1c1c1c] text-white p-4 rounded-xl border border-[#eef0eb] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5">
                                <div>
                                    <span class="text-[8px] font-bold text-[#888c80] uppercase leading-none">Orden ERP</span>
                                    <p class="text-xs font-bold font-mono text-primary mt-0.5">{{ numeroOrden }}</p>
                                </div>
                                <div class="hidden sm:block h-5 w-[1px] bg-[#eef0eb]/20"></div>
                                <div>
                                    <span class="text-[8px] font-bold text-[#888c80] uppercase leading-none">Razon Social</span>
                                    <p class="text-xs font-bold truncate max-w-[200px] mt-0.5" :title="datosOrden?.nombre_proveedor">{{ datosOrden?.nombre_proveedor }}</p>
                                </div>
                                <div class="hidden sm:block h-5 w-[1px] bg-[#eef0eb]/20"></div>
                                <div>
                                    <span class="text-[8px] font-bold text-[#888c80] uppercase leading-none">Centro Destino</span>
                                    <p class="text-xs font-bold text-primary mt-0.5">{{ datosOrden?.sucursal_nombre }}</p>
                                </div>
                            </div>
                            <button @click="paso = 1" class="border border-white/20 hover:bg-white/10 text-white text-[9px] font-bold px-2.5 py-1.5 rounded-lg uppercase tracking-wider transition-all">Cambiar Orden</button>
                        </div>

                        <!-- Grid Calendario -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            
                            <!-- Col 1: Fecha -->
                            <div class="bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden h-fit">
                                <div class="px-4 py-3 border-b border-[#eef0eb] bg-[#fcfbf8]"><h4 class="font-bold text-[10px] text-[#1c1c1c] uppercase tracking-wider">📅 1. Seleccione Fecha</h4></div>
                                <div class="p-2 space-y-1">
                                    <button v-for="f in fechasDisponibles" :key="f.fecha" @click="fechaSeleccionada = f.fecha"
                                        class="w-full text-left px-3.5 py-2 rounded-xl transition-all flex items-center justify-between text-xs font-bold"
                                        :class="fechaSeleccionada === f.fecha ? 'bg-primary text-white shadow-sm' : 'hover:bg-[#faf9f6] text-[#6c7263]'">
                                        <span class="capitalize">{{ f.dia_largo }}</span>
                                        <span v-if="f.es_hoy" class="text-[8px] font-black uppercase bg-white/20 px-1.5 py-0.5 rounded">Hoy</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Col 2: Horarios -->
                            <div class="lg:col-span-2 bg-white border border-[#eef0eb] rounded-2xl shadow-sm overflow-hidden">
                                <div class="px-4 py-3 border-b border-[#eef0eb] bg-[#fcfbf8] flex justify-between items-center">
                                    <h4 class="font-bold text-[10px] text-[#1c1c1c] uppercase tracking-wider">🕐 2. Horarios Disponibles</h4>
                                </div>
                                <div v-if="cargandoSlots" class="p-10 text-center text-xs text-[#888c80] font-medium">Buscando slots disponibles...</div>
                                <div v-else class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <button v-for="slot in slotsDisponibles" :key="slot.hora" @click="seleccionarSlot(slot)" :disabled="!slot.disponible"
                                        class="py-2.5 px-2 rounded-xl border border-[#eef0eb] flex flex-col items-center gap-0.5 transition-all text-xs font-bold"
                                        :class="[
                                            slotSeleccionado?.hora === slot.hora ? 'border-primary bg-primary/5 text-primary shadow-sm' :
                                            slot.disponible ? 'bg-white text-[#1c1c1c] hover:border-primary/45' : 'bg-[#faf9f6] text-[#888c80] opacity-40 cursor-not-allowed border-transparent'
                                        ]">
                                        <span class="font-mono">{{ slot.hora_formato }}</span>
                                        <span class="text-[8px] font-black uppercase" :class="slot.disponible ? 'text-emerald-600' : 'text-slate-400'">{{ slot.disponible ? 'Libre' : 'Full' }}</span>
                                    </button>
                                </div>

                                <!-- Form Detalle de Cita (si hay slot) -->
                                <div v-if="slotSeleccionado" class="p-4 border-t border-[#eef0eb] bg-[#fcfbf8]/40 space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-[#888c80] uppercase tracking-wider">Muelle Asignado</label>
                                            <div class="flex flex-wrap gap-1.5">
                                                <button v-for="m in slotSeleccionado.muelles" :key="m" @click="muelleSeleccionado = m"
                                                    class="px-3 py-1.5 rounded-lg border font-bold text-xs transition-all shadow-sm"
                                                    :class="muelleSeleccionado === m ? 'border-primary bg-primary text-white' : 'border-[#eef0eb] text-[#6c7263] bg-white hover:bg-[#faf9f6]'">
                                                    {{ getSucursalNombre(m) }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-[#888c80] uppercase tracking-wider">Ventana de Descarga</label>
                                            <p class="py-2 px-3 border border-[#eef0eb] rounded-xl text-xs font-bold text-[#1c1c1c] bg-white">{{ slotSeleccionado.hora_formato }} - {{ slotSeleccionado.hora_fin }}</p>
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black text-[#888c80] uppercase tracking-wider">Notas logísticas (Opcional)</label>
                                        <textarea v-model="observaciones" rows="2" class="w-full text-xs rounded-xl border-[#eef0eb] focus:border-primary focus:ring-primary/20 bg-white" placeholder="Ej: Ayudante a bordo, rampa mecánica requerida..."></textarea>
                                    </div>
                                    <p v-if="error" class="text-rose-500 font-bold text-xs text-center">⚠️ {{ error }}</p>
                                    <button @click="reservar" :disabled="cargando || !muelleSeleccionado"
                                        class="w-full bg-primary hover:bg-[#4f46e5] text-white py-3 rounded-xl text-xs font-bold shadow-md transition-all active:scale-[0.99] uppercase tracking-wider">
                                        {{ cargando ? 'Agendando...' : 'CONFIRMAR Y AGENDAR CITA' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PASO 3: Confirmación / Éxito -->
                <div v-if="paso === 3" class="max-w-xl mx-auto">
                    <div v-if="odcHabilitadaExitosa" class="bg-white border border-[#eef0eb] rounded-2xl p-6 shadow-sm text-center">
                        <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 text-primary border border-primary/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#1c1c1c]">Habilitación Exitosa</h3>
                        <p class="text-xs text-[#6c7263] mt-1.5 mb-5 max-w-sm mx-auto font-medium">La orden ha sido asignada. Se envió el acceso automático al correo del proveedor comercial.</p>
                        <button @click="nuevaReserva" class="text-xs font-bold text-primary hover:underline uppercase tracking-wider">Procesar otra orden</button>
                    </div>
                    <div v-else class="bg-white border border-[#eef0eb] rounded-2xl p-6 shadow-sm text-center">
                        <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600 border border-emerald-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#1c1c1c]">¡Cita Confirmada!</h3>
                        <p class="text-xs text-[#6c7263] mt-1.5 mb-5 max-w-sm mx-auto font-medium">La entrega de la OC {{ citaConfirmada?.numero_oc }} fue agendada en el muelle asignado.</p>
                        <button @click="nuevaReserva" class="text-xs font-bold text-primary hover:underline uppercase tracking-wider">Volver al Centro</button>
                    </div>
                </div>
            </div>

            <!-- ========== FLUJO PROVEEDOR (Estilo Lovable) ========== -->
            <div class="space-y-6" v-if="userRole === 'proveedor'">
                
                <!-- Paso 1: Mis Órdenes Habilitadas -->
                <div v-if="paso === 1" class="bg-white border border-[#eef0eb] rounded-2xl p-6 shadow-sm">
                    <h3 class="text-base font-bold text-[#1c1c1c] mb-1">Órdenes de Compra Habilitadas</h3>
                    <p class="text-xs text-[#6c7263] mb-5 font-medium">Selecciona tu orden para configurar el transporte e iniciar la reserva.</p>
                    
                    <div v-if="odcsPendientes.length === 0" class="text-[#888c80] text-center py-8 text-xs font-medium">
                        No posees órdenes comerciales pendientes de agendamiento.
                    </div>
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="odc in odcsPendientes" :key="odc.numero_oc" 
                            class="border border-[#eef0eb] rounded-2xl p-5 hover:border-primary/50 hover:bg-[#faf9f6]/40 transition-all cursor-pointer bg-white shadow-sm flex flex-col justify-between"
                            @click="abrirModalProveedor(odc)">
                            <div class="flex justify-between items-center mb-3">
                                <span class="font-extrabold text-sm text-[#1c1c1c] font-mono">{{ odc.numero_oc }}</span>
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 text-[8px] font-black px-2 py-0.5 rounded-full uppercase">Habilitada</span>
                            </div>
                            <div class="space-y-1 text-xs text-[#6c7263] font-medium">
                                <p>Perfil sugerido: <strong class="text-[#1c1c1c]">{{ odc.categoria_sugerida }}</strong></p>
                                <p>Peso total: <strong class="text-[#1c1c1c]">{{ odc.peso_estimado_ton }} Ton</strong></p>
                            </div>
                            <span class="text-[9px] font-black text-primary mt-4 flex items-center gap-1.5 uppercase tracking-wider">Configurar y reservar →</span>
                        </div>
                    </div>
                </div>

                <!-- Paso 2: Calendario para Proveedor -->
                <div v-if="paso === 2" class="bg-white border border-[#eef0eb] rounded-2xl p-6 shadow-sm">
                    <div v-if="isReprogramar" class="bg-amber-50 border-l-4 border-amber-500 p-3 mb-5 rounded-r-xl">
                        <h4 class="text-[10px] font-bold text-amber-800 uppercase tracking-wider">Reprogramando Entrega</h4>
                        <p class="text-xs text-amber-700 mt-0.5">Define un nuevo horario para la orden <strong>{{ formProveedor.numero_oc }}</strong>.</p>
                    </div>
                    
                    <div class="flex items-center justify-between border-b border-[#eef0eb] pb-3.5 mb-5">
                        <h3 class="text-sm font-bold text-[#1c1c1c]">Horarios Disponibles de Entrega</h3>
                        <button @click="nuevaReserva" class="border border-[#eef0eb] hover:bg-[#faf9f6] text-[#6c7263] text-xs font-bold px-3 py-1.5 rounded-xl transition-all uppercase tracking-wider flex items-center gap-1">
                            ← Volver
                        </button>
                    </div>

                    <!-- Date Selection horizontal row -->
                    <div class="flex gap-2 overflow-x-auto pb-3 mb-5">
                        <button v-for="fd in fechasDisponibles" :key="fd.fecha" @click="fechaSeleccionada = fd.fecha"
                            class="flex flex-col items-center justify-center min-w-[76px] p-2.5 rounded-2xl transition-all border shrink-0 text-center"
                            :class="fechaSeleccionada === fd.fecha ? 'bg-primary text-white border-primary shadow-sm' : 'bg-white text-[#6c7263] border-[#eef0eb] hover:border-primary/45'">
                            <span class="text-[8px] font-bold uppercase opacity-85">{{ new Date(fd.fecha + 'T00:00:00').toLocaleDateString('es-VE', { weekday: 'short' }) }}</span>
                            <span class="text-lg font-extrabold my-0.5">{{ new Date(fd.fecha + 'T00:00:00').getDate() }}</span>
                            <span class="text-[8px] font-bold uppercase opacity-85">{{ new Date(fd.fecha + 'T00:00:00').toLocaleDateString('es-VE', { month: 'short' }) }}</span>
                        </button>
                    </div>

                    <!-- Slots Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2.5">
                        <button v-for="slot in slotsDisponibles" :key="slot.hora" @click="seleccionarSlot(slot)" :disabled="!slot.disponible"
                            class="py-2.5 px-2 rounded-xl text-center font-bold text-xs transition-all border border-[#eef0eb]"
                            :class="[
                                !slot.disponible ? 'bg-[#faf9f6] text-[#888c80] opacity-40 cursor-not-allowed border-transparent' : 
                                slotSeleccionado?.hora === slot.hora ? 'bg-primary text-white border-primary shadow-sm' : 'bg-white text-[#1c1c1c] hover:border-primary/40'
                            ]">
                            {{ formatHora(`2000-01-01 ${slot.hora}:00`) }}
                        </button>
                    </div>

                    <!-- Bottom Actions -->
                    <div class="mt-6 flex justify-between items-center border-t border-[#eef0eb] pt-5">
                        <button @click="nuevaReserva" class="px-4 py-2 border border-[#eef0eb] hover:bg-[#faf9f6] text-[#6c7263] rounded-xl text-xs font-bold transition-all uppercase tracking-wider">
                            Volver
                        </button>
                        <button v-if="slotSeleccionado" @click="reservarComoProveedor" :disabled="cargando"
                            class="bg-primary hover:bg-[#4f46e5] text-white px-5 py-2 rounded-xl text-xs font-bold shadow-md transition-all active:scale-[0.99] uppercase tracking-wider">
                            {{ isReprogramar ? (cargando ? 'Guardando...' : 'Guardar Cita') : (cargando ? 'Agendando...' : 'Confirmar Cita') }}
                        </button>
                    </div>
                </div>

                <!-- Formulario de Despacho Modal (Proveedor) -->
                <Modal :show="modalInteligente" @close="modalInteligente = false">
                    <div class="p-6">
                        <h2 class="text-base font-extrabold text-[#1c1c1c] mb-4">Detalles del Transporte y Carga</h2>
                        <div class="space-y-4">
                            <div class="border border-[#eef0eb] rounded-xl p-4 bg-[#faf9f6]">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-xs font-bold text-[#6c7263] uppercase">Correo de Notificación</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer text-[9px] font-bold text-[#6c7263] select-none bg-white px-2 py-0.5 rounded border border-[#eef0eb] shadow-sm">
                                        <input type="checkbox" v-model="editarCorreo" class="rounded border-slate-300 text-primary focus:ring-primary">
                                        Editar
                                    </label>
                                </div>
                                <input v-model="formProveedor.email_contacto" type="email" :readonly="!editarCorreo" :class="editarCorreo ? 'bg-white border-[#eef0eb]' : 'bg-zinc-100 border-transparent text-zinc-500 cursor-not-allowed'" class="w-full text-xs rounded-xl px-4 py-2.5 focus:ring-primary/20" placeholder="ventas@proveedor.com">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-[#6c7263] uppercase">Nº Factura / Remisión</label>
                                    <input v-model="formProveedor.numero_factura" type="text" class="w-full text-xs rounded-xl border-[#eef0eb] bg-white px-3.5 py-2 focus:ring-primary/20" placeholder="Ej: 1234">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-[#6c7263] uppercase">Peso Real (Ton)</label>
                                    <input v-model="formProveedor.peso_factura_ton" type="number" step="any" class="w-full text-xs rounded-xl border-[#eef0eb] bg-white px-3.5 py-2 focus:ring-primary/20" placeholder="Ej: 12.5">
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-[#6c7263] uppercase">Formato Carga</label>
                                <select v-model="formProveedor.formato_carga" class="w-full text-xs rounded-xl border-[#eef0eb] bg-white px-3.5 py-2 focus:outline-none">
                                    <option value="suelta">Carga Suelta</option>
                                    <option value="paletizada">Carga Paletizada</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-[#6c7263] uppercase">Tipo de Vehículo</label>
                                <select v-model="formProveedor.tipo_vehiculo" class="w-full text-xs rounded-xl border-[#eef0eb] bg-white px-3.5 py-2 focus:outline-none">
                                    <option value="" disabled>Seleccione...</option>
                                    <option value="minivan">Minivan</option>
                                    <option value="camioneta_panel">Camioneta Carga</option>
                                    <option value="cava_pequena">Cava Pequeña</option>
                                    <option value="camion_liviano">NPR / Camión Liviano</option>
                                    <option value="gandola_furgon">Gandola</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button @click="modalInteligente = false" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cancelar</button>
                            <button @click="continuarAHorarios" class="bg-primary hover:bg-[#4f46e5] text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm transition-all">Seleccionar Horario</button>
                        </div>
                    </div>
                </Modal>
            </div>
        </div>

        <!-- MODAL CANCELAR (Admin / Recepción / Comprador) -->
        <Modal :show="modalCancelar" @close="cerrarModalCancelar">
            <div class="p-6">
                <h3 class="text-base font-bold text-rose-600 mb-2">Cancelar Cita Agendada</h3>
                <p class="text-xs text-[#6c7263] mb-4 font-medium">¿Confirmas la cancelación de la cita de la OC {{ citaSeleccionada?.numero_oc }}? Se liberará el muelle.</p>
                <textarea v-model="motivoCancelacion" rows="3" class="w-full text-xs rounded-xl border-[#eef0eb] focus:border-rose-500 focus:ring-rose-500/25 px-4 py-3" placeholder="Por favor introduce el motivo de la cancelación..."></textarea>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="cerrarModalCancelar" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cerrar</button>
                    <button @click="confirmarCancelacion" :disabled="motivoCancelacion.length < 5" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all">
                        Confirmar Cancelación
                    </button>
                </div>
            </div>
        </Modal>

        <!-- MODAL REPROGRAMAR (Admin / Recepción / Comprador) -->
        <Modal :show="modalReprogramar" @close="cerrarModalReprogramar">
            <div class="p-6 space-y-4">
                <h3 class="text-base font-bold text-[#1c1c1c]">Reprogramación de Cita</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-[#faf9f6] rounded-xl border border-[#eef0eb] p-3 h-fit space-y-1">
                        <label class="block text-[9px] font-bold text-[#888c80] uppercase tracking-wider mb-2">Nueva Fecha</label>
                        <button v-for="f in repFechasDisponibles" :key="f.fecha" @click="repFechaSeleccionada = f.fecha"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition-all"
                            :class="repFechaSeleccionada === f.fecha ? 'bg-primary text-white shadow-sm' : 'hover:bg-zinc-200/50 text-[#6c7263]'">
                            {{ f.dia_largo }}
                        </button>
                    </div>
                    <div class="md:col-span-2 space-y-4">
                        <div class="border border-[#eef0eb] rounded-xl p-4">
                            <label class="block text-[9px] font-bold text-[#888c80] uppercase tracking-wider mb-2">Nuevo Horario</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="slot in repSlotsDisponibles" :key="slot.hora" @click="seleccionarSlotReprogramacion(slot)" :disabled="!slot.disponible"
                                    class="px-2 py-2 border rounded-lg text-center text-xs font-bold"
                                    :class="repSlotSeleccionado?.hora === slot.hora ? 'border-primary bg-primary/5 text-primary font-bold' : 'border-[#eef0eb] text-[#1c1c1c]'">
                                    {{ slot.hora_formato }}
                                </button>
                            </div>
                        </div>
                        <div v-if="repSlotSeleccionado" class="space-y-4">
                            <div>
                                <label class="block text-[9px] font-bold text-[#888c80] uppercase tracking-wider mb-1.5">Muelle Asignado</label>
                                <div class="flex gap-2">
                                    <button v-for="m in repSlotSeleccionado.muelles" :key="m" @click="repMuelleSeleccionado = m"
                                        class="px-3 py-1.5 border rounded-lg text-xs font-bold"
                                        :class="repMuelleSeleccionado === m ? 'border-primary bg-primary text-white' : 'border-[#eef0eb] text-[#6c7263] bg-white'">
                                        {{ m }}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[9px] font-bold text-[#888c80] uppercase tracking-wider mb-1">Justificación del Cambio</label>
                                <textarea v-model="motivoReprogramacion" rows="2" class="w-full text-xs rounded-xl border-[#eef0eb]" placeholder="Por favor detalla la justificación..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-[#eef0eb]">
                    <button @click="cerrarModalReprogramar" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cancelar</button>
                    <button @click="confirmarReprogramacion" :disabled="motivoReprogramacion.length < 5" class="bg-primary hover:bg-[#4f46e5] text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm transition-all">
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </Modal>

        <!-- MODAL CORREO PROVEEDOR (Habilitación Comprador) -->
        <Modal :show="showEmailModal" @close="cancelarEmailModal">
            <div class="p-6">
                <h3 class="text-base font-bold text-[#1c1c1c] mb-2">Configurar Correo Electrónico</h3>
                <p class="text-xs text-[#6c7263] mb-4 font-medium font-sans">El correo electrónico ingresado recibirá las credenciales y el enlace para agendar.</p>
                <input type="email" v-model="modalEmailInput" class="w-full text-xs rounded-xl border-[#eef0eb] px-4 py-3 focus:border-primary focus:ring-primary/20" placeholder="correo@proveedor.com">
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="cancelarEmailModal" class="px-4 py-2 text-xs font-bold text-[#6c7263]">Cancelar</button>
                    <button @click="guardarYEnviarOdc" class="bg-primary hover:bg-[#4f46e5] text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md transition-all">
                        Habilitar Órden
                    </button>
                </div>
            </div>
        </Modal>

    </component>
</template>

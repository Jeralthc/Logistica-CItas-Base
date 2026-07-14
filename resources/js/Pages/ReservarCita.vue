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
    const m = { programada: 'bg-amber-100 text-amber-700', 'en muelle': 'bg-blue-100 text-blue-700', finalizada: 'bg-emerald-100 text-emerald-700', cancelada: 'bg-red-100 text-red-700' };
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
    <Head title="Reservar Cita" />
    <component :is="$page.props.auth.user ? AuthenticatedLayout : GuestLayout">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    Reservar Cita <span class="text-red-600">| Programar Recepción</span>
                </h2>
                <div class="flex items-center gap-2 text-sm text-slate-500 bg-slate-100 px-4 py-1 rounded-full border border-slate-200">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                    Horario: 8:00 AM - 7:00 PM
                </div>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" v-if="userRole !== 'proveedor'">

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
                <div v-if="paso === 1" class="max-w-xl mx-auto">
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

                    <!-- Citas programadas -->
                    <div v-if="citasProgramadas.length > 0" class="mt-8 bg-white overflow-hidden shadow-xl shadow-slate-200/50 sm:rounded-3xl border border-slate-100">
                        <div class="bg-slate-900 px-8 py-5">
                            <h3 class="text-white font-bold text-base">📋 Citas Programadas</h3>
                            <p class="text-slate-400 text-xs mt-0.5">Próximas recepciones agendadas</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="cita in citasProgramadas" :key="cita.id"
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
                            <p class="text-slate-500">Notifique al proveedor ingresando sus datos para que pueda registrarse y agendar su cita.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700">Nombre del Asesor o Vendedor Comercial</label>
                                <input v-model="formHabilitar.asesor" type="text" placeholder="Ej: Juan Pérez" class="mt-1 w-full border-slate-300 rounded-lg shadow-sm focus:border-red-500 focus:ring-red-500">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700">Correo Electrónico</label>
                                <input v-model="formHabilitar.email" type="email" placeholder="Para enviar la notificación" class="mt-1 w-full border-slate-300 rounded-lg shadow-sm focus:border-red-500 focus:ring-red-500">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700">Teléfono</label>
                                <input v-model="formHabilitar.telefono" type="text" placeholder="Ej: 0414-1234567" class="mt-1 w-full border-slate-300 rounded-lg shadow-sm focus:border-red-500 focus:ring-red-500">
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col-reverse sm:flex-row justify-center gap-3">
                            <button @click="nuevaReserva" class="px-6 py-3 font-bold text-slate-500 hover:text-slate-800 transition-colors">CANCELAR</button>
                            <button @click="habilitarOdc" :disabled="habilitando"
                                class="bg-red-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-red-700 transition-all shadow-lg active:scale-95 disabled:opacity-50">
                                {{ habilitando ? 'HABILITANDO...' : 'HABILITAR ORDEN PARA EL PROVEEDOR' }}
                            </button>
                        </div>
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

                            <div v-else class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button v-for="slot in slotsDisponibles" :key="slot.hora"
                                    @click="seleccionarSlot(slot)"
                                    :disabled="!slot.disponible"
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
                                        <span class="text-[9px] font-black uppercase tracking-tighter" :class="slot.disponible ? 'text-emerald-600' : 'text-slate-400'">
                                            {{ slot.disponible ? 'Libre' : 'Full' }}
                                        </span>
                                    </div>
                                </button>
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
                </div>

                <!-- ========== PASO 3: Confirmación ========== -->
                <div v-if="paso === 3" class="max-w-4xl mx-auto px-4">
                    <div v-if="odcHabilitadaExitosa" class="bg-white overflow-hidden shadow-xl shadow-blue-200/50 sm:rounded-3xl p-8 border border-blue-100 text-center mb-8">
                        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6 text-blue-600">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h3 class="text-3xl font-black text-slate-800 mb-2">¡Orden Habilitada!</h3>
                        <p class="text-slate-500 mb-8 max-w-md mx-auto text-lg">La orden de compra ha sido habilitada. Se ha enviado un correo electrónico de notificación al proveedor para que agende su cita.</p>
                        <button @click="nuevaReserva" class="text-blue-600 hover:text-blue-800 font-bold underline">Volver a mis órdenes / Buscar otra</button>
                    </div>
                    <div v-else class="bg-white overflow-hidden shadow-xl shadow-emerald-200/50 sm:rounded-3xl p-8 border border-emerald-100 text-center mb-8">
                        <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6 text-emerald-600">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h3 class="text-3xl font-black text-slate-800 mb-2">¡Cita <span v-if="isReprogramar">Reprogramada</span><span v-else>Agendada</span>!</h3>
                        <p class="text-slate-500 mb-8 max-w-md mx-auto text-lg">La cita para la ODC {{ citaConfirmada?.numero_oc }} ha sido <span v-if="isReprogramar">reprogramada</span><span v-else>reservada</span> con éxito. Se ha enviado la confirmación por correo.</p>
                        <button @click="nuevaReserva" class="text-emerald-600 hover:text-emerald-800 font-bold underline">Volver al inicio</button>
                    </div>
                </div>

        </div>
        
                <!-- ========== FLUJO PROVEEDOR ========== -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" v-if="userRole === 'proveedor'">
                    <div v-if="paso === 1" class="bg-white overflow-hidden shadow-xl sm:rounded-3xl p-8 mb-8">
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
                        
                        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-4">
                            <h3 class="text-xl font-black text-slate-800">Seleccione Horario de Recepción</h3>
                            <button @click="nuevaReserva" class="text-xs font-bold text-slate-500 hover:text-red-600 transition-colors flex items-center gap-1.5 py-1 px-2.5 rounded-lg hover:bg-slate-50 border border-slate-250">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Volver Atrás
                            </button>
                        </div>
                        
                        <!-- Fechas (reusamos la lista de fechas) -->
                        <div class="flex gap-2 overflow-x-auto pb-4 mb-6 scrollbar-hide">
                            <button v-for="fd in fechasDisponibles" :key="fd.fecha" @click="fechaSeleccionada = fd.fecha"
                                class="flex flex-col items-center justify-center min-w-[80px] p-3 rounded-2xl transition-all border"
                                :class="fechaSeleccionada === fd.fecha ? 'bg-red-600 text-white border-red-600 shadow-md shadow-red-600/30' : 'bg-white text-slate-600 border-slate-200 hover:border-red-300 hover:bg-red-50'">
                                <span class="text-xs font-bold uppercase opacity-80">{{ new Date(fd.fecha + 'T00:00:00').toLocaleDateString('es-VE', { weekday: 'short' }) }}</span>
                                <span class="text-2xl font-black">{{ new Date(fd.fecha + 'T00:00:00').getDate() }}</span>
                                <span class="text-[10px] font-bold uppercase opacity-80">{{ new Date(fd.fecha + 'T00:00:00').toLocaleDateString('es-VE', { month: 'short' }) }}</span>
                            </button>
                        </div>
                        <!-- Slots (reusamos la lista de slots) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
                            <button v-for="slot in slotsDisponibles" :key="slot.hora"
                                @click="seleccionarSlot(slot)"
                                :disabled="!slot.disponible"
                                class="py-3 px-2 rounded-xl text-center font-bold text-sm transition-all border border-transparent flex flex-col items-center justify-center gap-1 relative overflow-hidden"
                                :class="[
                                    !slot.disponible ? 'bg-slate-100 text-slate-400 cursor-not-allowed opacity-60' : 
                                    slotSeleccionado?.hora === slot.hora ? 'bg-red-600 text-white shadow-lg shadow-red-600/30 scale-105 z-10' : 'bg-red-50 text-red-900 hover:bg-red-100 cursor-pointer hover:-translate-y-0.5'
                                ]">
                                {{ formatHora(`2000-01-01 ${slot.hora}:00`) }}
                            </button>
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
                        </div>
                    </div>

                    <!-- Paso 3 was moved to root level so both roles can see it -->

                    <Modal :show="modalInteligente" @close="modalInteligente = false">
                        <div class="p-4 sm:p-8">
                            <h2 class="text-xl sm:text-2xl font-bold mb-4 text-slate-800">Formulario de Despacho</h2>
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
                                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">Nota: 1T = 1000 Kg</span>
                                    </div>
                                    <input v-model="formProveedor.peso_factura_ton" type="number" step="any" class="mt-1 block w-full rounded-md border-slate-300" placeholder="Ej: 24.975">
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
                                    <input type="file" @change="handleFacturaUpload" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                                </div>
                            </div>
                            <div class="mt-6 bg-slate-100 p-4 rounded-lg">
                                <p class="text-sm text-slate-600 font-bold text-center">
                                    Duración de descarga estimada: <span class="text-red-600 text-lg">{{ formatMinutos(duracionCalculada) }}</span>
                                </p>
                            </div>
                            <div class="mt-6 flex flex-col-reverse sm:flex-row justify-end gap-3">
                                <button @click="modalInteligente = false" class="px-4 py-3 sm:py-2 bg-slate-200 rounded font-bold w-full sm:w-auto text-slate-700">Cancelar</button>
                                <button @click="continuarAHorarios" class="px-4 py-3 sm:py-2 bg-red-600 text-white rounded font-bold w-full sm:w-auto">Seleccionar Fecha y Hora</button>
                            </div>
                        </div>
                    </Modal>
                </div>

            </div>
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

        <!-- MODAL CORREO PROVEEDOR -->
        <div v-if="showEmailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
            <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-100 transform transition-all duration-300 scale-100">
                <div class="p-6">
                    <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-lg font-black text-slate-800 leading-snug">Configurar Correo Electrónico</h3>
                    <p v-if="!editarCorreoComprador && modalEmailInput" class="text-xs text-slate-500 mt-2">
                        El proveedor ya tiene el siguiente correo registrado. Si desea cambiarlo, marque la casilla "Editar Correo".
                    </p>
                    <p v-else class="text-xs text-slate-500 mt-2">
                        Por favor, introduzca o modifique el correo electrónico donde el proveedor recibirá la notificación para agendar:
                    </p>
                    
                    <div class="mt-4 border border-slate-200 rounded-xl p-4 bg-slate-50">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-bold text-slate-700">Correo del Proveedor / Vendedor</label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-bold text-slate-600 hover:text-red-600 select-none bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm transition-colors" :class="editarCorreoComprador ? 'border-red-300 bg-red-50 text-red-700' : ''">
                                <input type="checkbox" v-model="editarCorreoComprador" class="rounded border-slate-300 text-red-600 focus:ring-red-600 cursor-pointer">
                                Editar Correo
                            </label>
                        </div>
                        <input 
                            type="email" 
                            v-model="modalEmailInput" 
                            :readonly="!editarCorreoComprador"
                            placeholder="ejemplo@proveedor.com"
                            class="block w-full border-slate-200 rounded-xl focus:border-red-600 focus:ring-red-600/20 text-xs px-4 py-3 font-mono transition-colors"
                            :class="editarCorreoComprador ? 'bg-white' : 'bg-slate-200 text-slate-500 cursor-not-allowed'"
                            @keyup.enter="guardarYEnviarOdc"
                        />
                        <p v-if="modalEmailError" class="text-red-500 font-bold mt-2 text-[11px] flex items-center gap-1">
                            <span>⚠️</span> {{ modalEmailError }}
                        </p>
                    </div>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex flex-col-reverse sm:flex-row justify-end gap-3 border-t border-slate-100">
                    <button @click="cancelarEmailModal"
                        class="px-5 py-3 sm:py-2.5 rounded-xl font-bold text-xs text-slate-500 hover:bg-slate-200 transition-colors w-full sm:w-auto">
                        Cancelar
                    </button>
                    <button @click="guardarYEnviarOdc"
                        class="px-5 py-3 sm:py-2.5 rounded-xl font-bold text-xs text-white bg-red-600 hover:bg-red-700 shadow-lg shadow-red-600/20 transition-all w-full sm:w-auto">
                        Guardar y Enviar
                    </button>
                </div>
            </div>
        </div>
    </component>
</template>

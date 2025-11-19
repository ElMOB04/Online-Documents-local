/*
  app.js - Lógica del formulario paso a paso y guardado en localStorage
  - Validaciones por paso
  - Navegación entre pasos
  - Guardado de la solicitud en localStorage bajo la key 'solicitudes'

  Código comentado en español y sin dependencias externas.
*/

// Obtener elementos del DOM
const loanForm = document.getElementById('loanForm');
const panels = document.querySelectorAll('[data-panel]');
const steps = document.querySelectorAll('.step');

// Botones de navegación
const toStep2 = document.getElementById('toStep2');
const toStep3 = document.getElementById('toStep3');
const toStep4 = document.getElementById('toStep4');
const backTo1 = document.getElementById('backTo1');
const backTo2 = document.getElementById('backTo2');
const backTo3 = document.getElementById('backTo3');

// Campos de cada paso (IDs según index.html)

// Paso 1
const v_nombre_completo = document.getElementById('v_nombre_completo');
const v_apellido = document.getElementById('v_apellido');
const v_cedula = document.getElementById('v_cedula');
const v_edad = document.getElementById('v_edad');
const v_zona = document.getElementById('v_zona');
// Nuevos campos Paso 1
const v_estado_civil = document.getElementById('v_estado_civil');
const v_fecha_nacimiento = document.getElementById('v_fecha_nacimiento');
const v_profesion = document.getElementById('v_profesion');
const v_tel_residencia = document.getElementById('v_tel_residencia');
const v_direccion_completa = document.getElementById('v_direccion_completa');
const v_calles = document.getElementById('v_calles');
const v_conyuge_nombre = document.getElementById('v_conyuge_nombre');
const v_conyuge_tel = document.getElementById('v_conyuge_tel');

// Paso 2
const s_telefono = document.getElementById('s_telefono');
const s_telefono_confirm = document.getElementById('s_telefono_confirm');
const s_movil = document.getElementById('s_movil');
const s_email = document.getElementById('s_email');
const s_sueldo = document.getElementById('s_sueldo');
const s_ahorros_totales = document.getElementById('s_ahorros_totales');
const s_ahorro_mensual = document.getElementById('s_ahorro_mensual');
// Todos los solicitantes son empleados de la cooperativa; requerimos código
const s_codigo_empleado = document.getElementById('s_codigo_empleado');
const loan_monto_solicitado = document.getElementById('loan_monto_solicitado');
const loan_duracion = document.getElementById('loan_duracion');
const loan_deuda_banco = document.getElementById('loan_deuda_banco');
const loan_otra_entidad = document.getElementById('loan_otra_entidad');
// Nuevos campos Paso 2 adicionales
const s_entrada_promedio = document.getElementById('s_entrada_promedio');
const s_tipo_negocio = document.getElementById('s_tipo_negocio');
// Datos laborales extendidos
const s_empresa_nombre = document.getElementById('s_empresa_nombre');
const s_empresa_tel = document.getElementById('s_empresa_tel');
const s_empresa_dir = document.getElementById('s_empresa_dir');
const s_empresa_cargo = document.getElementById('s_empresa_cargo');
const s_empresa_tiempo = document.getElementById('s_empresa_tiempo');
const s_tel_residencia = document.getElementById('s_tel_residencia');
// Referencias comerciales
const rc_nombre_1 = document.getElementById('rc_nombre_1');
const rc_dir_1 = document.getElementById('rc_dir_1');
const rc_tel_1 = document.getElementById('rc_tel_1');
const rc_nombre_2 = document.getElementById('rc_nombre_2');
const rc_dir_2 = document.getElementById('rc_dir_2');
const rc_tel_2 = document.getElementById('rc_tel_2');
const rc_nombre_3 = document.getElementById('rc_nombre_3');
const rc_dir_3 = document.getElementById('rc_dir_3');
const rc_tel_3 = document.getElementById('rc_tel_3');
// Referencias personales
const rp_nombre_1 = document.getElementById('rp_nombre_1');
const rp_dir_1 = document.getElementById('rp_dir_1');
const rp_tel_1 = document.getElementById('rp_tel_1');
const rp_nombre_2 = document.getElementById('rp_nombre_2');
const rp_dir_2 = document.getElementById('rp_dir_2');
const rp_tel_2 = document.getElementById('rp_tel_2');
const rp_nombre_3 = document.getElementById('rp_nombre_3');
const rp_dir_3 = document.getElementById('rp_dir_3');
const rp_tel_3 = document.getElementById('rp_tel_3');

// Paso 3
const g_nombre = document.getElementById('g_nombre');
const g_apellido = document.getElementById('g_apellido');
const g_cedula = document.getElementById('g_cedula');
const g_codigo = document.getElementById('g_codigo');
const g_telefono = document.getElementById('g_telefono');
const g_movil = document.getElementById('g_movil');
const g_email = document.getElementById('g_email');
const g_direccion = document.getElementById('g_direccion');
const g_relacion = document.getElementById('g_relacion');
// Nuevos campos garante expandido
const g_estado_civil = document.getElementById('g_estado_civil');
const g_profesion = document.getElementById('g_profesion');
const g_tipo_negocio = document.getElementById('g_tipo_negocio');
const g_entrada_promedio = document.getElementById('g_entrada_promedio');
const g_tel_residencia = document.getElementById('g_tel_residencia');

// Key para localStorage
const STORAGE_KEY = 'solicitudes_cooperativa_v1';

// Funciones utilitarias
function showPanel(n){
  panels.forEach(p=>p.classList.add('hidden'));
  steps.forEach(s=>s.classList.remove('active'));
  document.querySelector(`[data-panel="${n}"]`).classList.remove('hidden');
  document.querySelector(`.step[data-step="${n}"]`).classList.add('active');
  window.scrollTo({top:0,behavior:'smooth'});
}

// ---------- Formateo de números con separador de miles ----------
function formatNumberWithCommas(val){
  if(val === null || val === undefined || val === '') return '';
  // Acepta número o string; mantener decimales si existen
  const s = String(val).replace(/[^0-9.-]/g,'');
  if(s === '') return '';
  const parts = s.split('.');
  const intPart = parts[0];
  const decPart = parts[1] || '';
  const intFormatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return decPart ? `${intFormatted}.${decPart}` : intFormatted;
}

function parseNumberFromFormatted(str){
  if(str === null || str === undefined) return NaN;
  const cleaned = String(str).replace(/,/g,'').trim();
  if(cleaned === '') return NaN;
  return Number(cleaned);
}

// ---------- Formateo de cédula dominicana: XXX-XXXXXXX-X ----------
function formatDominicanCedula(val){
  if(val === null || val === undefined || val === '') return '';
  const cleaned = String(val).replace(/\D/g, '');
  if(cleaned.length === 0) return '';
  // Formato: XXX-XXXXXXX-X (11 dígitos totales)
  if(cleaned.length <= 3) return cleaned;
  if(cleaned.length <= 10) return cleaned.slice(0, 3) + '-' + cleaned.slice(3);
  return cleaned.slice(0, 3) + '-' + cleaned.slice(3, 10) + '-' + cleaned.slice(10, 11);
}

function parseCedulaFromFormatted(str){
  if(str === null || str === undefined) return '';
  return String(str).replace(/\D/g, '');
}

// ---------- Formateo de teléfono: XXX-XXX-XXXX ----------
function formatPhoneNumber(val){
  if(val === null || val === undefined || val === '') return '';
  const cleaned = String(val).replace(/\D/g, '');
  if(cleaned.length === 0) return '';
  // Formato: XXX-XXX-XXXX (10 dígitos)
  if(cleaned.length <= 3) return cleaned;
  if(cleaned.length <= 6) return cleaned.slice(0, 3) + '-' + cleaned.slice(3);
  return cleaned.slice(0, 3) + '-' + cleaned.slice(3, 6) + '-' + cleaned.slice(6, 10);
}

function parsePhoneFromFormatted(str){
  if(str === null || str === undefined) return '';
  return String(str).replace(/\D/g, '');
}

function attachNumberFormatter(input){
  if(!input) return;
  // Keep caret position roughly in place while formatting
  input.addEventListener('input', (e)=>{
    const el = e.target;
    const selectionStart = el.selectionStart;
    const oldLen = el.value.length;
    // Allow only digits, comma and dot
    const raw = el.value.replace(/[^0-9.,-]/g,'');
    // Normalize multiple dots: keep first
    const firstDotIndex = raw.indexOf('.');
    let normalized = raw;
    if(firstDotIndex !== -1){
      const before = raw.slice(0, firstDotIndex + 1);
      const after = raw.slice(firstDotIndex + 1).replace(/\./g,'');
      normalized = before + after;
    }
    const formatted = formatNumberWithCommas(normalized);
    el.value = formatted;
    // Try to restore approximate caret position
    const newLen = el.value.length;
    const delta = newLen - oldLen;
    try{ el.setSelectionRange(Math.max(0, selectionStart + delta), Math.max(0, selectionStart + delta)); }catch(e){}
  });
}

function attachCedulaFormatter(input){
  if(!input) return;
  input.addEventListener('input', (e)=>{
    const el = e.target;
    const selectionStart = el.selectionStart;
    const oldLen = el.value.length;
    const formatted = formatDominicanCedula(el.value);
    el.value = formatted;
    const newLen = el.value.length;
    const delta = newLen - oldLen;
    try{ el.setSelectionRange(Math.max(0, selectionStart + delta), Math.max(0, selectionStart + delta)); }catch(e){}
  });
}

function attachPhoneFormatter(input){
  if(!input) return;
  input.addEventListener('input', (e)=>{
    const el = e.target;
    const selectionStart = el.selectionStart;
    const oldLen = el.value.length;
    const formatted = formatPhoneNumber(el.value);
    el.value = formatted;
    const newLen = el.value.length;
    const delta = newLen - oldLen;
    try{ el.setSelectionRange(Math.max(0, selectionStart + delta), Math.max(0, selectionStart + delta)); }catch(e){}
  });
}

function updateProgressBar(step){
  const progressFill = document.querySelector('.progress-fill');
  if(progressFill){
    const percentage = (step / 4) * 100;
    progressFill.style.width = percentage + '%';
  }
}

function validateStep1(){
  // Validación del Paso 1: Datos Personales
  if(!v_cedula.value.trim()) return 'Ingrese la cédula.';
  const cedulaNumeros = parseCedulaFromFormatted(v_cedula.value);
  if(cedulaNumeros.length !== 11) return 'La cédula debe tener 11 dígitos.';
  if(!v_edad.value) return 'Seleccione su rango de edad.';
  if(!v_nombre_completo.value.trim()) return 'Ingrese su nombre.';
  if(!v_apellido.value.trim()) return 'Ingrese sus apellidos.';
  if(!v_zona.value) return 'Seleccione su zona de residencia.';
  if(!v_estado_civil || !v_estado_civil.value) return 'Seleccione su estado civil.';
  if(!v_fecha_nacimiento || !v_fecha_nacimiento.value) return 'Ingrese su fecha de nacimiento.';
  if(!v_profesion || !v_profesion.value.trim()) return 'Ingrese su profesión u oficio.';
  if(!v_direccion_completa || !v_direccion_completa.value.trim()) return 'Ingrese su dirección completa.';
  if(!v_tel_residencia || !v_tel_residencia.value.trim()) return 'Ingrese su teléfono de residencia.';
  return null;
}

function validateStep2(){
  // Validación del Paso 2: Contacto e Información Financiera
  const tel1 = parsePhoneFromFormatted(s_telefono.value);
  const tel2 = parsePhoneFromFormatted(s_telefono_confirm.value);
  if(!tel1 || tel1.length < 7) 
    return 'Ingrese un teléfono celular válido.';
  if(!tel2 || tel2.length < 7)
    return 'Confirme su teléfono celular.';
  if(tel1 !== tel2)
    return 'Los teléfonos no coinciden.';
  // Email opcional: si se proporciona, validar formato básico
  if(s_email && s_email.value && s_email.value.trim()){
    if(!s_email.value.includes('@')) return 'Ingrese un correo electrónico válido.';
  }
  if(!s_sueldo.value.trim()) return 'Ingrese sus ingresos mensuales.';
  // Ahorros: validar monto total y mensual
  const ahorroMens = parseNumberFromFormatted(s_ahorro_mensual && s_ahorro_mensual.value ? s_ahorro_mensual.value : '');
  const ahorroTotal = parseNumberFromFormatted(s_ahorros_totales && s_ahorros_totales.value ? s_ahorros_totales.value : '');
  if(!s_ahorro_mensual || s_ahorro_mensual.value === '' || Number.isNaN(ahorroMens) || ahorroMens <= 0) return 'Ingrese su ahorro mensual (mayor que 0).';
  if(!s_ahorros_totales || s_ahorros_totales.value === '' || Number.isNaN(ahorroTotal) || ahorroTotal < 0) return 'Ingrese su monto total de ahorros (>= 0).';
  // El total acumulado debe ser al menos el ahorro mensual (sanidad básica)
  if(ahorroTotal < ahorroMens) return 'El monto total de ahorros debe ser mayor o igual al ahorro mensual.';
  // Código de empleado opcional
  if(!loan_monto_solicitado.value.trim() || Number.isNaN(parseNumberFromFormatted(loan_monto_solicitado.value))) return 'Ingrese el monto solicitado.';
  if(!loan_duracion.value || loan_duracion.value < 1) return 'Ingrese una duración válida.';
  // Validaciones nuevos campos laborales
  if(!s_empresa_nombre || !s_empresa_nombre.value.trim()) return 'Ingrese el nombre de su empresa.';
  if(!s_empresa_tel || !s_empresa_tel.value.trim()) return 'Ingrese el teléfono de la empresa.';
  if(!s_empresa_dir || !s_empresa_dir.value.trim()) return 'Ingrese la dirección de la empresa.';
  if(!s_empresa_cargo || !s_empresa_cargo.value.trim()) return 'Ingrese su cargo en la empresa.';
  if(!s_empresa_tiempo || !s_empresa_tiempo.value.trim()) return 'Ingrese el tiempo empleado.';
  if(!s_tel_residencia || !s_tel_residencia.value.trim()) return 'Ingrese el teléfono de residencia.';
  // Referencia comercial mínima
  if(!rc_nombre_1 || !rc_nombre_1.value.trim()) return 'Debe ingresar al menos una referencia comercial.';
  if(!rc_dir_1 || !rc_dir_1.value.trim()) return 'Dirección de la referencia comercial 1 requerida.';
  if(!rc_tel_1 || !rc_tel_1.value.trim()) return 'Teléfono de la referencia comercial 1 requerido.';
  // Referencia personal mínima
  if(!rp_nombre_1 || !rp_nombre_1.value.trim()) return 'Debe ingresar al menos una referencia personal.';
  if(!rp_dir_1 || !rp_dir_1.value.trim()) return 'Dirección de la referencia personal 1 requerida.';
  if(!rp_tel_1 || !rp_tel_1.value.trim()) return 'Teléfono de la referencia personal 1 requerido.';
  return null;
}

function validateStep3(){
  // Validación del Paso 3: Datos del Garante
  if(!g_nombre.value.trim()) return 'Ingrese el nombre del garante.';
  if(!g_apellido.value.trim()) return 'Ingrese el apellido del garante.';
  if(!g_cedula || !g_cedula.value.trim()) return 'Ingrese la cédula del garante.';
  const cedulaGarante = parseCedulaFromFormatted(g_cedula.value);
  if(cedulaGarante.length !== 11) return 'La cédula del garante debe tener 11 dígitos.';
  const telGarante = parsePhoneFromFormatted(g_telefono.value);
  if(!telGarante || telGarante.length < 7) 
    return 'Ingrese un teléfono válido del garante.';
  if(!g_direccion.value.trim()) return 'Ingrese la dirección del garante.';
  if(!g_relacion.value) return 'Seleccione la relación con el solicitante.';
  if(!g_estado_civil || !g_estado_civil.value) return 'Seleccione estado civil del garante.';
  if(!g_profesion || !g_profesion.value.trim()) return 'Ingrese profesión del garante.';
  return null;
}

// Permitir navegación rápida a pasos anteriores al hacer click en la barra de pasos
steps.forEach((stepEl)=>{
  stepEl.addEventListener('click', ()=>{
    try{
      const target = Number(stepEl.dataset.step || 0);
      // Determinar paso actual
      const active = document.querySelector('.step.active');
      const current = active ? Number(active.dataset.step || 0) : 1;
      // Si el usuario quiere ir hacia atrás o al mismo paso, permitir siempre
      if(target <= current){
        showPanel(target);
        updateProgressBar(target);
        return;
      }
      // Si quiere avanzar, validar los pasos intermedios
      let ok = true;
      for(let s = 1; s < target; s++){
        const fn = (s === 1) ? validateStep1 : (s === 2) ? validateStep2 : (s === 3) ? validateStep3 : null;
        if(fn){ const err = fn(); if(err){ ok = false; alert(err); break; } }
      }
      if(ok){ showPanel(target); updateProgressBar(target); }
    }catch(e){/* ignore */}
  });
});

// Manejo de navegación
toStep2.addEventListener('click', ()=>{
  const err = validateStep1();
  if(err){alert(err);return}
  showPanel(2);
  updateProgressBar(2);
});

toStep3.addEventListener('click', ()=>{
  const err = validateStep2();
  if(err){alert(err);return}
  showPanel(3);
  updateProgressBar(3);
});

toStep4.addEventListener('click', ()=>{
  const err = validateStep3();
  if(err){alert(err);return}
  showPanel(4);
  updateProgressBar(4);
});

backTo1.addEventListener('click', ()=>{showPanel(1);updateProgressBar(1)});
backTo2.addEventListener('click', ()=>{showPanel(2);updateProgressBar(2)});
backTo3.addEventListener('click', ()=>{showPanel(3);updateProgressBar(3)});

// Envío final
loanForm.addEventListener('submit', (e)=>{
  e.preventDefault();
  
  // Validar aceptación de privacidad
  const privacyAccept = document.getElementById('privacy_accept');
  if(!privacyAccept || !privacyAccept.checked){
    alert('Debe aceptar los términos de privacidad para enviar su solicitud.');
    return;
  }
  
  // Validar todo antes de guardar
  const v1 = validateStep1();
  const v2 = validateStep2();
  const v3 = validateStep3();
  if(v1||v2||v3){alert(v1||v2||v3);return}

  // Construir objeto de solicitud con estructura corporativa
  const now = new Date();
  const solicitud = {
    id: 's_' + Date.now(),
    fecha: now.toISOString(),
    estado: 'Pendiente',
    datosPersonales: {
      cedula: parseCedulaFromFormatted(v_cedula.value),
      edad: v_edad.value,
      nombres: v_nombre_completo.value.trim(),
      apellidos: v_apellido.value.trim(),
      zona: v_zona.value,
      estadoCivil: v_estado_civil ? v_estado_civil.value : '',
      fechaNacimiento: v_fecha_nacimiento ? v_fecha_nacimiento.value : '',
      profesion: v_profesion ? v_profesion.value.trim() : '',
      telResidencia: parsePhoneFromFormatted(v_tel_residencia ? v_tel_residencia.value : ''),
      direccionCompleta: v_direccion_completa ? v_direccion_completa.value.trim() : '',
      callesReferencia: v_calles ? v_calles.value.trim() : '',
      conyugeNombre: v_conyuge_nombre ? v_conyuge_nombre.value.trim() : '',
      conyugeTelefono: parsePhoneFromFormatted(v_conyuge_tel ? v_conyuge_tel.value : '')
    },
    datosContacto: {
      telefono: parsePhoneFromFormatted(s_telefono.value),
      whatsapp: parsePhoneFromFormatted(s_movil.value),
      email: s_email.value.trim()
    },
    datosLaborales: {
      codigoEmpleado: (s_codigo_empleado && s_codigo_empleado.value) ? s_codigo_empleado.value.trim() : '',
      empresaNombre: s_empresa_nombre ? s_empresa_nombre.value.trim() : '',
      empresaTelefono: parsePhoneFromFormatted(s_empresa_tel ? s_empresa_tel.value : ''),
      empresaDireccion: s_empresa_dir ? s_empresa_dir.value.trim() : '',
      empresaCargo: s_empresa_cargo ? s_empresa_cargo.value.trim() : '',
      empresaTiempo: s_empresa_tiempo ? s_empresa_tiempo.value.trim() : '',
      telefonoResidencia: parsePhoneFromFormatted(s_tel_residencia ? s_tel_residencia.value : ''),
      tipoNegocio: s_tipo_negocio ? s_tipo_negocio.value.trim() : '',
      entradaPromedio: parseNumberFromFormatted(s_entrada_promedio ? s_entrada_promedio.value : '')
    },
    infoFinanciera: {
      ingresosMensuales: parseNumberFromFormatted(s_sueldo.value),
      ahorrosMensuales: parseNumberFromFormatted(s_ahorro_mensual && s_ahorro_mensual.value ? s_ahorro_mensual.value : ''),
      ahorrosTotales: parseNumberFromFormatted(s_ahorros_totales && s_ahorros_totales.value ? s_ahorros_totales.value : '')
    },
    detallesPrestamo: {
      montoSolicitado: parseNumberFromFormatted(loan_monto_solicitado.value),
      duracion: loan_duracion.value,
      deudaBanco: parseNumberFromFormatted(loan_deuda_banco.value),
      otraEntidad: loan_otra_entidad.value.trim()
    },
    garante: {
      nombres: g_nombre.value.trim(),
      apellidos: g_apellido.value.trim(),
      cedula: parseCedulaFromFormatted(g_cedula.value),
      codigo: g_codigo.value.trim(),
      telefono: parsePhoneFromFormatted(g_telefono.value),
      whatsapp: parsePhoneFromFormatted(g_movil.value),
      email: g_email.value.trim(),
      direccion: g_direccion.value.trim(),
      relacion: g_relacion.value,
      estadoCivil: g_estado_civil ? g_estado_civil.value : '',
      profesion: g_profesion ? g_profesion.value.trim() : '',
      tipoNegocio: g_tipo_negocio ? g_tipo_negocio.value.trim() : '',
      entradaPromedio: parseNumberFromFormatted(g_entrada_promedio ? g_entrada_promedio.value : ''),
      telResidencia: parsePhoneFromFormatted(g_tel_residencia ? g_tel_residencia.value : '')
    },
    referenciasComerciales: [
      { nombre: rc_nombre_1 ? rc_nombre_1.value.trim() : '', direccion: rc_dir_1 ? rc_dir_1.value.trim() : '', telefono: parsePhoneFromFormatted(rc_tel_1 ? rc_tel_1.value : '') },
      { nombre: rc_nombre_2 ? rc_nombre_2.value.trim() : '', direccion: rc_dir_2 ? rc_dir_2.value.trim() : '', telefono: parsePhoneFromFormatted(rc_tel_2 ? rc_tel_2.value : '') },
      { nombre: rc_nombre_3 ? rc_nombre_3.value.trim() : '', direccion: rc_dir_3 ? rc_dir_3.value.trim() : '', telefono: parsePhoneFromFormatted(rc_tel_3 ? rc_tel_3.value : '') }
    ],
    referenciasPersonales: [
      { nombre: rp_nombre_1 ? rp_nombre_1.value.trim() : '', direccion: rp_dir_1 ? rp_dir_1.value.trim() : '', telefono: parsePhoneFromFormatted(rp_tel_1 ? rp_tel_1.value : '') },
      { nombre: rp_nombre_2 ? rp_nombre_2.value.trim() : '', direccion: rp_dir_2 ? rp_dir_2.value.trim() : '', telefono: parsePhoneFromFormatted(rp_tel_2 ? rp_tel_2.value : '') },
      { nombre: rp_nombre_3 ? rp_nombre_3.value.trim() : '', direccion: rp_dir_3 ? rp_dir_3.value.trim() : '', telefono: parsePhoneFromFormatted(rp_tel_3 ? rp_tel_3.value : '') }
    ],
    clausulaMoraAceptada: true
  };

  saveSolicitud(solicitud);
  alert('✓ Solicitud enviada exitosamente.\n\nSu solicitud ha sido registrada. Nos pondremos en contacto pronto.');
  // Reset formulario a paso 1
  loanForm.reset();
  privacyAccept.checked = false;
  showPanel(1);
  updateProgressBar(1);
});// Guardar en localStorage
function saveSolicitud(s){
  const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  arr.unshift(s); // agregar al inicio
  localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
}

// Inicializar vista
showPanel(1);

// --- C c1LCULO DIN c1MICO DE CUOTA MENSUAL ---
const quotaDisplay = document.getElementById('quotaDisplay');
const QUOTA_RATE_KEY = 'coop_interest_rate'; // tasa anual en porcentaje guardada por admin

function getAnnualRateFromStorage(){
  try{
    const raw = localStorage.getItem(QUOTA_RATE_KEY);
    if(!raw) return null;
    const v = Number(raw);
    return Number.isFinite(v) ? v : null;
  }catch(e){return null}
}

function computeMonthlyQuota(amount, months, annualRatePercent){
  // amount: number, months: integer, annualRatePercent: number (ej. 18)
  const P = Number(amount);
  const n = Number(months);
  if(!P || !n || n <= 0) return null;
  const annual = Number(annualRatePercent) || 0;
  const i = annual / 1200; // tasa mensual en decimal
  if(i === 0){
    return P / n; // sin interes
  }
  const numerator = i * Math.pow(1 + i, n);
  const denominator = Math.pow(1 + i, n) - 1;
  const cuota = P * (numerator / denominator);
  return cuota;
}

function formatMoney(v){
  if(v === null || v === undefined || Number.isNaN(Number(v))) return '--';
  return 'RD$ ' + Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
}

function updateQuotaDisplay(){
  try{
  const monto = parseNumberFromFormatted(loan_monto_solicitado && loan_monto_solicitado.value ? loan_monto_solicitado.value : '');
    const meses = loan_duracion && loan_duracion.value ? Number(loan_duracion.value) : 0;
    const anual = getAnnualRateFromStorage();
    if(!monto || !meses || !anual){
      if(quotaDisplay) quotaDisplay.textContent = '--';
      return;
    }
    const cuota = computeMonthlyQuota(monto, meses, anual);
    if(quotaDisplay) quotaDisplay.textContent = cuota ? formatMoney(cuota) : '--';
  }catch(e){ if(quotaDisplay) quotaDisplay.textContent = '--'; }
}

// Listeners para recalcular en tiempo real
// Attach formatters and listeners
// Cédulas
attachCedulaFormatter(v_cedula);
attachCedulaFormatter(g_cedula);

// Teléfonos
attachPhoneFormatter(s_telefono);
attachPhoneFormatter(s_telefono_confirm);
attachPhoneFormatter(s_movil);
attachPhoneFormatter(s_tel_residencia);
attachPhoneFormatter(g_telefono);
attachPhoneFormatter(g_movil);
attachPhoneFormatter(s_empresa_tel);
attachPhoneFormatter(rc_tel_1);
attachPhoneFormatter(rc_tel_2);
attachPhoneFormatter(rc_tel_3);
attachPhoneFormatter(rp_tel_1);
attachPhoneFormatter(rp_tel_2);
attachPhoneFormatter(rp_tel_3);

// Números con miles
attachNumberFormatter(s_sueldo);
attachNumberFormatter(s_ahorro_mensual);
attachNumberFormatter(s_ahorros_totales);
attachNumberFormatter(loan_monto_solicitado);
attachNumberFormatter(loan_deuda_banco);
attachNumberFormatter(s_entrada_promedio);
attachNumberFormatter(g_entrada_promedio);

loan_monto_solicitado?.addEventListener('input', updateQuotaDisplay);
loan_duracion?.addEventListener('input', updateQuotaDisplay);
s_ahorro_mensual?.addEventListener('input', ()=>{
  updateQuotaDisplay();
  // update note if exists
  const note = document.getElementById('savingsNote');
  if(note){
    const total = parseNumberFromFormatted(s_ahorros_totales && s_ahorros_totales.value ? s_ahorros_totales.value : '') || 0;
    const monthly = parseNumberFromFormatted(s_ahorro_mensual && s_ahorro_mensual.value ? s_ahorro_mensual.value : '') || 0;
    note.textContent = `Total: RD$ ${total.toLocaleString(undefined,{minimumFractionDigits:2})} • Mensual: RD$ ${monthly.toLocaleString(undefined,{minimumFractionDigits:2})}`;
  }
});

s_ahorros_totales?.addEventListener('input', ()=>{
  const note = document.getElementById('savingsNote');
  if(note){
    const total = parseNumberFromFormatted(s_ahorros_totales && s_ahorros_totales.value ? s_ahorros_totales.value : '') || 0;
    const monthly = parseNumberFromFormatted(s_ahorro_mensual && s_ahorro_mensual.value ? s_ahorro_mensual.value : '') || 0;
    note.textContent = `Total: RD$ ${total.toLocaleString(undefined,{minimumFractionDigits:2})} • Mensual: RD$ ${monthly.toLocaleString(undefined,{minimumFractionDigits:2})}`;
  }
  updateQuotaDisplay();
});

// Escuchar cambios en localStorage para la tasa (puede cambiar desde admin en otra pesta f1a)
window.addEventListener('storage', (e)=>{
  if(e.key === QUOTA_RATE_KEY){ updateQuotaDisplay(); }
});

// Calcular una vez al cargar
updateQuotaDisplay();

// Tema (light/dark) - persistir elección en localStorage
const THEME_KEY = 'coop_theme';
// themeToggle button removed from markup to force stable headings color in dark mode
function applyTheme(t){
  try{
    document.documentElement.classList.remove('theme-light','theme-dark');
    if(t === 'dark') document.documentElement.classList.add('theme-dark');
    else document.documentElement.classList.add('theme-light');
  // no theme toggle visible; we still apply classes based on saved preference
  }catch(e){/* ignore */}
}
function toggleTheme(){
  const cur = localStorage.getItem(THEME_KEY) || 'light';
  const next = (cur === 'dark') ? 'light' : 'dark';
  localStorage.setItem(THEME_KEY, next);
  applyTheme(next);
}
// Inicializar tema en carga
(function(){
  const saved = localStorage.getItem(THEME_KEY) || 'light';
  applyTheme(saved);
  // themeToggle removed: no click handler registration
})();

/* Fin de app.js */

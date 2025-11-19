/*
  admin.js - Panel administrativo (VERSIÓN LIMPIA Y FUNCIONAL)
  Carga solicitudes, renderiza lista, permite ver detalles y exportar PDF.
*/

const STORAGE_KEY = 'solicitudes_cooperativa_v1';
const listContainer = document.getElementById('listContainer');
const refreshBtn = document.getElementById('refreshBtn');
const clearBtn = document.getElementById('clearBtn');

const detailModal = document.getElementById('detailModal');
const detailArea = document.getElementById('detailArea');
const closeModal = document.getElementById('closeModal');
const logoPreview = document.getElementById('logoPreview');
const filterNameInput = document.getElementById('filterName');
const filterDateFrom = document.getElementById('filterDateFrom');
const filterDateTo = document.getElementById('filterDateTo');
const filterStatus = document.getElementById('filterStatus');

let cooperativeLogoData = './logodecoopatria.png';
let cooperativeLogoBase64 = null; // Almacenar logo como base64 para el PDF
let allSolicitudes = [];

function loadLogoFromStorage(){
  try{
    // Usar logo fijo en el proyecto
    const defaultPath = './logodecoopatria.png';
    cooperativeLogoData = defaultPath;
    if(logoPreview){ logoPreview.src = defaultPath; logoPreview.style.display = 'block'; }
  }catch(e){
    console.error('Error loading logo', e);
  }
}

function loadLogoAsBase64(imagePath){
  // Esta función ya no es necesaria - simplificar
  // Dejar que html2canvas maneje la imagen
  return;
}

function loadDefaultLogo(){
  // fallback interno: si el logo fijo no carga, ocultar preview
  const defaultPath = './logodecoopatria.png';
  const img = new Image();
  img.onload = function(){ if(logoPreview){ logoPreview.src = defaultPath; logoPreview.style.display = 'block'; } };
  img.onerror = function(){ if(logoPreview) logoPreview.style.display = 'none'; };
  img.src = defaultPath;
}

// No se permite subir/quitar logo: se usa el logo fijo en ./logodecoopatria.png

// Protección: si los elementos no existen por alguna razón, no continuar para evitar errores que
// paren la ejecución del script y dejen la UI en un estado intermedio.
if(!listContainer || !detailModal || !detailArea){
  console.warn('admin.js: elementos DOM críticos ausentes. Abortando inicialización.');
} 

// Cargar y renderizar
function loadSolicitudes(){
  allSolicitudes = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  applyFilters();
}

function isFilterActive(){
  return Boolean(
    (filterNameInput && filterNameInput.value.trim()) ||
    (filterDateFrom && filterDateFrom.value) ||
    (filterDateTo && filterDateTo.value) ||
    (filterStatus && filterStatus.value)
  );
}

function applyFilters(){
  let filtered = Array.isArray(allSolicitudes) ? [...allSolicitudes] : [];

  if(filterNameInput){
    const query = filterNameInput.value.trim().toLowerCase();
    if(query){
      filtered = filtered.filter((s)=>{
        const nombres = ((s.datosPersonales && s.datosPersonales.nombres) || '') + ' ' + ((s.datosPersonales && s.datosPersonales.apellidos) || '');
        const alt = ((s.solicitante && s.solicitante.nombre) || '') + ' ' + ((s.solicitante && s.solicitante.apellido) || '');
        const combined = `${nombres} ${alt}`.toLowerCase();
        return combined.includes(query);
      });
    }
  }

  if(filterDateFrom && filterDateFrom.value){
    const fromDate = new Date(`${filterDateFrom.value}T00:00:00`);
    if(!Number.isNaN(fromDate.getTime())){
      filtered = filtered.filter((s)=>{
        const fecha = new Date(s.fecha || s.createdAt || s.fechaSolicitud || 0);
        if(Number.isNaN(fecha.getTime())) return false;
        return fecha >= fromDate;
      });
    }
  }

  if(filterDateTo && filterDateTo.value){
    const toDate = new Date(`${filterDateTo.value}T23:59:59`);
    if(!Number.isNaN(toDate.getTime())){
      filtered = filtered.filter((s)=>{
        const fecha = new Date(s.fecha || s.createdAt || s.fechaSolicitud || 0);
        if(Number.isNaN(fecha.getTime())) return false;
        return fecha <= toDate;
      });
    }
  }

  if(filterStatus && filterStatus.value){
    const desired = filterStatus.value.toLowerCase();
    filtered = filtered.filter((s)=>((s.estado || '').toLowerCase() === desired));
  }

  renderList(filtered);
}

// Normalizar objetos de solicitud para soportar ambas estructuras (antigua y nueva)
function normalizeSolicitud(s){
  if(!s) return {};
  return {
    id: s.id,
    estado: s.estado,
    fecha: s.fecha,
    nombre: (s.datosPersonales && s.datosPersonales.nombres) || (s.solicitante && s.solicitante.nombre) || '',
    apellido: (s.datosPersonales && s.datosPersonales.apellidos) || (s.solicitante && s.solicitante.apellido) || '',
    cedula: (s.datosPersonales && s.datosPersonales.cedula) || (s.verificacion && s.verificacion.cedula) || '',
    telefono: (s.datosContacto && s.datosContacto.telefono) || (s.solicitante && s.solicitante.telefono) || '',
  direccion: (s.datosPersonales && s.datosPersonales.direccionCompleta) || (s.solicitante && s.solicitante.direccion) || '',
    rol: 'Empleado',
    sueldo: (s.infoFinanciera && s.infoFinanciera.ingresosMensuales) || '',
    ahorros: '',
    prestamo: {
      monto_solicitado: (s.detallesPrestamo && s.detallesPrestamo.montoSolicitado) || (s.prestamo && s.prestamo.monto_solicitado) || '',
      duracion: (s.detallesPrestamo && s.detallesPrestamo.duracion) || (s.prestamo && s.prestamo.duracion) || ''
    },
  garante: s.garante || (s.garante ? s.garante : {}),
    estadoCivil: (s.datosPersonales && s.datosPersonales.estadoCivil) || '',
    fechaNacimiento: (s.datosPersonales && s.datosPersonales.fechaNacimiento) || '',
    profesion: (s.datosPersonales && s.datosPersonales.profesion) || '',
    telResidencia: (s.datosPersonales && s.datosPersonales.telResidencia) || '',
    conyugeNombre: (s.datosPersonales && s.datosPersonales.conyugeNombre) || '',
    conyugeTelefono: (s.datosPersonales && s.datosPersonales.conyugeTelefono) || '',
    empresaNombre: (s.datosLaborales && s.datosLaborales.empresaNombre) || '',
    empresaCargo: (s.datosLaborales && s.datosLaborales.empresaCargo) || '',
    empresaTiempo: (s.datosLaborales && s.datosLaborales.empresaTiempo) || '',
    empresaTelefono: (s.datosLaborales && s.datosLaborales.empresaTelefono) || '',
    empresaDireccion: (s.datosLaborales && s.datosLaborales.empresaDireccion) || '',
    referenciasComerciales: Array.isArray(s.referenciasComerciales) ? s.referenciasComerciales : [],
    referenciasPersonales: Array.isArray(s.referenciasPersonales) ? s.referenciasPersonales : []
  };
}

function buildCardElement(s){
  const n = normalizeSolicitud(s);
  const div = document.createElement('div');
  div.className = 'card-item';
  div.innerHTML = `
      <h3>${escapeHtml(n.nombre)} ${escapeHtml(n.apellido)}</h3>
      <div class="meta">Cédula: ${escapeHtml(n.cedula)} • Tel: ${escapeHtml(n.telefono)}</div>
      <div class="meta">Estado: <strong>${escapeHtml(s.estado)}</strong></div>
      <div class="item-actions">
        <button class="btn" data-id="${s.id}" data-action="view">Ver Detalles</button>
        <button class="btn" data-id="${s.id}" data-action="pdf">Exportar PDF</button>
      </div>
  `;
  return div;
}

function renderList(arr){
  listContainer.innerHTML = '';
  if(!arr.length){
    const message = isFilterActive()
      ? 'No hay solicitudes que coincidan con los filtros.'
      : 'No hay solicitudes registradas.';
    listContainer.innerHTML = `<div class="card-item empty"><em>${message}</em></div>`;
    return;
  }

  const pending = arr.filter((s)=> (s.estado || '').toLowerCase() === 'pendiente');
  const processed = arr.filter((s)=> (s.estado || '').toLowerCase() !== 'pendiente');

  const wrapper = document.createElement('div');
  wrapper.className = 'list-columns';

  const buildColumn = (items, title, columnClass, emptyText)=>{
    const col = document.createElement('div');
    col.className = `list-column ${columnClass}` + (items.length ? '' : ' empty');
    const heading = document.createElement('h3');
    heading.textContent = title;
    col.appendChild(heading);
    if(!items.length){
      const emptyNode = document.createElement('div');
      emptyNode.className = 'card-item empty';
      emptyNode.innerHTML = `<em>${emptyText}</em>`;
      col.appendChild(emptyNode);
    } else {
      items.forEach((item)=>{
        col.appendChild(buildCardElement(item));
      });
    }
    return col;
  };

  wrapper.appendChild(
    buildColumn(
      pending,
      'Solicitudes nuevas',
      'pending-column',
      isFilterActive() ? 'Sin coincidencias para las solicitudes pendientes.' : 'No hay solicitudes pendientes.'
    )
  );

  wrapper.appendChild(
    buildColumn(
      processed,
      'Solicitudes trabajadas',
      'processed-column',
      isFilterActive() ? 'Sin coincidencias en solicitudes trabajadas.' : 'Aún no hay solicitudes con estado Aprobado o Rechazado.'
    )
  );

  listContainer.appendChild(wrapper);
}

// Manejar clicks en la lista (delegación)
if(listContainer){
  listContainer.addEventListener('click', (e)=>{
    const btn = e.target.closest('button');
    if(!btn) return;
    const id = btn.dataset.id;
    const action = btn.dataset.action;
    if(action === 'view') openDetail(id);
    if(action === 'pdf') exportSolicitudPDF(id);
  });
}

// Abrir modal con detalles
function openDetail(id){
  const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  const s = arr.find(x=>x.id===id);
  if(!s) return alert('Solicitud no encontrada.');

  const n = normalizeSolicitud(s);
  detailArea.innerHTML = buildDetailHtml(s, n);
  // Attach event for changing state and export
  const stateSelect = detailArea.querySelector('#stateSelect');
  if(stateSelect){
    stateSelect.addEventListener('change', ()=>{
      s.estado = stateSelect.value;
      // Guardar cambio
      localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
      loadSolicitudes();
    });
  }

  const pdfBtn = detailArea.querySelector('#pdfExportDetail');
  if(pdfBtn) pdfBtn.addEventListener('click', ()=>exportSolicitudPDF(id));

  // Handler para guardar condiciones modificadas por admin (monto y duracion)
  const saveTermsBtn = detailArea.querySelector('#saveTermsBtn');
  if(saveTermsBtn){
    saveTermsBtn.addEventListener('click', ()=>{
      const montoInput = detailArea.querySelector('#admin_monto_input');
      const durInput = detailArea.querySelector('#admin_duracion_input');
      const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      const idx = arr.findIndex(x=>x.id===id);
      if(idx === -1) return alert('No se encontró la solicitud para guardar.');
      // Guardar en la estructura existente (detallesPrestamo o prestamo)
  const montoVal = montoInput ? montoInput.value.trim() : '';
  const durVal = durInput ? durInput.value.trim() : '';
  // Validación básica: monto numérico mayor a 0 y duración entero >= 1
  const montoNum = parseNumberFromFormatted(montoVal);
  const durNum = Number(durVal);
  if(!montoVal || Number.isNaN(montoNum) || montoNum <= 0){ alert('Ingrese un monto aprobado válido (número mayor que 0).'); return; }
  if(!durVal || Number.isNaN(durNum) || !Number.isInteger(durNum) || durNum < 1){ alert('Ingrese una duración válida en meses (entero >= 1).'); return; }
  if(!arr[idx].detallesPrestamo) arr[idx].detallesPrestamo = {};
  // Guardar los valores tal como los introduce el admin (formateados), y la lógica de parseo se usa cuando se necesite calcular
  arr[idx].detallesPrestamo.montoSolicitado = montoVal;
  arr[idx].detallesPrestamo.duracion = durVal;
      // Persistir el cambio
      localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
      // Recargar la lista y reabrir el modal con los nuevos datos (reutiliza openDetail para reatachar handlers)
      loadSolicitudes();
      // Cerrar modal actual y reabrir con datos actualizados para reatachar listeners
      detailModal.classList.add('hidden');
      detailModal.setAttribute('aria-hidden','true');
      // breve retraso para permitir cerrar y luego abrir
      setTimeout(()=>{
        openDetail(id);
        alert('Condiciones guardadas. Ahora puede exportar el PDF con los valores actualizados.');
      },80);
    });
  }

  // Attach formatter to admin monto input if present
  const adminMontoInput = detailArea.querySelector('#admin_monto_input');
  if(adminMontoInput) attachNumberFormatter(adminMontoInput);

  // Mostrar modal y marcar aria-hidden para cumplir accesibilidad y evitar estilos sobrescritos
  try{
    detailModal.classList.remove('hidden');
    detailModal.setAttribute('aria-hidden','false');
      // Poner foco en el primer control dentro del modal para evitar que la interacción propague a la ventana
      setTimeout(()=>{
        try{
          const first = detailArea.querySelector('input, select, textarea, button');
          if(first && typeof first.focus === 'function') first.focus();
        }catch(e){}
      },120);
  }catch(err){
    console.error('No fue posible abrir modal:', err);
  }
}

// Cerrar modal con botón, clic fuera del contenido o Escape
if(closeModal){
  closeModal.addEventListener('click', ()=>{
    detailModal.classList.add('hidden');
    detailModal.setAttribute('aria-hidden','true');
  });
}

if(detailModal){
  // Clic en backdrop (fuera del modal-content)
  // Prevent clicks inside the modal-content from bubbling to the backdrop
  const __modalContent = detailModal.querySelector('.modal-content');
  if(__modalContent){
    __modalContent.addEventListener('click', (e)=>{ e.stopPropagation(); });
    __modalContent.addEventListener('pointerdown', (e)=>{ e.stopPropagation(); });
  }

  detailModal.addEventListener('click', (e)=>{
    if(e.target === detailModal){
      detailModal.classList.add('hidden');
      detailModal.setAttribute('aria-hidden','true');
    }
  });
  
  window.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape' && detailModal.getAttribute('aria-hidden') === 'false'){
      detailModal.classList.add('hidden');
      detailModal.setAttribute('aria-hidden','true');
    }
  });
  
  detailModal.classList.add('hidden');
  detailModal.setAttribute('aria-hidden','true');
}

function buildDetailHtml(s){
  const fecha = new Date(s.fecha).toLocaleString();
  const n = normalizeSolicitud(s);
  return `
    <h2>Solicitud de ${escapeHtml(n.nombre)} ${escapeHtml(n.apellido)}</h2>
    <div class="detail-row">
      <div class="detail-col">
        <div class="detail-label">Cédula</div>
        <div>${escapeHtml(n.cedula)}</div>
      </div>
      <div class="detail-col">
        <div class="detail-label">Teléfono</div>
        <div>${escapeHtml(n.telefono)}</div>
      </div>
    </div>
    <hr />
    <h3>Datos del Solicitante</h3>
    <div class="detail-row">
      <div class="detail-col"><div class="detail-label">Nombre</div><div>${escapeHtml(n.nombre)}</div></div>
      <div class="detail-col"><div class="detail-label">Apellido</div><div>${escapeHtml(n.apellido)}</div></div>
      <div class="detail-col"><div class="detail-label">Rol</div><div>${escapeHtml(n.rol)}</div></div>
    </div>
    <hr />
    <h3>Datos del Garante</h3>
    <div class="detail-row">
      <div class="detail-col"><div class="detail-label">Nombre</div><div>${escapeHtml((n.garante.nombres || '') + ' ' + (n.garante.apellidos || ''))}</div></div>
      <div class="detail-col"><div class="detail-label">Teléfono</div><div>${escapeHtml(n.garante.telefono || '')}</div></div>
    </div>
    <hr />
    <h3>Condiciones (Administración)</h3>
    <div class="detail-row">
      <div class="detail-col">
        <div class="detail-label">Monto aprobado (RD$)</div>
        <div><input id="admin_monto_input" type="text" style="padding:8px;border-radius:8px;border:1px solid var(--input-border);width:100%" value="${escapeHtml((s.detallesPrestamo && s.detallesPrestamo.montoSolicitado) || (s.prestamo && s.prestamo.monto_solicitado) || '')}" /></div>
      </div>
      <div class="detail-col">
        <div class="detail-label">Duración (meses)</div>
        <div><input id="admin_duracion_input" type="number" min="1" style="padding:8px;border-radius:8px;border:1px solid var(--input-border);width:100%" value="${escapeHtml((s.detallesPrestamo && s.detallesPrestamo.duracion) || (s.prestamo && s.prestamo.duracion) || '')}" /></div>
      </div>
      <div style="display:flex;align-items:flex-end;gap:8px">
        <button id="saveTermsBtn" class="btn btn-primary">Guardar Condiciones</button>
      </div>
    </div>
    <div class="detail-row" style="align-items:center;gap:12px">
      <div>
        <div class="detail-label">Fecha de Solicitud</div>
        <div>${escapeHtml(fecha)}</div>
      </div>
      <div>
        <div class="detail-label">Estado</div>
        <select id="stateSelect">
          <option ${s.estado==='Pendiente'?'selected':''}>Pendiente</option>
          <option ${s.estado==='Aprobado'?'selected':''}>Aprobado</option>
          <option ${s.estado==='Rechazado'?'selected':''}>Rechazado</option>
        </select>
      </div>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
      <button id="pdfExportDetail" class="btn btn-primary">Exportar PDF</button>
    </div>
  `;
}

// Exportar solicitud a PDF (desde lista o detalle)
async function exportSolicitudPDF(id){
  const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  const s = arr.find(x=>x.id===id);
  if(!s) return alert('Solicitud no encontrada.');
  
  console.log('=== Generando PDF para solicitud:', id);
  
  // Crear un contenedor DOM temporal con formato de impresión
  const wrapper = document.createElement('div');
  wrapper.style.width = '800px';
  wrapper.style.padding = '24px';
  wrapper.style.background = 'white';
  wrapper.style.color = '#0f1724';
  wrapper.innerHTML = buildPrintableHtml(s);
  document.body.appendChild(wrapper);

  // Esperar que el DOM se pinte
  await new Promise(r=>setTimeout(r,200));

        try{
            console.log('Iniciando exportación...');
            if(typeof html2canvas === 'undefined') throw new Error('html2canvas no está cargado.');

            // Capturar cada .pdf-page por separado
            const pages = Array.from(wrapper.querySelectorAll('.pdf-page'));
            if(pages.length === 0){
              throw new Error('No se encontraron páginas para PDF');
            }
            console.log('Páginas encontradas:', pages.length);

            // Compatibilidad con distintas exposiciones de jsPDF
            const jsPDFCtor = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF || (window.jspdf && window.jspdf);
            if(!jsPDFCtor) throw new Error('jsPDF no está cargado.');

            // Si jsPDF es un objeto con propiedad jsPDF (UMD), usar new window.jspdf.jsPDF(), si es función constructora usar new jsPDFCtor()
            const pdf = (typeof window.jspdf === 'object' && window.jspdf.jsPDF)
                ? new window.jspdf.jsPDF({unit:'pt',format:'a4'})
                : new jsPDFCtor({unit:'pt',format:'a4'});

            const pageWidth = pdf.internal.pageSize.getWidth();
            const margin = 20;

            // Procesar cada página
            for(let i = 0; i < pages.length; i++){
              console.log(`Procesando página ${i+1}/${pages.length}...`);
              
              const pageCanvas = await html2canvas(pages[i], {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: false,
                allowTaint: true,
                logging: false
              });
              
              const imgData = pageCanvas.toDataURL('image/jpeg', 0.92);
              const ratio = pageCanvas.width / pageCanvas.height;
              const imgWidth = pageWidth - margin * 2;
              const imgHeight = imgWidth / ratio;

              // Agregar nueva página si no es la primera
              if(i > 0){
                pdf.addPage();
              }
              pdf.addImage(imgData, 'JPEG', margin, margin, imgWidth, imgHeight);
            }
            
            console.log('PDF generado, descargando...');
            
            // Intentar descarga; si falla, generar blob y abrir en nueva pestaña
            try{
              // Evitar TypeError si `s.verificacion` es undefined (varias versiones del objeto usan `datosPersonales.cedula`)
              const fileNameId = (s && s.verificacion && s.verificacion.cedula) ? s.verificacion.cedula : (s && s.id ? s.id : 'solicitud');
              pdf.save(`solicitud_${fileNameId}.pdf`);
              console.log('✓ PDF descargado: solicitud_' + fileNameId + '.pdf');
            }catch(errSave){
              console.warn('pdf.save falló, abriendo en nueva pestaña...');
              const pdfBlob = pdf.output('blob');
              const url = URL.createObjectURL(pdfBlob);
              window.open(url, '_blank');
              setTimeout(()=>URL.revokeObjectURL(url), 30000);
            }

  }catch(err){
    console.error('Error generando PDF:', err.message);
    alert(`Error al generar el PDF:\n\n${err.message}`);
  }finally{
    // Asegurarse de remover el wrapper temporal
    try{ document.body.removeChild(wrapper); }catch(e){}
  }
}

// HTML que se convertirá a PDF (debe ser auto-contenido y con recuadros vacíos para firma)
function buildPrintableHtml(s){
  const fecha = new Date(s.fecha).toLocaleDateString();
  const dp = s.datosPersonales || {};
  const dc = s.datosContacto || {};
  const dl = s.datosLaborales || {};
  const fi = s.infoFinanciera || {};
  const pr = s.detallesPrestamo || {};
  const g  = s.garante || {};
  const rc = Array.isArray(s.referenciasComerciales) ? s.referenciasComerciales : [];
  const rp = Array.isArray(s.referenciasPersonales) ? s.referenciasPersonales : [];

  // Usar SVG para evitar problemas de CORS con la imagen PNG
  const logo = `<svg width="60" height="60" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" rx="12" fill="#0B5E3B"></rect><text x="50" y="62" fill="#fff" font-size="42" font-family="Arial" text-anchor="middle">C</text></svg>`;

  const cell = (label, value, style="")=>`
    <td style="padding:6px;vertical-align:bottom;${style}">
      <div style="font-size:11px;color:#0f1724;font-weight:700">${escapeHtml(label)}</div>
      <div style="border-bottom:1px solid #cbd5e1;padding:6px 4px;font-size:13px">${escapeHtml(value||'')}</div>
    </td>`;

  const sectionTitle = (t)=>`<div style="margin:10px 0 6px 0;font-weight:800;color:#0B5E3B;letter-spacing:.3px">${escapeHtml(t)}</div>`;

  function refTable(title, items){
    const rows = [0,1,2].map((i)=>{
      const it = items[i] || {};
      return `
        <tr>
          ${cell((i+1)+'.- '+(title.includes('COMERCIALES')?'Negocio':'Nombres'), it.nombre || '')}
          ${cell('Dirección', it.direccion || '')}
          ${cell('Tel.:', formatPhoneNumber(it.telefono || ''))}
        </tr>`;
    }).join('');
    return `
      ${sectionTitle(title)}
      <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:6px">${rows}</table>`;
  }

  return `
  <div style="font-family:Arial, Helvetica, sans-serif;color:#0f1724;padding:0;margin:0;font-size:14px;line-height:1.25;">
    <div class="pdf-page" style="padding:18px 16px 10px 16px;min-height:1030px;box-sizing:border-box;position:relative;">
      <div style="position:absolute;top:8px;right:16px;font-size:11px;color:#475569;font-weight:600">Hoja 1 de 2</div>
    <div style="display:flex;align-items:center;gap:12px;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:15px;flex:1">
        ${logo}
        <div style="display:flex;flex-direction:column;gap:2px">
          <div style="font-size:28px;font-weight:900;letter-spacing:1.5px;color:#0B5E3B;line-height:1">COOPATRIA</div>
          <div style="font-size:11px;color:#334155;font-weight:700;letter-spacing:0.5px">SOLICITUD DE PRÉSTAMO</div>
        </div>
      </div>
      <div style="text-align:right;font-size:12px;color:#0f1724">
        <div><strong>Fecha:</strong> ${escapeHtml(fecha)}</div>
        <div><strong>Monto que aspira (RD$):</strong> ${escapeHtml(formatNumberWithCommas(pr.montoSolicitado || ''))}</div>
      </div>
    </div>

    <div style="height:8px"></div>

    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <tr>
        ${cell('Nombres', dp.nombres)}
        ${cell('Apellidos', dp.apellidos)}
      </tr>
      <tr>
        ${cell('Cédula', formatDominicanCedula(dp.cedula))}
        ${cell('Estado Civil', dp.estadoCivil)}
        ${cell('Tel.:', formatPhoneNumber(dc.telefono))}
      </tr>
      <tr>
        ${cell('Dirección', dp.direccionCompleta || '')}
        ${cell('Entre qué calles reside', dp.callesReferencia || '')}
      </tr>
      <tr>
        ${cell('Fecha de Nacimiento', dp.fechaNacimiento || '')}
        ${cell('Profesión u oficio', dp.profesion || '')}
      </tr>
      <tr>
        ${cell('Tipo de Negocio', dl.tipoNegocio || '')}
        ${cell('Entrada promedio mensual', formatNumberWithCommas(dl.entradaPromedio || fi.entradaPromedio || ''))}
      </tr>
      <tr>
        ${cell('Donde Trabaja', dl.empresaNombre || '')}
        ${cell('Dirección', dl.empresaDireccion || '')}
        ${cell('Teléfono', formatPhoneNumber(dl.empresaTelefono || ''))}
      </tr>
      <tr>
        ${cell('Tiempo Empleado', dl.empresaTiempo || '')}
        ${cell('Cargo', dl.empresaCargo || '')}
        ${cell('Teléfono Residencia', formatPhoneNumber(dl.telefonoResidencia || dp.telResidencia || ''))}
      </tr>
      <tr>
        ${cell('Nombre del Cónyuge', dp.conyugeNombre || '')}
        ${cell('Teléfono del Cónyuge', formatPhoneNumber(dp.conyugeTelefono || ''))}
      </tr>
    </table>

  ${refTable('REFERENCIAS COMERCIALES:', rc)}
  ${refTable('REFERENCIAS PERSONALES:', rp)}

    ${sectionTitle('DATOS DEL FIADOR GARANTE SOLIDARIO')}
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <tr>
        ${cell('Nombres', g.nombres || '')}
        ${cell('Apellidos', g.apellidos || '')}
      </tr>
      <tr>
        ${cell('Cédula', formatDominicanCedula(g.cedula || ''))}
        ${cell('Estado Civil', g.estadoCivil || '')}
        ${cell('Tel.:', formatPhoneNumber(g.telefono || ''))}
      </tr>
      <tr>
        ${cell('Dirección', g.direccion || '')}
        ${cell('Teléfono Residencia', formatPhoneNumber(g.telResidencia || ''))}
      </tr>
      <tr>
        ${cell('Profesión u oficio', g.profesion || '')}
        ${cell('Tipo de Negocio', g.tipoNegocio || '')}
        ${cell('Entrada promedio mensual', formatNumberWithCommas(g.entradaPromedio || ''))}
      </tr>
    </table>

    <div style="position:absolute;bottom:8px;left:16px;right:16px;font-size:10px;color:#64748b;display:flex;justify-content:space-between">
      <span>Generado electrónicamente - Datos sujetos a verificación</span>
      <span>Hoja 1</span>
    </div>
    </div>
    <div class="pdf-page" style="padding:18px 16px 24px 16px;min-height:1030px;box-sizing:border-box;position:relative;">
      <div style="position:absolute;top:8px;right:16px;font-size:11px;color:#475569;font-weight:600">Hoja 2 de 2</div>
      <div style="font-size:15px;font-weight:800;color:#0B5E3B;margin-bottom:10px">FIRMAS Y DECLARACIONES</div>
      <div style="display:flex;gap:30px;margin-top:40px;align-items:flex-end">
        <div style="flex:1;text-align:center">
          <div style="border-bottom:1px solid #000;height:60px;margin-bottom:6px"></div>
          <div style="font-size:12px;color:#475569">FIRMA DEL SOLICITANTE</div>
        </div>
        <div style="flex:1;text-align:center">
          <div style="border-bottom:1px solid #000;height:60px;margin-bottom:6px"></div>
          <div style="font-size:12px;color:#475569">FIRMA DEL FIADOR / GARANTE</div>
        </div>
      </div>
      <div style="display:flex;gap:30px;margin-top:60px;align-items:flex-end">
        <div style="flex:1;text-align:center">
          <div style="border-bottom:1px solid #000;height:60px;margin-bottom:6px"></div>
          <div style="font-size:12px;color:#475569">OFICIAL DE CRÉDITO</div>
        </div>
        <div style="flex:1;text-align:center">
          <div style="border:1px solid #000;height:60px;margin-bottom:6px"></div>
          <div style="font-size:12px;color:#475569">SELLO COOPERATIVA</div>
        </div>
      </div>
      <div style="margin-top:50px;padding:12px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;font-size:11px;color:#334155;line-height:1.5">
        <strong>Cláusula de Mora:</strong> El solicitante reconoce que si incurre en <strong>mora mayor a cinco (5) días</strong> en el pago de cualquier cuota, la cooperativa aplicará un <strong>interés moratorio del cinco por ciento (5%) mensual</strong> sobre el monto vencido, acumulable mientras persista la mora. Asimismo, acepta que la cooperativa podrá, conforme a las disposiciones legales y al contrato suscrito, iniciar las gestiones de cobro, retención, compensación o <strong>ejecución de garantías y bienes</strong> ofrecidos en respaldo del préstamo hasta satisfacer la obligación pendiente.
      </div>
      <div style="position:absolute;bottom:8px;left:16px;right:16px;font-size:10px;color:#64748b;display:flex;justify-content:space-between">
        <span>Generado electrónicamente - Datos sujetos a verificación</span>
        <span>Hoja 2</span>
      </div>
    </div>
  </div>`;
}

// Pequeña función para escapar texto a HTML
function escapeHtml(str){
  if(!str && str!==0) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Botones de control
refreshBtn?.addEventListener('click', loadSolicitudes);
clearBtn?.addEventListener('click', ()=>{
  if(!confirm('Eliminar todas las solicitudes de localStorage? Esto es solo para demo.')) return;
  localStorage.removeItem(STORAGE_KEY);
  if(filterNameInput) filterNameInput.value = '';
  if(filterDateFrom) filterDateFrom.value = '';
  if(filterDateTo) filterDateTo.value = '';
  if(filterStatus) filterStatus.value = '';
  allSolicitudes = [];
  applyFilters();
});

filterNameInput?.addEventListener('input', applyFilters);
filterDateFrom?.addEventListener('change', applyFilters);
filterDateTo?.addEventListener('change', applyFilters);
filterStatus?.addEventListener('change', applyFilters);

// Inicializar
// Aplicar tema guardado (si existe) para mantener consistencia con la página pública
(function(){
  try{
    const THEME_KEY = 'coop_theme';
    const t = localStorage.getItem(THEME_KEY) || 'light';
    document.documentElement.classList.remove('theme-light','theme-dark');
    document.documentElement.classList.add(t === 'dark' ? 'theme-dark' : 'theme-light');
  }catch(e){/* ignore */}
})();

// Cargar logo almacenado (si lo hay) y luego las solicitudes
loadLogoFromStorage();
loadSolicitudes();

// --- Configuración de Tasa de Interés ---
const INTEREST_RATE_KEY = 'coop_interest_rate';
const openSettingsBtn = document.getElementById('openSettingsBtn');
const closeSettingsBtn = document.getElementById('closeSettingsBtn');
const settingsPanel = document.getElementById('settingsPanel');
const interestRateInput = document.getElementById('interestRateInput');
const saveInterestBtn = document.getElementById('saveInterestBtn');

function loadInterestRateToUI(){
  try{
    const raw = localStorage.getItem(INTEREST_RATE_KEY);
    if(raw !== null && interestRateInput){ interestRateInput.value = raw; }
  }catch(e){ /* ignore */ }
}

function showSettings(show){
  try{
    if(!settingsPanel) return;
    settingsPanel.style.display = show ? 'block' : 'none';
  }catch(e){}
}

openSettingsBtn?.addEventListener('click', ()=>{
  loadInterestRateToUI();
  showSettings(true);
});
closeSettingsBtn?.addEventListener('click', ()=> showSettings(false));

saveInterestBtn?.addEventListener('click', ()=>{
  if(!interestRateInput) return;
  const v = interestRateInput.value;
  if(v === '' || Number.isNaN(Number(v))){ alert('Ingrese una tasa anual v\u00e1lida.'); return; }
  try{
    localStorage.setItem(INTEREST_RATE_KEY, String(Number(v)));
    // Notificar al resto (storage event no se dispara en la misma pestaña en algunos navegadores)
    // Creamos un cambio temporal para forzar event en otras pesta f1as: set then set again
    // (otra alternativa es dispatchEvent; aquí basta con guardar)
    alert('Tasa guardada.');
    showSettings(false);
  }catch(e){ console.error('No se pudo guardar la tasa', e); alert('No fue posible guardar la tasa. Revise consola.'); }
});

// Inicializar valor en UI
loadInterestRateToUI();

// ---------- Formateo de números con separador de miles (reutilizable) ----------
function formatNumberWithCommas(val){
  if(val === null || val === undefined || val === '') return '';
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

function attachNumberFormatter(input){
  if(!input) return;
  input.addEventListener('input', (e)=>{
    const el = e.target;
    const selectionStart = el.selectionStart;
    const oldLen = el.value.length;
    const raw = el.value.replace(/[^0-9.,-]/g,'');
    const firstDotIndex = raw.indexOf('.');
    let normalized = raw;
    if(firstDotIndex !== -1){
      const before = raw.slice(0, firstDotIndex + 1);
      const after = raw.slice(firstDotIndex + 1).replace(/\./g,'');
      normalized = before + after;
    }
    const formatted = formatNumberWithCommas(normalized);
    el.value = formatted;
    const newLen = el.value.length;
    const delta = newLen - oldLen;
    try{ el.setSelectionRange(Math.max(0, selectionStart + delta), Math.max(0, selectionStart + delta)); }catch(e){}
  });
}

/* Fin admin.js */

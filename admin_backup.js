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
const logoInput = document.getElementById('logoInput');
const logoPreview = document.getElementById('logoPreview');
const removeLogoBtn = document.getElementById('removeLogoBtn');

let cooperativeLogoData = null;

function loadLogoFromStorage(){
  try{
    const data = localStorage.getItem('coop_logo');
    if(data){
      cooperativeLogoData = data;
      logoPreview.src = data;
      logoPreview.style.display = 'block';
      removeLogoBtn.style.display = 'inline-block';
    } else {
      logoPreview.style.display = 'none';
      removeLogoBtn.style.display = 'none';
      loadDefaultLogo();
    }
  }catch(e){
    console.error('Error loading logo', e);
  }
}

function loadDefaultLogo(){
  const defaultPath = './LOGO ONAMET.webp';
  const img = new Image();
  img.onload = function(){
    cooperativeLogoData = defaultPath;
    logoPreview.src = defaultPath;
    logoPreview.style.display = 'block';
    removeLogoBtn.style.display = 'inline-block';
  };
  img.onerror = function(){
    // no hay logo por defecto accesible
    logoPreview.style.display = 'none';
    removeLogoBtn.style.display = 'none';
  };
  // Intentar cargar (espacios en nombre son permitidos, el navegador los manejará)
  img.src = defaultPath;
}

// Guardar dataURL en localStorage
function saveLogoDataURL(dataURL){
  try{
    localStorage.setItem('coop_logo', dataURL);
    cooperativeLogoData = dataURL;
    loadLogoFromStorage();
  }catch(e){
    console.error('Error guardando logo en localStorage', e);
  }
}

// Manejar input de archivo
if(logoInput){
  logoInput.addEventListener('change', (e)=>{
    const file = e.target.files && e.target.files[0];
    if(!file) return;
    const reader = new FileReader();
    reader.onload = function(ev){
      const dataURL = ev.target.result;
      saveLogoDataURL(dataURL);
    };
    reader.readAsDataURL(file);
  });
}

if(removeLogoBtn){
  removeLogoBtn.addEventListener('click', ()=>{
    localStorage.removeItem('coop_logo');
    cooperativeLogoData = null;
    loadLogoFromStorage();
  });
}

// Protección: si los elementos no existen por alguna razón, no continuar para evitar errores que
// paren la ejecución del script y dejen la UI en un estado intermedio.
if(!listContainer || !detailModal || !detailArea){
  console.warn('admin.js: elementos DOM críticos ausentes. Abortando inicialización.');
} 

// Cargar y renderizar
function loadSolicitudes(){
  const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  renderList(arr);
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
    direccion: (s.solicitante && s.solicitante.direccion) || '',
    rol: 'Empleado',
    sueldo: (s.infoFinanciera && s.infoFinanciera.ingresosMensuales) || '',
    ahorros: '',
    prestamo: {
      monto_solicitado: (s.detallesPrestamo && s.detallesPrestamo.montoSolicitado) || (s.prestamo && s.prestamo.monto_solicitado) || '',
      duracion: (s.detallesPrestamo && s.detallesPrestamo.duracion) || (s.prestamo && s.prestamo.duracion) || ''
    },
    garante: s.garante || (s.garante ? s.garante : {})
  };
}

function renderList(arr){
  listContainer.innerHTML = '';
  if(!arr.length){
    listContainer.innerHTML = '<div class="card-item"><em>No hay solicitudes aún.</em></div>';
    return;
  }

  arr.forEach(s =>{
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
    listContainer.appendChild(div);
  });
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

  // Mostrar modal y marcar aria-hidden para cumplir accesibilidad y evitar estilos sobrescritos
  try{
    detailModal.classList.remove('hidden');
    detailModal.setAttribute('aria-hidden','false');
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
  detailModal.addEventListener('click', (e)=>{
    if(e.target === detailModal){
      const fecha = new Date(s.fecha).toLocaleString();
      // s: original, n: normalizado
      return (function(){
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
        <h3>Datos del solicitante</h3>
        <div class="detail-row">
          <div class="detail-col"><div class="detail-label">Nombre</div><div>${escapeHtml(n.nombre)}</div></div>
          <div class="detail-col"><div class="detail-label">Apellido</div><div>${escapeHtml(n.apellido)}</div></div>
          <div class="detail-col"><div class="detail-label">Dirección</div><div>${escapeHtml(n.direccion)}</div></div>
          <div class="detail-col"><div class="detail-label">Rol</div><div>${escapeHtml(n.rol)}</div></div>
        </div>
        <hr />
        <h3>Datos del garante</h3>
        <div class="detail-row">
          <div class="detail-col"><div class="detail-label">Nombre</div><div>${escapeHtml((s.garante && (s.garante.nombres || s.garante.nombre)) || '')} ${escapeHtml((s.garante && (s.garante.apellidos || s.garante.apellido)) || '')}</div></div>
          <div class="detail-col"><div class="detail-label">Teléfono</div><div>${escapeHtml((s.garante && (s.garante.telefono || s.garante.tel)) || '')}</div></div>
          <div class="detail-col"><div class="detail-label">Dirección</div><div>${escapeHtml((s.garante && s.garante.direccion) || '')}</div></div>
          <div class="detail-col"><div class="detail-label">Relación</div><div>${escapeHtml((s.garante && s.garante.relacion) || '')}</div></div>
        </div>
        <hr />
        <div class="detail-row" style="align-items:center;gap:12px">
          <div>
            <div class="detail-label">Fecha de solicitud</div>
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
        <hr />
        <div style="display:flex;gap:12px;align-items:center">
          <div>
            <div class="signature-box"><div class="sig-frame"></div><div class="sig-label">Firma del solicitante</div></div>
          </div>
          <div>
            <div class="signature-box"><div class="sig-frame"></div><div class="sig-label">Firma del garante</div></div>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
          <button id="pdfExportDetail" class="btn btn-primary">Exportar PDF</button>
        </div>
      `;
      })();
          <option ${s.estado==='Aprobado'?'selected':''}>Aprobado</option>
          <option ${s.estado==='Rechazado'?'selected':''}>Rechazado</option>
        </select>
      </div>
    </div>
    <hr />
    <div style="display:flex;gap:12px;align-items:center">
      <div style="flex:1">
        <div class="signature-box"><div class="sig-frame"></div><div class="sig-label">Firma del solicitante</div></div>
      </div>
      <div style="flex:1">
        <div class="signature-box"><div class="sig-frame"></div><div class="sig-label">Firma del garante</div></div>
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
  // Crear un contenedor DOM temporal con formato de impresión
  const wrapper = document.createElement('div');
  wrapper.style.width = '800px';
  wrapper.style.padding = '24px';
  wrapper.style.background = 'white';
  wrapper.style.color = '#0f1724';
  wrapper.innerHTML = buildPrintableHtml(s);
  document.body.appendChild(wrapper);

  // Esperar que el DOM se pinte
  await new Promise(r=>setTimeout(r,150));

  // Intentar convertir logo en ruta a dataURL para evitar problemas de CORS/tainted canvas.
  async function tryInlineLogo(wrapperEl){
    if(!cooperativeLogoData) return;
    // Si ya es data URL, no hace falta
    if(typeof cooperativeLogoData === 'string' && cooperativeLogoData.startsWith('data:')){
      // Reemplazar img en wrapper si existe
      const imgNode = wrapperEl.querySelector('img[src]');
      if(imgNode) imgNode.src = cooperativeLogoData;
      return;
    }
    // Si es una ruta relativa o URL, intentar fetch y convertir a dataURL
    if(typeof cooperativeLogoData === 'string'){
      try{
        const resp = await fetch(cooperativeLogoData);
        if(!resp.ok) throw new Error('fetch falló con status ' + resp.status);
        const blob = await resp.blob();
        // Convertir blob a dataURL
        const dataURL = await new Promise((res,rej)=>{
          const fr = new FileReader();
          fr.onload = ()=>res(fr.result);
          fr.onerror = ()=>rej(new Error('No se pudo leer blob como dataURL'));
          fr.readAsDataURL(blob);
        });
        // Reemplazar imagen en el wrapper si existe
        const imgNode = wrapperEl.querySelector('img[src]');
        if(imgNode) imgNode.src = dataURL;
        // Actualizar cooperativeLogoData para futuras operaciones
        cooperativeLogoData = dataURL;
        // Guardar en localStorage para persistir
        try{ localStorage.setItem('coop_logo', dataURL); }catch(e){/* ignore */}
      }catch(err){
        // Si falla el fetch (p.ej. file:// o CORS), eliminar la imagen del wrapper para evitar canvas tainted
        console.warn('No fue posible inlinear el logo, se eliminará para evitar tainted canvas:', err);
        const imgNode = wrapperEl.querySelector('img[src]');
        if(imgNode && imgNode.parentNode) imgNode.parentNode.removeChild(imgNode);
      }
    }
  }

  try{
    if(typeof html2canvas === 'undefined') throw new Error('html2canvas no está cargado.');

  // Intentar inlinear logo en el contenedor temporal para prevenir problemas de CORS/tainted canvas
  await tryInlineLogo(wrapper);

  const canvas = await html2canvas(wrapper, {scale:2});
    const imgData = canvas.toDataURL('image/jpeg', 0.95);

    // Compatibilidad con distintas exposiciones de jsPDF
    const jsPDFCtor = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF || (window.jspdf && window.jspdf);
    if(!jsPDFCtor) throw new Error('jsPDF no está cargado o no es accesible (revisar la inclusión del script).');

    // Si jsPDF es un objeto con propiedad jsPDF (UMD), usar new window.jspdf.jsPDF(), si es función constructora usar new jsPDFCtor()
    const pdf = (typeof window.jspdf === 'object' && window.jspdf.jsPDF)
      ? new window.jspdf.jsPDF({unit:'pt',format:'a4'})
      : new jsPDFCtor({unit:'pt',format:'a4'});

    const pageWidth = pdf.internal.pageSize.getWidth();
    const imgProps = canvas;
    const ratio = imgProps.width / imgProps.height;
    const imgWidth = pageWidth - 40; // márgenes
    const imgHeight = imgWidth / ratio;
    pdf.addImage(imgData, 'JPEG', 20, 20, imgWidth, imgHeight);

    // Intentar descarga; si falla, generar blob y abrir en nueva pestaña
    try{
      pdf.save(`solicitud_${s.verificacion.cedula || s.id}.pdf`);
    }catch(errSave){
      console.warn('pdf.save falló, intento fallback a blob URL', errSave);
      const pdfBlob = pdf.output('blob');
      const url = URL.createObjectURL(pdfBlob);
      window.open(url, '_blank');
      // Liberar la URL después
      setTimeout(()=>URL.revokeObjectURL(url), 30000);
    }

  }catch(err){
    console.error('Error generando PDF:', err);
    alert('Ocurrió un error al generar el PDF. Revisa la consola del navegador para más detalles.');
  }finally{
    // Asegurarse de remover el wrapper temporal
    try{ document.body.removeChild(wrapper); }catch(e){}
  }
}

// HTML que se convertirá a PDF (debe ser auto-contenido y con recuadros vacíos para firma)
function buildPrintableHtml(s){
  const fecha = new Date(s.fecha).toLocaleDateString();
  // Usar logo cargado si existe, si no usar placeholder SVG
  // Usar tamaño pequeño para logo dentro del encabezado del PDF
  const logo = cooperativeLogoData
    ? `<img src="${cooperativeLogoData}" style="height:36px;object-fit:contain;border-radius:4px;margin-right:10px;"/>`
    : `<svg width="36" height="36" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" rx="12" fill="#0b63d6"></rect><text x="50" y="62" fill="#fff" font-size="42" font-family="Inter, Arial" text-anchor="middle">C</text></svg>`;
  const n = normalizeSolicitud(s);
  // Construimos un HTML con estructura similar a un formulario impreso.
  return `
    <div style="font-family:Inter, Arial, sans-serif;color:#0f1724;padding:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:12px">
          <div>${logo}</div>
          <div>
              <div style="font-size:13px;font-weight:700;line-height:1.05">COOPERATIVA NACIONAL DE AHORROS, CRÉDITOS Y SERVICIOS MÚLTIPLES DE LOS EMPLEADOS DE METEOROLOGÍA \"JOSÉ GUERRERO NATERA\" (COOPNAMET)</div>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-size:18px;color:#0b63d6;font-weight:700">Solicitud de Préstamo</div>
          <div style="font-size:12px;color:#374151;margin-top:6px">Fecha: ${escapeHtml(fecha)}</div>
        </div>
      </div>

      <hr style="border:none;border-top:2px solid #eef2ff;margin:12px 0" />

      <!-- Datos personales del socio -->
      <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:#0b63d6">Datos Personales del Socio</div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px">
        <tr>
          <td style="width:40%;padding:6px"><div style="color:#6b7280;font-size:11px">Nombre(s)</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.nombre || '')}</div></td>
          <td style="width:30%;padding:6px"><div style="color:#6b7280;font-size:11px">Apellido(s)</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.apellido || '')}</div></td>
          <td style="width:30%;padding:6px"><div style="color:#6b7280;font-size:11px">Cédula</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.cedula || '')}</div></td>
        </tr>
        <tr>
          <td style="padding:6px"><div style="color:#6b7280;font-size:11px">Teléfono</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.telefono || '')}</div></td>
          <td colspan="2" style="padding:6px"><div style="color:#6b7280;font-size:11px">Dirección</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.direccion || '')}</div></td>
        </tr>
        <tr>
          <td style="padding:6px"><div style="color:#6b7280;font-size:11px">Rol en la empresa</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.rol || '')}</div></td>
          <td style="padding:6px"><div style="color:#6b7280;font-size:11px">Sueldo (opcional)</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.sueldo || '')}</div></td>
          <td style="padding:6px"><div style="color:#6b7280;font-size:11px">Ahorros (opcional)</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.ahorros || '')}</div></td>
        </tr>
      </table>

      <!-- Información sobre el préstamo (simulada con espacios para completar) -->
      <div style="font-size:13px;font-weight:700;margin:8px 0;color:#0b63d6">Información sobre el Préstamo</div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px">
        <tr>
          <td style="width:50%;padding:6px"><div style="color:#6b7280;font-size:11px">Tipo de préstamo</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.prestamo && s.prestamo.tipo) || '')}</div></td>
          <td style="width:25%;padding:6px"><div style="color:#6b7280;font-size:11px">Monto solicitado</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((n.prestamo && n.prestamo.monto_solicitado) || '')}</div></td>
          <td style="width:25%;padding:6px"><div style="color:#6b7280;font-size:11px">Duración</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((n.prestamo && n.prestamo.duracion) || '')}</div></td>
        </tr>
      </table>

      <!-- Datos del garante -->
      <div style="font-size:13px;font-weight:700;margin:8px 0;color:#0b63d6">Datos del Garante Solidario</div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px">
        <tr>
          <td style="width:35%;padding:6px"><div style="color:#6b7280;font-size:11px">Nombre(s)</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.garante && (s.garante.nombre || s.garante.nombres)) || '')}</div></td>
          <td style="width:30%;padding:6px"><div style="color:#6b7280;font-size:11px">Apellido(s)</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.garante && (s.garante.apellido || s.garante.apellidos)) || '')}</div></td>
          <td style="width:35%;padding:6px"><div style="color:#6b7280;font-size:11px">Cédula / Código</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.garante && (s.garante.cedula || s.garante.codigo)) || '')}</div></td>
        </tr>
        <tr>
          <td style="padding:6px"><div style="color:#6b7280;font-size:11px">Teléfono</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.garante && (s.garante.telefono || s.garante.tel)) || '')}</div></td>
          <td style="padding:6px"><div style="color:#6b7280;font-size:11px">Móvil</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.garante && s.garante.movil) || '')}</div></td>
          <td style="padding:6px"><div style="color:#6b7280;font-size:11px">E-mail</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.garante && s.garante.email) || '')}</div></td>
        </tr>
        <tr>
          <td colspan="3" style="padding:6px"><div style="color:#6b7280;font-size:11px">Dirección</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((s.garante && s.garante.direccion) || '')}</div></td>
        </tr>
      </table>

      <!-- Firmas -->
      <div style="display:flex;gap:20px;margin-top:18px;align-items:flex-end">
        <div style="flex:1;text-align:center">
          <div style="border-bottom:1px solid #000;height:36px;margin-bottom:6px"></div>
          <div style="font-size:12px;color:#6b7280">Firma del Interesado</div>
        </div>
        <div style="flex:1;text-align:center">
          <div style="border-bottom:1px solid #000;height:36px;margin-bottom:6px"></div>
          <div style="font-size:12px;color:#6b7280">Firma del Garante Solidario</div>
        </div>
      </div>

      <div style="margin-top:18px;font-size:11px;color:#9aa4b2">Documento generado desde la demo de la Cooperativa. Información sujeta a verificación.</div>
    </div>
  `;
}

// Pequeña función para escapar texto a HTML
function escapeHtml(str){
  if(!str && str!==0) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Botones de control
refreshBtn.addEventListener('click', loadSolicitudes);
clearBtn.addEventListener('click', ()=>{
  if(!confirm('Eliminar todas las solicitudes de localStorage? Esto es solo para demo.')) return;
  localStorage.removeItem(STORAGE_KEY);
  loadSolicitudes();
});

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

/* Fin admin.js */

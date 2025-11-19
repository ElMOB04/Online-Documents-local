/*
  admin.js - Lógica del panel administrativo
  - Carga solicitudes desde localStorage
  - Renderiza tarjetas con acciones: Ver detalles, Exportar PDF
  - Modal de detalles con opción de cambiar estado
  - Exportación a PDF usando html2canvas + jsPDF (sin backend)
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
    logoPreview.style.display = 'none';
    removeLogoBtn.style.display = 'none';
  };
  img.src = defaultPath;
}

function saveLogoDataURL(dataURL){
  try{
    localStorage.setItem('coop_logo', dataURL);
    cooperativeLogoData = dataURL;
    loadLogoFromStorage();
  }catch(e){
    console.error('Error saving logo', e);
  }
}

if(logoInput){
  logoInput.addEventListener('change', (e)=>{
    const file = e.target.files && e.target.files[0];
    if(!file) return;
    const reader = new FileReader();
    reader.onload = function(ev){
      saveLogoDataURL(ev.target.result);
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

if(!listContainer || !detailModal || !detailArea){
  console.warn('admin.js: critical DOM elements missing');
} 

function loadSolicitudes(){
  const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  renderList(arr);
}

function normalizeSolicitud(s){
  if(!s) return {};
  return {
    id: s.id,
    estado: s.estado,
    fecha: s.fecha,
    nombre: (s.datosPersonales && s.datosPersonales.nombres) || '',
    apellido: (s.datosPersonales && s.datosPersonales.apellidos) || '',
    cedula: (s.datosPersonales && s.datosPersonales.cedula) || '',
    telefono: (s.datosContacto && s.datosContacto.telefono) || '',
    direccion: (s.solicitante && s.solicitante.direccion) || '',
    rol: 'Empleado',
    sueldo: (s.infoFinanciera && s.infoFinanciera.ingresosMensuales) || '',
    prestamo: {
      monto_solicitado: (s.detallesPrestamo && s.detallesPrestamo.montoSolicitado) || '',
      duracion: (s.detallesPrestamo && s.detallesPrestamo.duracion) || ''
    },
    garante: s.garante || {}
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

function openDetail(id){
  const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  const s = arr.find(x=>x.id===id);
  if(!s) return alert('Solicitud no encontrada.');

  detailArea.innerHTML = buildDetailHtml(s);
  const stateSelect = detailArea.querySelector('#stateSelect');
  if(stateSelect){
    stateSelect.addEventListener('change', ()=>{
      s.estado = stateSelect.value;
      localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
      loadSolicitudes();
    });
  }

  const pdfBtn = detailArea.querySelector('#pdfExportDetail');
  if(pdfBtn) pdfBtn.addEventListener('click', ()=>exportSolicitudPDF(id));

  try{
    detailModal.classList.remove('hidden');
    detailModal.setAttribute('aria-hidden','false');
  }catch(err){
    console.error('Error opening modal:', err);
  }
}

if(closeModal){
  closeModal.addEventListener('click', ()=>{
    detailModal.classList.add('hidden');
    detailModal.setAttribute('aria-hidden','true');
  });
}

if(detailModal){
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
    <h3>Datos del solicitante</h3>
    <div class="detail-row">
      <div class="detail-col"><div class="detail-label">Nombre</div><div>${escapeHtml(n.nombre)}</div></div>
      <div class="detail-col"><div class="detail-label">Apellido</div><div>${escapeHtml(n.apellido)}</div></div>
      <div class="detail-col"><div class="detail-label">Rol</div><div>${escapeHtml(n.rol)}</div></div>
    </div>
    <hr />
    <h3>Datos del garante</h3>
    <div class="detail-row">
      <div class="detail-col"><div class="detail-label">Nombre</div><div>${escapeHtml((s.garante && (s.garante.nombres || s.garante.nombre)) || '')} ${escapeHtml((s.garante && (s.garante.apellidos || s.garante.apellido)) || '')}</div></div>
      <div class="detail-col"><div class="detail-label">Teléfono</div><div>${escapeHtml((s.garante && s.garante.telefono) || '')}</div></div>
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
    <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
      <button id="pdfExportDetail" class="btn btn-primary">Exportar PDF</button>
    </div>
  `;
}

async function exportSolicitudPDF(id){
  const arr = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  const s = arr.find(x=>x.id===id);
  if(!s) return alert('Solicitud no encontrada.');
  const wrapper = document.createElement('div');
  wrapper.style.width = '800px';
  wrapper.style.padding = '24px';
  wrapper.style.background = 'white';
  wrapper.style.color = '#0f1724';
  wrapper.innerHTML = buildPrintableHtml(s);
  document.body.appendChild(wrapper);

  await new Promise(r=>setTimeout(r,150));

  async function tryInlineLogo(wrapperEl){
    if(!cooperativeLogoData) return;
    if(typeof cooperativeLogoData === 'string' && cooperativeLogoData.startsWith('data:')){
      const imgNode = wrapperEl.querySelector('img[src]');
      if(imgNode) imgNode.src = cooperativeLogoData;
      return;
    }
    if(typeof cooperativeLogoData === 'string'){
      try{
        const resp = await fetch(cooperativeLogoData);
        if(!resp.ok) throw new Error('fetch failed');
        const blob = await resp.blob();
        const dataURL = await new Promise((res,rej)=>{
          const fr = new FileReader();
          fr.onload = ()=>res(fr.result);
          fr.onerror = ()=>rej(new Error('FileReader error'));
          fr.readAsDataURL(blob);
        });
        const imgNode = wrapperEl.querySelector('img[src]');
        if(imgNode) imgNode.src = dataURL;
        cooperativeLogoData = dataURL;
        try{ localStorage.setItem('coop_logo', dataURL); }catch(e){}
      }catch(err){
        console.warn('Could not inline logo:', err);
        const imgNode = wrapperEl.querySelector('img[src]');
        if(imgNode && imgNode.parentNode) imgNode.parentNode.removeChild(imgNode);
      }
    }
  }

  try{
    if(typeof html2canvas === 'undefined') throw new Error('html2canvas not loaded');
    await tryInlineLogo(wrapper);
    const canvas = await html2canvas(wrapper, {scale:2});
    const imgData = canvas.toDataURL('image/jpeg', 0.95);
    const jsPDFCtor = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF;
    if(!jsPDFCtor) throw new Error('jsPDF not loaded');
    const pdf = (typeof window.jspdf === 'object' && window.jspdf.jsPDF)
      ? new window.jspdf.jsPDF({unit:'pt',format:'a4'})
      : new jsPDFCtor({unit:'pt',format:'a4'});
    const pageWidth = pdf.internal.pageSize.getWidth();
    const ratio = canvas.width / canvas.height;
    const imgWidth = pageWidth - 40;
    const imgHeight = imgWidth / ratio;
    pdf.addImage(imgData, 'JPEG', 20, 20, imgWidth, imgHeight);
    try{
      pdf.save(`solicitud_${(s.datosPersonales && s.datosPersonales.cedula) || s.id}.pdf`);
    }catch(errSave){
      const pdfBlob = pdf.output('blob');
      const url = URL.createObjectURL(pdfBlob);
      window.open(url, '_blank');
      setTimeout(()=>URL.revokeObjectURL(url), 30000);
    }
  }catch(err){
    console.error('PDF export error:', err);
    alert('Error generating PDF. Check browser console for details.');
  }finally{
    try{ document.body.removeChild(wrapper); }catch(e){}
  }
}

function buildPrintableHtml(s){
  const fecha = new Date(s.fecha).toLocaleDateString();
  const logo = cooperativeLogoData
    ? `<img src="${cooperativeLogoData}" style="height:36px;object-fit:contain;border-radius:4px;margin-right:10px;"/>`
    : `<svg width="36" height="36" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" rx="12" fill="#0b63d6"></rect><text x="50" y="62" fill="#fff" font-size="42" font-family="Inter, Arial" text-anchor="middle">C</text></svg>`;
  const n = normalizeSolicitud(s);
  return `
    <div style="font-family:Inter, Arial, sans-serif;color:#0f1724;padding:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:12px">
          <div>${logo}</div>
          <div>
              <div style="font-size:13px;font-weight:700;line-height:1.05">COOPERATIVA NACIONAL DE AHORROS, CRÉDITOS Y SERVICIOS MÚLTIPLES DE LOS EMPLEADOS DE METEOROLOGÍA "JOSÉ GUERRERO NATERA" (COOPNAMET)</div>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-size:18px;color:#0b63d6;font-weight:700">Solicitud de Préstamo</div>
          <div style="font-size:12px;color:#374151;margin-top:6px">Fecha: ${escapeHtml(fecha)}</div>
        </div>
      </div>
      <hr style="border:none;border-top:2px solid #eef2ff;margin:12px 0" />
      <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:#0b63d6">Datos Personales</div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px">
        <tr>
          <td style="width:40%;padding:6px"><div style="color:#6b7280;font-size:11px">Nombre</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.nombre || '')}</div></td>
          <td style="width:30%;padding:6px"><div style="color:#6b7280;font-size:11px">Apellido</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.apellido || '')}</div></td>
          <td style="width:30%;padding:6px"><div style="color:#6b7280;font-size:11px">Cédula</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml(n.cedula || '')}</div></td>
        </tr>
      </table>
      <div style="font-size:13px;font-weight:700;margin:8px 0;color:#0b63d6">Préstamo</div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px">
        <tr>
          <td style="width:50%;padding:6px"><div style="color:#6b7280;font-size:11px">Monto</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((n.prestamo && n.prestamo.monto_solicitado) || '')}</div></td>
          <td style="width:50%;padding:6px"><div style="color:#6b7280;font-size:11px">Duración (meses)</div><div style="border-bottom:1px solid #dbeafe;padding:6px 4px">${escapeHtml((n.prestamo && n.prestamo.duracion) || '')}</div></td>
        </tr>
      </table>
      <div style="margin-top:18px;font-size:11px;color:#9aa4b2">Documento generado desde COOPNAMET.</div>
    </div>
  `;
}

function escapeHtml(str){
  if(!str && str!==0) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

refreshBtn.addEventListener('click', loadSolicitudes);
clearBtn.addEventListener('click', ()=>{
  if(!confirm('Delete all requests?')) return;
  localStorage.removeItem(STORAGE_KEY);
  loadSolicitudes();
});

(function(){
  try{
    const THEME_KEY = 'coop_theme';
    const t = localStorage.getItem(THEME_KEY) || 'light';
    document.documentElement.classList.remove('theme-light','theme-dark');
    document.documentElement.classList.add(t === 'dark' ? 'theme-dark' : 'theme-light');
  }catch(e){}
})();

loadLogoFromStorage();
loadSolicitudes();

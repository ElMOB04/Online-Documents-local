# Sistema de Gestión de Solicitudes de Préstamos - Cooperativa

## 📋 Descripción

Plataforma web moderna para la gestión completa de solicitudes de préstamos de una cooperativa financiera. Sistema desarrollado con **HTML5**, **CSS3** y **JavaScript vanilla** (sin dependencias externas en el frontend, solo librerías CDN para PDF).

### Características Principales

✅ **Formulario Wizard de 4 Pasos** - Experiencia fluida y progresiva  
✅ **Diseño Corporativo 2025** - Glassmorphism, fintech premium  
✅ **Datos Personales** - Cédula, edad, nombres, zona  
✅ **Contacto & Financiero** - Teléfono, email, ingresos, deudas  
✅ **Garante** - Información del avalista  
✅ **Privacidad** - Autorización y consentimiento de datos  
✅ **Panel Administrativo** - Listado, detalles, cambio de estado  
✅ **Exportación PDF** - Con logo, datos, firma, fecha  
✅ **Persistencia Local** - localStorage (sin servidor backend)  
✅ **Responsive** - Laptop, tablet, móvil  
✅ **Animaciones Suaves** - Transiciones profesionales  

---

## 🚀 Cómo Usar

### 1. Iniciar el Servidor Local

```bash
cd "c:\Users\alex\Desktop\cooperativa proyect"
python -m http.server 8000
```

Luego abre tu navegador en: `http://localhost:8000`

### 2. Estructura de Archivos

```
cooperativa project/
├── index.html          # Formulario público de solicitud
├── admin.html          # Panel administrativo
├── style.css           # Estilos corporativos 2025
├── app.js              # Lógica del formulario
├── admin.js            # Lógica del panel admin
├── LOGO ONAMET.webp    # Logo de la cooperativa
└── README.md           # Este archivo
```

### 3. Formulario Público (`index.html`)

**Paso 1: Datos Personales**
- Cédula (formato: 000-0000000-0)
- Rango de edad (18-25, 26-35, etc.)
- Nombre y apellidos
- Zona de residencia

**Paso 2: Contacto e Información Financiera**
- Teléfono celular con prefijo +809
- Confirmación de teléfono
- WhatsApp (opcional)
- Correo electrónico
- Ingresos mensuales (RD$)
- Otros ingresos (opcional)
- Situación laboral (Empleado/Independiente/Otro)
- Detalles del préstamo (monto, duración, deudas)

**Paso 3: Datos del Garante**
- Nombre y apellidos
- Cédula y código
- Teléfono y WhatsApp
- Email y dirección
- Relación con solicitante

**Paso 4: Autorización**
- Espacios de firma (visuales)
- Aceptación de privacidad y consentimiento
- Botón "ENVIAR SOLICITUD"

### 4. Panel Administrativo (`admin.html`)

- **Listado de Solicitudes** - Tarjetas con información resumida
- **Ver Detalles** - Abre modal con datos completos
- **Cambiar Estado** - Pendiente → Aprobada → Rechazada
- **Exportar PDF** - Descarga documento completo con firma
- **Subir Logo** - Personaliza el logo de la cooperativa
- **Refrescar** - Recarga lista desde localStorage
- **Borrar Todas** - Limpia datos (solo demo)

---

## 💾 Almacenamiento de Datos

Los datos se guardan en **localStorage** del navegador:

```javascript
// Clave principal
'solicitudes_cooperativa_v1': Array<solicitud>

// Estructura de solicitud
{
  id: 's_1731355200000',
  fecha: '2025-11-12T10:00:00.000Z',
  estado: 'Pendiente',
  datosPersonales: { cedula, edad, nombres, apellidos, zona },
  datosContacto: { telefono, whatsapp, email, esClienteCoop },
  infoFinanciera: { ingresosMensuales, otrosIngresos, situacionLaboral },
  detallesPrestamo: { montoSolicitado, duracion, deudaBanco, otraEntidad },
  garante: { nombres, apellidos, cedula, codigo, telefono, whatsapp, email, direccion, relacion }
}

// Logo de la cooperativa
'coop_logo': 'data:image/webp;base64,...'
```

### ⚠️ Nota Importante
- **No hay servidor backend** - Los datos se pierden si se vacía el localStorage
- Los datos se guardan **solo en el navegador local**
- Para producción, implementar backend y base de datos

---

## 🎨 Diseño Visual (2025)

### Paleta de Colores
- **Primario**: `#0E5DE6` (Azul corporativo)
- **Secundario**: `#3AA6F2` (Azul claro)
- **Acento**: `#22C1C3` (Turquesa)
- **Fondo**: `#F8FAFC` (Gris muy claro)
- **Texto**: `#0F172A` (Negro elegante)

### Tipografía
- **Headlines**: Poppins (700)
- **Body**: Inter (400-600)
- **Labels**: Manrope (600)

### Efectos
- **Glassmorphism** - backdrop-filter blur
- **Sombras Elevadas** - Shadow system 3D
- **Bordes Redondeados** - 12-32px
- **Animaciones** - 0.3s easing smooth
- **Gradientes** - Fintech premium

---

## 🔄 Flujo de Uso

### Usuario Final
1. Accede a `http://localhost:8000/index.html`
2. Completa formulario en 4 pasos
3. Valida campos automáticamente
4. Acepta privacidad y autorización
5. Envía solicitud
6. ✓ Confirmación y guardado automático

### Administrador
1. Accede a `http://localhost:8000/admin.html`
2. Ve listado de todas las solicitudes
3. Hace clic en tarjeta para ver detalles
4. Cambia estado (Pendiente → Aprobada/Rechazada)
5. Exporta a PDF
6. Descarga documento firmado

---

## 📱 Responsividad

| Dispositivo | Rango | Comportamiento |
|------------|-------|----------------|
| **Móvil** | < 480px | Stack vertical, botones full-width |
| **Tablet** | 480-768px | Grid de 2 columnas, modal ajustado |
| **Desktop** | 768-1024px | Grid 2 col, optimizado |
| **Laptop** | > 1024px | Grid 3 col, layout ideal |

---

## 🔧 Configuración

### Cambiar Colores Primarios
En `style.css`, modifica `:root`:
```css
:root {
  --primary: #0E5DE6;      /* Color azul primario */
  --secondary: #3AA6F2;    /* Color azul claro */
  --accent: #22C1C3;       /* Color turquesa */
}
```

### Agregar Más Campos
1. Añade `<input>` o `<select>` en el fieldset correspondiente
2. Asigna un `id` único
3. Actualiza `app.js` - referencia el elemento
4. Agrega validación en `validateStepX()`
5. Incluye en objeto `solicitud`

### Cambiar Logo
1. Reemplaza `LOGO ONAMET.webp` en la carpeta raíz
2. O carga en admin.html: Panel Admin → "Subir logo"

---

## 📊 Validaciones

### Paso 1 (Datos Personales)
- ✓ Cédula: requerida
- ✓ Edad: rango seleccionado
- ✓ Nombre: no vacío
- ✓ Apellidos: no vacío
- ✓ Zona: seleccionada

### Paso 2 (Contacto e Información)
- ✓ Teléfono: 7+ dígitos
- ✓ Confirmación: coincide con teléfono
- ✓ Email: formato válido
- ✓ Ingresos: requerido
- ✓ Monto préstamo: requerido
- ✓ Duración: 1-120 meses

### Paso 3 (Garante)
- ✓ Nombre: requerido
- ✓ Apellidos: requerido
- ✓ Teléfono: 7+ dígitos
- ✓ Dirección: requerida
- ✓ Relación: seleccionada

### Paso 4 (Autorización)
- ✓ Privacidad: checkbox obligatorio

---

## 🐛 Troubleshooting

### El formulario no carga
- Verifica que el servidor esté corriendo: `python -m http.server 8000`
- Abre `http://localhost:8000/index.html`

### Los estilos no aparecen
- Limpia caché: Ctrl+Shift+R (Chrome) o Cmd+Shift+R (Mac)
- Verifica que `style.css` exista en la carpeta

### PDF no exporta
- Asegúrate de que html2canvas y jsPDF cargan desde CDN
- Prueba en Chrome/Firefox (mejor soporte)

### Logo no aparece en PDF
- Sube logo desde admin.html: "Subir logo"
- El archivo local requiere servidor para funcionar

### localStorage vacío
- Verifica localStorage en DevTools: F12 → Application → LocalStorage
- Completa formulario nuevamente para guardar datos

---

## 🔐 Seguridad (Notas)

⚠️ **Esta es una demo educativa:**

- ❌ No usar con datos reales en producción
- ❌ localStorage es inseguro para datos sensibles
- ✅ Implementar backend con autenticación
- ✅ Encriptar datos en tránsito (HTTPS)
- ✅ Validar datos en servidor
- ✅ Cumplir regulaciones (RGPD, LPCCD)

---

## 📝 Licencia

Proyecto educativo para gestión de solicitudes de préstamos.
Uso libre con fines de enseñanza y desarrollo.

---

## 👨‍💻 Autor

Desarrollado como solución completa de gestión de solicitudes para cooperativas.
Diseño corporativo 2025 con tecnologías web modernas.

---

## 📞 Contacto & Soporte

Para preguntas o reportar problemas, contacta a través de:
- Email de administrador
- Panel de soporte
- Documentación técnica

---

**Última actualización**: Noviembre 2025  
**Versión**: 1.0  
**Estado**: ✅ Funcional y Listo para Uso

<?php
session_start();
include '../../config/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';
$error = '';

// Obtener ID de la solicitud
$solicitud_id = intval($_GET['id'] ?? 0);

if ($solicitud_id <= 0) {
    header("Location: pedidos.php");
    exit;
}

// Obtener información de la solicitud
$query = "SELECT * FROM store_requests WHERE id = ? AND user_id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("ii", $solicitud_id, $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header("Location: pedidos.php");
    exit;
}

$solicitud = $resultado->fetch_assoc();

// Verificar que la solicitud esté en estado pendiente
if ($solicitud['status'] !== 'pendiente') {
    header("Location: pedido_detalle.php?id=" . $solicitud_id . "&error=no_editable");
    exit;
}

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $eliminar_pdf = isset($_POST['eliminar_pdf']);
    
    // Validaciones
    if (empty($titulo)) {
        $error = 'El asunto es obligatorio.';
    } elseif (empty($descripcion)) {
        $error = 'La descripción es obligatoria.';
    } else {
        // Validar archivo PDF si se subió uno nuevo
        $pdf_path = $solicitud['pdf_path'];
        if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['pdf'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_mime = mime_content_type($file['tmp_name']);
            $file_size = $file['size'];
            
            // Validaciones del archivo
            $extensiones_permitidas = ['pdf', 'xlsx', 'xls', 'docx', 'doc'];
            $mime_types_permitidos = [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                'application/vnd.ms-excel', // xls
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                'application/msword' // doc
            ];
            
            if (!in_array($file_extension, $extensiones_permitidas)) {
                $error = 'Solo se permiten archivos PDF, Excel (.xlsx, .xls) y Word (.docx, .doc).';
            } elseif (!in_array($file_mime, $mime_types_permitidos)) {
                $error = 'El archivo no es válido. Solo se permiten PDF, Excel y Word.';
            } elseif ($file_size > 15 * 1024 * 1024) { // 15MB
                $error = 'El archivo no puede ser mayor a 15MB.';
            } else {
                // Crear directorio si no existe
                $upload_dir = 'uploads/pedidos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Eliminar PDF anterior si existe
                if (!empty($solicitud['pdf_path']) && file_exists($solicitud['pdf_path'])) {
                    unlink($solicitud['pdf_path']);
                }
                
                // Generar nombre único para el archivo
                $timestamp = time();
                $new_filename = $timestamp . '_' . $usuario_id . '_' . basename($file['name']);
                $pdf_path = $upload_dir . $new_filename;
                
                // Mover archivo
                if (!move_uploaded_file($file['tmp_name'], $pdf_path)) {
                    $error = 'Error al subir el archivo PDF.';
                }
            }
        } elseif ($eliminar_pdf && !empty($solicitud['pdf_path'])) {
            // Eliminar PDF existente
            if (file_exists($solicitud['pdf_path'])) {
                unlink($solicitud['pdf_path']);
            }
            $pdf_path = null;
        }
        
        // Si no hay errores, actualizar en la base de datos
        if (empty($error)) {
            $stmt = $conexion->prepare("UPDATE store_requests SET title = ?, description = ?, pdf_path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("sssi", $titulo, $descripcion, $pdf_path, $solicitud_id);
            
            if ($stmt->execute()) {
                // Registrar evento de auditoría
                $event_data = json_encode([
                    'titulo_anterior' => $solicitud['title'],
                    'titulo_nuevo' => $titulo,
                    'descripcion_anterior' => $solicitud['description'],
                    'descripcion_nueva' => $descripcion,
                    'pdf_anterior' => !empty($solicitud['pdf_path']),
                    'pdf_nuevo' => !empty($pdf_path)
                ]);
                $stmt_event = $conexion->prepare("INSERT INTO store_request_events (request_id, user_id, event_type, data_json) VALUES (?, ?, 'UPDATED', ?)");
                $stmt_event->bind_param("iis", $solicitud_id, $usuario_id, $event_data);
                $stmt_event->execute();
                
                $mensaje = 'Solicitud actualizada exitosamente.';
                
                // Redirigir después de 2 segundos
                header("refresh:2;url=pedido_detalle.php?id=" . $solicitud_id);
            } else {
                $error = 'Error al actualizar la solicitud. Inténtelo de nuevo.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Solicitud #<?= $solicitud_id ?> - Pedidos Recibidos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background: #f6f8fa;
        }
        .container {
            max-width: 800px;
        }
        .minimal-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            border: none;
            margin-bottom: 32px;
        }
        .minimal-card-header {
            background: #f0f4f8;
            border-radius: 18px 18px 0 0;
            padding: 18px 28px;
            font-size: 1.2rem;
            font-weight: 600;
            color: #222;
            border-bottom: 1px solid #eaeaea;
        }
        .minimal-card-body {
            padding: 28px;
        }
        .btn-minimal {
            border-radius: 8px;
            font-size: 0.95rem;
            padding: 8px 20px;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            padding: 18px 28px;
            border-left: 8px solid #ffc107;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #222;
            letter-spacing: -1px;
            margin-bottom: 0;
        }
        .header-logo img {
            height: 50px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px 16px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #ffc107;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }
        .char-counter {
            font-size: 0.875rem;
            color: #666;
            text-align: right;
        }
        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }
        .file-upload-area:hover {
            border-color: #ffc107;
            background: #fffbf0;
        }
        .file-upload-area.dragover {
            border-color: #ffc107;
            background: #fff8e1;
        }
        .current-pdf {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        /* Animaciones para confirmación de archivo */
        .upload-icon-container {
            transition: all 0.3s ease;
        }
        
        .file-success-animation {
            animation: successPulse 1.5s ease-in-out;
        }
        
        .file-success-icon {
            color: #28a745 !important;
            animation: bounceIn 0.8s ease-in-out;
        }
        
        .file-success-text {
            color: #28a745 !important;
            font-weight: bold !important;
            animation: slideInUp 0.6s ease-in-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); background-color: #d4edda; }
            100% { transform: scale(1); }
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3) rotate(-10deg); opacity: 0; }
            50% { transform: scale(1.1) rotate(5deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }
        
        @keyframes slideInUp {
            0% { transform: translateY(20px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        
        .file-upload-area.success-state {
            border: 3px solid #28a745 !important;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.3) !important;
        }
        
        .success-checkmark {
            position: relative;
            display: inline-block;
        }
        
        .success-checkmark::after {
            content: '✓';
            position: absolute;
            top: -5px;
            right: -5px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            animation: checkmarkAppear 0.5s ease-in-out;
        }
        
        @keyframes checkmarkAppear {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="dashboard-header">
        <div class="header-left">
            <div class="header-logo">
                <img src="img/logo_indomet.png" alt="Logo INDOMET">
            </div>
            <div class="header-title">Editar Solicitud #<?= $solicitud_id ?></div>
        </div>
        <div class="header-actions">
            <a href="pedido_detalle.php?id=<?= $solicitud_id ?>" class="btn btn-minimal btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Detalle
            </a>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="minimal-card">
        <div class="minimal-card-header">Editar Información de la Solicitud</div>
        <div class="minimal-card-body">
            <form method="POST" enctype="multipart/form-data" id="editarForm">
                <div class="row">
                    <div class="col-12">
                        <div class="mb-4">
                            <label for="titulo" class="form-label">
                                Asunto/Título <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="titulo" 
                                   name="titulo" 
                                   value="<?= htmlspecialchars($solicitud['title']) ?>"
                                   placeholder="Ej: Solicitud de material de oficina"
                                   required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="mb-4">
                            <label for="descripcion" class="form-label">
                                Descripción <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="descripcion" 
                                      name="descripcion" 
                                      rows="5" 
                                      placeholder="Describa detalladamente los materiales que necesita..."
                                      required><?= htmlspecialchars($solicitud['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="mb-4">
                            <label class="form-label">Documento Adjunto</label>
                            
                            <?php if (!empty($solicitud['pdf_path']) && file_exists($solicitud['pdf_path'])): ?>
                                <div class="current-pdf">
                                    <i class="bi bi-file-pdf text-danger"></i>
                                    <strong>PDF actual:</strong> <?= basename($solicitud['pdf_path']) ?>
                                    <div class="mt-2">
                                        <a href="pedido_descargar_pdf.php?id=<?= $solicitud_id ?>" class="btn btn-sm btn-outline-danger me-2">
                                            <i class="bi bi-download"></i> Ver PDF actual
                                        </a>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="eliminar_pdf" id="eliminar_pdf">
                                            <label class="form-check-label" for="eliminar_pdf">
                                                Eliminar PDF actual
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="file-upload-area" id="fileUploadArea">
                                <div id="uploadIcon" class="upload-icon-container">
                                    <i class="bi bi-cloud-upload display-4 text-muted mb-3"></i>
                                </div>
                                <p id="uploadText" class="mb-2">Arrastra un nuevo archivo aquí o haz clic para seleccionar</p>
                                <p class="text-muted small">Tamaño máximo: 15MB | Archivos: PDF, Excel (.xlsx, .xls), Word (.docx, .doc)</p>
                                <input type="file" 
                                       class="form-control d-none" 
                                       id="pdf" 
                                       name="pdf" 
                                       accept=".pdf,.xlsx,.xls,.docx,.doc">
                                <button type="button" class="btn btn-outline-warning" onclick="document.getElementById('pdf').click()">
                                    <i class="bi bi-folder2-open"></i> Seleccionar Nuevo Archivo
                                </button>
                            </div>
                            <div id="fileInfo" class="mt-3" style="display: none;">
                                <div class="alert alert-success border-2 border-success">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-pdf fs-4 me-3"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">
                                                <span id="fileName"></span>
                                            </div>
                                            <small class="text-muted">
                                                <span id="fileSize"></span> • Nuevo archivo seleccionado
                                            </small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFile()" title="Eliminar archivo">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <a href="pedido_detalle.php?id=<?= $solicitud_id ?>" class="btn btn-minimal btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-minimal btn-warning">
                                <i class="bi bi-check-circle"></i> Actualizar Solicitud
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Contadores opcionales: solo si existen en el DOM
const tituloCounter = document.getElementById('titulo-counter');
const descripcionCounter = document.getElementById('descripcion-counter');

const tituloInput = document.getElementById('titulo');
const descripcionInput = document.getElementById('descripcion');

if (tituloCounter && tituloInput) {
    tituloInput.addEventListener('input', function() {
        tituloCounter.textContent = this.value.length;
    });
    tituloCounter.textContent = tituloInput.value.length;
}

if (descripcionCounter && descripcionInput) {
    descripcionInput.addEventListener('input', function() {
        descripcionCounter.textContent = this.value.length;
    });
    descripcionCounter.textContent = descripcionInput.value.length;
}

// Drag and drop para archivos
const fileUploadArea = document.getElementById('fileUploadArea');
const fileInput = document.getElementById('pdf');
const fileInfo = document.getElementById('fileInfo');

fileUploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
});

fileUploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
});

fileUploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const file = files[0];
        const allowedTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword'
        ];
        
        if (allowedTypes.includes(file.type)) {
            fileInput.files = files;
            showFileInfo(file);
            
            // Mostrar mensaje de confirmación
            const uploadArea = this.querySelector('p');
            const originalText = uploadArea.textContent;
            uploadArea.textContent = '¡Nuevo archivo agregado correctamente!';
            uploadArea.style.color = '#28a745';
            uploadArea.style.fontWeight = 'bold';
            
            // Restaurar texto original después de 2 segundos
            setTimeout(() => {
                uploadArea.textContent = originalText;
                uploadArea.style.color = '';
                uploadArea.style.fontWeight = '';
            }, 2000);
        } else {
            alert('Solo se permiten archivos PDF, Excel y Word.');
        }
    }
});

fileUploadArea.addEventListener('click', function() {
    fileInput.click();
});

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        showFileInfo(this.files[0]);
    }
});

function showFileInfo(file) {
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadArea = document.getElementById('fileUploadArea');
    
    // ANIMACIÓN DRAMÁTICA SIMPLE Y DIRECTA
    if (uploadArea) {
        // 1. Cambiar el área de subida a estado de éxito
        uploadArea.classList.add('success-state', 'file-success-animation');
        
        // 2. Cambiar el contenido del área de subida temporalmente
        const originalContent = uploadArea.innerHTML;
        uploadArea.innerHTML = `
            <div style="text-align: center; color: #28a745; font-weight: bold;">
                <i class="bi bi-check-circle-fill" style="font-size: 3rem; animation: bounceIn 0.8s ease-in-out;"></i>
                <p style="margin-top: 15px; font-size: 1.2rem; animation: slideInUp 0.6s ease-in-out;">
                    ¡NUEVO ARCHIVO ADJUNTADO CORRECTAMENTE!
                </p>
                <p style="font-size: 0.9rem; margin-top: 10px;">
                    ${file.name} (${formatFileSize(file.size)})
                </p>
            </div>
        `;
        
        // 3. Restaurar contenido original después de 3 segundos
        setTimeout(() => {
            uploadArea.innerHTML = originalContent;
            uploadArea.classList.remove('success-state', 'file-success-animation');
        }, 3000);
    }
    
    // 4. Mostrar información detallada del archivo (si los elementos existen)
    if (fileName) fileName.textContent = file.name;
    if (fileSize) fileSize.textContent = formatFileSize(file.size);
    
    // 5. Mostrar información detallada del archivo (si existe el preview)
    const fileInfo = document.getElementById('fileInfo');
    if (fileInfo) {
        // Determinar el icono según el tipo de archivo
        const fileExtension = file.name.split('.').pop().toLowerCase();
        let fileIcon = 'bi-file';
        
        switch(fileExtension) {
            case 'pdf':
                fileIcon = 'bi-file-pdf text-danger';
                break;
            case 'xlsx':
            case 'xls':
                fileIcon = 'bi-file-excel text-success';
                break;
            case 'docx':
            case 'doc':
                fileIcon = 'bi-file-word text-primary';
                break;
        }
        
        const iconElement = fileInfo.querySelector('i');
        if (iconElement) {
            iconElement.className = 'bi ' + fileIcon + ' fs-4 me-3';
        }
        
        // Mostrar información del archivo
        fileInfo.style.display = 'block';
        fileInfo.classList.add('alert-success');
        fileInfo.classList.remove('alert-warning');
    }
    
    // 6. Sonido de confirmación (si el navegador lo permite)
    try {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
        audio.play().catch(() => {}); // Ignorar errores si no se puede reproducir
    } catch (e) {}
}

function clearFile() {
    fileInput.value = '';
    fileInfo.style.display = 'none';
    
    // Resetear clases CSS
    fileInfo.classList.remove('alert-success');
    fileInfo.classList.add('alert-warning');
    
    // Resetear icono
    const iconElement = fileInfo.querySelector('i');
    iconElement.className = 'bi bi-file-pdf fs-4 me-3';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Validación del formulario (sin límites de caracteres, acepta PDF/Excel/Word)
document.getElementById('editarForm').addEventListener('submit', function(e) {
    const titulo = document.getElementById('titulo').value.trim();
    const descripcion = document.getElementById('descripcion').value.trim();
    const file = document.getElementById('pdf').files[0];

    if (!titulo) {
        e.preventDefault();
        alert('El asunto es obligatorio.');
        return;
    }

    if (!descripcion) {
        e.preventDefault();
        alert('La descripción es obligatoria.');
        return;
    }

    if (file) {
        const allowedTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword'
        ];
        if (!allowedTypes.includes(file.type)) {
            e.preventDefault();
            alert('Solo se permiten archivos PDF, Excel y Word.');
            return;
        }
        if (file.size > 15 * 1024 * 1024) {
            e.preventDefault();
            alert('El archivo no puede ser mayor a 15MB.');
            return;
        }
    }
});
</script>
</body>
</html>

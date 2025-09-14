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

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $pdf_obligatorio = true; // Parametrizable - por ahora obligatorio
    
    // Validaciones
    if (empty($titulo)) {
        $error = 'El asunto es obligatorio.';
    } elseif (empty($descripcion)) {
        $error = 'La descripción es obligatoria.';
    } elseif ($pdf_obligatorio && (!isset($_FILES['pdf']) || (is_array($_FILES['pdf']['error']) ? count(array_filter($_FILES['pdf']['error'], function($e){return $e===UPLOAD_ERR_OK;}))===0 : $_FILES['pdf']['error'] !== UPLOAD_ERR_OK))) {
        $error = 'Debe adjuntar al menos un archivo (PDF, Excel o Word).';
    } else {
        // Validación y empaquetado de múltiples archivos en ZIP
        $pdf_path = null;
        if (isset($_FILES['pdf'])) {
            $extensiones_permitidas = ['pdf', 'xlsx', 'xls', 'docx', 'doc'];
            $mime_types_permitidos = [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword'
            ];

            $files_ok = [];
            if (is_array($_FILES['pdf']['name'])) {
                $num = count($_FILES['pdf']['name']);
                for ($i=0; $i<$num; $i++) {
                    if ($_FILES['pdf']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $tmp_name = $_FILES['pdf']['tmp_name'][$i];
                    $name = $_FILES['pdf']['name'][$i];
                    $size = $_FILES['pdf']['size'][$i];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $mime = mime_content_type($tmp_name);
                    if (!in_array($ext, $extensiones_permitidas)) { $error = 'Algún archivo tiene extensión no permitida.'; break; }
                    if (!in_array($mime, $mime_types_permitidos)) { $error = 'Algún archivo tiene tipo no permitido.'; break; }
                    if ($size > 15 * 1024 * 1024) { $error = 'Algún archivo supera 15MB.'; break; }
                    $files_ok[] = ['tmp'=>$tmp_name,'name'=>$name];
                }
            } else {
                if ($_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['pdf']['tmp_name'];
                    $name = $_FILES['pdf']['name'];
                    $size = $_FILES['pdf']['size'];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $mime = mime_content_type($tmp_name);
                    if (!in_array($ext, $extensiones_permitidas)) { $error = 'Extensión no permitida.'; }
                    elseif (!in_array($mime, $mime_types_permitidos)) { $error = 'Tipo de archivo no permitido.'; }
                    elseif ($size > 15 * 1024 * 1024) { $error = 'El archivo supera 15MB.'; }
                    else { $files_ok[] = ['tmp'=>$tmp_name,'name'=>$name]; }
                }
            }

            if (empty($error) && count($files_ok) > 0) {
                // Crear directorio si no existe
                $upload_dir = 'uploads/pedidos/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

                $timestamp = time();

                if (count($files_ok) === 1) {
                    // Caso de un solo archivo: mover directamente
                    $f = $files_ok[0];
                    $new_filename = $timestamp . '_' . $usuario_id . '_' . preg_replace('/[^a-zA-Z0-9_.\-]/','_', basename($f['name']));
                    $dest_path = $upload_dir . $new_filename;
                    if (!move_uploaded_file($f['tmp'], $dest_path)) {
                        $error = 'Error al subir el archivo adjunto.';
                    } else {
                        $pdf_path = $dest_path;
                    }
                } else {
                    // Múltiples archivos: empaquetar en ZIP si está disponible
                    if (class_exists('ZipArchive')) {
                        $zip_filename = $timestamp . '_' . $usuario_id . '.zip';
                        $zip_path = $upload_dir . $zip_filename;
                        $zip = new ZipArchive();
                        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
                            $error = 'No se pudo crear el archivo ZIP.';
                        } else {
                            foreach ($files_ok as $f) {
                                $safeName = preg_replace('/[^a-zA-Z0-9_.\-]/','_', basename($f['name']));
                                $zip->addFile($f['tmp'], $safeName);
                            }
                            $zip->close();
                            $pdf_path = $zip_path; // Guardamos el ZIP
                        }
                    } else {
                        // Fallback: subir solo el primer archivo para no bloquear la creación
                        $f = $files_ok[0];
                        $new_filename = $timestamp . '_' . $usuario_id . '_' . preg_replace('/[^a-zA-Z0-9_.\-]/','_', basename($f['name']));
                        $dest_path = $upload_dir . $new_filename;
                        if (!move_uploaded_file($f['tmp'], $dest_path)) {
                            $error = 'Error al subir el archivo adjunto.';
                        } else {
                            $pdf_path = $dest_path;
                        }
                    }
                }
            }
        }
        
        // Si no hay errores, insertar en la base de datos
        if (empty($error)) {
            $stmt = $conexion->prepare("INSERT INTO store_requests (user_id, title, description, pdf_path, status) VALUES (?, ?, ?, ?, 'pendiente')");
            $stmt->bind_param("isss", $usuario_id, $titulo, $descripcion, $pdf_path);
            
            if ($stmt->execute()) {
                $solicitud_id = $conexion->insert_id;
                
                // Registrar evento de auditoría
                $event_data = json_encode([
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'tiene_pdf' => !empty($pdf_path)
                ]);
                $stmt_event = $conexion->prepare("INSERT INTO store_request_events (request_id, user_id, event_type, data_json) VALUES (?, ?, 'CREATED', ?)");
                $stmt_event->bind_param("iis", $solicitud_id, $usuario_id, $event_data);
                $stmt_event->execute();
                
                // Enviar notificación a usuarios de almacén y editores
                $stmt_almacen = $conexion->prepare("SELECT id FROM usuarios WHERE rol = 'almacen' OR rol = 'Editor'");
                $stmt_almacen->execute();
                $result_almacen = $stmt_almacen->get_result();
                
                while ($almacen_user = $result_almacen->fetch_assoc()) {
                    $mensaje_notif = "Nueva solicitud #{$solicitud_id} creada por " . $_SESSION['usuario_nombre'] . ": " . $titulo;
                    $stmt_notif = $conexion->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (?, ?)");
                    $stmt_notif->bind_param("is", $almacen_user['id'], $mensaje_notif);
                    $stmt_notif->execute();
                }
                
                $mensaje = 'Solicitud creada exitosamente. Número de solicitud: #' . $solicitud_id;
                
                // Redirigir después de 2 segundos
                header("refresh:2;url=pedidos.php");
            } else {
                $error = 'Error al crear la solicitud. Inténtelo de nuevo.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Solicitud - Pedidos Recibidos</title>
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
            border-left: 8px solid #28a745;
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
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
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
            border-color: #28a745;
            background: #f0f8f0;
        }
        .file-upload-area.dragover {
            border-color: #28a745;
            background: #e8f5e8;
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
            <div class="header-title">Nueva Solicitud a Almacén</div>
        </div>
        <div class="header-actions">
            <a href="pedidos.php" class="btn btn-minimal btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Pedidos
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
        <div class="minimal-card-header">Información de la Solicitud</div>
        <div class="minimal-card-body">
            <form method="POST" enctype="multipart/form-data" id="solicitudForm">
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
                                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>"
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
                                      required><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="mb-4">
                            <label for="pdf" class="form-label">
                                Documento Adjunto <span class="text-danger">*</span>
                            </label>
                            <div class="file-upload-area" id="fileUploadArea">
                                <div id="uploadIcon" class="upload-icon-container">
                                    <i class="bi bi-cloud-upload display-4 text-muted mb-3"></i>
                                </div>
                                <p id="uploadText" class="mb-2">Arrastra tu archivo aquí o haz clic para seleccionar</p>
                                <p class="text-muted small">Tamaño máximo: 15MB | Archivos: PDF, Excel (.xlsx, .xls), Word (.docx, .doc)</p>
                                <input type="file" 
                                       class="form-control d-none" 
                                       id="pdf" 
                                       name="pdf[]" 
                                       accept=".pdf,.xlsx,.xls,.docx,.doc"
                                       multiple
                                       required>
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('pdf').click()">
                                    <i class="bi bi-folder2-open"></i> Seleccionar Archivo
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
                                                <span id="fileSize"></span> • Archivo seleccionado correctamente
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
                            <a href="pedidos.php" class="btn btn-minimal btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-minimal btn-success">
                                <i class="bi bi-check-circle"></i> Crear Solicitud
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
// Contador de caracteres
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
        } else {
            alert('Solo se permiten archivos PDF, Excel y Word.');
        }
    }
});

fileUploadArea.addEventListener('click', function() {
    fileInput.click();
});

fileInput.addEventListener('change', function() {
    if (this.files && this.files.length > 0) {
        // Mostrar el primer archivo en el preview y contar los demás
        showFileInfo(this.files[0]);
        if (this.files.length > 1) {
            const uploadText = document.getElementById('uploadText');
            if (uploadText) {
                uploadText.textContent = `Se seleccionaron ${this.files.length} archivos`;
            }
        }
    }
});

function showFileInfo(file) {
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadArea = document.getElementById('fileUploadArea');
    const uploadText = document.getElementById('uploadText');

    // Realzar visualmente sin eliminar elementos del DOM
    if (uploadArea) {
        uploadArea.classList.add('success-state', 'file-success-animation');
        setTimeout(() => {
            uploadArea.classList.remove('file-success-animation');
        }, 1200);

        if (uploadText) {
            const total = (document.getElementById('pdf')?.files?.length) || 1;
            const originalText = uploadText.textContent;
            uploadText.textContent = total > 1
                ? `¡${total} archivos adjuntados correctamente!`
                : '¡Archivo adjuntado correctamente!';
            setTimeout(() => {
                uploadText.textContent = originalText;
            }, 2500);
        }
    }

    // Mostrar información detallada del archivo (si los elementos existen)
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
            iconElement.className = 'bi ' + fileIcon;
        }
        
        // Mostrar información del archivo
        fileInfo.style.display = 'block';
        fileInfo.classList.add('alert-success');
        fileInfo.classList.remove('alert-info');
    }
    
    // Sonido de confirmación (si el navegador lo permite)
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
    fileInfo.classList.add('alert-info');
    
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
document.getElementById('solicitudForm').addEventListener('submit', function(e) {
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

    if (!file) {
        e.preventDefault();
        alert('Debe adjuntar un archivo.');
        return;
    }

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
});
</script>
</body>
</html>

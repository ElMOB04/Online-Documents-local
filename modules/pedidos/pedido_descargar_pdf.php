<?php
session_start();
include '../../config/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'usuario';

// Obtener ID de la solicitud
$solicitud_id = intval($_GET['id'] ?? 0);

if ($solicitud_id <= 0) {
    header("Location: pedidos.php");
    exit;
}

// Obtener información de la solicitud
$query = "SELECT sr.*, u.nombre as solicitante_nombre 
          FROM store_requests sr 
          JOIN usuarios u ON sr.user_id = u.id 
          WHERE sr.id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $solicitud_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header("Location: pedidos.php");
    exit;
}

$solicitud = $resultado->fetch_assoc();

// Verificar permisos de acceso al PDF
$puede_descargar = false;
if ($rol === 'admin' || $rol === 'dueno') {
    $puede_descargar = true;
} elseif ($rol === 'almacen' || $rol === 'Editor') {
    $puede_descargar = true;
} elseif ($solicitud['user_id'] == $usuario_id) {
    $puede_descargar = true;
}

if (!$puede_descargar) {
    header("Location: pedidos.php");
    exit;
}

// Verificar que existe el archivo
if (empty($solicitud['pdf_path']) || !file_exists($solicitud['pdf_path'])) {
    header("Location: pedido_detalle.php?id=" . $solicitud_id . "&error=no_archivo");
    exit;
}

// Registrar evento de descarga en auditoría
$stmt_event = $conexion->prepare("INSERT INTO store_request_events (request_id, user_id, event_type, data_json) VALUES (?, ?, 'DOWNLOADED', ?)");
$event_data = json_encode([
    'archivo' => basename($solicitud['pdf_path']),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);
$stmt_event->bind_param("iis", $solicitud_id, $usuario_id, $event_data);
$stmt_event->execute();

// Detectar tipo de archivo y configurar headers
$file_path = $solicitud['pdf_path'];
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$original_filename = basename($file_path);

// Mapeo de tipos MIME
$mime_types = [
    'pdf' => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'zip' => 'application/zip'
];

$content_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Configurar headers para descarga
$filename = 'Solicitud_' . $solicitud_id . '_' . $solicitud['title'] . '.' . $file_extension;
$filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename); // Limpiar nombre de archivo

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Leer y enviar el archivo
readfile($file_path);
exit;
?>

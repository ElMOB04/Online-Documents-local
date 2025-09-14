<?php
session_start();
include 'includes/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'usuario';

// Validar ID del documento
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$doc_id = intval($_GET['id']);

// Obtener información del documento
$query = "SELECT * FROM documentos WHERE id = ?";
$stmt = $conexion->prepare($query);

if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}

$stmt->bind_param("i", $doc_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header("Location: dashboard.php");
    exit();
}

$documento = $resultado->fetch_assoc();

// Verificar permisos
if ($rol !== 'admin' && $documento['usuario_id'] != $usuario_id) {
    echo "No tienes permiso para eliminar este documento.";
    exit();
}

// Eliminar archivo físico si existe
if (!empty($documento['ruta']) && file_exists($documento['ruta'])) {
    unlink($documento['ruta']);
}

// Eliminar los comentarios asociados al documento
$delete_comments = $conexion->prepare("DELETE FROM comentarios WHERE documento_id = ?");
if ($delete_comments) {
    $delete_comments->bind_param("i", $doc_id);
    $delete_comments->execute();
}

// Eliminar el documento de la base de datos
$delete_doc = $conexion->prepare("DELETE FROM documentos WHERE id = ?");
if ($delete_doc) {
    $delete_doc->bind_param("i", $doc_id);
    $delete_doc->execute();
}

header("Location: dashboard.php");
exit();
?>

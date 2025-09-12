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

// Eliminar archivo del sistema de archivos
if (file_exists($documento['ruta'])) {
    unlink($documento['ruta']);
}

// Eliminar de la base de datos
$delete = $conexion->prepare("DELETE FROM documentos WHERE id = ?");
$delete->bind_param("i", $doc_id);
$delete->execute();

header("Location: dashboard.php");
exit();
?>

<?php
session_start();
include 'includes/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$usuario_id_logueado = $_SESSION['usuario_id'];
$rol_logueado = $_SESSION['rol'] ?? 'usuario';

// Verificar si se proporcionó un ID de comentario y de documento
if (!isset($_GET['id']) || !isset($_GET['documento_id'])) {
    // Redirigir de vuelta a la página de comentarios si falta información
    header("Location: comentarios.php");
    exit();
}

$comentario_id = intval($_GET['id']);
$documento_id = intval($_GET['documento_id']);

// Obtener el usuario_id del comentario y el usuario_id del documento asociado
$stmt = $conexion->prepare("SELECT c.usuario_id, d.usuario_id AS documento_usuario_id FROM comentarios c JOIN documentos d ON c.documento_id = d.id WHERE c.id = ?");
$stmt->bind_param("i", $comentario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    // Si el comentario no existe, redirigir
    header("Location: comentarios.php?documento_id=" . $documento_id);
    exit();
}

$fila = $resultado->fetch_assoc();
$usuario_id_comentario = $fila['usuario_id'];
$documento_usuario_id = $fila['documento_usuario_id'];

$stmt->close();

// Verificar si el usuario logueado tiene permiso para eliminar el comentario
// Permisos: admin, gestor_usuarios, dueño, dueño del comentario, o dueño del documento asociado
if ($rol_logueado === 'admin' || $rol_logueado === 'gestor_usuarios' || $rol_logueado === 'dueno' || $usuario_id_logueado === $usuario_id_comentario || $usuario_id_logueado === $documento_usuario_id) {
    // Eliminar el comentario
    $stmt_delete = $conexion->prepare("DELETE FROM comentarios WHERE id = ?");
    $stmt_delete->bind_param("i", $comentario_id);

    if ($stmt_delete->execute()) {
        // Comentario eliminado correctamente
        // Redirigir de vuelta a la página de comentarios del documento
        header("Location: comentarios.php?documento_id=" . $documento_id);
        exit();
    } else {
        // Error al eliminar
        echo "Error al eliminar el comentario: " . $stmt_delete->error;
    }
    $stmt_delete->close();
} else {
    // No tiene permisos
    echo "No tienes permiso para eliminar este comentario.";
    // Considerar redirigir con un mensaje de error
}

$conexion->close();
?> 
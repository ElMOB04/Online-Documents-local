<?php
session_start();
include 'includes/conexion.php';

header('Content-Type: application/json');

// Debugging: Log received POST data
error_log("DEBUG: editar_comentario.php received POST: " . print_r($_POST, true));

if (!isset($_SESSION['usuario_id'])) {
    error_log("DEBUG: editar_comentario.php - No autenticado");
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$texto = isset($_POST['texto']) ? trim($_POST['texto']) : '';

// Debugging: Log user ID and received comment ID and text
error_log("DEBUG: editar_comentario.php - User ID: " . $usuario_id . ", Comment ID: " . $id . ", Text: " . $texto);

if ($id <= 0 || $texto === '') {
    error_log("DEBUG: editar_comentario.php - Datos inválidos");
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

// Verificar que el comentario pertenece al usuario logueado
$stmt = $conexion->prepare('SELECT usuario_id FROM comentarios WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($autor_id);

// Debugging: Log fetched author ID
error_log("DEBUG: editar_comentario.php - Fetched Author ID: " . $autor_id);

// Si no se encuentra el comentario o el autor no coincide, denegar el acceso
// Fetch antes de la verificación
$stmt->fetch();

if ($autor_id != $usuario_id) {
    $stmt->close();
    error_log("DEBUG: editar_comentario.php - No autorizado. Autor ID: " . $autor_id . ", Logged in User ID: " . $usuario_id);
    echo json_encode(['success' => false, 'error' => 'No autorizado para editar este comentario']);
    exit;
}
$stmt->close();

// Debugging: Log successful authorization
error_log("DEBUG: editar_comentario.php - Autorizado para editar.");

// Actualizar el comentario
$stmt = $conexion->prepare('UPDATE comentarios SET comentario = ? WHERE id = ?');
$stmt->bind_param('si', $texto, $id);
if ($stmt->execute()) {
    error_log("DEBUG: editar_comentario.php - Actualización exitosa.");
    echo json_encode([
        'success' => true,
        'texto_html' => nl2br(htmlspecialchars($texto))
    ]);
} else {
    error_log("DEBUG: editar_comentario.php - Error al actualizar: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
} 
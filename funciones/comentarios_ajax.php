<?php
session_start();
include 'includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'crear':
        if (!isset($_POST['documento_id']) || !isset($_POST['comentario'])) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        $stmt = $conexion->prepare("INSERT INTO comentarios (documento_id, usuario_id, comentario) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $_POST['documento_id'], $_SESSION['usuario_id'], $_POST['comentario']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al crear el comentario']);
        }
        break;

    case 'editar':
        if (!isset($_POST['comentario_id']) || !isset($_POST['comentario'])) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        // Verificar que el comentario pertenece al usuario
        $stmt = $conexion->prepare("SELECT usuario_id FROM comentarios WHERE id = ?");
        $stmt->bind_param("i", $_POST['comentario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $comentario = $result->fetch_assoc();

        if ($comentario['usuario_id'] != $_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }

        $stmt = $conexion->prepare("UPDATE comentarios SET comentario = ? WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("sii", $_POST['comentario'], $_POST['comentario_id'], $_SESSION['usuario_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al editar el comentario']);
        }
        break;

    case 'eliminar':
        if (!isset($_POST['comentario_id'])) {
            echo json_encode(['success' => false, 'error' => 'ID de comentario no proporcionado']);
            exit;
        }

        // Verificar que el comentario pertenece al usuario
        $stmt = $conexion->prepare("SELECT usuario_id FROM comentarios WHERE id = ?");
        $stmt->bind_param("i", $_POST['comentario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $comentario = $result->fetch_assoc();

        if ($comentario['usuario_id'] != $_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }

        $stmt = $conexion->prepare("DELETE FROM comentarios WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $_POST['comentario_id'], $_SESSION['usuario_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al eliminar el comentario']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}
?>

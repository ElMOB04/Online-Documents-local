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

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pedidos.php");
    exit;
}

$action = $_POST['action'] ?? '';
$solicitud_id = intval($_POST['solicitud_id'] ?? 0);

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

// Verificar permisos según la acción
$puede_actuar = false;
if ($action === 'comentar') {
    // Cualquier usuario involucrado puede comentar
    $puede_actuar = ($rol === 'admin' || $rol === 'dueno' || $rol === 'almacen' || $rol === 'Editor' || $solicitud['user_id'] == $usuario_id);
} elseif (in_array($action, ['aprobar', 'rechazar'])) {
    // Solo almacén, admin y dueño pueden aprobar/rechazar
    $puede_actuar = ($rol === 'admin' || $rol === 'dueno' || $rol === 'almacen' || $rol === 'Editor');
}

if (!$puede_actuar) {
    header("Location: pedido_detalle.php?id=" . $solicitud_id . "&error=sin_permisos");
    exit;
}

$comentario = trim($_POST['comentario'] ?? '');
$interno_almacen = isset($_POST['interno_almacen']) ? 1 : 0;

// Validar comentario
if (empty($comentario)) {
    header("Location: pedido_detalle.php?id=" . $solicitud_id . "&error=comentario_requerido");
    exit;
}

try {
    $conexion->begin_transaction();
    
    // Insertar comentario
    $stmt_comentario = $conexion->prepare("INSERT INTO store_request_comments (request_id, user_id, comment, visibility) VALUES (?, ?, ?, ?)");
    $visibilidad = $interno_almacen ? 'interno_almacen' : 'publico';
    $stmt_comentario->bind_param("iiss", $solicitud_id, $usuario_id, $comentario, $visibilidad);
    $stmt_comentario->execute();
    
    // Procesar acción específica
    if ($action === 'aprobar') {
        // Verificar que la solicitud esté en estado apropiado
        if (!in_array($solicitud['status'], ['pendiente', 'en_revision'])) {
            throw new Exception('La solicitud no puede ser aprobada en su estado actual.');
        }
        
        // Actualizar estado
        $stmt_update = $conexion->prepare("UPDATE store_requests SET status = 'aprobada', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_update->bind_param("i", $solicitud_id);
        $stmt_update->execute();
        
        // Registrar evento
        $event_data = json_encode([
            'estado_anterior' => $solicitud['status'],
            'estado_nuevo' => 'aprobada',
            'comentario' => $comentario
        ]);
        $stmt_event = $conexion->prepare("INSERT INTO store_request_events (request_id, user_id, event_type, data_json) VALUES (?, ?, 'APPROVED', ?)");
        $stmt_event->bind_param("iis", $solicitud_id, $usuario_id, $event_data);
        $stmt_event->execute();
        
        // Enviar notificación al solicitante
        $mensaje_notif = "Su solicitud #{$solicitud_id} ha sido APROBADA por " . $_SESSION['usuario_nombre'] . ". Comentario: " . substr($comentario, 0, 100) . "...";
        $stmt_notif = $conexion->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (?, ?)");
        $stmt_notif->bind_param("is", $solicitud['user_id'], $mensaje_notif);
        $stmt_notif->execute();
        
    } elseif ($action === 'rechazar') {
        // Verificar que la solicitud esté en estado apropiado
        if (!in_array($solicitud['status'], ['pendiente', 'en_revision'])) {
            throw new Exception('La solicitud no puede ser rechazada en su estado actual.');
        }
        
        // Actualizar estado
        $stmt_update = $conexion->prepare("UPDATE store_requests SET status = 'rechazada', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_update->bind_param("i", $solicitud_id);
        $stmt_update->execute();
        
        // Registrar evento
        $event_data = json_encode([
            'estado_anterior' => $solicitud['status'],
            'estado_nuevo' => 'rechazada',
            'comentario' => $comentario
        ]);
        $stmt_event = $conexion->prepare("INSERT INTO store_request_events (request_id, user_id, event_type, data_json) VALUES (?, ?, 'REJECTED', ?)");
        $stmt_event->bind_param("iis", $solicitud_id, $usuario_id, $event_data);
        $stmt_event->execute();
        
        // Enviar notificación al solicitante
        $mensaje_notif = "Su solicitud #{$solicitud_id} ha sido RECHAZADA por " . $_SESSION['usuario_nombre'] . ". Comentario: " . substr($comentario, 0, 100) . "...";
        $stmt_notif = $conexion->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (?, ?)");
        $stmt_notif->bind_param("is", $solicitud['user_id'], $mensaje_notif);
        $stmt_notif->execute();
        
    } elseif ($action === 'comentar') {
        // Solo agregar comentario, no cambiar estado
        $event_data = json_encode([
            'comentario' => $comentario,
            'visibilidad' => $visibilidad
        ]);
        $stmt_event = $conexion->prepare("INSERT INTO store_request_events (request_id, user_id, event_type, data_json) VALUES (?, ?, 'COMMENTED', ?)");
        $stmt_event->bind_param("iis", $solicitud_id, $usuario_id, $event_data);
        $stmt_event->execute();
        
        // Enviar notificación a la contraparte (solo para comentarios públicos)
        if ($visibilidad === 'publico') {
            if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor') {
                // Almacén comenta, notificar al solicitante
                $mensaje_notif = "Nuevo comentario en su solicitud #{$solicitud_id} por " . $_SESSION['usuario_nombre'] . ": " . substr($comentario, 0, 100) . "...";
                $stmt_notif = $conexion->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (?, ?)");
                $stmt_notif->bind_param("is", $solicitud['user_id'], $mensaje_notif);
                $stmt_notif->execute();
            } else {
                // Solicitante comenta, notificar a almacén y editores
                $stmt_almacen = $conexion->prepare("SELECT id FROM usuarios WHERE rol = 'almacen' OR rol = 'Editor'");
                $stmt_almacen->execute();
                $result_almacen = $stmt_almacen->get_result();
                
                while ($almacen_user = $result_almacen->fetch_assoc()) {
                    $mensaje_notif = "Nuevo comentario en solicitud #{$solicitud_id} por " . $_SESSION['usuario_nombre'] . ": " . substr($comentario, 0, 100) . "...";
                    $stmt_notif = $conexion->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (?, ?)");
                    $stmt_notif->bind_param("is", $almacen_user['id'], $mensaje_notif);
                    $stmt_notif->execute();
                }
            }
        } elseif ($visibilidad === 'interno_almacen') {
            // Para comentarios internos, solo notificar a otros usuarios con rol almacén/admin/Editor
            $stmt_internos = $conexion->prepare("SELECT id FROM usuarios WHERE (rol = 'almacen' OR rol = 'Editor' OR rol = 'admin' OR rol = 'dueno') AND id != ?");
            $stmt_internos->bind_param("i", $usuario_id);
            $stmt_internos->execute();
            $result_internos = $stmt_internos->get_result();
            
            while ($interno_user = $result_internos->fetch_assoc()) {
                $mensaje_notif = "Nuevo comentario interno en solicitud #{$solicitud_id} por " . $_SESSION['usuario_nombre'] . ": " . substr($comentario, 0, 100) . "...";
                $stmt_notif = $conexion->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (?, ?)");
                $stmt_notif->bind_param("is", $interno_user['id'], $mensaje_notif);
                $stmt_notif->execute();
            }
        }
    }
    
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    $mensaje = '';
    switch ($action) {
        case 'aprobar':
            $mensaje = 'Solicitud aprobada exitosamente.';
            break;
        case 'rechazar':
            $mensaje = 'Solicitud rechazada exitosamente.';
            break;
        case 'comentar':
            $mensaje = 'Comentario agregado exitosamente.';
            break;
    }
    
    header("Location: pedido_detalle.php?id=" . $solicitud_id . "&success=" . urlencode($mensaje));
    exit;
    
} catch (Exception $e) {
    $conexion->rollback();
    header("Location: pedido_detalle.php?id=" . $solicitud_id . "&error=" . urlencode($e->getMessage()));
    exit;
}
?>

<?php
session_start();
include '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// Marcar notificación como leída
if (isset($_POST['marcar_leido']) && isset($_POST['notificacion_id'])) {
    $stmt = $conexion->prepare("UPDATE notificaciones SET leido = TRUE WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $_POST['notificacion_id'], $_SESSION['usuario_id']);
    $stmt->execute();
    exit;
}

// Obtener notificaciones no leídas
$stmt = $conexion->prepare("
    SELECT id, mensaje, fecha_creacion 
    FROM notificaciones 
    WHERE usuario_id = ? AND leido = FALSE 
    ORDER BY fecha_creacion DESC
");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$notificaciones = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Notificaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h2 class="mb-4">Notificaciones</h2>
        
        <?php if (empty($notificaciones)): ?>
            <div class="alert alert-info">
                No tienes notificaciones nuevas.
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notificaciones as $notif): ?>
                    <div class="list-group-item list-group-item-action" id="notif-<?= $notif['id'] ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <p class="mb-1"><?= htmlspecialchars($notif['mensaje']) ?></p>
                            <small class="text-muted">
                                <?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?>
                            </small>
                        </div>
                        <button class="btn btn-sm btn-outline-primary mt-2" 
                                onclick="marcarLeido(<?= $notif['id'] ?>)">
                            Marcar como leído
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn btn-secondary mt-3">Volver al Dashboard</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function marcarLeido(id) {
        $.post('notificaciones.php', {
            marcar_leido: true,
            notificacion_id: id
        }, function() {
            $('#notif-' + id).fadeOut();
        });
    }
    </script>
</body>
</html> 
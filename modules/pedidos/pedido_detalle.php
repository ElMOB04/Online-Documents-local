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

// Manejar mensajes de error y éxito
$mensaje_error = '';
$mensaje_exito = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'sin_permisos':
            $mensaje_error = 'No tienes permisos para realizar esta acción.';
            break;
        case 'comentario_invalido':
            $mensaje_error = 'El comentario no es válido.';
            break;
        case 'comentario_requerido':
            $mensaje_error = 'El comentario es obligatorio para esta acción.';
            break;
        case 'no_archivo':
            $mensaje_error = 'No se encontró el archivo adjunto.';
            break;
        default:
            $mensaje_error = 'Ha ocurrido un error.';
    }
}

if (isset($_GET['success'])) {
    $mensaje_exito = $_GET['success'];
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

// Verificar permisos de acceso
$puede_ver = false;
if ($rol === 'admin' || $rol === 'dueno') {
    $puede_ver = true;
} elseif ($rol === 'almacen' || $rol === 'Editor') {
    $puede_ver = true;
} elseif ($solicitud['user_id'] == $usuario_id) {
    $puede_ver = true;
}

if (!$puede_ver) {
    header("Location: pedidos.php");
    exit;
}

// Obtener comentarios de la solicitud
$query_comentarios = "SELECT src.*, u.nombre as autor_nombre 
                      FROM store_request_comments src 
                      JOIN usuarios u ON src.user_id = u.id 
                      WHERE src.request_id = ? 
                      ORDER BY src.created_at ASC";
$stmt_comentarios = $conexion->prepare($query_comentarios);
$stmt_comentarios->bind_param("i", $solicitud_id);
$stmt_comentarios->execute();
$comentarios = $stmt_comentarios->get_result();

// Obtener eventos de auditoría
$query_eventos = "SELECT sre.*, u.nombre as usuario_nombre 
                  FROM store_request_events sre 
                  JOIN usuarios u ON sre.user_id = u.id 
                  WHERE sre.request_id = ? 
                  ORDER BY sre.created_at ASC";
$stmt_eventos = $conexion->prepare($query_eventos);
$stmt_eventos->bind_param("i", $solicitud_id);
$stmt_eventos->execute();
$eventos = $stmt_eventos->get_result();

// Función para obtener el color del estado
function getEstadoColor($estado) {
    switch ($estado) {
        case 'pendiente': return 'warning';
        case 'aprobada': return 'success';
        case 'rechazada': return 'danger';
        case 'en_revision': return 'info';
        case 'cerrada': return 'secondary';
        default: return 'secondary';
    }
}

// Función para obtener el texto del estado
function getEstadoTexto($estado) {
    switch ($estado) {
        case 'pendiente': return 'Pendiente';
        case 'aprobada': return 'Aprobada';
        case 'rechazada': return 'Rechazada';
        case 'en_revision': return 'En Revisión';
        case 'cerrada': return 'Cerrada';
        default: return ucfirst($estado);
    }
}

// Función para obtener el icono del evento
function getEventoIcono($tipo) {
    switch ($tipo) {
        case 'CREATED': return 'bi-plus-circle';
        case 'APPROVED': return 'bi-check-circle';
        case 'REJECTED': return 'bi-x-circle';
        case 'COMMENTED': return 'bi-chat-dots';
        case 'DOWNLOADED': return 'bi-download';
        case 'UPDATED': return 'bi-pencil';
        default: return 'bi-info-circle';
    }
}

// Función para obtener el color del evento
function getEventoColor($tipo) {
    switch ($tipo) {
        case 'CREATED': return 'primary';
        case 'APPROVED': return 'success';
        case 'REJECTED': return 'danger';
        case 'COMMENTED': return 'info';
        case 'DOWNLOADED': return 'secondary';
        case 'UPDATED': return 'warning';
        default: return 'secondary';
    }
}

// Función para obtener el texto del evento
function getEventoTexto($tipo) {
    switch ($tipo) {
        case 'CREATED': return 'Solicitud creada';
        case 'APPROVED': return 'Solicitud aprobada';
        case 'REJECTED': return 'Solicitud rechazada';
        case 'COMMENTED': return 'Comentario agregado';
        case 'DOWNLOADED': return 'PDF descargado';
        case 'UPDATED': return 'Solicitud actualizada';
        default: return ucfirst(strtolower($tipo));
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Solicitud #<?= $solicitud_id ?> - Pedidos Recibidos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background: #f6f8fa;
        }
        .container {
            max-width: 1000px;
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
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 120px;
        }
        .info-value {
            color: #333;
            flex: 1;
            margin-left: 20px;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-left: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 8px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #e9ecef;
        }
        .timeline-item.primary::before {
            border-color: #007bff;
        }
        .timeline-item.success::before {
            border-color: #28a745;
        }
        .timeline-item.danger::before {
            border-color: #dc3545;
        }
        .timeline-item.info::before {
            border-color: #17a2b8;
        }
        .timeline-item.warning::before {
            border-color: #ffc107;
        }
        .timeline-item.secondary::before {
            border-color: #6c757d;
        }
        .timeline-content {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #e9ecef;
        }
        .timeline-item.primary .timeline-content {
            border-left-color: #007bff;
        }
        .timeline-item.success .timeline-content {
            border-left-color: #28a745;
        }
        .timeline-item.danger .timeline-content {
            border-left-color: #dc3545;
        }
        .timeline-item.info .timeline-content {
            border-left-color: #17a2b8;
        }
        .timeline-item.warning .timeline-content {
            border-left-color: #ffc107;
        }
        .timeline-item.secondary .timeline-content {
            border-left-color: #6c757d;
        }
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .timeline-title {
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .timeline-time {
            font-size: 0.875rem;
            color: #666;
        }
        .timeline-body {
            color: #555;
            margin: 0;
        }
        .comment-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .pdf-preview {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f9f9f9;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <?php if (!empty($mensaje_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($mensaje_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($mensaje_exito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-header">
        <div class="header-left">
            <div class="header-logo">
                <img src="img/logo_indomet.png" alt="Logo INDOMET">
            </div>
            <div class="header-title">Solicitud #<?= $solicitud_id ?></div>
        </div>
        <div class="header-actions">
            <a href="pedidos.php" class="btn btn-minimal btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Pedidos
            </a>
        </div>
    </div>

    <!-- Información principal de la solicitud -->
    <div class="minimal-card">
        <div class="minimal-card-header">Información de la Solicitud</div>
        <div class="minimal-card-body">
            <div class="info-row">
                <div class="info-label">Número:</div>
                <div class="info-value"><strong>#<?= $solicitud['id'] ?></strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Solicitante:</div>
                <div class="info-value"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de creación:</div>
                <div class="info-value"><?= date('d/m/Y H:i', strtotime($solicitud['created_at'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Última actualización:</div>
                <div class="info-value"><?= date('d/m/Y H:i', strtotime($solicitud['updated_at'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value">
                    <span class="badge bg-<?= getEstadoColor($solicitud['status']) ?> fs-6">
                        <?= getEstadoTexto($solicitud['status']) ?>
                    </span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Asunto:</div>
                <div class="info-value"><?= htmlspecialchars($solicitud['title']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Descripción:</div>
                <div class="info-value"><?= nl2br(htmlspecialchars($solicitud['description'])) ?></div>
            </div>
            <?php if (!empty($solicitud['pdf_path'])): ?>
            <div class="info-row">
                <div class="info-label">Documento PDF:</div>
                <div class="info-value">
                    <div class="pdf-preview">
                        <i class="bi bi-file-pdf display-4 text-danger mb-2"></i>
                        <p class="mb-2">Documento adjunto disponible</p>
                        <a href="pedido_descargar_pdf.php?id=<?= $solicitud['id'] ?>" 
                           class="btn btn-minimal btn-danger">
                            <i class="bi bi-download"></i> Descargar PDF
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Acciones disponibles según el rol y estado -->
    <?php if (($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor') && in_array($solicitud['status'], ['pendiente', 'en_revision'])): ?>
    <div class="minimal-card">
        <div class="minimal-card-header">Acciones de Almacén</div>
        <div class="minimal-card-body">
            <div class="row">
                <div class="col-md-4">
                    <button class="btn btn-minimal btn-success w-100" data-bs-toggle="modal" data-bs-target="#aprobarModal">
                        <i class="bi bi-check-circle"></i> Aprobar Solicitud
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-minimal btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rechazarModal">
                        <i class="bi bi-x-circle"></i> Rechazar Solicitud
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-minimal btn-info w-100" data-bs-toggle="modal" data-bs-target="#comentarModal">
                        <i class="bi bi-chat-dots"></i> Agregar Comentario
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Timeline de eventos y comentarios -->
    <div class="minimal-card">
        <div class="minimal-card-header">Historial y Comentarios</div>
        <div class="minimal-card-body">
            <div class="timeline">
                <?php
                // Combinar eventos y comentarios en orden cronológico
                $timeline_items = [];
                
                // Agregar eventos
                while ($evento = $eventos->fetch_assoc()) {
                    $timeline_items[] = [
                        'tipo' => 'evento',
                        'fecha' => $evento['created_at'],
                        'usuario' => $evento['usuario_nombre'],
                        'evento' => $evento['event_type'],
                        'data' => $evento['data_json']
                    ];
                }
                
                // Agregar comentarios
                while ($comentario = $comentarios->fetch_assoc()) {
                    $timeline_items[] = [
                        'tipo' => 'comentario',
                        'fecha' => $comentario['created_at'],
                        'usuario' => $comentario['autor_nombre'],
                        'comentario' => $comentario['comment'],
                        'visibilidad' => $comentario['visibility']
                    ];
                }
                
                // Ordenar por fecha
                usort($timeline_items, function($a, $b) {
                    return strtotime($a['fecha']) - strtotime($b['fecha']);
                });
                
                // Mostrar items del timeline
                foreach ($timeline_items as $item):
                    if ($item['tipo'] === 'evento'):
                ?>
                    <div class="timeline-item <?= getEventoColor($item['evento']) ?>">
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">
                                    <i class="bi <?= getEventoIcono($item['evento']) ?>"></i>
                                    <?= getEventoTexto($item['evento']) ?>
                                </h6>
                                <small class="timeline-time">
                                    <?= date('d/m/Y H:i', strtotime($item['fecha'])) ?>
                                </small>
                            </div>
                            <p class="timeline-body">
                                Por: <strong><?= htmlspecialchars($item['usuario']) ?></strong>
                                <?php if (!empty($item['data'])): 
                                    $data = json_decode($item['data'], true);
                                    if (isset($data['titulo'])): ?>
                                        <br>Asunto: <?= htmlspecialchars($data['titulo']) ?>
                                    <?php endif;
                                endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: 
                    // Solo mostrar comentarios internos a roles autorizados
                    if ($item['visibilidad'] === 'interno_almacen' && 
                        !($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor')) {
                        continue;
                    }
                ?>
                    <div class="timeline-item <?= $item['visibilidad'] === 'interno_almacen' ? 'warning' : 'info' ?>">
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">
                                    <?php if ($item['visibilidad'] === 'interno_almacen'): ?>
                                        <i class="bi bi-shield-lock text-warning"></i>
                                        <span class="text-warning">Comentario Interno</span>
                                    <?php else: ?>
                                        <i class="bi bi-chat-dots"></i>
                                        Comentario
                                    <?php endif; ?>
                                </h6>
                                <small class="timeline-time">
                                    <?= date('d/m/Y H:i', strtotime($item['fecha'])) ?>
                                </small>
                            </div>
                            <p class="timeline-body">
                                <strong><?= htmlspecialchars($item['usuario']) ?>:</strong><br>
                                <?= nl2br(htmlspecialchars($item['comentario'])) ?>
                                <?php if ($item['visibilidad'] === 'interno_almacen'): ?>
                                    <br><small class="text-muted">
                                        <i class="bi bi-info-circle"></i> Este comentario solo es visible para almacén
                                    </small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif;
                endforeach; ?>
            </div>

            <!-- Formulario para agregar comentario (solo para usuarios involucrados) -->
            <?php if (($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor') || $solicitud['user_id'] == $usuario_id): ?>
            <div class="comment-form">
                <h6><i class="bi bi-chat-dots"></i> Agregar Comentario</h6>
                <form method="POST" action="pedido_acciones.php">
                    <input type="hidden" name="action" value="comentar">
                    <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
                    <div class="mb-3">
                        <textarea class="form-control" name="comentario" rows="3" 
                                  placeholder="Escriba su comentario aquí..." required></textarea>
                    </div>
                    
                    <!-- Opción de comentario interno solo para almacén/admin/Editor -->
                    <!-- DEBUG: Rol actual = <?= htmlspecialchars($rol) ?> -->
                    <?php if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor'): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="interno_almacen" id="interno_almacen" value="1">
                            <label class="form-check-label text-warning" for="interno_almacen">
                                <i class="bi bi-shield-lock"></i> <strong>Comentario Interno</strong>
                                <small class="d-block text-muted">Solo visible para almacén</small>
                            </label>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- DEBUG: El usuario con rol "<?= htmlspecialchars($rol) ?>" no puede ver comentarios internos -->
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-minimal btn-primary">
                        <i class="bi bi-send"></i> Enviar Comentario
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para aprobar solicitud -->
<div class="modal fade" id="aprobarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Aprobar Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="pedido_acciones.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="aprobar">
                    <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
                    <div class="mb-3">
                        <label for="comentario_aprobar" class="form-label">Comentario de aprobación <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="comentario" id="comentario_aprobar" rows="4" 
                                  placeholder="Escriba el motivo de la aprobación..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Aprobar Solicitud</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para rechazar solicitud -->
<div class="modal fade" id="rechazarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rechazar Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="pedido_acciones.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="rechazar">
                    <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
                    <div class="mb-3">
                        <label for="comentario_rechazar" class="form-label">Comentario de rechazo <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="comentario" id="comentario_rechazar" rows="4" 
                                  placeholder="Escriba el motivo del rechazo..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rechazar Solicitud</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

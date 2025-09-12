<?php
session_start();
include 'includes/conexion.php';

if (!isset($_SESSION['usuario_id']) || !isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$documento_id = $_GET['id'];

// Registrar lectura del documento
try {
    $stmt = $conexion->prepare("INSERT INTO lecturas_documentos (documento_id, usuario_id) VALUES (?, ?)");
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta de lectura: " . $conexion->error);
    }
    
    $stmt->bind_param("ii", $documento_id, $_SESSION['usuario_id']);
    if (!$stmt->execute()) {
        throw new Exception("Error al registrar la lectura: " . $stmt->error);
    }
} catch (Exception $e) {
    // Si hay un error, lo registramos pero continuamos con la visualización
    error_log("Error al registrar lectura: " . $e->getMessage());
}

// Obtener información del documento
$stmt = $conexion->prepare("
    SELECT d.*, u.nombre as nombre_usuario 
    FROM documentos d 
    JOIN usuarios u ON d.usuario_id = u.id 
    WHERE d.id = ?
");
$stmt->bind_param("i", $documento_id);
$stmt->execute();
$documento = $stmt->get_result()->fetch_assoc();

if (!$documento) {
    header("Location: dashboard.php");
    exit;
}

// Obtener lista de usuarios que han visto el documento
$stmt = $conexion->prepare("
    SELECT DISTINCT u.nombre, ld.fecha_lectura 
    FROM lecturas_documentos ld 
    JOIN usuarios u ON ld.usuario_id = u.id 
    WHERE ld.documento_id = ? 
    ORDER BY ld.fecha_lectura DESC
");
$stmt->bind_param("i", $documento_id);
$stmt->execute();
$lectores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener comentarios
$stmt = $conexion->prepare("
    SELECT c.*, u.nombre as nombre_usuario 
    FROM comentarios c 
    JOIN usuarios u ON c.usuario_id = u.id 
    WHERE c.documento_id = ? 
    ORDER BY c.fecha_creacion DESC
");
$stmt->bind_param("i", $documento_id);
$stmt->execute();
$comentarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Documento - <?= htmlspecialchars($documento['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .visto-por {
            position: fixed;
            right: 20px;
            top: 20px;
            width: 300px;
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><?= htmlspecialchars($documento['nombre']) ?></h4>
                        <small class="text-muted">
                            Subido por: <?= htmlspecialchars($documento['nombre_usuario']) ?> 
                            el <?= date('d/m/Y H:i', strtotime($documento['fecha_subida'])) ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php
                        $extension = strtolower(pathinfo($documento['ruta'], PATHINFO_EXTENSION));
                        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                            echo '<img src="' . htmlspecialchars($documento['ruta']) . '" class="img-fluid" alt="Imagen">';
                        } else {
                            echo '<iframe src="' . htmlspecialchars($documento['ruta']) . '" width="100%" height="600px"></iframe>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Sección de Comentarios -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Comentarios</h5>
                    </div>
                    <div class="card-body">
                        <form id="comentarioForm" class="mb-4">
                            <div class="mb-3">
                                <textarea class="form-control" id="comentario" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Enviar Comentario</button>
                        </form>

                        <div id="listaComentarios">
                            <?php foreach ($comentarios as $comentario): ?>
                                <div class="card mb-2" id="comentario-<?= $comentario['id'] ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                <?= htmlspecialchars($comentario['nombre_usuario']) ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($comentario['fecha_creacion'])) ?>
                                            </small>
                                        </div>
                                        <p class="card-text"><?= htmlspecialchars($comentario['comentario']) ?></p>
                                        <?php if ($comentario['usuario_id'] == $_SESSION['usuario_id']): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-editar" 
                                                        data-id="<?= $comentario['id'] ?>">
                                                    Editar
                                                </button>
                                                <button class="btn btn-outline-danger btn-eliminar" 
                                                        data-id="<?= $comentario['id'] ?>">
                                                    Eliminar
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Sección de Vistos -->
                <div class="card mb-4 visto-por">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-eye"></i> Visto por
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lectores)): ?>
                            <p class="text-muted">Nadie ha visto este documento aún.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($lectores as $lector): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                            <?= htmlspecialchars($lector['nombre']) ?>
                                        </span>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($lector['fecha_lectura'])) ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Enviar comentario
        $('#comentarioForm').on('submit', function(e) {
            e.preventDefault();
            $.post('comentarios_ajax.php', {
                accion: 'crear',
                documento_id: <?= $documento_id ?>,
                comentario: $('#comentario').val()
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error al enviar el comentario');
                }
            });
        });

        // Eliminar comentario
        $('.btn-eliminar').click(function() {
            if (confirm('¿Estás seguro de eliminar este comentario?')) {
                const id = $(this).data('id');
                $.post('comentarios_ajax.php', {
                    accion: 'eliminar',
                    comentario_id: id
                }, function(response) {
                    if (response.success) {
                        $('#comentario-' + id).fadeOut();
                    } else {
                        alert('Error al eliminar el comentario');
                    }
                });
            }
        });

        // Editar comentario
        $('.btn-editar').click(function() {
            const id = $(this).data('id');
            const comentario = $('#comentario-' + id + ' .card-text').text();
            const nuevoComentario = prompt('Editar comentario:', comentario);
            
            if (nuevoComentario !== null) {
                $.post('comentarios_ajax.php', {
                    accion: 'editar',
                    comentario_id: id,
                    comentario: nuevoComentario
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error al editar el comentario');
                    }
                });
            }
        });
    });
    </script>
</body>
</html>

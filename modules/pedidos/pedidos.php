<?php
session_start();
include '../../config/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'usuario';

// Parámetros de filtro
$estado_filtro = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$solicitante_filtro = $_GET['solicitante'] ?? '';

// Paginación
$limite = 20;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina - 1) * $limite;

// Determinar qué solicitudes mostrar según el rol
if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor') {
    // Almacén, Admin y Dueño ven todas las solicitudes
    $query = "SELECT sr.*, u.nombre as solicitante_nombre 
              FROM store_requests sr 
              JOIN usuarios u ON sr.user_id = u.id 
              WHERE 1=1";
    $params = [];
    $paramTypes = '';
} else {
    // Usuarios normales solo ven sus propias solicitudes
    $query = "SELECT sr.*, u.nombre as solicitante_nombre 
              FROM store_requests sr 
              JOIN usuarios u ON sr.user_id = u.id 
              WHERE sr.user_id = ?";
    $params = [$usuario_id];
    $paramTypes = 'i';
}

// Aplicar filtros
if (!empty($estado_filtro)) {
    $query .= " AND sr.status = ?";
    $params[] = $estado_filtro;
    $paramTypes .= 's';
}

if (!empty($fecha_desde)) {
    $query .= " AND DATE(sr.created_at) >= ?";
    $params[] = $fecha_desde;
    $paramTypes .= 's';
}

if (!empty($fecha_hasta)) {
    $query .= " AND DATE(sr.created_at) <= ?";
    $params[] = $fecha_hasta;
    $paramTypes .= 's';
}

if (!empty($solicitante_filtro) && ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor')) {
    $query .= " AND u.nombre LIKE ?";
    $params[] = '%' . $solicitante_filtro . '%';
    $paramTypes .= 's';
}

// Ordenar y paginar
$query .= " ORDER BY sr.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limite;
$params[] = $offset;
$paramTypes .= 'ii';

// Ejecutar consulta
$stmt = $conexion->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();

// Obtener total de registros para paginación
$query_total = str_replace("LIMIT ? OFFSET ?", "", $query);
$query_total = str_replace("ORDER BY sr.created_at DESC", "", $query_total);
$stmt_total = $conexion->prepare($query_total);
if (!empty($params) && count($params) > 2) {
    $params_total = array_slice($params, 0, -2);
    $paramTypes_total = substr($paramTypes, 0, -2);
    $stmt_total->bind_param($paramTypes_total, ...$params_total);
}
$stmt_total->execute();
$total = $stmt_total->get_result()->num_rows;
$totalPaginas = ceil($total / $limite);

// Obtener lista de solicitantes para filtro (solo para almacén/admin)
$solicitantes = [];
if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor') {
    $query_solicitantes = "SELECT DISTINCT u.nombre FROM usuarios u JOIN store_requests sr ON u.id = sr.user_id ORDER BY u.nombre";
    $result_solicitantes = $conexion->query($query_solicitantes);
    while ($row = $result_solicitantes->fetch_assoc()) {
        $solicitantes[] = $row['nombre'];
    }
}

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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>
        <?php if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno'): ?>
            Pedidos
        <?php else: ?>
            Realizar pedidos
        <?php endif; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background: #f6f8fa;
        }
        .container {
            max-width: 1200px;
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
        .table-minimal th, .table-minimal td {
            border: none;
            background: transparent;
        }
        .table-minimal th {
            color: #888;
            font-weight: 500;
            background: #f6f8fa;
        }
        .table-minimal tbody tr {
            background: #f9fafb;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }
        .btn-minimal {
            border-radius: 8px;
            font-size: 0.95rem;
            padding: 6px 16px;
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
        .nav-tabs .nav-link {
            border-radius: 8px 8px 0 0;
            border: none;
            color: #666;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background: #28a745;
            color: white;
            border: none;
        }
        .nav-tabs .nav-link:hover {
            border: none;
            background: #e9ecef;
        }
        .nav-tabs .nav-link.active:hover {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="dashboard-header">
        <div class="header-left">
            <div class="header-logo">
                <img src="img/logo_indomet.png" alt="Logo INDOMET">
            </div>
            <div class="header-title">
                <?php if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno'): ?>
                    Pedidos
                <?php else: ?>
                    Realizar pedidos
                <?php endif; ?>
            </div>
        </div>
        <div class="header-actions">
            <a href="pedido_nuevo.php" class="btn btn-minimal btn-success">
                <i class="bi bi-plus-circle"></i> 
                <?php if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno'): ?>
                    Pedidos
                <?php else: ?>
                    Realizar pedidos
                <?php endif; ?>
            </a>
            <a href="dashboard.php" class="btn btn-minimal btn-outline-secondary">Volver al Dashboard</a>
        </div>
    </div>

    <!-- Pestañas según el rol -->
    <ul class="nav nav-tabs mb-4" id="pedidosTabs" role="tablist">
        <?php if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor'): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="todas-tab" data-bs-toggle="tab" data-bs-target="#todas" type="button" role="tab">
                    <i class="bi bi-list-ul"></i> Todas las Solicitudes
                </button>
            </li>
        <?php endif; ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($rol === 'usuario') ? 'active' : '' ?>" id="mis-tab" data-bs-toggle="tab" data-bs-target="#mis" type="button" role="tab">
                <i class="bi bi-person"></i> Mis Solicitudes
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pedidosTabContent">
        <!-- Pestaña de todas las solicitudes (solo para almacén/admin/editor) -->
        <?php if ($rol === 'almacen' || $rol === 'admin' || $rol === 'dueno' || $rol === 'Editor'): ?>
        <div class="tab-pane fade show active" id="todas" role="tabpanel">
            <div class="minimal-card">
                <div class="minimal-card-header">Todas las Solicitudes</div>
                <div class="minimal-card-body">
                    <!-- Filtros -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <select name="estado" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="pendiente" <?= ($estado_filtro === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="aprobada" <?= ($estado_filtro === 'aprobada') ? 'selected' : '' ?>>Aprobada</option>
                                    <option value="rechazada" <?= ($estado_filtro === 'rechazada') ? 'selected' : '' ?>>Rechazada</option>
                                    <option value="en_revision" <?= ($estado_filtro === 'en_revision') ? 'selected' : '' ?>>En Revisión</option>
                                    <option value="cerrada" <?= ($estado_filtro === 'cerrada') ? 'selected' : '' ?>>Cerrada</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>" placeholder="Desde">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>" placeholder="Hasta">
                            </div>
                            <div class="col-md-3">
                                <select name="solicitante" class="form-select">
                                    <option value="">Todos los solicitantes</option>
                                    <?php foreach ($solicitantes as $solicitante): ?>
                                        <option value="<?= htmlspecialchars($solicitante) ?>" <?= ($solicitante_filtro === $solicitante) ? 'selected' : '' ?>><?= htmlspecialchars($solicitante) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-minimal btn-primary me-2">Filtrar</button>
                                <a href="pedidos.php" class="btn btn-minimal btn-outline-secondary">Limpiar</a>
                            </div>
                        </div>
                    </form>

                    <!-- Tabla de solicitudes -->
                    <div class="table-responsive">
                        <table class="table table-minimal align-middle">
                            <thead>
                                <tr>
                                    <th>N° Solicitud</th>
                                    <th>Fecha</th>
                                    <th>Solicitante</th>
                                    <th>Asunto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($solicitud = $resultado->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?= $solicitud['id'] ?></strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($solicitud['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></td>
                                        <td><?= htmlspecialchars($solicitud['title']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= getEstadoColor($solicitud['status']) ?>">
                                                <?= getEstadoTexto($solicitud['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="pedido_detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-minimal btn-info btn-sm">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($resultado->num_rows === 0): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <p class="text-muted mt-3">No se encontraron solicitudes que coincidan con los filtros.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Paginación -->
                    <?php if ($totalPaginas > 1): ?>
                        <nav aria-label="Paginación de solicitudes">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                                        <a class="page-link" href="?pagina=<?= $i ?>&estado=<?= $estado_filtro ?>&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&solicitante=<?= $solicitante_filtro ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pestaña de mis solicitudes -->
        <div class="tab-pane fade <?= ($rol === 'usuario') ? 'show active' : '' ?>" id="mis" role="tabpanel">
            <div class="minimal-card">
                <div class="minimal-card-header">Mis Solicitudes</div>
                <div class="minimal-card-body">
                    <!-- Filtros para mis solicitudes -->
                    <form method="GET" class="mb-4">
                        <input type="hidden" name="tab" value="mis">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <select name="estado" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="pendiente" <?= ($estado_filtro === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="aprobada" <?= ($estado_filtro === 'aprobada') ? 'selected' : '' ?>>Aprobada</option>
                                    <option value="rechazada" <?= ($estado_filtro === 'rechazada') ? 'selected' : '' ?>>Rechazada</option>
                                    <option value="en_revision" <?= ($estado_filtro === 'en_revision') ? 'selected' : '' ?>>En Revisión</option>
                                    <option value="cerrada" <?= ($estado_filtro === 'cerrada') ? 'selected' : '' ?>>Cerrada</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>" placeholder="Desde">
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>" placeholder="Hasta">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-minimal btn-primary me-2">Filtrar</button>
                                <a href="pedidos.php" class="btn btn-minimal btn-outline-secondary">Limpiar</a>
                            </div>
                        </div>
                    </form>

                    <!-- Tabla de mis solicitudes -->
                    <div class="table-responsive">
                        <table class="table table-minimal align-middle">
                            <thead>
                                <tr>
                                    <th>N° Solicitud</th>
                                    <th>Fecha</th>
                                    <th>Asunto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Re-ejecutar consulta para mis solicitudes si estamos en la pestaña mis
                                if ($rol === 'usuario' || (isset($_GET['tab']) && $_GET['tab'] === 'mis')) {
                                    $query_mis = "SELECT sr.*, u.nombre as solicitante_nombre 
                                                 FROM store_requests sr 
                                                 JOIN usuarios u ON sr.user_id = u.id 
                                                 WHERE sr.user_id = ?";
                                    $params_mis = [$usuario_id];
                                    $paramTypes_mis = 'i';
                                    
                                    if (!empty($estado_filtro)) {
                                        $query_mis .= " AND sr.status = ?";
                                        $params_mis[] = $estado_filtro;
                                        $paramTypes_mis .= 's';
                                    }
                                    
                                    if (!empty($fecha_desde)) {
                                        $query_mis .= " AND DATE(sr.created_at) >= ?";
                                        $params_mis[] = $fecha_desde;
                                        $paramTypes_mis .= 's';
                                    }
                                    
                                    if (!empty($fecha_hasta)) {
                                        $query_mis .= " AND DATE(sr.created_at) <= ?";
                                        $params_mis[] = $fecha_hasta;
                                        $paramTypes_mis .= 's';
                                    }
                                    
                                    $query_mis .= " ORDER BY sr.created_at DESC LIMIT ? OFFSET ?";
                                    $params_mis[] = $limite;
                                    $params_mis[] = $offset;
                                    $paramTypes_mis .= 'ii';
                                    
                                    $stmt_mis = $conexion->prepare($query_mis);
                                    $stmt_mis->bind_param($paramTypes_mis, ...$params_mis);
                                    $stmt_mis->execute();
                                    $resultado_mis = $stmt_mis->get_result();
                                    
                                    while ($solicitud = $resultado_mis->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong>#<?= $solicitud['id'] ?></strong></td>
                                            <td><?= date('d/m/Y H:i', strtotime($solicitud['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($solicitud['title']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= getEstadoColor($solicitud['status']) ?>">
                                                    <?= getEstadoTexto($solicitud['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="pedido_detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-minimal btn-info btn-sm me-1">
                                                    <i class="bi bi-eye"></i> Ver
                                                </a>
                                                <?php if ($solicitud['status'] === 'pendiente'): ?>
                                                    <a href="pedido_editar.php?id=<?= $solicitud['id'] ?>" class="btn btn-minimal btn-warning btn-sm">
                                                        <i class="bi bi-pencil"></i> Editar
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                } else {
                                    // Mostrar las mismas solicitudes de la consulta principal
                                    $resultado->data_seek(0);
                                    while ($solicitud = $resultado->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong>#<?= $solicitud['id'] ?></strong></td>
                                            <td><?= date('d/m/Y H:i', strtotime($solicitud['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($solicitud['title']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= getEstadoColor($solicitud['status']) ?>">
                                                    <?= getEstadoTexto($solicitud['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="pedido_detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-minimal btn-info btn-sm me-1">
                                                    <i class="bi bi-eye"></i> Ver
                                                </a>
                                                <?php if ($solicitud['status'] === 'pendiente' && $solicitud['user_id'] == $usuario_id): ?>
                                                    <a href="pedido_editar.php?id=<?= $solicitud['id'] ?>" class="btn btn-minimal btn-warning btn-sm">
                                                        <i class="bi bi-pencil"></i> Editar
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                } ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (($rol === 'usuario' || (isset($_GET['tab']) && $_GET['tab'] === 'mis')) && $resultado_mis->num_rows === 0): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <p class="text-muted mt-3">No tienes solicitudes registradas.</p>
                            <a href="pedido_nuevo.php" class="btn btn-minimal btn-success">
                                <i class="bi bi-plus-circle"></i> Crear Primera Solicitud
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

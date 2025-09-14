<?php
session_start();
include 'config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$rol = $_SESSION['rol'] ?? 'usuario';

// Obtener notificaciones no leídas
$stmt_notif = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leido = FALSE");
$stmt_notif->bind_param("i", $usuario_id);
$stmt_notif->execute();
$notificaciones_count = $stmt_notif->get_result()->fetch_assoc()['total'];

// Obtener parámetros de búsqueda y filtro
$searchTerm = $_GET['search'] ?? '';
$filterOwner = $_GET['owner'] ?? '';

// Consulta base para obtener documentos con propietario
$query = "SELECT d.*, u.nombre AS propietario 
          FROM documentos d 
          JOIN usuarios u ON d.usuario_id = u.id 
          WHERE 1"; // Condición base siempre verdadera

$params = [];
$paramTypes = '';

// Agregar condición de búsqueda si hay término
if (!empty($searchTerm)) {
    $query .= " AND (d.nombre LIKE ? OR u.nombre LIKE ?)";
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
    $paramTypes .= 'ss';
}

// Agregar condición de filtro por propietario si está seleccionado
if (!empty($filterOwner)) {
    $query .= " AND u.nombre = ?";
    $params[] = $filterOwner;
    $paramTypes .= 's';
}

// Ordenar resultados
$query .= " ORDER BY d.fecha_subida DESC";

// Preparar y ejecutar la consulta
$stmt = $conexion->prepare($query);

if ($stmt === false) {
    die("Error al preparar la consulta: " . $conexion->error);
}

// Bind parameters if any
if (!empty($params)) {
    // Build the type string dynamically
    $param_types = '';
    foreach($params as $param) {
        if(is_int($param)) {
            $param_types .= 'i';
        } elseif (is_double($param)) {
            $param_types .= 'd';
        } else {
            $param_types .= 's';
        }
    }

    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$documentos = $stmt->get_result();

// Obtener lista de propietarios para el filtro (opcional, para un dropdown)
$queryOwners = "SELECT DISTINCT u.nombre FROM usuarios u JOIN documentos d ON u.id = d.usuario_id ORDER BY u.nombre";
$resultOwners = $conexion->query($queryOwners);
$owners = [];
while ($row = $resultOwners->fetch_assoc()) {
    $owners[] = $row['nombre'];
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f6f8fa;
        }
        .container {
            max-width: 950px;
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
        .table-minimal tr {
            border-radius: 12px;
            margin-bottom: 8px;
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
            padding: 18px 28px; /* Añadir padding similar a card header */
            border-left: 8px solid #007bff; /* Borde izquierdo como en flag comments */
        }
        .header-left {
            display: flex;
            align-items: center; /* Alinear logo y título verticalmente */
            gap: 15px; /* Espacio entre logo y título */
        }
        .header-title {
            font-size: 1.8rem; /* Ajustar tamaño de fuente */
            font-weight: 700;
            color: #222;
            letter-spacing: -1px;
            margin-bottom: 0; /* Eliminar margen inferior */
        }
         .header-logo img {
             height: 50px; /* Ajustar tamaño del logo si es necesario */
         }
         .header-actions {
             display: flex;
             align-items: center;
             gap: 10px;
         }

        .dashboard-logo {
           display: none; /* Ocultar el viejo div del logo si no se eliminó */
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="dashboard-header">
        <div class="header-left">
            <!-- Logo -->
            <div class="header-logo">
                 <img src="img/logo_indomet.png" alt="Logo INDOMET">
            </div>
            <!-- Título de bienvenida -->
            <div class="header-title">Bienvenido, <?= htmlspecialchars($nombre) ?></div>
        </div>
        <!-- Botones de acción -->
        <div class="header-actions">
            <?php if ($rol === 'Editor'): ?>
                <a href="pedidos.php" class="btn btn-minimal btn-outline-primary me-2">Pedidos Recibidos</a>
            <?php elseif ($rol === 'usuario'): ?>
                <a href="pedidos.php" class="btn btn-minimal btn-outline-primary me-2">Realizar Pedidos</a>
            <?php endif; ?>
            <a href="notificaciones.php" class="btn btn-minimal btn-outline-info me-2 position-relative">
                <i class="bi bi-bell"></i> Notificaciones
                <?php if ($notificaciones_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $notificaciones_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php if ($rol === 'admin' || $rol === 'dueno'): ?>
                <a href="usuarios.php" class="btn btn-minimal btn-outline-secondary me-2">Gestionar Usuarios</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-minimal btn-outline-danger">Salir</a>
        </div>
    </div>
    <hr>

    <div class="minimal-card">
        <div class="minimal-card-header">Subir Documento</div>
        <div class="minimal-card-body">
            <form action="subir_documento.php" method="POST" enctype="multipart/form-data">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <input type="file" name="documento" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-minimal btn-success w-100">Subir</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="minimal-card mb-4">
        <div class="minimal-card-header">Documentos Subidos</div>
        <div class="minimal-card-body">
            <!-- Formulario de búsqueda y filtro -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por nombre o propietario" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="owner" class="form-select">
                            <option value="">Todos los propietarios</option>
                            <?php foreach ($owners as $ownerName): ?>
                                <option value="<?= htmlspecialchars($ownerName) ?>" <?= ($filterOwner === $ownerName) ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(mb_strtolower($ownerName))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-minimal btn-primary w-100">Buscar</button>
                    </div>
                </div>
            </form>

            <table class="table table-minimal align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Propietario</th>
                        <th>Fecha Subida</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($doc = $documentos->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['nombre']) ?></td>
                        <td><?= htmlspecialchars(ucwords(mb_strtolower($doc['propietario']))) ?></td>
                        <td><?= $doc['fecha_subida'] ?></td>
                        <td>
                            <div class="d-flex">
                                <a href="visualizar.php?ruta=<?= urlencode($doc['ruta']) ?>" target="_blank" class="btn btn-minimal btn-info me-1">Ver</a>
                                <a href="comentarios.php?documento_id=<?= $doc['id'] ?>" class="btn btn-minimal btn-secondary me-1">Confirmar Recibido</a>
                                <?php if ($rol === 'admin' || $rol === 'dueno' || $doc['usuario_id'] == $usuario_id): ?>
                                    <a href="eliminar_documento.php?id=<?= $doc['id'] ?>" class="btn btn-minimal btn-danger" onclick="return confirm('¿Eliminar este documento?')">Eliminar</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($documentos->num_rows === 0 && (!empty($searchTerm) || !empty($filterOwner))): ?>
                <p class="text-center mt-4">No se encontraron documentos que coincidan con los criterios de búsqueda o filtro.</p>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>

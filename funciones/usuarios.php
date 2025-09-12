<?php
session_start();
include 'includes/conexion.php';

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// Variables de filtro
$rolFiltro = isset($_GET['rol']) ? $_GET['rol'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

// Paginación
$limite = 10;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina - 1) * $limite;

// Eliminar usuario
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);

    if ($_SESSION['rol'] === 'dueno') {
        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $mensaje = "Usuario eliminado correctamente.";
    } else {
        $mensaje = "No tienes permiso para eliminar usuarios.";
    }
}

// Consulta con filtros
$query = "SELECT * FROM usuarios WHERE 1=1 ";
$params = [];

if (!empty($busqueda)) {
    $query .= " AND (usuario LIKE ? OR nombre LIKE ?)";
    $params[] = '%' . $busqueda . '%';
    $params[] = '%' . $busqueda . '%';
}

if ($rolFiltro) {
    $query .= " AND rol = ? ";
    $params[] = $rolFiltro;
}

$query .= " LIMIT ? OFFSET ?";
$params[] = $limite;
$params[] = $offset;

// Ejecutar consulta
$stmt = $conexion->prepare($query);
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params) - 2) . 'ii', ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();

// Obtener total de registros para la paginación
$query_total = "SELECT COUNT(*) FROM usuarios WHERE 1=1";
if (!empty($busqueda)) {
    $query_total .= " AND (usuario LIKE '%$busqueda%' OR nombre LIKE '%$busqueda%')";
}
if ($rolFiltro) {
    $query_total .= " AND rol = '$rolFiltro'";
}
$total = $conexion->query($query_total)->fetch_row()[0];
$totalPaginas = ceil($total / $limite);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <style>
        body { font-family: Arial; padding: 40px; background-color: #f4f4f4; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #007bff; color: white; }
        a.btn { padding: 6px 12px; text-decoration: none; color: white; border-radius: 4px; }
        .editar { background-color: #28a745; }
        .eliminar { background-color: #dc3545; }
        .mensaje { margin-bottom: 10px; color: green; }
        .top-nav { margin-bottom: 20px; }
        .paginacion { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>

<div class="top-nav">
    <a href="dashboard.php">← Volver al Dashboard</a> |
    <a href="logout.php">Cerrar sesión</a>
</div>

<h2>Usuarios Registrados</h2>

<!-- Filtros de búsqueda -->
<form method="GET">
    <input type="text" name="busqueda" value="<?= $busqueda ?>" placeholder="Buscar por nombre o usuario">
    <select name="rol">
        <option value="">Filtrar por rol</option>
        <option value="dueno" <?= $rolFiltro == 'dueno' ? 'selected' : '' ?>>Dueño</option>
        <option value="usuario" <?= $rolFiltro == 'usuario' ? 'selected' : '' ?>>Usuario</option>
    </select>
    <button type="submit">Buscar</button>
</form>

<?php if (isset($mensaje)) echo "<p class='mensaje'>$mensaje</p>"; ?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?= $fila['id'] ?></td>
                <td><?= $fila['nombre'] ?></td>
                <td><?= $fila['usuario'] ?></td>
                <td><?= $fila['rol'] ?></td>
                <td>
                    <a class='btn editar' href='editar_usuario.php?id=<?= $fila['id'] ?>'>Editar</a>
                    <?php if ($_SESSION['rol'] === 'dueno' && $_SESSION['usuario_id'] != $fila['id']) { ?>
                        <a class='btn eliminar' href='usuarios.php?eliminar=<?= $fila['id'] ?>' onclick="return confirm('¿Estás seguro de eliminar este usuario?')">Eliminar</a>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<!-- Paginación -->
<div class="paginacion">
    <?php for ($i = 1; $i <= $totalPaginas; $i++) { ?>
        <a href="usuarios.php?pagina=<?= $i ?>&busqueda=<?= $busqueda ?>&rol=<?= $rolFiltro ?>"><?= $i ?></a>
    <?php } ?>
</div>

</body>
</html>

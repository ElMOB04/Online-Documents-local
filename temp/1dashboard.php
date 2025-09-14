<?php
session_start();
include 'includes/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// Consultar los documentos subidos
$query = "SELECT * FROM documentos WHERE usuario_id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Documentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2>Bienvenido, <?= $_SESSION['usuario_nombre'] ?></h2>
    <a href="subir_documento.php" class="btn btn-primary mb-3">Subir Documento</a>
    <h3>Mis Documentos</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Fecha de Subida</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($documento = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($documento['nombre']) ?></td>
                    <td><?= $documento['fecha_subida'] ?></td>
                    <td>
                        <!-- Enlace para visualizar el documento -->
                        <a href="ver_documento.php?id=<?= $documento['id'] ?>" class="btn btn-info">Ver</a>
                        <!-- Enlace para eliminar el documento -->
                        <a href="eliminar_documento.php?id=<?= $documento['id'] ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar este documento?')">Eliminar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>

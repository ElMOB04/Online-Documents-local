<?php
session_start();
include 'includes/conexion.php';

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$resultado = $conexion->query("SELECT * FROM documentos");

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Documentos</title>
    <style>
        body { font-family: Arial; padding: 40px; background-color: #f4f4f4; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #007bff; color: white; }
    </style>
</head>
<body>

<h2>Documentos Subidos</h2>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Ver</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($doc = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?= $doc['id'] ?></td>
                <td><?= $doc['nombre'] ?></td>
                <td><a href="ver_documento.php?id=<?= $doc['id'] ?>">Ver Documento</a></td>
                <td>
                    <?php if ($_SESSION['rol'] === 'dueno') { ?>
                        <a href="eliminar_documento.php?id=<?= $doc['id'] ?>" onclick="return confirm('¿Estás seguro de eliminar este documento?')">Eliminar</a>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

</body>
</html>

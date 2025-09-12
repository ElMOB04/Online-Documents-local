<?php
session_start();
include 'includes/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// Obtener el ID del documento desde la URL
$id_documento = $_GET['id'];

// Consultar el documento desde la base de datos
$query = "SELECT * FROM documentos WHERE id = ? AND usuario_id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("ii", $id_documento, $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Si no se encuentra el documento, redirigir
    header("Location: dashboard.php");
    exit;
}

$documento = $result->fetch_assoc();
$ruta_documento = $documento['ruta'];
$extension = pathinfo($ruta_documento, PATHINFO_EXTENSION);

// Mostrar el documento según su tipo
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Documento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2>Visualizando: <?= htmlspecialchars($documento['nombre']) ?></h2>
    <div class="text-center">
        <?php if ($extension === 'pdf'): ?>
            <!-- Para documentos PDF: Mostrar en un iframe -->
            <iframe src="<?= $ruta_documento ?>" width="100%" height="600px"></iframe>
        <?php elseif ($extension === 'docx' || $extension === 'doc'): ?>
            <!-- Para documentos Word: Permitir la descarga -->
            <p><a href="<?= $ruta_documento ?>" class="btn btn-success" download>Descargar Documento Word</a></p>
        <?php else: ?>
            <p>Formato no compatible para visualizar en línea.</p>
        <?php endif; ?>
    </div>
    <a href="dashboard.php" class="btn btn-secondary mt-3">Volver al Dashboard</a>
</div>
</body>
</html>

<?php
session_start();
include '../../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$mensaje = "";

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === 0) {
        $archivo = $_FILES['documento'];
        $nombre_original = basename($archivo['name']);
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $permitidos = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

        if (in_array($extension, $permitidos)) {
            $nuevo_nombre = time() . '_' . $nombre_original;
            $ruta_destino = 'documentos/' . $nuevo_nombre;

            if (!is_dir('documentos')) {
                mkdir('documentos', 0777, true);
            }

            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                $nombre = pathinfo($nombre_original, PATHINFO_FILENAME);
                
                // Iniciar transacción
                $conexion->begin_transaction();
                
                try {
                    // Insertar documento
                    $stmt = $conexion->prepare("INSERT INTO documentos (nombre, ruta, usuario_id, fecha_subida) VALUES (?, ?, ?, NOW())");
                    if ($stmt === false) {
                        throw new Exception("Error al preparar la consulta de inserción: " . $conexion->error);
                    }
                    
                    $stmt->bind_param("ssi", $nombre, $ruta_destino, $_SESSION['usuario_id']);
                    if (!$stmt->execute()) {
                        throw new Exception("Error al insertar el documento: " . $stmt->error);
                    }
                    
                    $conexion->commit();
                    header("Location: dashboard.php");
                    exit;
                } catch (Exception $e) {
                    $conexion->rollback();
                    $mensaje = "Error al procesar el documento: " . $e->getMessage();
                }
            } else {
                $mensaje = "No se pudo guardar el archivo.";
            }
        } else {
            $mensaje = "Formato no permitido. Solo PDF, DOC, DOCX, JPG, JPEG, PNG.";
        }
    } else {
        $mensaje = "Debes seleccionar un archivo válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Documento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">📤 Subir Documento</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="documento" class="form-label">Seleccionar archivo</label>
                            <input type="file" class="form-control" name="documento" id="documento" required>
                        </div>
                        <button type="submit" class="btn btn-success">Subir</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

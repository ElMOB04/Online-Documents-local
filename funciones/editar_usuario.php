<?php
session_start();
include 'includes/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: usuarios.php");
    exit;
}

$id = intval($_GET['id']);

// Obtener el rol del usuario logueado
$rol_logueado = $_SESSION['rol'] ?? 'usuario';

// Mensajes para mostrar al usuario
$mensaje = '';
$mensaje_tipo = ''; // Puede ser 'success' o 'danger'

// Procesar actualización de datos del usuario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_datos'])) {

    // Permitir a admins, gestores_usuarios o al propio usuario editar sus datos básicos (nombre y usuario)
    if ($rol_logueado === 'admin' || $rol_logueado === 'gestor_usuarios' || $_SESSION['usuario_id'] == $id) {
        $nombre_post = $_POST['nombre'];
        $usuario_post = $_POST['usuario'];
        $rol_post = $_POST['rol'];

        // Si el usuario logueado NO es admin, asegurarse de que no intente cambiar el rol
        if ($rol_logueado !== 'admin') {
            // Mantener el rol original del usuario que está siendo editado
            $rol_a_guardar = $usuario_a_editar['rol']; // Necesitamos obtener el rol original primero
            // Volver a obtener los datos del usuario para tener el rol original
             $stmt_original_rol = $conexion->prepare("SELECT rol FROM usuarios WHERE id = ?");
             $stmt_original_rol->bind_param("i", $id);
             $stmt_original_rol->execute();
             $stmt_original_rol->bind_result($rol_a_guardar);
             $stmt_original_rol->fetch();
             $stmt_original_rol->close();

             // Actualizar solo nombre y usuario
             $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, usuario = ? WHERE id = ?");
             $stmt->bind_param("ssi", $nombre_post, $usuario_post, $id);

        } else {
            // Si el usuario logueado ES admin, permitir cambiar nombre, usuario y rol
            $rol_a_guardar = $rol_post; // El admin puede establecer el rol enviado en el POST
            $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, usuario = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nombre_post, $usuario_post, $rol_a_guardar, $id);
        }

        // Ejecutar la consulta de actualización (nombre, usuario, y rol si es admin)
        if ($stmt->execute()) {
             // Éxito
            $mensaje = "Datos del usuario actualizados correctamente.";
            $mensaje_tipo = 'success';

            // Si el usuario actualizó sus propios datos y cambió su nombre, actualizar la sesión
            if ($_SESSION['usuario_id'] == $id && $_SESSION['usuario_nombre'] !== $nombre_post) {
                $_SESSION['usuario_nombre'] = $nombre_post;
            }
             // Si el usuario actualizó sus propios datos y cambió su rol, actualizar la sesión
            if ($_SESSION['usuario_id'] == $id && $_SESSION['rol'] !== $rol_a_guardar) {
                $_SESSION['rol'] = $rol_a_guardar;
            }

        } else {
            $mensaje = "Error al actualizar los datos del usuario: " . $stmt->error;
            $mensaje_tipo = 'danger';
        }
        $stmt->close();
    } else {
         $mensaje = "Error: No tienes permiso para editar este usuario.";
         $mensaje_tipo = 'danger';
    }
}

// Procesar restablecimiento de contraseña
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {

    // Permitir a administradores, gestores de usuarios o dueños restablecer contraseñas
    if ($rol_logueado === 'admin' || $rol_logueado === 'gestor_usuarios' || $rol_logueado === 'dueno' || $_SESSION['usuario_id'] == $id) {
        $nueva_contrasena = $_POST['nueva_contrasena'];
        $confirmar_nueva_contrasena = $_POST['confirmar_nueva_contrasena'];

        if (empty($nueva_contrasena) || empty($confirmar_nueva_contrasena)) {
            $mensaje = "Error: La nueva contraseña y su confirmación no pueden estar vacías.";
            $mensaje_tipo = 'danger';
        } elseif ($nueva_contrasena !== $confirmar_nueva_contrasena) {
            $mensaje = "Error: Las contraseñas no coinciden.";
            $mensaje_tipo = 'danger';
        } else {
            // Hashear la nueva contraseña
            $hash_nueva_contrasena = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

            // Actualizar la contraseña en la base de datos
            $stmt = $conexion->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
            $stmt->bind_param("si", $hash_nueva_contrasena, $id);

            if ($stmt->execute()) {
                $mensaje = "Contraseña restablecida correctamente.";
                $mensaje_tipo = 'success';
            } else {
                $mensaje = "Error al restablecer la contraseña: " . $stmt->error;
                $mensaje_tipo = 'danger';
            }
            $stmt->close();
        }
    } else {
         $mensaje = "Error: No tienes permiso para restablecer la contraseña.";
         $mensaje_tipo = 'danger';
    }
}

// Obtener datos del usuario para mostrar en el formulario (después de procesar POST por si se actualizaron)
$stmt = $conexion->prepare("SELECT id, nombre, usuario, rol FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$usuario_a_editar = $res->fetch_assoc();

// Si el usuario no existe, redirigir
if (!$usuario_a_editar) {
    header("Location: usuarios.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial; padding: 40px; background: #f4f4f4; }
        .container-sm { max-width: 500px; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .form-container h2 { margin-bottom: 20px; }
         .alert { margin-top: 20px; }
    </style>
</head>
<body class="bg-light">
<div class="container container-sm mt-5">
    <a href="usuarios.php" class="btn btn-secondary mb-3">← Volver a Usuarios</a>

    <div class="form-container">
        <h2>Editar Usuario: <?= htmlspecialchars($usuario_a_editar['usuario']) ?></h2>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $mensaje_tipo ?>" role="alert">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de actualización de datos básicos -->
        <form method="POST">
            <input type="hidden" name="actualizar_datos" value="1"> <!-- Indicador para procesar este formulario -->
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre:</label>
                <input type="text" class="form-control" name="nombre" id="nombre" value="<?= htmlspecialchars($usuario_a_editar['nombre']) ?>" required>
            </div>
             <div class="mb-3">
                <label for="usuario_input" class="form-label">Usuario (Login):</label>
                <input type="text" class="form-control" name="usuario" id="usuario_input" value="<?= htmlspecialchars($usuario_a_editar['usuario']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol:</label>
                <select name="rol" id="rol" class="form-select" <?= ($rol_logueado !== 'admin') ? 'disabled' : '' ?>> <!-- Solo admin puede cambiar rol -->
                    <option value="usuario" <?= ($usuario_a_editar['rol'] === 'usuario') ? 'selected' : '' ?>>Usuario</option>
                    <option value="admin" <?= ($usuario_a_editar['rol'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                    <option value="dueno" <?= ($usuario_a_editar['rol'] === 'dueno') ? 'selected' : '' ?> <?= ($rol_logueado !== 'admin') ? 'disabled' : '' ?>>Dueño</option>
                    <?php if ($rol_logueado === 'admin' || $rol_logueado === 'gestor_usuarios'): ?>
                        <option value="gestor_usuarios" <?= ($usuario_a_editar['rol'] === 'gestor_usuarios') ? 'selected' : '' ?> <?= ($rol_logueado !== 'admin') ? 'disabled' : '' ?>>Gestor de Usuarios</option>
                    <?php endif; ?>
                </select>
                 <?php if ($rol_logueado !== 'admin'): ?>
                    <small class="form-text text-muted">Solo los administradores pueden cambiar el rol.</small>
                 <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
    </div>

    <!-- Formulario de restablecimiento de contraseña -->
    <?php if ($rol_logueado === 'admin' || $rol_logueado === 'gestor_usuarios' || $rol_logueado === 'dueno' || $_SESSION['usuario_id'] == $id): ?>
        <div class="form-container">
            <h2>Cambiar Contraseña</h2>
            <form method="POST">
                <input type="hidden" name="reset_password" value="1">
                <div class="mb-3">
                    <label for="nueva_contrasena" class="form-label">Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="nueva_contrasena" id="nueva_contrasena" required>
                </div>
                <div class="mb-3">
                    <label for="confirmar_nueva_contrasena" class="form-label">Confirmar Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="confirmar_nueva_contrasena" id="confirmar_nueva_contrasena" required>
                </div>
                <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
            </form>
        </div>
    <?php endif; ?>

</div>
</body>
</html>

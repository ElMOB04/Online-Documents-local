<?php
session_start();
include 'includes/conexion.php';

$mensaje = "";

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    // Validar login
    $stmt = $conexion->prepare("SELECT id, nombre, contrasena, rol FROM usuarios WHERE nombre = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $nombre, $hash_contrasena, $rol);
        $stmt->fetch();

        if (password_verify($contrasena, $hash_contrasena)) {
            $_SESSION['usuario_id'] = $id;
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['rol'] = $rol;
            // Redirigir según el rol
            if ($rol === 'admin') {
                header("Location: dashboard.php");
            } elseif ($rol === 'gestor_usuarios') {
                 header("Location: usuarios.php"); // Redirigir a la gestión de usuarios
            } else {
                // Rol por defecto o usuario normal
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $mensaje = "Credenciales inválidas.";
        }
    } else {
        $mensaje = "Credenciales inválidas.";
    }
    $stmt->close();
}

// Procesar registro de nuevo usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar'])) {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];

    if ($contrasena === $confirmar_contrasena) {
        $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, contrasena) VALUES (?, ?)");
        $stmt->bind_param("ss", $usuario, $hash_contrasena);

        if ($stmt->execute()) {
            // Redirigir a login después de registro exitoso
            header("Location: index.php?login=true");
            exit;
        } else {
            $mensaje = "Error al registrar el usuario.";
        }
    } else {
        $mensaje = "Las contraseñas no coinciden.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login y Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0"><?= isset($_GET['login']) ? 'Iniciar sesión' : 'Registrarse' ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
                    <?php endif; ?>

                    <?php if (isset($_GET['login'])): ?>
                        <!-- Formulario de Login -->
                        <form method="POST">
                            <div class="mb-3">
                                <label for="usuario" class="form-label">Usuario</label>
                                <input type="text" class="form-control" name="usuario" id="usuario" required>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" name="contrasena" id="contrasena" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-success">Iniciar sesión</button>
                        </form>
                    <?php else: ?>
                        <!-- Formulario de Registro -->
                        <form method="POST">
                            <div class="mb-3">
                                <label for="usuario" class="form-label">Usuario</label>
                                <input type="text" class="form-control" name="usuario" id="usuario" required>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" name="contrasena" id="contrasena" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña</label>
                                <input type="password" class="form-control" name="confirmar_contrasena" id="confirmar_contrasena" required>
                            </div>
                            <button type="submit" name="registrar" class="btn btn-primary">Registrarse</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!isset($_GET['login'])): ?>
                        <a href="index.php?login=true" class="btn btn-link mt-3">¿Ya tienes cuenta? Inicia sesión</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center mt-3 mb-2">
                <img src="img/logo_indomet.png" alt="Logo INDOMET" style="height:60px;">
            </div>
        </div>
    </div>
</div>
</body>
</html>


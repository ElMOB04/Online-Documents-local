<?php
session_start();
include 'includes/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['nombre'] ?? 'Invitado';
$rol = $_SESSION['rol'] ?? 'usuario';

$documento_id = isset($_GET['documento_id']) ? intval($_GET['documento_id']) : 0;

// Guardar nuevo comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['comentario']) && !empty(trim($_POST['comentario']))) {
        $comentario = trim($_POST['comentario']);

        $stmt = $conexion->prepare("INSERT INTO comentarios (comentario, documento_id, usuario_id) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sii", $comentario, $documento_id, $usuario_id);
            $stmt->execute();
            header("Location: comentarios.php?documento_id=" . $documento_id);
            exit();
        } else {
            die("Error en la consulta SQL: " . $conexion->error);
        }
    }
}

// Obtener comentarios del documento
$query = "SELECT c.*, u.nombre AS autor 
          FROM comentarios c 
          JOIN usuarios u ON c.usuario_id = u.id 
          WHERE c.documento_id = ? 
          ORDER BY c.fecha_creacion DESC";

$stmt = $conexion->prepare($query);
if (!$stmt) {
    die("Error al preparar la consulta de comentarios: " . $conexion->error);
}
$stmt->bind_param("i", $documento_id);
$stmt->execute();
$result = $stmt->get_result();

$comentarios_por_usuario = [];
while ($row = $result->fetch_assoc()) {
    $comentarios_por_usuario[$row['autor']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comentarios - Documento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .kanban-board {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .kanban-column {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            width: 320px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .kanban-column h5 {
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }
        .flag-comment {
            display: flex;
            align-items: flex-start;
            background: #fff;
            border-radius: 8px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-left: 8px solid var(--flag-color, #007bff);
            padding: 12px 16px;
            position: relative;
        }
        .flag-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--flag-color, #007bff);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: 14px;
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .flag-body {
            flex: 1;
        }
        .flag-author {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }
        .flag-date {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 6px;
        }
        .flag-text {
            font-size: 1.05rem;
            color: #222;
            margin-bottom: 0;
        }
        .flag-actions {
            margin-top: 6px;
        }
    </style>
    <script>
    // Genera un color pastel basado en el nombre del usuario
    function stringToColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const h = hash % 360;
        return `hsl(${h}, 70%, 70%)`;
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-flag-author]').forEach(function(el) {
            const name = el.getAttribute('data-flag-author');
            el.style.setProperty('--flag-color', stringToColor(name));
        });

        // Manejo de edición de comentarios
        document.querySelectorAll('.btn-editar-comentario').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const commentId = this.dataset.comentarioId;
                const commentElement = this.closest('.flag-comment'); // Obtener el div del comentario
                const commentTextDiv = commentElement.querySelector('.flag-text');
                const originalText = commentTextDiv.innerText;
                
                // Ocultar acciones y mostrar área de edición
                commentElement.querySelector('.flag-actions').style.display = 'none';

                // Crear input para edición
                const input = document.createElement('textarea');
                input.className = 'form-control mb-2';
                input.value = originalText;
                commentTextDiv.innerHTML = ''; // Limpiar el texto actual
                commentTextDiv.appendChild(input);
                
                // Contenedor para botones de guardar/cancelar
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'mt-2';

                // Botón guardar
                const saveBtn = document.createElement('button');
                saveBtn.className = 'btn btn-success btn-sm me-2';
                saveBtn.innerText = 'Guardar';
                
                // Botón cancelar
                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'btn btn-secondary btn-sm';
                cancelBtn.innerText = 'Cancelar';

                buttonContainer.appendChild(saveBtn);
                buttonContainer.appendChild(cancelBtn);
                commentTextDiv.appendChild(buttonContainer); // Añadir botones al div de texto

                // Guardar cambios
                saveBtn.onclick = function() {
                    const nuevoTexto = input.value;
                    fetch('editar_comentario.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${commentId}&texto=${encodeURIComponent(nuevoTexto)}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Actualizar el texto y mostrar acciones de nuevo
                            commentTextDiv.innerHTML = nl2br(htmlspecialchars(data.texto_html)); // Usar nl2br y htmlspecialchars si la respuesta ya es texto plano
                             // Si la respuesta ya viene formateada (con <br> etc.), solo usa data.texto_html
                            // commentTextDiv.innerHTML = data.texto_html; 
                            commentElement.querySelector('.flag-actions').style.display = 'block';
                        } else {
                            alert('Error al editar el comentario: ' + data.error);
                            // Revertir si hay error
                            commentTextDiv.innerHTML = originalText;
                             commentElement.querySelector('.flag-actions').style.display = 'block';
                        }
                    })
                     .catch(error => {
                        console.error('Error en la solicitud Fetch:', error);
                        alert('Error de comunicación con el servidor.');
                        // Revertir si hay error de comunicación
                         commentTextDiv.innerHTML = originalText;
                         commentElement.querySelector('.flag-actions').style.display = 'block';
                    });
                };
                // Cancelar edición
                cancelBtn.onclick = function() {
                    commentTextDiv.innerHTML = originalText;
                    commentElement.querySelector('.flag-actions').style.display = 'block';
                };

                 // Helper function for nl2br (since it's PHP, need JS equivalent)
                function nl2br(str) {
                    return str.replace(/\n/g, '<br>');
                }
                 // Helper function for basic htmlspecialchars (prevent XSS)
                 function htmlspecialchars(str) {
                     var map = {
                         '&': '&amp;',
                         '<': '&lt;',
                         '>': '&gt;',
                         '"': '&quot;',
                         '\'': '&#039;'
                     };
                     return str.replace(/[&<>'"]/g, function(m) { return map[m]; });
                 }

            });
        });

         // Contador de caracteres
        const commentInput = document.querySelector('textarea[name="comentario"]');
        const charCount = document.getElementById('charCount');
        const maxChars = 256; // Ajusta según tu límite de base de datos si es necesario

        if(commentInput && charCount) {
             commentInput.addEventListener('input', function() {
                const currentLength = this.value.length;
                charCount.innerText = currentLength + '/' + maxChars;
                if (currentLength > maxChars) {
                    charCount.style.color = 'red';
                } else {
                    charCount.style.color = 'inherit';
                }
            });

            // Enviar comentario al presionar Enter (sin Shift)
            commentInput.addEventListener('keypress', function(event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault(); // Evitar el salto de línea por defecto
                    this.closest('form').submit(); // Enviar el formulario más cercano
                }
            });
        }


    });
    </script>
</head>
<body class="bg-light">
<div class="container py-4">
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Volver al Dashboard</a>

    <h3 class="mb-4">Comentarios del Documento</h3>

    <!-- Formulario para comentar -->
    <form action="comentarios.php?documento_id=<?= $documento_id ?>" method="POST" class="mb-4">
        <div class="form-group mb-3">
             <?php /* Eliminamos la etiqueta "Grita como:" y los botones de formato */ ?>
             <?php /* <label for="comentario">Grita como:</label> */ ?>
             <!-- Eliminamos las Opciones de formato -->
             <?php /*
             <div class="btn-group mb-2" role="group" aria-label="Opciones de formato">
                 <button type="button" class="btn btn-outline-secondary btn-sm" data-tag="b"><b>B</b></button>
                 <button type="button" class="btn btn-outline-secondary btn-sm" data-tag="i"><i>I</i></button>
                 <button type="button" class="btn btn-outline-secondary btn-sm" data-tag="u"><u>U</u></button>
                 <button type="button" class="btn btn-outline-secondary btn-sm" data-tag="s"><s>S</s></button>
             </div>
             */ ?>
             <textarea name="comentario" id="comentario" class="form-control" placeholder="Ingrese su respuesta aquí..." rows="3" required></textarea>
             <small id="charCount" class="form-text text-muted">0/256</small>
        </div>
        <button class="btn btn-primary" type="submit">Enviar</button>
    </form>

    <!-- Lista de Comentarios -->
    <div class="comments-list mt-4">
        <?php 
        // Re-ejecutar la consulta para obtener los comentarios ordenados por fecha
        $query_list = "SELECT c.*, u.nombre AS autor 
                       FROM comentarios c 
                       JOIN usuarios u ON c.usuario_id = u.id 
                       WHERE c.documento_id = ? 
                       ORDER BY c.fecha_creacion DESC";

        $stmt_list = $conexion->prepare($query_list);
        if (!$stmt_list) {
            die("Error al preparar la consulta de comentarios para la lista: " . $conexion->error);
        }
        $stmt_list->bind_param("i", $documento_id);
        $stmt_list->execute();
        $resultado_lista_comentarios = $stmt_list->get_result();

        while ($comentario = $resultado_lista_comentarios->fetch_assoc()): ?>
            <?php $inicial = strtoupper(mb_substr($comentario['autor'], 0, 1)); ?>
            <div class="flag-comment" data-flag-author="<?= htmlspecialchars($comentario['autor']) ?>">
                <div class="flag-avatar"><?= $inicial ?></div>
                <div class="flag-body">
                    <div class="flag-author"><?= htmlspecialchars($comentario['autor']) ?></div>
                    <div class="flag-date"><?= $comentario['fecha_creacion'] ?></div>
                    <div class="flag-text" id="comentario-texto-<?= $comentario['id'] ?>"><?= nl2br(htmlspecialchars($comentario['comentario'])) ?></div>
                    <?php if ($rol === 'admin' || $rol === 'gestor_usuarios' || $rol === 'dueno' || $comentario['usuario_id'] == $usuario_id): ?>
                         <div class="flag-actions mt-2">
                             <?php if ($comentario['usuario_id'] == $usuario_id): ?>
                                 <button type="button" class="btn btn-sm btn-outline-primary btn-editar-comentario me-2" data-comentario-id="<?= $comentario['id'] ?>">Editar</button>
                             <?php endif; ?>
                             <?php // Permitir eliminar si es admin, gestor, dueño o el propio autor
                             if ($rol === 'admin' || $rol === 'gestor_usuarios' || $rol === 'dueno' || $comentario['usuario_id'] == $usuario_id): ?>
                                <a href="eliminar_comentario.php?id=<?= $comentario['id'] ?>&documento_id=<?= $documento_id ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('¿Eliminar este comentario?')">Eliminar</a>
                            <?php endif; ?>
                         </div>
                     <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
</script>
</body>
</html>

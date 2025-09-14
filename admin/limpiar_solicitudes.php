<?php
session_start();
include '../config/conexion.php';

// Verificar que sea admin o dueño
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['admin', 'dueno'])) {
    die("❌ Acceso denegado. Solo administradores pueden limpiar solicitudes.");
}

echo "<h2>🧹 Limpieza de Solicitudes de Almacén</h2>";

// Procesar acción si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        $conexion->begin_transaction();
        
        switch ($accion) {
            case 'limpiar_todo':
                echo "<h3>🗑️ Limpiando todas las solicitudes...</h3>";
                $conexion->query("DELETE FROM store_request_comments");
                $conexion->query("DELETE FROM store_request_events");
                $conexion->query("DELETE FROM store_requests");
                $conexion->query("DELETE FROM notificaciones");
                
                // Reiniciar contadores
                $conexion->query("ALTER TABLE store_request_comments AUTO_INCREMENT = 1");
                $conexion->query("ALTER TABLE store_request_events AUTO_INCREMENT = 1");
                $conexion->query("ALTER TABLE store_requests AUTO_INCREMENT = 1");
                $conexion->query("ALTER TABLE notificaciones AUTO_INCREMENT = 1");
                
                echo "<p style='color: green;'>✅ Todas las solicitudes han sido eliminadas</p>";
                break;
                
            case 'limpiar_pendientes':
                echo "<h3>🗑️ Limpiando solicitudes pendientes...</h3>";
                $result = $conexion->query("DELETE FROM store_requests WHERE status = 'pendiente'");
                echo "<p style='color: green;'>✅ Se eliminaron " . $conexion->affected_rows . " solicitudes pendientes</p>";
                break;
                
            case 'limpiar_antiguas':
                echo "<h3>🗑️ Limpiando solicitudes de más de 30 días...</h3>";
                $result = $conexion->query("DELETE FROM store_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                echo "<p style='color: green;'>✅ Se eliminaron " . $conexion->affected_rows . " solicitudes antiguas</p>";
                break;
                
            case 'limpiar_notificaciones':
                echo "<h3>🗑️ Limpiando notificaciones...</h3>";
                $result = $conexion->query("DELETE FROM notificaciones");
                $conexion->query("ALTER TABLE notificaciones AUTO_INCREMENT = 1");
                echo "<p style='color: green;'>✅ Se eliminaron todas las notificaciones</p>";
                break;
        }
        
        $conexion->commit();
        
    } catch (Exception $e) {
        $conexion->rollback();
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

// Mostrar estadísticas actuales
echo "<h3>📊 Estado Actual de las Solicitudes:</h3>";

// Resumen por estado
$result = $conexion->query("
    SELECT 
        status as Estado,
        COUNT(*) as Cantidad,
        MIN(created_at) as Mas_Antigua,
        MAX(created_at) as Mas_Reciente
    FROM store_requests 
    GROUP BY status
");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>Estado</th><th>Cantidad</th><th>Más Antigua</th><th>Más Reciente</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['Estado'] . "</strong></td>";
        echo "<td>" . $row['Cantidad'] . "</td>";
        echo "<td>" . ($row['Mas_Antigua'] ? date('d/m/Y H:i', strtotime($row['Mas_Antigua'])) : 'N/A') . "</td>";
        echo "<td>" . ($row['Mas_Reciente'] ? date('d/m/Y H:i', strtotime($row['Mas_Reciente'])) : 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay solicitudes en la base de datos.</p>";
}

// Contar otros elementos
$comentarios = $conexion->query("SELECT COUNT(*) as count FROM store_request_comments")->fetch_assoc()['count'];
$eventos = $conexion->query("SELECT COUNT(*) as count FROM store_request_events")->fetch_assoc()['count'];
$notificaciones = $conexion->query("SELECT COUNT(*) as count FROM notificaciones")->fetch_assoc()['count'];

echo "<p><strong>Comentarios:</strong> $comentarios | <strong>Eventos:</strong> $eventos | <strong>Notificaciones:</strong> $notificaciones</p>";

?>

<h3>🛠️ Opciones de Limpieza:</h3>

<div style="display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0;">
    
    <form method="POST" style="background: #ffebee; padding: 15px; border: 1px solid #f44336; border-radius: 5px;">
        <h4 style="color: #d32f2f; margin-top: 0;">⚠️ Limpiar TODO</h4>
        <p>Elimina todas las solicitudes, comentarios, eventos y notificaciones.</p>
        <input type="hidden" name="accion" value="limpiar_todo">
        <button type="submit" onclick="return confirm('¿Estás SEGURO? Esto eliminará TODAS las solicitudes y no se puede deshacer.')" 
                style="background: #f44336; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer;">
            🗑️ Eliminar TODO
        </button>
    </form>

    <form method="POST" style="background: #fff3e0; padding: 15px; border: 1px solid #ff9800; border-radius: 5px;">
        <h4 style="color: #f57c00; margin-top: 0;">📋 Solo Pendientes</h4>
        <p>Elimina únicamente las solicitudes con estado "pendiente".</p>
        <input type="hidden" name="accion" value="limpiar_pendientes">
        <button type="submit" onclick="return confirm('¿Eliminar todas las solicitudes pendientes?')" 
                style="background: #ff9800; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer;">
            🗑️ Eliminar Pendientes
        </button>
    </form>

    <form method="POST" style="background: #e3f2fd; padding: 15px; border: 1px solid #2196f3; border-radius: 5px;">
        <h4 style="color: #1976d2; margin-top: 0;">📅 Solicitudes Antiguas</h4>
        <p>Elimina solicitudes de más de 30 días.</p>
        <input type="hidden" name="accion" value="limpiar_antiguas">
        <button type="submit" onclick="return confirm('¿Eliminar solicitudes de más de 30 días?')" 
                style="background: #2196f3; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer;">
            🗑️ Eliminar Antiguas
        </button>
    </form>

    <form method="POST" style="background: #f3e5f5; padding: 15px; border: 1px solid #9c27b0; border-radius: 5px;">
        <h4 style="color: #7b1fa2; margin-top: 0;">🔔 Solo Notificaciones</h4>
        <p>Elimina todas las notificaciones (mantiene solicitudes).</p>
        <input type="hidden" name="accion" value="limpiar_notificaciones">
        <button type="submit" onclick="return confirm('¿Eliminar todas las notificaciones?')" 
                style="background: #9c27b0; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer;">
            🗑️ Limpiar Notificaciones
        </button>
    </form>

</div>

<br><br>
<a href="dashboard.php">← Volver al Dashboard</a>

<?php $conexion->close(); ?>

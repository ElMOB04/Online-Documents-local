<?php
include 'includes/auth.php';
include 'includes/conexion.php';

$id = $_GET['id'];
$stmt = $conexion->prepare("SELECT * FROM documentos WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $id, $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $doc = $result->fetch_assoc();
    unlink($doc['archivo']);
    $conexion->query("DELETE FROM documentos WHERE id = $id");
}
header("Location: dashboard.php");

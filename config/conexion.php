<?php
$host = "localhost";
$usuario = "root";
$contrasena = "";
$basedatos = "documents";

// Crear conexión
$conexion = new mysqli($host, $usuario, $contrasena, $basedatos);

// Verificar conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>


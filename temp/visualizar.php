<?php
// visualizar.php

if (!isset($_GET['ruta'])) {
    echo "Ruta no proporcionada.";
    exit();
}

// Obtener solo el nombre del archivo (evita rutas maliciosas)
$nombre_archivo = basename($_GET['ruta']);
$ruta_completa = __DIR__ . "/documentos/" . $nombre_archivo;

// Verifica que el archivo exista físicamente
if (!file_exists($ruta_completa)) {
    echo "El archivo no existe.";
    exit();
}

// Detectar el tipo MIME del archivo
$mime = mime_content_type($ruta_completa);

// Enviar encabezados para mostrar el archivo en el navegador
header("Content-Type: $mime");
header("Content-Disposition: inline; filename=\"$nombre_archivo\"");
readfile($ruta_completa);
exit();

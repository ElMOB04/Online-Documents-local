<?php
include 'includes/auth.php';
include 'includes/conexion.php';

$id = $_GET['id'];
$query = $conexion->prepare("SELECT * FROM documentos WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$result = $query->get_result();
$documento = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head><title>Visualizar</title></head>
<body>
<h2><?= $documento['titulo'] ?></h2>
<?php if ($documento['tipo'] == 'pdf') { ?>
    <iframe src="<?= $documento['archivo'] ?>" width="100%" height="600px"></iframe>
<?php } else { ?>
    <p>Documento descargable: <a href="<?= $documento['archivo'] ?>" target="_blank">Descargar <?= $documento['tipo'] ?></a></p>
<?php } ?>
<a href="dashboard.php">Volver</a>
</body>
</html>

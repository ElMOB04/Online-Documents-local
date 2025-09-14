-- Script para agregar el rol 'Editor' al sistema
-- Fecha: $(date)
-- Objetivo: Crear rol Editor con permisos específicos

-- 1. Modificar el ENUM de roles para incluir 'Editor'
ALTER TABLE `usuarios` MODIFY `rol` ENUM('usuario','dueno','admin','almacen','Editor') DEFAULT 'usuario';

-- 2. Crear usuario de almacén con rol Editor si no existe
-- (Ajustar el nombre de usuario según sea necesario)
INSERT INTO `usuarios` (`nombre`, `usuario`, `contrasena`, `rol`) 
VALUES ('Usuario Almacén', 'almacen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Editor')
ON DUPLICATE KEY UPDATE `rol` = 'Editor';

-- 3. Si ya existe un usuario con rol 'almacen', cambiarlo a 'Editor'
UPDATE `usuarios` SET `rol` = 'Editor' WHERE `rol` = 'almacen';

-- Verificar los cambios
SELECT id, nombre, usuario, rol FROM usuarios WHERE rol = 'Editor';


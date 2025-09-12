-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 22, 2025 at 08:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `documents`
--

-- --------------------------------------------------------

--
-- Table structure for table `comentarios`
--

CREATE TABLE `comentarios` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `documento_id` int(11) DEFAULT NULL,
  `contenido` text NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `comentario` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Dumping data for table `comentarios`
--

INSERT INTO `comentarios` (`id`, `usuario_id`, `documento_id`, `contenido`, `fecha`, `fecha_creacion`, `comentario`) VALUES
(7, 14, 20, '', '2025-05-22 16:04:15', '2025-05-22 12:04:15', 'eso es de estaban');

-- --------------------------------------------------------

--
-- Table structure for table `documentos`
--

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `ruta` varchar(255) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Dumping data for table `documentos`
--

INSERT INTO `documentos` (`id`, `nombre`, `ruta`, `usuario_id`, `fecha_subida`) VALUES
(19, 'Especificaciones Técnica-compras y contractaciones', 'documentos/1747929487_Especificaciones Técnica-compras y contractaciones.pdf', 8, '2025-05-22 15:58:07'),
(20, 'Instrucciones dadas por esteban', 'documentos/1747929840_Instrucciones dadas por esteban.pdf', 14, '2025-05-22 16:04:00'),
(22, '_certificate_aj0998019-gmail-com_77e28dab-8c28-4229-92e0-e42d35b7c571', 'documentos/1747930321__certificate_aj0998019-gmail-com_77e28dab-8c28-4229-92e0-e42d35b7c571.pdf', 16, '2025-05-22 16:12:01'),
(23, 'LABORATORIO GUADALUPE', 'documentos/1747930553_LABORATORIO GUADALUPE.pdf', 17, '2025-05-22 16:15:53');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  `rol` enum('usuario','dueno') DEFAULT 'usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `contrasena`, `rol`) VALUES
(6, 'Orlando Severino', 'oseverino', '$2y$10$q6Yop6P5aa939FL7LQ2OPeDPckaENEnBxFHM7uMM8UtpDsQM6GF2G', 'dueno'),
(7, 'juan carlos', 'jfernandez', '$2y$10$Ig5jaTkaxC.d6P5GEM88zOinGhlnP.DLQYR6XbfUTtST8fpl2yYQm', 'usuario'),
(8, 'oseverino', NULL, '$2y$10$eAAzothK6yJEK4Wari/q4.P./SsQu2KjSebY4owfl6YTWvr7tzDtm', 'usuario'),
(9, 'jfernandez', NULL, '$2y$10$xZs8yYtRYcuf2/A6pG7eGeqq0Az8VbBWhunVOdBlZ7YYDwDubKAn6', 'usuario'),
(10, 'jfernandez', NULL, '$2y$10$aZkP8FyysRbbhwPpY9fGeOeADPiJklLTf.dcejR8heDSRrF.d04FG', 'dueno'),
(13, 'Administrador', NULL, '$2y$10$9wsOIaBkyld5ZvHATab.ue8kOVOJsVaFDuu8JHdMkQBkYAjKWBSDe', 'usuario'),
(14, 'alex', NULL, '$2y$10$CVQB7aqXLwIxdS6Y5MgakurEjZEGXMh6zcX58bIPmO0UdfVwsdgpK', 'usuario'),
(15, 'Stward', NULL, '$2y$10$F17cddjUY0aMPz/NhnyEOuwFI/wZEgwpIPDZ6n1IUNWfw9m7n7A6O', 'usuario'),
(16, 'angel', NULL, '$2y$10$f9HgNzGUHEkHWC7s4NxI8OnFhOISsnJDYkPqfjxrr3HzQonXhYXy2', 'usuario'),
(17, 'guillen1', NULL, '$2y$10$a/4lA9RGO4G5J8NYw2zng.pvgWvbKQrMvnakplSZj4f2zQQ12aJ8q', 'usuario');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comentarios`
--
ALTER TABLE `comentarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `documento_id` (`documento_id`);

--
-- Indexes for table `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_usuario_documento` (`usuario_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comentarios`
--
ALTER TABLE `comentarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comentarios`
--
ALTER TABLE `comentarios`
  ADD CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `comentarios_ibfk_2` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`);

--
-- Constraints for table `documentos`
--
ALTER TABLE `documentos`
  ADD CONSTRAINT `documentos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_usuario_documento` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

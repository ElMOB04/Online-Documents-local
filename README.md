# 📑 Online Documents Local

**Online Documents Local** es una aplicación web en **PHP** diseñada para la gestión interna de documentos y comunicaciones digitales.  
El objetivo es facilitar la organización, consulta y control de archivos de forma centralizada dentro de una institución u organización.

---

## 🚀 Características principales

- **Autenticación de usuarios**  
  Inicio y cierre de sesión mediante las páginas `index.php` y `logout.php`.

- **Panel de control (Dashboard)**  
  Visualización rápida de las principales funciones del sistema (`dashboard.php`).

- **Gestión de usuarios**  
  Administración de cuentas con opciones para ver y editar usuarios (`usuarios.php`, `editar_usuario.php`).

- **Gestión de documentos**  
  - Subida de archivos (`subir_documento.php`)  
  - Consulta de documentos cargados (`ver_documento.php`)  
  - Visualización detallada (`visualizar.php`)

- **Comentarios y notificaciones**  
  Archivos dedicados al manejo de comentarios (`comentarios.php`) y notificaciones internas (`notificaciones.php`).

- **Soporte de base de datos**  
  Incluye el archivo `documents.sql` con la estructura necesaria para desplegar la base de datos.

---

## 🗂️ Estructura del proyecto
Online-Documents-local/
│
├── index.php # Página de inicio / login
├── dashboard.php # Panel principal
├── usuarios.php # Listado de usuarios
├── editar_usuario.php # Edición de usuarios
├── subir_documento.php # Subida de documentos
├── ver_documento.php # Consulta de documentos
├── visualizar.php # Visualización de un documento
├── comentarios.php # Gestión de comentarios
├── notificaciones.php # Módulo de notificaciones
├── logout.php # Cierre de sesión
├── documents.sql # Script SQL de la base de datos
└── README.md # Documentación del proyecto

---

## ⚙️ Requisitos

- Servidor web con soporte **PHP 7.4+**
- **MySQL/MariaDB** para la base de datos
- Navegador moderno para acceder a la aplicación

---

## 📥 Instalación

1. Clona este repositorio en tu servidor local:
   ```bash
   git clone https://github.com/ElMOB04/Online-Documents-local.git


<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/funciones.php';

session_start();
session_destroy();
redirigir('admin/login.php');
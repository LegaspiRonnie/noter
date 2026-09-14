<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'categories';
include __DIR__ . '/backend/api/Note.php';

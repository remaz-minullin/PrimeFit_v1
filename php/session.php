<?php
session_start();

$USER = null;

if (isset($_SESSION['user_id'])) {
    $id = (int) $_SESSION['user_id'];
    $sql = "SELECT * FROM users WHERE id = $id";
    $USER = $database->query($sql)->fetch();
}

if (isset($_GET['exit'])) {
    session_destroy();
    unset($_SESSION['user_id']);
    header("Location: ./");
    exit;
}
?>
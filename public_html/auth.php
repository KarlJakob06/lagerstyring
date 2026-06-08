<?php
session_start();

if (empty($_SESSION['bruker_id'])) {
    header('Location: login.php');
    exit;
}
?>

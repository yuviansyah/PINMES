<?php
session_start();
require_once 'controller/config.php';
if (!empty($_SESSION['user'])) {
    echo 'User: ' . $_SESSION['user']['name'] . ', role: ' . $_SESSION['user']['role'] . ', workshop_id: ' . ($_SESSION['user']['workshop_id'] ?? 0);
} else {
    echo 'Not logged in';
}

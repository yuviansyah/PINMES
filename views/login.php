<?php
require_once '../controller/config.php';
if (current_user()) {
    header('Location: home.php');
    exit;
}
header('Location: ../index.php');
exit;

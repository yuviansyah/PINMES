<?php
session_start();
require_once 'controller/config.php';
$u = $_SESSION['user'] ?? null;
if ($u) {
    echo "Role: " . $u['role'] . "<br>";
    echo "workshop_id: " . ($u['workshop_id'] ?? 0) . "<br>";
}
echo "<br>Total items di DB: " . db()->query("SELECT COUNT(*) as n FROM items")->fetch_assoc()['n'];

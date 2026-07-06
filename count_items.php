<?php
require_once 'controller/config.php';
$r = db()->query('SELECT workshop_id, COUNT(*) as cnt FROM items GROUP BY workshop_id');
while ($c = $r->fetch_assoc()) {
    echo 'ws ' . $c['workshop_id'] . ': ' . $c['cnt'] . " items\n";
}
echo 'Total: ' . db()->query('SELECT COUNT(*) as n FROM items')->fetch_assoc()['n'] . " items\n";

<?php
require_once 'config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if (!in_array($user['role'], ['admin','kepala','kepala_bengkel'])) { echo 'Akses ditolak.'; exit; }

$rows = db()->query('SELECT i.*, c.name AS category_name FROM items i LEFT JOIN categories c ON i.category_id=c.id ORDER BY i.id DESC');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=items_' . date('Ymd_His') . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Kode','Nama','Kategori','Deskripsi','Quantity','Location','Created At']);
if ($rows && $rows->num_rows>0){
    while($r = $rows->fetch_assoc()){
        fputcsv($out, [
            $r['id'],$r['code'],$r['name'],$r['category_name'],
            $r['description'],$r['quantity'],$r['location'],$r['created_at']
        ]);
    }
}
fclose($out);
exit;

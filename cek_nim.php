<?php
require_once 'controller/config.php';
header('Content-Type: application/json');

$nim = trim($_GET['nim'] ?? '');
$type = $_GET['type'] ?? 'mahasiswa';

if (strlen($nim) < 3) {
  echo json_encode(['status' => 'error', 'message' => 'NIM/NIDN terlalu pendek']);
  exit;
}

$db = db();

// Check if NIM/NIDN already registered in users — ambil data name juga
$stmt = $db->prepare('SELECT id, name, prodi FROM users WHERE nim = ? LIMIT 1');
$stmt->bind_param('s', $nim);
$stmt->execute();
$existing_user = $stmt->get_result()->fetch_assoc();
if ($existing_user) {
  echo json_encode([
    'status' => 'registered',
    'message' => $type === 'dosen' ? 'NIDN sudah terdaftar' : 'NIM sudah terdaftar',
    'data' => [
      'name' => $existing_user['name'],
      'prodi' => $existing_user['prodi'] ?? '',
    ]
  ]);
  exit;
}

// Look up in pre_students or pre_lecturers
if ($type === 'dosen') {
  $stmt = $db->prepare('SELECT nidn, name, prodi, "" as angkatan FROM pre_lecturers WHERE nidn = ? LIMIT 1');
  $stmt->bind_param('s', $nim);
  $stmt->execute();
  $lecturer = $stmt->get_result()->fetch_assoc();
  if ($lecturer) {
    echo json_encode([
      'status' => 'found',
      'data' => [
        'nim' => $lecturer['nidn'],
        'name' => $lecturer['name'],
        'prodi' => $lecturer['prodi'],
        'angkatan' => '-',
      ]
    ]);
    exit;
  }
} else {
  $stmt = $db->prepare('SELECT nim, name, prodi, angkatan FROM pre_students WHERE nim = ? LIMIT 1');
  $stmt->bind_param('s', $nim);
  $stmt->execute();
  $student = $stmt->get_result()->fetch_assoc();
  if ($student) {
    echo json_encode([
      'status' => 'found',
      'data' => [
        'nim' => $student['nim'],
        'name' => $student['name'],
        'prodi' => $student['prodi'],
        'angkatan' => $student['angkatan'],
      ]
    ]);
    exit;
  }
}

echo json_encode(['status' => 'not_found', 'message' => $type === 'dosen' ? 'NIDN tidak ditemukan' : 'NIM tidak ditemukan']);

-- ============================================================
-- MIGRATION: Tambah tabel & kolom yang kurang
-- Database: apk_mesin (sesuai config.php)
-- ============================================================

-- 1. Tabel workshops
CREATE TABLE IF NOT EXISTS workshops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabel pre_students (data mahasiswa pra-daftar)
CREATE TABLE IF NOT EXISTS pre_students (
  nim VARCHAR(50) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  prodi VARCHAR(100),
  angkatan VARCHAR(10),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Tabel pre_lecturers (data dosen pra-daftar)
CREATE TABLE IF NOT EXISTS pre_lecturers (
  nidn VARCHAR(50) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  prodi VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Tabel admin_requests (permintaan daftar admin/kepala)
CREATE TABLE IF NOT EXISTS admin_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  requested_role ENUM('admin','kepala','penanggung jawab') NOT NULL DEFAULT 'admin',
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  workshop_id INT DEFAULT 0
) ENGINE=InnoDB;

-- 5. Tabel loan_details (detail barang per peminjaman)
CREATE TABLE IF NOT EXISTS loan_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  item_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ALTER TABLE: Tambah kolom yang kurang
-- ============================================================

-- users: tambah kolom
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS nim VARCHAR(50) DEFAULT NULL AFTER name,
  ADD COLUMN IF NOT EXISTS prodi VARCHAR(100) DEFAULT NULL AFTER nim,
  ADD COLUMN IF NOT EXISTS angkatan VARCHAR(10) DEFAULT NULL AFTER prodi,
  ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL AFTER password,
  ADD COLUMN IF NOT EXISTS workshop_id INT DEFAULT 0 AFTER photo,
  ADD COLUMN IF NOT EXISTS fine DECIMAL(12,2) DEFAULT 0.00 AFTER role;

-- items: tambah kolom workshop_id
ALTER TABLE items
  ADD COLUMN IF NOT EXISTS workshop_id INT DEFAULT 0 AFTER category_id;

-- loans: tambah kolom fine
ALTER TABLE loans
  ADD COLUMN IF NOT EXISTS fine DECIMAL(12,2) DEFAULT 0.00 AFTER status;

-- ============================================================
-- Seed data: workshops
-- ============================================================
INSERT IGNORE INTO workshops (name, description) VALUES
('Bengkel Mesin', 'Bengkel utama Teknik Mesin'),
('Bengkel CNC', 'Bengkel mesin CNC'),
('Bengkel Las', 'Bengkel pengelasan'),
('Bengkel Otomotif', 'Bengkel kendaraan bermotor'),
('Lab Komputer', 'Laboratorium komputer');

-- ============================================================
-- Ubah tipe kolom nim jadi VARCHAR agar bisa simpan NIDN
-- ============================================================
ALTER TABLE users MODIFY COLUMN nim VARCHAR(50) DEFAULT NULL;

-- ============================================================
-- Update role ENUM jika perlu (biarkan VARCHAR agar fleksibel)
-- ============================================================
-- Ubah kolom role menjadi VARCHAR agar bisa menyimpan role baru seperti 'penanggung jawab'
ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'mahasiswa';

-- Migrasi role 'user' → 'mahasiswa'/'dosen'
UPDATE users SET role='mahasiswa' WHERE role='user' AND angkatan IS NOT NULL AND angkatan != '';
UPDATE users SET role='dosen' WHERE role='user' AND nim COLLATE utf8mb4_0900_ai_ci IN (SELECT nidn FROM pre_lecturers);
UPDATE users SET role='mahasiswa' WHERE role='user';
-- Update ENUM admin_requests.requested_role untuk mendukung 'penanggung jawab'
ALTER TABLE admin_requests MODIFY COLUMN requested_role ENUM('admin','kepala','penanggung jawab') NOT NULL DEFAULT 'admin';
-- Tambah workshop_id ke admin_requests jika belum ada
ALTER TABLE admin_requests ADD COLUMN IF NOT EXISTS workshop_id INT DEFAULT 0 AFTER password;
-- Tambah nidn ke admin_requests
ALTER TABLE admin_requests ADD COLUMN IF NOT EXISTS nidn VARCHAR(50) DEFAULT '' AFTER name;

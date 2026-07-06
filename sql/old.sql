CREATE DATABASE IF NOT EXISTS aplikasi_peminjaman_barang CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE aplikasi_peminjaman_barang;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','mahasiswa') NOT NULL DEFAULT 'mahasiswa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT
) ENGINE=InnoDB;

-- ITEMS TABLE
CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  category_id INT,
  quantity INT NOT NULL DEFAULT 0,
  location VARCHAR(100),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- LOANS TABLE
CREATE TABLE IF NOT EXISTS loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_number VARCHAR(50) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  reason VARCHAR(255),
  loan_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE DEFAULT NULL,
  status ENUM('pending','borrowed','returned','rejected','overdue') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- LOGS TABLE
CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(255),
  meta TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- SETTINGS TABLE (baru ditambahkan)
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT DEFAULT NULL
) ENGINE=InnoDB;

-- INITIAL DATA
INSERT IGNORE INTO categories (name,description) VALUES
('Elektronika','Peralatan elektronika'),
('Audio','Peralatan audio'),
('Multimedia','Peralatan multimedia');

INSERT IGNORE INTO items (code,name,category_id,quantity,location,description) VALUES
('CAM-001','kamera dslr',3,5,'Gudang','kamera dslr'),
('LGT-001','lightning',1,10,'Gudang','lightning cable'),
('ADL-001','adapter led',1,8,'Gudang','adapter untuk LED'),
('TAB-001','tab',3,6,'Gudang','tablet'),
('PEN-001','pen tab',3,6,'Gudang','pen untuk tablet'),
('LED-001','led',1,20,'Gudang','lampu LED'),
('ADP-001','adaptor',1,12,'Gudang','adaptor listrik'),
('TRG-001','trigger',3,7,'Gudang','trigger untuk kamera'),
('STD-001','studio',3,2,'Gudang','studio kit'),
('SPK-001','speaker set',2,3,'Gudang','speaker set'),
('TRI-001','tripod',3,15,'Gudang','tripod kamera'),
('CAMFHD-001','kamera fhd',3,4,'Gudang','kamera full HD'),
('HPH-001','headphone',2,10,'Gudang','headphone');

INSERT IGNORE INTO users (name,email,password,role) VALUES
('Administrator','admin','admin123','admin'),
('Mahasiswa Contoh','mahasiswa1@poltesa.ac.id','123456','mahasiswa');

INSERT IGNORE INTO settings (`key`,`value`) VALUES
('loan_period_days','7'),
('fine_per_day','20000'),
('max_loans_per_user','3');
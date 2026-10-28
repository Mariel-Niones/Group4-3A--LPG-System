CREATE DATABASE IF NOT EXISTS bgs_lpg_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bgs_lpg_system;

CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_name VARCHAR(120) NOT NULL UNIQUE,
  address VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','staff') NOT NULL DEFAULT 'staff',
  branch_id INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(150) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'LPG Cylinder',
  unit VARCHAR(30) NOT NULL DEFAULT 'piece',
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_stock INT NOT NULL DEFAULT 5,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_branch_product (branch_id, product_id),
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE stock_ins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  notes VARCHAR(255) DEFAULT '',
  received_by INT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  user_id INT NULL,
  customer_name VARCHAR(120) DEFAULT 'Walk-in Customer',
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) DEFAULT '',
  sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO branches (branch_name, address) VALUES ('Main Branch', 'BGS LPG Store');
INSERT INTO products (product_name, category, unit, price, min_stock) VALUES
('LPG Cylinder - 11kg', 'LPG Cylinder', 'cylinder', 0, 5),
('LPG Cylinder - 22kg', 'LPG Cylinder', 'cylinder', 0, 3),
('LPG Regulator', 'Accessory', 'piece', 0, 5),
('LPG Hose', 'Accessory', 'piece', 0, 5);

-- Demo login: owner / password
-- Replace this hash after installation using the included setup.php page.
INSERT INTO users (full_name, username, password_hash, role, branch_id)
VALUES ('System Owner', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner', 1);

-- Patikum Veteriner Klinik Yönetim Sistemi - Veritabanı Şeması (MySQL / MariaDB)
-- cPanel > phpMyAdmin üzerinden içe aktarabilir ya da /install/ sihirbazını kullanabilirsiniz.

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  skey VARCHAR(100) NOT NULL PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  phone2 VARCHAR(30) NULL,
  email VARCHAR(160) NULL,
  tc_no VARCHAR(20) NULL,
  company VARCHAR(200) NULL,
  tax_office VARCHAR(100) NULL,
  is_efatura TINYINT(1) NOT NULL DEFAULT 0,
  address TEXT NULL,
  city VARCHAR(60) NULL,
  district VARCHAR(60) NULL,
  notes TEXT NULL,
  panel_password VARCHAR(255) NULL,
  wa_optin TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE species (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  icon VARCHAR(10) NULL,
  color VARCHAR(20) NULL,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE breeds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  species_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  species_id INT UNSIGNED NULL,
  breed VARCHAR(100) NULL,
  name VARCHAR(100) NOT NULL,
  gender VARCHAR(10) NULL,
  birth_date DATE NULL,
  weight DECIMAL(7,2) NULL,
  color VARCHAR(60) NULL,
  chip_no VARCHAR(60) NULL,
  passport_no VARCHAR(60) NULL,
  neutered TINYINT(1) NOT NULL DEFAULT 0,
  allergies TEXT NULL,
  notes TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'aktif',
  created_at DATETIME NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(80) NULL,
  name VARCHAR(200) NOT NULL,
  barcode VARCHAR(60) NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'Adet',
  buy_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sell_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  vat_rate INT NOT NULL DEFAULT 20,
  stock DECIMAL(12,2) NOT NULL DEFAULT 0,
  critical_stock DECIMAL(12,2) NOT NULL DEFAULT 0,
  expiry_date DATE NULL,
  is_service TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  type VARCHAR(20) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  note VARCHAR(255) NULL,
  ref_type VARCHAR(30) NULL,
  ref_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NULL,
  patient_id INT UNSIGNED NULL,
  sale_date DATETIME NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  vat_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  source VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'aktif',
  user_id INT UNSIGNED NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  vat_rate INT NOT NULL DEFAULT 20,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NULL,
  sale_id INT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  method VARCHAR(20) NOT NULL DEFAULT 'nakit',
  pay_date DATETIME NOT NULL,
  note VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(80) NULL,
  description VARCHAR(255) NULL,
  supplier VARCHAR(150) NULL,
  amount DECIMAL(12,2) NOT NULL,
  method VARCHAR(20) NOT NULL DEFAULT 'nakit',
  exp_date DATE NOT NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE examinations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  exam_date DATETIME NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'Muayene',
  complaint TEXT NULL,
  findings TEXT NULL,
  diagnosis TEXT NULL,
  treatment TEXT NULL,
  weight DECIMAL(7,2) NULL,
  temperature DECIMAL(4,1) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_id INT UNSIGNED NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vaccine_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  species_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  product_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE SET NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vaccine_protocols (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  species_id INT UNSIGNED NOT NULL,
  vaccine_type_id INT UNSIGNED NOT NULL,
  first_age_weeks INT NOT NULL DEFAULT 8,
  doses INT NOT NULL DEFAULT 1,
  interval_days INT NOT NULL DEFAULT 21,
  booster_days INT NULL,
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE,
  FOREIGN KEY (vaccine_type_id) REFERENCES vaccine_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vaccinations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  vaccine_type_id INT UNSIGNED NOT NULL,
  dose_label VARCHAR(30) NULL,
  due_date DATE NOT NULL,
  done_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'planlandi',
  product_id INT UNSIGNED NULL,
  batch_no VARCHAR(60) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (vaccine_type_id) REFERENCES vaccine_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NULL,
  patient_id INT UNSIGNED NULL,
  guest_name VARCHAR(150) NULL,
  guest_phone VARCHAR(30) NULL,
  pet_info VARCHAR(200) NULL,
  start_at DATETIME NOT NULL,
  duration INT NOT NULL DEFAULT 30,
  type VARCHAR(60) NOT NULL DEFAULT 'Muayene',
  status VARCHAR(20) NOT NULL DEFAULT 'onayli',
  notes TEXT NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'admin',
  reminded_at DATETIME NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NULL,
  invoice_type VARCHAR(20) NOT NULL DEFAULT 'e-arsiv',
  invoice_no VARCHAR(30) NOT NULL,
  uuid VARCHAR(40) NOT NULL,
  issue_date DATETIME NOT NULL,
  receiver_name VARCHAR(200) NULL,
  receiver_vkn VARCHAR(20) NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  vat_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'taslak',
  xml MEDIUMTEXT NULL,
  response TEXT NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wa_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  meta_name VARCHAR(120) NULL,
  meta_params VARCHAR(255) NULL,
  auto_send TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wa_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NULL,
  phone VARCHAR(30) NOT NULL,
  body TEXT NOT NULL,
  vars TEXT NULL,
  template_code VARCHAR(50) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'kuyruk',
  error TEXT NULL,
  ref_key VARCHAR(80) NULL,
  attempts INT NOT NULL DEFAULT 0,
  sent_at DATETIME NULL,
  created_at DATETIME NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_patients_customer ON patients(customer_id);
CREATE INDEX idx_vacc_due ON vaccinations(status, due_date);
CREATE INDEX idx_vacc_patient ON vaccinations(patient_id);
CREATE INDEX idx_appt_start ON appointments(start_at);
CREATE INDEX idx_sales_customer ON sales(customer_id);
CREATE INDEX idx_sales_date ON sales(sale_date);
CREATE INDEX idx_pay_customer ON payments(customer_id);
CREATE INDEX idx_pay_date ON payments(pay_date);
CREATE INDEX idx_exp_date ON expenses(exp_date);
CREATE INDEX idx_wa_status ON wa_messages(status);
CREATE INDEX idx_wa_ref ON wa_messages(ref_key);
CREATE INDEX idx_products_barcode ON products(barcode);
CREATE INDEX idx_exam_patient ON examinations(patient_id);

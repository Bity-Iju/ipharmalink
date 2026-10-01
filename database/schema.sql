CREATE DATABASE IF NOT EXISTS ipharmalink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ipharmalink;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NULL,
  status ENUM('pending','active','suspended','deactivated') NOT NULL DEFAULT 'pending',
  email_verified_at TIMESTAMP NULL,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NULL,
  type ENUM('supplier','retail_pharmacy') NOT NULL,
  business_name VARCHAR(190) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  registration_number VARCHAR(100) NULL,
  pharmacy_license_number VARCHAR(100) NULL,
  pharmacist_name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  logo_url VARCHAR(500) NULL,
  description TEXT NULL,
  address_line1 VARCHAR(255) NULL,
  address_line2 VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  country VARCHAR(100) NOT NULL DEFAULT 'Nigeria',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  verification_status ENUM('pending','under_review','approved','rejected','suspended','deactivated') NOT NULL DEFAULT 'pending',
  verified_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_org_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_org_type_status (type, verification_status)
) ENGINE=InnoDB;

CREATE TABLE organization_users (
  organization_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  job_title VARCHAR(100) NULL,
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (organization_id, user_id),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE verification_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  file_url VARCHAR(500) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  rejection_reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  generic_name VARCHAR(190) NULL,
  brand VARCHAR(150) NULL,
  manufacturer VARCHAR(190) NULL,
  active_ingredient VARCHAR(255) NULL,
  strength VARCHAR(100) NULL,
  dosage_form VARCHAR(100) NULL,
  sku VARCHAR(100) NOT NULL,
  barcode VARCHAR(100) NULL,
  description TEXT NULL,
  prescription_required BOOLEAN NOT NULL DEFAULT FALSE,
  classification ENUM('medicine','supplement','medical_device','personal_care','other') NOT NULL DEFAULT 'medicine',
  status ENUM('draft','active','inactive','out_of_stock') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_supplier_sku (supplier_id, sku),
  FOREIGN KEY (supplier_id) REFERENCES organizations(id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_product_search (name, generic_name, brand, manufacturer)
) ENGINE=InnoDB;

CREATE TABLE product_packaging (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  unit_name ENUM('unit','strip','pack','box','carton','case','bundle') NOT NULL,
  units_per_parent INT UNSIGNED NOT NULL DEFAULT 1,
  is_order_unit BOOLEAN NOT NULL DEFAULT FALSE,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_product_unit (product_id, unit_name)
) ENGINE=InnoDB;

CREATE TABLE product_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  batch_number VARCHAR(100) NOT NULL,
  manufacturing_date DATE NULL,
  expiry_date DATE NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  reserved_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  cost_price DECIMAL(14,2) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_batch (product_id, batch_number),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB;

CREATE TABLE wholesale_price_tiers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  packaging_id BIGINT UNSIGNED NOT NULL,
  min_quantity INT UNSIGNED NOT NULL,
  max_quantity INT UNSIGNED NULL,
  price DECIMAL(14,2) NOT NULL,
  price_type ENUM('standard','bulk','distributor','special','promotion') NOT NULL DEFAULT 'standard',
  buyer_organization_id BIGINT UNSIGNED NULL,
  starts_at TIMESTAMP NULL,
  ends_at TIMESTAMP NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (packaging_id) REFERENCES product_packaging(id),
  FOREIGN KEY (buyer_organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  INDEX idx_price_lookup (product_id, min_quantity, max_quantity)
) ENGINE=InnoDB;

CREATE TABLE carts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  buyer_id BIGINT UNSIGNED NOT NULL,
  type ENUM('wholesale','retail') NOT NULL,
  retail_pharmacy_id BIGINT UNSIGNED NULL,
  status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (retail_pharmacy_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  retail_product_id BIGINT UNSIGNED NULL,
  packaging_id BIGINT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (packaging_id) REFERENCES product_packaging(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE wholesale_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  buyer_id BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','submitted','accepted','rejected','processing','ready','dispatched','in_transit','delivered','cancelled') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  delivery_address JSON NOT NULL,
  notes TEXT NULL,
  placed_at TIMESTAMP NULL,
  delivered_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (buyer_id) REFERENCES organizations(id),
  FOREIGN KEY (supplier_id) REFERENCES organizations(id),
  INDEX idx_wholesale_status (supplier_id, status)
) ENGINE=InnoDB;

CREATE TABLE wholesale_order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  packaging_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  FOREIGN KEY (order_id) REFERENCES wholesale_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (packaging_id) REFERENCES product_packaging(id),
  FOREIGN KEY (batch_id) REFERENCES product_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE retail_inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  reserved_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  retail_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_price DECIMAL(14,2) NULL,
  visible BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE KEY uq_retail_stock (pharmacy_id, product_id, batch_id),
  FOREIGN KEY (pharmacy_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (batch_id) REFERENCES product_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  type ENUM('opening','received','sold','adjustment','damaged','returned','expired') NOT NULL,
  quantity INT NOT NULL,
  reference_type VARCHAR(50) NULL,
  reference_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pharmacy_id) REFERENCES organizations(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (batch_id) REFERENCES product_batches(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE retail_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  price DECIMAL(14,2) NOT NULL,
  discount_price DECIMAL(14,2) NULL,
  visible BOOLEAN NOT NULL DEFAULT TRUE,
  featured BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (pharmacy_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uq_pharmacy_product (pharmacy_id, product_id)
) ENGINE=InnoDB;

CREATE TABLE retail_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  pharmacy_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','paid','accepted','processing','ready','out_for_delivery','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  fulfilment ENUM('delivery','pickup') NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  delivery_address JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id),
  FOREIGN KEY (pharmacy_id) REFERENCES organizations(id)
) ENGINE=InnoDB;

CREATE TABLE retail_order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  retail_product_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES retail_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (retail_product_id) REFERENCES retail_products(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(100) NOT NULL UNIQUE,
  payer_id BIGINT UNSIGNED NOT NULL,
  wholesale_order_id BIGINT UNSIGNED NULL,
  retail_order_id BIGINT UNSIGNED NULL,
  method ENUM('paystack','flutterwave','bank_transfer','wallet','credit','manual') NOT NULL,
  status ENUM('pending','processing','successful','failed','partially_paid','overdue','refunded','cancelled') NOT NULL DEFAULT 'pending',
  amount DECIMAL(14,2) NOT NULL,
  gateway_reference VARCHAR(190) NULL,
  proof_url VARCHAR(500) NULL,
  paid_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payer_id) REFERENCES users(id),
  FOREIGN KEY (wholesale_order_id) REFERENCES wholesale_orders(id) ON DELETE SET NULL,
  FOREIGN KEY (retail_order_id) REFERENCES retail_orders(id) ON DELETE SET NULL,
  INDEX idx_payment_status (status)
) ENGINE=InnoDB;

CREATE TABLE deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delivery_number VARCHAR(50) NOT NULL UNIQUE,
  wholesale_order_id BIGINT UNSIGNED NULL,
  retail_order_id BIGINT UNSIGNED NULL,
  delivery_person_id BIGINT UNSIGNED NULL,
  sender_name VARCHAR(190) NOT NULL,
  receiver_name VARCHAR(190) NOT NULL,
  receiver_phone VARCHAR(40) NOT NULL,
  address JSON NOT NULL,
  status ENUM('assigned','preparing','ready','dispatched','in_transit','delivered','failed','cancelled') NOT NULL DEFAULT 'assigned',
  otp_hash VARCHAR(255) NULL,
  proof_url VARCHAR(500) NULL,
  delivery_note TEXT NULL,
  delivered_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (wholesale_order_id) REFERENCES wholesale_orders(id) ON DELETE SET NULL,
  FOREIGN KEY (retail_order_id) REFERENCES retail_orders(id) ON DELETE SET NULL,
  FOREIGN KEY (delivery_person_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE supplier_credit_terms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id BIGINT UNSIGNED NOT NULL,
  buyer_id BIGINT UNSIGNED NOT NULL,
  term ENUM('cash','7_days','14_days','30_days','60_days','custom') NOT NULL DEFAULT 'cash',
  credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0,
  current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  enabled BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY uq_supplier_buyer_credit (supplier_id, buyer_id),
  FOREIGN KEY (supplier_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO roles (name, description) VALUES
('admin','Platform administrator'),('supplier','Wholesale supplier'),('retail_pharmacy','Retail pharmacy'),('staff','Pharmacy staff'),('delivery','Delivery personnel'),('customer','End customer');

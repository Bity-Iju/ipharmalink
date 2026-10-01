-- ============================================================================
--  iPharmaLink_utf8mb4  ::  Multi-Pharmacy E-Commerce & Delivery Platform
--  Database Schema  (MySQL 8.0+ / MariaDB 10.4+)
--  Engine : InnoDB, utf8mb4
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
-- ---------------------------------------------------------------------------
--  1.  PLATFORM SETTINGS  (key/value store - admin configurable)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_name` VARCHAR(50) NOT NULL DEFAULT 'general',
  `key_name` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  `is_secret` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_group_key` (`group_name`, `key_name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  2.  ROLES / PERMISSIONS / USERS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  -- super_admin, pharmacy_owner, pharmacy_staff, customer, delivery_personnel
  `label` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  -- e.g. pharmacy.products.manage
  `group_name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perm_name` (`name`),
  KEY `idx_perm_group` (`group_name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(32) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `profile_image` VARCHAR(255) NULL,
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `verification_token` VARCHAR(100) NULL,
  `reset_token` VARCHAR(100) NULL,
  `reset_expires_at` DATETIME NULL,
  `failed_logins` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `status` ENUM('active', 'pending', 'suspended', 'deactivated') NOT NULL DEFAULT 'active',
  `two_factor_secret` VARCHAR(64) NULL,
  -- ready for TOTP
  `remember_token` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_phone` (`phone`),
  KEY `idx_users_role` (`role_id`, `status`),
  KEY `idx_users_status`(`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_roles` (
  -- supports multi-role assignment
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_addresses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `label` VARCHAR(50) NOT NULL DEFAULT 'Home',
  `recipient_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(32) NOT NULL,
  `state` VARCHAR(80) NOT NULL,
  `city` VARCHAR(80) NOT NULL,
  `address_line` VARCHAR(255) NOT NULL,
  `landmark` VARCHAR(160) NULL,
  `latitude` DECIMAL(10, 7) NULL,
  `longitude` DECIMAL(10, 7) NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addr_user` (`user_id`),
  CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  3.  PHARMACIES (vendors)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pharmacies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `legal_name` VARCHAR(180) NULL,
  -- registered business name
  `registration_number` VARCHAR(80) NULL,
  -- pharmacy council licence no.
  `regulatory_body` VARCHAR(120) NULL,
  -- e.g. PCN / NAFDAC
  `pharmacist_name` VARCHAR(150) NULL,
  `phone` VARCHAR(32) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `website` VARCHAR(190) NULL,
  `logo` VARCHAR(255) NULL,
  `cover_image` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `state` VARCHAR(80) NOT NULL,
  `city` VARCHAR(80) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `latitude` DECIMAL(10, 7) NULL,
  `longitude` DECIMAL(10, 7) NULL,
  `open_time` TIME NOT NULL DEFAULT '08:00:00',
  `close_time` TIME NOT NULL DEFAULT '20:00:00',
  `delivery_available` TINYINT(1) NOT NULL DEFAULT 1,
  `pickup_available` TINYINT(1) NOT NULL DEFAULT 1,
  `delivery_radius_km` DECIMAL(6, 2) NOT NULL DEFAULT 10.00,
  `delivery_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `free_delivery_threshold` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `estimated_delivery_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
  `min_order_value` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `preparation_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
  `accept_orders_automatically` TINYINT(1) NOT NULL DEFAULT 0,
  `bank_name` VARCHAR(120) NULL,
  `bank_account_name` VARCHAR(150) NULL,
  `bank_account_number` VARCHAR(32) NULL,
  `commission_rate` DECIMAL(5, 2) NULL,
  -- NULL => use platform default
  `rating_avg` DECIMAL(3, 2) NOT NULL DEFAULT 0.00,
  `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM(
    'pending',
    'approved',
    'suspended',
    'rejected',
    'deactivated'
  ) NOT NULL DEFAULT 'pending',
  `status_reason` VARCHAR(255) NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pharm_slug` (`slug`),
  UNIQUE KEY `uq_pharm_owner`(`owner_id`),
  KEY `idx_pharm_status` (`status`, `deleted_at`),
  KEY `idx_pharm_city` (`city`, `state`),
  KEY `idx_pharm_rating` (`rating_avg`),
  FULLTEXT KEY `ft_pharm_search` (`name`, `description`, `city`),
  CONSTRAINT `fk_pharm_owner` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_pharm_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `pharmacy_staff` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `job_title` VARCHAR(80) NULL,
  `can_manage_orders` TINYINT(1) NOT NULL DEFAULT 1,
  `can_manage_inventory` TINYINT(1) NOT NULL DEFAULT 0,
  `can_manage_products` TINYINT(1) NOT NULL DEFAULT 0,
  `can_view_reports` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active', 'invited', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_pharm_user` (`pharmacy_id`, `user_id`),
  CONSTRAINT `fk_staff_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `pharmacy_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `doc_type` VARCHAR(60) NOT NULL,
  -- licence, incorporation, tax_cert, id
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(190) NULL,
  `review_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `review_note` VARCHAR(255) NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_pharm` (`pharmacy_id`),
  CONSTRAINT `fk_doc_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Pharmacy-scoped settings overrides (general/delivery/order/payment/notification)
CREATE TABLE IF NOT EXISTS `pharmacy_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `key_name` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pharm_setting` (`pharmacy_id`, `key_name`),
  CONSTRAINT `fk_ps_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `registered_name` VARCHAR(180) NOT NULL,
  `registration_number` VARCHAR(80) NOT NULL,
  `pharmacist_name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(32) NOT NULL,
  `logo` VARCHAR(255) NULL,
  `business_address` VARCHAR(255) NOT NULL,
  `warehouse_address` VARCHAR(255) NULL,
  `state` VARCHAR(80) NOT NULL,
  `city` VARCHAR(80) NOT NULL,
  `delivery_areas` TEXT NULL,
  `opening_hours` TEXT NULL,
  `description` TEXT NULL,
  `bank_name` VARCHAR(120) NULL,
  `bank_account_name` VARCHAR(150) NULL,
  `bank_account_number` VARCHAR(32) NULL,
  `status` ENUM('pending', 'under_review', 'approved', 'rejected', 'suspended', 'deactivated') NOT NULL DEFAULT 'pending',
  `status_reason` VARCHAR(255) NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supplier_owner` (`owner_id`),
  UNIQUE KEY `uq_supplier_slug` (`slug`),
  UNIQUE KEY `uq_supplier_registration` (`registration_number`),
  KEY `idx_supplier_status` (`status`, `deleted_at`),
  KEY `idx_supplier_location` (`state`, `city`),
  CONSTRAINT `fk_supplier_owner` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_supplier_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `supplier_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `doc_type` VARCHAR(60) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(190) NULL,
  `review_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `review_note` VARCHAR(255) NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_doc` (`supplier_id`),
  CONSTRAINT `fk_supplier_doc_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  4.  CATALOG
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `icon` VARCHAR(60) NULL,
  `parent_id` INT UNSIGNED NULL,
  -- subcategories share the same table
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_slug` (`slug`),
  KEY `idx_cat_parent` (`parent_id`),
  FULLTEXT KEY `ft_cat_search` (`name`, `description`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `logo` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brand_slug` (`slug`),
  UNIQUE KEY `uq_brand_name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `sku` VARCHAR(64) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `generic_name` VARCHAR(160) NULL,
  `brand_id` INT UNSIGNED NULL,
  `brand_name` VARCHAR(120) NULL,
  `category_id` INT UNSIGNED NULL,
  `subcategory_id` INT UNSIGNED NULL,
  `description` TEXT NULL,
  `active_ingredient` VARCHAR(255) NULL,
  `strength` VARCHAR(80) NULL,
  -- 500mg
  `dosage_form` VARCHAR(80) NULL,
  -- Tablet, Capsule, Syrup
  `pack_size` VARCHAR(80) NULL,
  -- 20 tablets
  `manufacturer` VARCHAR(160) NULL,
  `requires_prescription` TINYINT(1) NOT NULL DEFAULT 0,
  `product_class` ENUM(
    'otc',
    'prescription',
    'restricted',
    'device',
    'supplement',
    'cosmetic'
  ) NOT NULL DEFAULT 'otc',
  `price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `discount_price` DECIMAL(12, 2) NULL,
  `tax_rate` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `stock_qty` INT NOT NULL DEFAULT 0,
  -- denormalised fast-read counter
  `min_stock_level` INT NOT NULL DEFAULT 5,
  `expiry_date` DATE NULL,
  `batch_number` VARCHAR(60) NULL,
  `manufacturing_date` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
  -- admin moderation
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `sales_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `rating_avg` DECIMAL(3, 2) NOT NULL DEFAULT 0.00,
  `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prod_sku` (`pharmacy_id`, `sku`),
  UNIQUE KEY `uq_prod_slug` (`slug`),
  KEY `idx_prod_pharm` (`pharmacy_id`, `is_active`, `deleted_at`),
  KEY `idx_prod_cat` (`category_id`, `subcategory_id`),
  KEY `idx_prod_price` (`price`),
  KEY `idx_prod_stock` (`stock_qty`),
  KEY `idx_prod_expiry` (`expiry_date`),
  KEY `idx_prod_featured`(`is_featured`, `created_at`),
  FULLTEXT KEY `ft_prod_search` (
    `name`,
    `generic_name`,
    `brand_name`,
    `active_ingredient`,
    `description`
  ),
  CONSTRAINT `fk_prod_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE
  SET NULL,
    CONSTRAINT `fk_prod_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `categories`(`id`) ON DELETE
  SET NULL,
    CONSTRAINT `fk_prod_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_img_prod` (`product_id`, `sort_order`),
  CONSTRAINT `fk_img_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Batches drive expiry-aware inventory (FEFO allocation)
CREATE TABLE IF NOT EXISTS `product_batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `batch_number` VARCHAR(60) NOT NULL,
  `manufacturing_date` DATE NULL,
  `expiry_date` DATE NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `cost_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch` (`product_id`, `batch_number`),
  KEY `idx_batch_exp` (`expiry_date`),
  CONSTRAINT `fk_batch_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  5.  INVENTORY LEDGER (append-only audit trail)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `batch_id` BIGINT UNSIGNED NULL,
  `type` ENUM(
    'purchase',
    'sale',
    'return',
    'adjustment',
    'expiry_writeoff',
    'reservation',
    'release',
    'cancellation_restore'
  ) NOT NULL DEFAULT 'adjustment',
  `previous_qty` INT NOT NULL DEFAULT 0,
  `change_qty` INT NOT NULL DEFAULT 0,
  `new_qty` INT NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NULL,
  `reference_type` VARCHAR(40) NULL,
  -- order / pharmacy_order
  `reference_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inv_prod` (`product_id`, `created_at`),
  KEY `idx_inv_pharm`(`pharmacy_id`, `created_at`),
  KEY `idx_inv_type` (`type`),
  CONSTRAINT `fk_inv_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `inventory_alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `alert_type` ENUM('low_stock', 'out_of_stock', 'expiring_90', 'expiring_60', 'expiring_30', 'expired') NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `first_triggered_at` DATETIME NULL,
  `last_notified_at` DATETIME NULL,
  `resolved_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_alert_product_type` (`product_id`, `alert_type`),
  KEY `idx_inventory_alert_pharmacy_active` (`pharmacy_id`, `is_active`, `alert_type`),
  CONSTRAINT `fk_inventory_alert_pharmacy` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_alert_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  6.  CART
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `carts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  -- NULL = guest cart identified by session_key
  `session_key` VARCHAR(80) NULL,
  `status` ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_user` (`user_id`),
  UNIQUE KEY `uq_cart_session`(`session_key`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_prod` (`cart_id`, `product_id`),
  CONSTRAINT `fk_ci_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  7.  ORDERS  (parent order + per-pharmacy sub-orders)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(30) NOT NULL,
  -- IPL-20260928-8F3A21
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM(
    'pending_payment',
    'paid',
    'received',
    'processing',
    'preparing',
    'ready_for_pickup',
    'ready_for_delivery',
    'out_for_delivery',
    'delivered',
    'cancelled',
    'refunded'
  ) NOT NULL DEFAULT 'pending_payment',
  `payment_status` ENUM(
    'pending',
    'processing',
    'successful',
    'failed',
    'refunded',
    'cancelled'
  ) NOT NULL DEFAULT 'pending',
  `fulfilment_method` ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
  `delivery_address_id` BIGINT UNSIGNED NULL,
  `address_snapshot` TEXT NULL,
  -- immutable copy at checkout time
  `delivery_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `coupon_id` BIGINT UNSIGNED NULL,
  `customer_note` VARCHAR(500) NULL,
  `placed_at` DATETIME NULL,
  `paid_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `cancel_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_no` (`order_number`),
  KEY `idx_order_cust` (`customer_id`, `created_at`),
  KEY `idx_order_status`(`status`),
  KEY `idx_order_pay` (`payment_status`),
  CONSTRAINT `fk_order_cust` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_order_address` FOREIGN KEY (`delivery_address_id`) REFERENCES `user_addresses`(`id`) ON DELETE
  SET NULL,
    CONSTRAINT `fk_order_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  -- snapshot
  `sku` VARCHAR(64) NOT NULL,
  -- snapshot
  `image` VARCHAR(255) NULL,
  `unit_price` DECIMAL(12, 2) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `discount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `tax_rate` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(12, 2) NOT NULL,
  `requires_prescription` TINYINT(1) NOT NULL DEFAULT 0,
  `prescription_status` ENUM('not_required', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'not_required',
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_pharm` (`pharmacy_id`),
  KEY `idx_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_oi_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- The pharmacy's own view of a slice of a parent order
CREATE TABLE IF NOT EXISTS `pharmacy_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `sub_order_number` VARCHAR(30) NOT NULL,
  -- IPL-20260928-8F3A21-A
  `status` ENUM(
    'pending_payment',
    'paid',
    'received',
    'processing',
    'preparing',
    'ready_for_pickup',
    'ready_for_delivery',
    'out_for_delivery',
    'delivered',
    'cancelled',
    'refunded'
  ) NOT NULL DEFAULT 'pending_payment',
  `items_subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `delivery_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `platform_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  -- commission on this slice
  `pharmacy_earnings` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `accept_deadline` DATETIME NULL,
  `pharmacy_note` VARCHAR(500) NULL,
  `accepted_at` DATETIME NULL,
  `ready_at` DATETIME NULL,
  `delivered_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `cancel_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_so_number` (`sub_order_number`),
  UNIQUE KEY `uq_so_order_pharm` (`order_id`, `pharmacy_id`),
  KEY `idx_so_pharm` (`pharmacy_id`, `status`, `created_at`),
  CONSTRAINT `fk_so_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_so_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `scope` ENUM('order', 'pharmacy_order') NOT NULL DEFAULT 'order',
  `scope_id` BIGINT UNSIGNED NULL,
  `from_status` VARCHAR(30) NULL,
  `to_status` VARCHAR(30) NOT NULL,
  `note` VARCHAR(500) NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `actor_role` VARCHAR(30) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_osh_order` (`order_id`, `created_at`),
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_osh_actor` FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  8.  PAYMENTS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  -- paystack, flutterwave, bank_transfer, cash_on_delivery
  `name` VARCHAR(80) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `is_sandbox` TINYINT(1) NOT NULL DEFAULT 1,
  `credentials` TEXT NULL,
  -- JSON, encrypted at rest
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gw_code` (`code`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `gateway_code` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `status` ENUM(
    'pending',
    'processing',
    'successful',
    'failed',
    'refunded',
    'cancelled'
  ) NOT NULL DEFAULT 'pending',
  `reference` VARCHAR(80) NULL,
  -- platform reference
  `gateway_response` JSON NULL,
  -- raw gateway payload (masked)
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_order` (`order_id`),
  KEY `idx_pay_status` (`status`),
  KEY `idx_pay_ref` (`reference`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` BIGINT UNSIGNED NULL,
  `gateway_code` VARCHAR(30) NOT NULL,
  `event` VARCHAR(40) NOT NULL,
  -- initialized, redirect, webhook, refund
  `gateway_ref` VARCHAR(120) NULL,
  `amount` DECIMAL(12, 2) NULL,
  `status` VARCHAR(40) NULL,
  `payload` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pt_pay` (`payment_id`),
  KEY `idx_pt_event`(`gateway_code`, `event`),
  CONSTRAINT `fk_pt_pay` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `payment_id` BIGINT UNSIGNED NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `gateway_ref` VARCHAR(120) NULL,
  `processed_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ref_order` (`order_id`),
  CONSTRAINT `fk_ref_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_pay` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE
  SET NULL,
    CONSTRAINT `fk_ref_user` FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
--  9.  DELIVERY
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `pharmacy_order_id` BIGINT UNSIGNED NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `personnel_id` BIGINT UNSIGNED NULL,
  `tracking_number` VARCHAR(40) NULL,
  `status` ENUM(
    'pending_assignment',
    'assigned',
    'picked_up',
    'in_transit',
    'delivered',
    'failed_delivery',
    'cancelled'
  ) NOT NULL DEFAULT 'pending_assignment',
  `pickup_address` TEXT NULL,
  `dropoff_address` TEXT NULL,
  `pickup_latitude` DECIMAL(10, 7) NULL,
  `pickup_longitude` DECIMAL(10, 7) NULL,
  `dropoff_latitude` DECIMAL(10, 7) NULL,
  `dropoff_longitude` DECIMAL(10, 7) NULL,
  `distance_km` DECIMAL(6, 2) NULL,
  `delivery_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `otp_code` CHAR(6) NULL,
  -- customer confirms receipt with this
  `proof_image` VARCHAR(255) NULL,
  `signature_image` VARCHAR(255) NULL,
  `delivery_note` VARCHAR(500) NULL,
  `failure_reason` VARCHAR(255) NULL,
  `assigned_at` DATETIME NULL,
  `picked_up_at` DATETIME NULL,
  `delivered_at` DATETIME NULL,
  `confirmed_by_customer_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_del_order` (`order_id`, `pharmacy_id`),
  KEY `idx_del_personnel` (`personnel_id`, `status`),
  KEY `idx_del_status` (`status`),
  CONSTRAINT `fk_del_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_del_po` FOREIGN KEY (`pharmacy_order_id`) REFERENCES `pharmacy_orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_del_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_del_person` FOREIGN KEY (`personnel_id`) REFERENCES `users`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `delivery_personnel` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `pharmacy_id` BIGINT UNSIGNED NULL,
  -- NULL = platform-wide rider
  `vehicle_type` VARCHAR(40) NULL,
  -- motorcycle, bicycle, car, van
  `plate_number` VARCHAR(20) NULL,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `max_active_deliveries` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dp_user` (`user_id`),
  KEY `idx_dp_avail` (`is_available`),
  CONSTRAINT `fk_dp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dp_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `delivery_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(30) NULL,
  `to_status` VARCHAR(30) NOT NULL,
  `note` VARCHAR(500) NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `latitude` DECIMAL(10, 7) NULL,
  `longitude` DECIMAL(10, 7) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dsh_del` (`delivery_id`, `created_at`),
  CONSTRAINT `fk_dsh_del` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
-- 10.  PRESCRIPTIONS (compliance)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(190) NULL,
  `notes` VARCHAR(500) NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `review_note` VARCHAR(500) NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rx_user` (`user_id`, `status`),
  KEY `idx_rx_order`(`order_id`),
  CONSTRAINT `fk_rx_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rx_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE
  SET NULL,
    CONSTRAINT `fk_rx_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
-- 11.  ENGAGEMENT
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `entity_type` ENUM('product', 'pharmacy') NOT NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `pharmacy_id` BIGINT UNSIGNED NULL,
  `order_id` BIGINT UNSIGNED NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NULL,
  `body` TEXT NULL,
  `service_rating` TINYINT UNSIGNED NULL,
  `availability_rating` TINYINT UNSIGNED NULL,
  `delivery_rating` TINYINT UNSIGNED NULL,
  `status` ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'published',
  `moderated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rev_product` (`product_id`, `status`),
  KEY `idx_rev_pharmacy` (`pharmacy_id`, `status`),
  KEY `idx_rev_user` (`user_id`),
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `review_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `review_id` BIGINT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ri_rev` (`review_id`),
  CONSTRAINT `fk_ri_rev` FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `wishlists` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(80) NOT NULL DEFAULT 'My Wishlist',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wl_user_name` (`user_id`, `name`),
  CONSTRAINT `fk_wl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `wishlist_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wishlist_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wli` (`wishlist_id`, `product_id`),
  CONSTRAINT `fk_wli_wl` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wli_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  -- order.placed, payment.success, stock.low ...
  `title` VARCHAR(160) NOT NULL,
  `body` VARCHAR(500) NULL,
  `link` VARCHAR(255) NULL,
  `entity_type` VARCHAR(40) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `channel` ENUM('inapp', 'email', 'sms') NOT NULL DEFAULT 'inapp',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
-- 12.  MARKETING: COUPONS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `description` VARCHAR(255) NULL,
  `type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(12, 2) NOT NULL,
  `min_order_value` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `max_discount` DECIMAL(12, 2) NULL,
  `usage_limit` INT UNSIGNED NULL,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `per_user_limit` INT UNSIGNED NOT NULL DEFAULT 3,
  `pharmacy_id` BIGINT UNSIGNED NULL,
  -- NULL = platform-wide
  `starts_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`),
  KEY `idx_coupon_active` (`is_active`, `expires_at`),
  CONSTRAINT `fk_coupon_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `coupon_usages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coupon_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `discount` DECIMAL(12, 2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cu_coupon` (`coupon_id`),
  KEY `idx_cu_user` (`user_id`),
  CONSTRAINT `fk_cu_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cu_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cu_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
-- 13.  FINANCE: COMMISSION / WALLET / PAYOUT
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pharmacy_order_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `base_amount` DECIMAL(12, 2) NOT NULL,
  `rate_percent` DECIMAL(5, 2) NOT NULL,
  `fixed_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `commission_amount` DECIMAL(12, 2) NOT NULL,
  `pharmacy_earnings` DECIMAL(12, 2) NOT NULL,
  `status` ENUM('pending', 'credited', 'reversed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comm_po` (`pharmacy_order_id`),
  KEY `idx_comm_pharm` (`pharmacy_id`, `status`),
  CONSTRAINT `fk_comm_po` FOREIGN KEY (`pharmacy_order_id`) REFERENCES `pharmacy_orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comm_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comm_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `pharmacy_wallets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `balance` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
  `pending_balance` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
  -- not yet released
  `total_earned` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
  `total_paid` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wallet_pharm` (`pharmacy_id`),
  CONSTRAINT `fk_wallet_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` BIGINT UNSIGNED NOT NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM(
    'credit',
    'debit',
    'payout',
    'refund_deduction',
    'adjustment'
  ) NOT NULL,
  `amount` DECIMAL(14, 2) NOT NULL,
  `balance_after` DECIMAL(14, 2) NOT NULL,
  `description` VARCHAR(255) NULL,
  `reference_type` VARCHAR(40) NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wtx_wallet` (`wallet_id`, `created_at`),
  CONSTRAINT `fk_wtx_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `pharmacy_wallets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wtx_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `payouts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(40) NOT NULL,
  `pharmacy_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(14, 2) NOT NULL,
  `bank_name` VARCHAR(120) NULL,
  `account_number` VARCHAR(32) NULL,
  `status` ENUM(
    'requested',
    'approved',
    'processing',
    'paid',
    'rejected',
    'cancelled'
  ) NOT NULL DEFAULT 'requested',
  `notes` VARCHAR(500) NULL,
  `requested_by` BIGINT UNSIGNED NULL,
  `processed_by` BIGINT UNSIGNED NULL,
  `processed_at` DATETIME NULL,
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payout_ref` (`reference`),
  KEY `idx_payout_status` (`status`, `created_at`),
  CONSTRAINT `fk_payout_pharm` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
-- 14.  CMS / SETTINGS CONTENT
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(150) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `body` LONGTEXT NULL,
  `meta_title` VARCHAR(180) NULL,
  `meta_description` VARCHAR(255) NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_slug` (`slug`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(160) NOT NULL,
  `subtitle` VARCHAR(255) NULL,
  `image` VARCHAR(255) NULL,
  `mobile_image` VARCHAR(255) NULL,
  `button_text` VARCHAR(60) NULL,
  `button_link` VARCHAR(255) NULL,
  `placement` VARCHAR(40) NOT NULL DEFAULT 'home_slider',
  -- home_slider, home_mid, pharmacy_top
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `starts_at` DATETIME NULL,
  `ends_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banner_placement` (`placement`, `is_active`, `sort_order`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(80) NOT NULL DEFAULT 'General',
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faq_active` (`is_active`, `sort_order`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(32) NULL,
  `subject` VARCHAR(190) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('new', 'read', 'replied', 'archived') NOT NULL DEFAULT 'new',
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cm_status` (`status`, `created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
-- 15.  AUDIT / RATE LIMIT / SESSIONS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `actor_name` VARCHAR(120) NULL,
  `actor_role` VARCHAR(30) NULL,
  `action` VARCHAR(80) NOT NULL,
  -- pharmacy.approved, product.price_updated ...
  `entity_type` VARCHAR(40) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `description` VARCHAR(500) NULL,
  `old_value` JSON NULL,
  `new_value` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_user` (`user_id`, `created_at`),
  KEY `idx_audit_action` (`action`, `created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(190) NOT NULL,
  -- email or phone
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NULL,
  `successful` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_ident` (`identifier`, `created_at`),
  KEY `idx_la_ip` (`ip_address`, `created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket_key` VARCHAR(160) NOT NULL,
  -- e.g. search:9.12.3.4
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_started_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_key` (`bucket_key`),
  KEY `idx_rate_exp` (`expires_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
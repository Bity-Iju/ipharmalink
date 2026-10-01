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

INSERT INTO `roles` (`name`, `label`, `description`, `is_system`)
VALUES ('wholesale_supplier', 'Wholesale Supplier', 'Supplies pharmaceutical products in bulk to pharmacies', 1)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `description` = VALUES(`description`);

INSERT IGNORE INTO `permissions` (`name`, `group_name`, `description`)
VALUES ('supplier.dashboard.view', 'supplier', 'View own supplier workspace');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.name = 'wholesale_supplier' AND p.name = 'supplier.dashboard.view';
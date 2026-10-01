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
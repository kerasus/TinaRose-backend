CREATE TABLE IF NOT EXISTS `equipments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `downtime_reasons` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `productions`
ADD COLUMN `downtime_reason_id` bigint(20) UNSIGNED NULL DEFAULT NULL AFTER `description`;
ALTER TABLE `productions`
ADD CONSTRAINT `productions_downtime_reason_id_foreign`
FOREIGN KEY (`downtime_reason_id`) REFERENCES `downtime_reasons` (`id`)
ON DELETE SET NULL;
ALTER TABLE `productions`
ADD COLUMN `downtime_duration` int(11) NULL DEFAULT NULL COMMENT 'مدت توقف به دقیقه' AFTER `downtime_reason_id`;
ALTER TABLE `productions`
ADD COLUMN `equipment_id` bigint(20) UNSIGNED NULL DEFAULT NULL AFTER `downtime_duration`;
ALTER TABLE `productions`
ADD CONSTRAINT `productions_equipment_id_foreign`
FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`)
ON DELETE SET NULL;

ALTER TABLE `colors`
ADD COLUMN `fabric_id` bigint(20) UNSIGNED NULL DEFAULT NULL AFTER `color_hex`;
ALTER TABLE `colors`
ADD CONSTRAINT `colors_fabric_id_foreign`
FOREIGN KEY (`fabric_id`) REFERENCES `fabrics` (`id`)
ON DELETE SET NULL;

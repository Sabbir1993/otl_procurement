/*
SQLyog Community v13.3.1 (64 bit)
MySQL - 8.0.30-commercial : Database - otl_erp_uat_v2
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`otl_erp_uat_v2` /*!40100 DEFAULT CHARACTER SET latin1 */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `otl_erp_uat_v2`;

/*Table structure for table `brand` */

DROP TABLE IF EXISTS `brand`;

CREATE TABLE `brand` (
  `id` int NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `is_bulk` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `brand_name_UNIQUE` (`brand_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2758 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `customer` */

DROP TABLE IF EXISTS `customer`;

CREATE TABLE `customer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_short_name` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_officer_id` int DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `customer_type_id` int NOT NULL,
  `contact_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `website` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_name_id` int DEFAULT NULL,
  `division_id` int NOT NULL,
  `zone_id` int NOT NULL,
  `name_of_md` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_id` int DEFAULT NULL,
  `no_of_production_lines` int DEFAULT NULL,
  `business_with_oil_since` date DEFAULT NULL,
  `no_of_merchandiser` double DEFAULT NULL,
  `no_of_brands` double DEFAULT NULL,
  `prod_capacity_per_month` double DEFAULT NULL,
  `party_bond_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_reg_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bin_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `erc_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `irc_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exp_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boi_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sap_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_officer_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_officer_mobile` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_officer_email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` decimal(20,15) DEFAULT NULL,
  `latitude` decimal(20,15) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `is_bulk` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_customer_bank_id` (`bank_id`),
  KEY `idx_customer_short_name` (`customer_short_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1888 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `export_ci` */

DROP TABLE IF EXISTS `export_ci`;

CREATE TABLE `export_ci` (
  `id` int NOT NULL AUTO_INCREMENT,
  `export_lc_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `invoice_no` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_date` date NOT NULL,
  `total` double(15,4) NOT NULL,
  `discount_percent` double NOT NULL DEFAULT '0',
  `discount_amount` double NOT NULL DEFAULT '0',
  `total_after_discount` double NOT NULL,
  `notifying_party` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_id` int DEFAULT NULL,
  `delivery_company_id` int DEFAULT NULL,
  `delivery_address` int DEFAULT NULL,
  `is_note_show` enum('Yes','No') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `exp_ci_trade_terms_id` int DEFAULT NULL,
  `exp_ci_status_id` int NOT NULL,
  `export_flow_status_id` int DEFAULT NULL,
  `trade_terms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pi_order_no` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pi_style_no` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `delivery_from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_date` date DEFAULT NULL,
  `bank_account_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `is_bulk` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no_UNIQUE` (`invoice_no`),
  KEY `export_ci_customer_id_foreign` (`customer_id`),
  KEY `export_lc_id` (`export_lc_id`),
  KEY `idx_export_ci_export_lc_id` (`export_lc_id`),
  CONSTRAINT `export_ci_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customer-ccc` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65536 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `export_pi` */

DROP TABLE IF EXISTS `export_pi`;

CREATE TABLE `export_pi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exp_pi_type_id` int NOT NULL DEFAULT '1',
  `is_merge_pi` tinyint NOT NULL DEFAULT '0',
  `remarks` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'for merge pi ',
  `pi_number` varbinary(255) NOT NULL,
  `original_pi_number` text COLLATE utf8mb4_unicode_ci,
  `pi_date` date NOT NULL,
  `pi_created_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `pi_created_date_backup` date DEFAULT NULL COMMENT 'values before pi_created_date correction',
  `export_booking_id` int DEFAULT NULL,
  `bdt_rate` double DEFAULT NULL,
  `exp_organization_id` int DEFAULT NULL,
  `marketing_person_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `pi_to_id` int DEFAULT NULL,
  `brand_id` int DEFAULT NULL,
  `old_brand_id` int DEFAULT NULL,
  `style_id` int DEFAULT NULL,
  `po_number_id` int DEFAULT NULL,
  `style_po_no` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `sales_mode_id` int DEFAULT NULL,
  `exp_lc_tenure_id` int DEFAULT NULL,
  `percent_in_spl` double(15,4) DEFAULT NULL,
  `exp_pi_status_id` int DEFAULT NULL,
  `export_flow_status_id` int DEFAULT NULL,
  `export_order_status_id` int NOT NULL DEFAULT '1',
  `dependant_pi_id` int DEFAULT NULL,
  `cancellation_date` date DEFAULT NULL,
  `cancellation_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `total_pi_value` double(15,4) DEFAULT NULL,
  `total_spl_value` double(15,4) DEFAULT NULL,
  `total_actual_value` double(15,4) DEFAULT NULL,
  `total_weight` int DEFAULT NULL,
  `total_gross_weight` int DEFAULT NULL,
  `bank_account_id` int DEFAULT NULL,
  `applicant` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hs_code` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exp_payment_mode_id` int DEFAULT NULL,
  `exp_charge_type_id` int DEFAULT NULL,
  `amount_type_id` int DEFAULT NULL,
  `exp_pi_trade_terms_id` int DEFAULT NULL,
  `charge_percentage` double NOT NULL DEFAULT '0',
  `charge_value` double(15,4) NOT NULL DEFAULT '0.0000',
  `discount_percentage` double NOT NULL DEFAULT '0',
  `discount_value` double(15,4) NOT NULL DEFAULT '0.0000',
  `after_discount_total` double(15,4) NOT NULL DEFAULT '0.0000',
  `total_cpt_value` double(15,4) NOT NULL,
  `old_total_cpt_value` double(15,4) NOT NULL,
  `is_sales_returned` tinyint DEFAULT NULL,
  `tt_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_and_condition` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `pi_number_backup_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_bulk` int DEFAULT '0',
  `old_erp_booking_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_open_for_customer_list` tinyint DEFAULT '1',
  `is_open_for_export_pi_list` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `marketing_person_id` (`marketing_person_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_exp_pi_status_id` (`exp_pi_status_id`),
  KEY `idx_pi_date` (`pi_date`),
  KEY `exp_organization_id` (`exp_organization_id`),
  KEY `brand_id` (`brand_id`),
  KEY `idx_export_pi_customer_id` (`customer_id`),
  KEY `idx_export_pi_number` (`pi_number`),
  KEY `idx_pi_number` (`pi_number`),
  KEY `idx_old_erp_booking_number` (`old_erp_booking_number`),
  KEY `idx_export_pi_old_booking` (`old_erp_booking_number`,`id`),
  KEY `idx_export_pi_lookup` (`pi_number`,`id`),
  KEY `idx_export_pi_created_date` (`pi_created_date`)
) ENGINE=InnoDB AUTO_INCREMENT=133122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `marketing_person` */

DROP TABLE IF EXISTS `marketing_person`;

CREATE TABLE `marketing_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nick_name` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_name_old` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reporting_to` int DEFAULT NULL,
  `team_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation_id` int DEFAULT NULL,
  `short_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_mobile_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `joining_date` date DEFAULT NULL,
  `experience_in_year_prev` int DEFAULT NULL,
  `academic_qualification` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `major_subject` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `is_bulk` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  UNIQUE KEY `employee_id_UNIQUE` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `mktp_target` */

DROP TABLE IF EXISTS `mktp_target`;

CREATE TABLE `mktp_target` (
  `id` int NOT NULL AUTO_INCREMENT,
  `marketing_person_id` int NOT NULL,
  `export_pi_id` text CHARACTER SET latin1 COLLATE latin1_swedish_ci COMMENT 'moved to child table - can be deleted upon verified ',
  `target_year` int NOT NULL,
  `target_month` int NOT NULL,
  `customer_id_old` int DEFAULT NULL,
  `brand_id_old` int DEFAULT NULL,
  `mktp_order_type_id` int DEFAULT NULL,
  `unit_id_old` int DEFAULT NULL,
  `total_target` double(15,4) DEFAULT NULL,
  `order_expected_date` date DEFAULT NULL,
  `lc_expected_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `is_updatable` tinyint DEFAULT '1',
  `is_deletable` tinyint DEFAULT '0',
  `full_date` date GENERATED ALWAYS AS (cast(concat(`target_year`,_utf8mb3'-',convert(lpad(`target_month`,2,_latin1'0') using utf8mb3),_utf8mb3'-01') as date)) VIRTUAL,
  PRIMARY KEY (`id`),
  KEY `marketing_person_id` (`marketing_person_id`),
  KEY `target_year` (`target_year`),
  KEY `target_month` (`target_month`)
) ENGINE=InnoDB AUTO_INCREMENT=3064 DEFAULT CHARSET=latin1;

/*Table structure for table `product` */

DROP TABLE IF EXISTS `product`;

CREATE TABLE `product` (
  `id` int NOT NULL,
  `is_clone` tinyint DEFAULT NULL,
  `clone_source_product_id` int DEFAULT NULL,
  `composition_id` int NOT NULL,
  `old_composition_id` int DEFAULT NULL,
  `product_range_id` int DEFAULT NULL,
  `article` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `article_20250807` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_type_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_meta_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_meta_name_check` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_match` tinyint(1) NOT NULL DEFAULT '0',
  `is_d_grade` tinyint DEFAULT '0' COMMENT '1= d grade,0=not d grade',
  `standard_adhesive_gsm` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interlining_type_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `purchase` tinyint DEFAULT NULL COMMENT 'Operation Scope\n',
  `sell` tinyint DEFAULT NULL COMMENT 'Operation Scope\n',
  `produce` tinyint DEFAULT NULL COMMENT 'Operation Scope\n',
  `convert` tinyint DEFAULT NULL COMMENT 'Operation Scope\n',
  `use` tinyint DEFAULT NULL COMMENT 'Use (Non Production)',
  `scrap` tinyint DEFAULT NULL,
  `initial_reorder_level` int NOT NULL,
  `unit_id` int NOT NULL,
  `sales_unit_id` int DEFAULT NULL COMMENT 'for export module',
  `demand_level_id` int DEFAULT NULL,
  `hs_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_id` int DEFAULT NULL,
  `color_id_06_aug_25` int DEFAULT NULL,
  `width` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width_15sep26` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width_20250824` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width_m` double DEFAULT NULL,
  `roll_size` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gsm` int DEFAULT NULL,
  `gsm_20260218` int DEFAULT NULL,
  `accessories_weight` double DEFAULT NULL,
  `min_qty_to_consider_undercutting` int DEFAULT NULL COMMENT 'Minimum Quantity to Consider Undercutting',
  `max_commission_per_yard` int DEFAULT NULL COMMENT 'Maximum Commission per Yard',
  `initial_markup` int DEFAULT NULL,
  `picture_path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `construction` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bonding_strength` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temperature` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pressure` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stretch` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_hand_feel_id` int DEFAULT NULL,
  `fiber_binding_process` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `washing_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `product_time_id` int DEFAULT NULL,
  `product_temperature_id` int DEFAULT NULL,
  `product_pressure_id` int DEFAULT NULL,
  `product_washing_type_id` int DEFAULT NULL,
  `product_bonding_strength_id` int DEFAULT NULL,
  `product_stretch_id` int DEFAULT NULL,
  `product_coating_material_id` int DEFAULT NULL,
  `product_coating_type_id` int DEFAULT NULL,
  `product_oeko_tx_crt_id` int DEFAULT NULL,
  `width_unit_id` int DEFAULT NULL,
  `width_value` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_value` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diameter_unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diameter_value` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thickness_unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thickness_value` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_special_attribute_id` int DEFAULT NULL,
  `is_changed_by_autoname_dm` tinyint(1) NOT NULL DEFAULT '0',
  `changed_fields` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `width_unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_width_id` int DEFAULT NULL,
  UNIQUE KEY `article_id_UNIQUE` (`article`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  KEY `composition_id` (`composition_id`),
  KEY `product_type_id` (`product_type_id`),
  KEY `unit_id` (`unit_id`),
  KEY `sales_unit_id` (`sales_unit_id`),
  KEY `idx_product_filters` (`active`,`purchase`,`product_type_id`),
  KEY `idx_product_color_id` (`color_id`),
  KEY `idx_product_interlining_type_id` (`interlining_type_id`),
  KEY `idx_product_demand_level_id` (`demand_level_id`),
  CONSTRAINT `product_ibfk_1` FOREIGN KEY (`composition_id`) REFERENCES `composition` (`id`),
  CONSTRAINT `product_ibfk_2` FOREIGN KEY (`product_type_id`) REFERENCES `product_type` (`id`),
  CONSTRAINT `product_ibfk_3` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`),
  CONSTRAINT `product_ibfk_4` FOREIGN KEY (`sales_unit_id`) REFERENCES `unit` (`id`),
  CONSTRAINT `product_ibfk_5` FOREIGN KEY (`composition_id`) REFERENCES `composition` (`id`),
  CONSTRAINT `product_ibfk_6` FOREIGN KEY (`product_type_id`) REFERENCES `product_type` (`id`),
  CONSTRAINT `product_ibfk_7` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`),
  CONSTRAINT `product_ibfk_8` FOREIGN KEY (`sales_unit_id`) REFERENCES `unit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `product_req_from_mktp` */

DROP TABLE IF EXISTS `product_req_from_mktp`;

CREATE TABLE `product_req_from_mktp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `marketing_person_id` int NOT NULL,
  `product_req_from_mktp_date` date DEFAULT NULL,
  `projection_year` int NOT NULL,
  `projection_month` int NOT NULL,
  `customer_id_old` int DEFAULT NULL,
  `brand_id_old` int DEFAULT NULL,
  `product_id_old` int DEFAULT NULL,
  `unit_id_old` int DEFAULT NULL,
  `quantity_old` double DEFAULT NULL,
  `quantity_yd_old` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `date_idx` (`product_req_from_mktp_date`),
  KEY `projection_year` (`projection_year`),
  KEY `projection_month` (`projection_month`),
  KEY `idx_prod_req_mktp_projection_month` (`projection_month`),
  KEY `idx_prod_req_mktp_projection_year` (`projection_year`),
  KEY `idx_prod_req_mktp_deleted_at` (`deleted_at`),
  KEY `idx_prod_req_mktp_month_year` (`projection_month`,`projection_year`)
) ENGINE=InnoDB AUTO_INCREMENT=2108 DEFAULT CHARSET=latin1;

/*Table structure for table `product_req_from_mktp_dtl` */

DROP TABLE IF EXISTS `product_req_from_mktp_dtl`;

CREATE TABLE `product_req_from_mktp_dtl` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_req_from_mktp_id` int unsigned DEFAULT NULL,
  `mktp_target_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `brand_id` int DEFAULT NULL,
  `old_brand_id` int DEFAULT NULL,
  `export_pi_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `req_qty` double DEFAULT NULL,
  `req_qty_mtr` double DEFAULT NULL,
  `req_qty_yrd` double DEFAULT NULL,
  `order_exp_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `product_req_from_mktp_id` (`product_req_from_mktp_id`),
  KEY `idx_prod_req_mktp_dtl_deleted_at` (`deleted_at`),
  KEY `mktp_target_id` (`mktp_target_id`),
  KEY `customer_id` (`customer_id`),
  KEY `brand_id` (`brand_id`),
  KEY `export_pi_id` (`export_pi_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31355 DEFAULT CHARSET=latin1;

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `log_viewer` tinyint DEFAULT '0',
  `force_password` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = default, lifetime 1 time will be used. if 1 user must change password\\\\n0 = after change password',
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `password_history` text COLLATE utf8mb4_unicode_ci,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_otl_user` tinyint(1) DEFAULT '0',
  `department_id` int DEFAULT NULL,
  `designation_id` int DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_expires_at` timestamp NULL DEFAULT NULL,
  `otp_verified` tinyint DEFAULT NULL,
  `trusted_device_token` text COLLATE utf8mb4_unicode_ci,
  `trusted_device_expires_at` timestamp NULL DEFAULT NULL,
  `fcm_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

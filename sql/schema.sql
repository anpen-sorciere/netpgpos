-- netpgpos schema export
-- generated at 2025-10-20T22:31:20+09:00

SET FOREIGN_KEY_CHECKS=0;

-- -----------------------------
-- Table structure for `api_rate_limits`
-- -----------------------------
CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scope_key` varchar(50) NOT NULL,
  `limit_type` enum('hour','day') NOT NULL,
  `request_count` int(11) NOT NULL DEFAULT '0',
  `reset_time` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_scope_limit` (`scope_key`,`limit_type`),
  KEY `idx_reset_time` (`reset_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `base_api_tokens`
-- -----------------------------
CREATE TABLE `base_api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scope_key` varchar(50) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text NOT NULL,
  `access_expires` int(11) NOT NULL,
  `refresh_expires` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scope_key` (`scope_key`),
  KEY `idx_scope` (`scope_key`),
  KEY `idx_access_expires` (`access_expires`),
  KEY `idx_refresh_expires` (`refresh_expires`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `base_order_items`
-- -----------------------------
CREATE TABLE `base_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_order_id` varchar(50) NOT NULL,
  `product_id` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=413 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `base_orders`
-- -----------------------------
CREATE TABLE `base_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_order_id` varchar(50) NOT NULL,
  `order_date` datetime DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `base_order_id` (`base_order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=513 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `base_rate_limit`
-- -----------------------------
CREATE TABLE `base_rate_limit` (
  `id` int(11) NOT NULL DEFAULT '1',
  `requests_per_hour` int(11) DEFAULT '1000',
  `requests_per_minute` int(11) DEFAULT '100',
  `current_hour_requests` int(11) DEFAULT '0',
  `current_minute_requests` int(11) DEFAULT '0',
  `last_hour_reset` int(11) DEFAULT '0',
  `last_minute_reset` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `base_system_logs`
-- -----------------------------
CREATE TABLE `base_system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_level` enum('INFO','WARNING','ERROR','CRITICAL') NOT NULL,
  `scope_key` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_level` (`log_level`),
  KEY `idx_scope_key` (`scope_key`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `card_sales_temp_tbl`
-- -----------------------------
CREATE TABLE `card_sales_temp_tbl` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `data_day` int(8) NOT NULL COMMENT '売上仕入実施日',
  `sales_amount` int(11) NOT NULL COMMENT '売上仕入金額',
  `purchase_cost` int(11) NOT NULL COMMENT '仕入れ買取合計',
  `personnel_cost` int(11) NOT NULL COMMENT '人件費1日合計',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=378 DEFAULT CHARSET=utf8mb4 COMMENT='カード売上仕入れ一時テーブル';

-- -----------------------------
-- Table structure for `cast_mst`
-- -----------------------------
CREATE TABLE `cast_mst` (
  `cast_id` int(11) NOT NULL AUTO_INCREMENT,
  `cast_name` varchar(50) NOT NULL,
  `cast_yomi` varchar(30) NOT NULL,
  `real_name` varchar(50) NOT NULL,
  `yomigana` varchar(50) NOT NULL,
  `birthday` varchar(10) NOT NULL,
  `address` varchar(200) NOT NULL,
  `tel1` varchar(15) NOT NULL,
  `tel2` varchar(15) NOT NULL,
  `station` varchar(50) NOT NULL COMMENT '最寄り駅',
  `tc` varchar(5) NOT NULL COMMENT '交通費(TravelCost)',
  `joinday` varchar(10) NOT NULL COMMENT '入店日',
  `cast_type` int(1) NOT NULL COMMENT '0:キャスト 1:スタッフ 2:ゲスト',
  `dropday` varchar(10) NOT NULL COMMENT '退店日',
  `drop_flg` int(11) NOT NULL DEFAULT '0' COMMENT '0:在籍中 1:退店済',
  PRIMARY KEY (`cast_id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `category_mst`
-- -----------------------------
CREATE TABLE `category_mst` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `cost_temp_tbl`
-- -----------------------------
CREATE TABLE `cost_temp_tbl` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `shop_id` int(2) NOT NULL,
  `data_day` varchar(8) NOT NULL COMMENT '売上仕入実施日',
  `cost_flg` int(2) NOT NULL COMMENT 'コストフラグ(人件費0経費1)',
  `amount` int(11) NOT NULL COMMENT '売上仕入金額',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='カード売上仕入れ一時テーブル';

-- -----------------------------
-- Table structure for `error_statistics`
-- -----------------------------
CREATE TABLE `error_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `error_type` varchar(100) NOT NULL,
  `error_message` text NOT NULL,
  `scope_key` varchar(50) DEFAULT NULL,
  `http_code` int(11) DEFAULT NULL,
  `occurred_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_error_type` (`error_type`),
  KEY `idx_scope_key` (`scope_key`),
  KEY `idx_occurred_at` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `hourly_rate_mst`
-- -----------------------------
CREATE TABLE `hourly_rate_mst` (
  `hourly_rate` int(11) NOT NULL COMMENT '時給',
  `regular_work` int(11) NOT NULL COMMENT '通常勤務',
  `short_time_work` int(11) NOT NULL COMMENT '短時間勤務',
  PRIMARY KEY (`hourly_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `item_mst`
-- -----------------------------
CREATE TABLE `item_mst` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(50) NOT NULL,
  `item_yomi` varchar(255) NOT NULL,
  `category` int(3) NOT NULL,
  `price` int(11) NOT NULL COMMENT '販売価格',
  `back_price` int(11) NOT NULL COMMENT 'キャストへのバック金額',
  `cost` int(11) NOT NULL COMMENT '仕入価格',
  `tax_type_id` int(11) NOT NULL,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=350 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `month_hourly_rate`
-- -----------------------------
CREATE TABLE `month_hourly_rate` (
  `rate_id` int(11) NOT NULL AUTO_INCREMENT,
  `cast_id` int(11) NOT NULL,
  `yyyymm` int(6) NOT NULL COMMENT 'yyyymmを数字6桁で表記',
  `hour_rate` int(6) NOT NULL COMMENT 'その月の時給',
  PRIMARY KEY (`rate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `online_month`
-- -----------------------------
CREATE TABLE `online_month` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `cast_id` int(11) NOT NULL,
  `online_ym` varchar(6) NOT NULL,
  `online_amount` int(11) NOT NULL,
  `is_paid` tinyint(4) NOT NULL,
  `paid_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `order_item_events`
-- -----------------------------
CREATE TABLE `order_item_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `event_type` enum('created','qty_updated','started','served','canceled') NOT NULL,
  `event_qty` int(11) DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_created` (`order_item_id`,`created_at`),
  CONSTRAINT `fk_events_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `order_items`
-- -----------------------------
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `unit_price` int(11) NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL,
  `canceled_quantity` int(11) NOT NULL DEFAULT '0',
  `status` enum('pending','in_progress','served','canceled') NOT NULL DEFAULT 'pending',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`),
  KEY `idx_item` (`item_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `order_table_mst`
-- -----------------------------
CREATE TABLE `order_table_mst` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shop_utype` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `table_label` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_table` (`shop_utype`,`table_number`),
  KEY `idx_shop_active` (`shop_utype`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `orders`
-- -----------------------------
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_utype` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `device_session_id` varchar(128) DEFAULT NULL,
  `status` enum('pending','in_progress','served','canceled') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_status_created` (`shop_utype`,`status`,`created_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `pay_tbl`
-- -----------------------------
CREATE TABLE `pay_tbl` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cast_id` int(11) NOT NULL COMMENT 'キャストID',
  `set_month` varchar(6) NOT NULL COMMENT '対象年月(YYYYMM)',
  `pay` int(11) NOT NULL COMMENT '時間単価',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=450 DEFAULT CHARSET=utf8mb4 COMMENT='時給テーブル';

-- -----------------------------
-- Table structure for `payment_mst`
-- -----------------------------
CREATE TABLE `payment_mst` (
  `payment_type` int(11) NOT NULL AUTO_INCREMENT,
  `payment_name` varchar(50) NOT NULL,
  PRIMARY KEY (`payment_type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `receipt_detail_tbl`
-- -----------------------------
CREATE TABLE `receipt_detail_tbl` (
  `receipt_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(2) NOT NULL,
  `receipt_id` bigint(12) NOT NULL,
  `receipt_day` varchar(30) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL COMMENT '数量',
  `price` int(11) NOT NULL,
  `cast_id` int(11) NOT NULL COMMENT 'バック対象ならキャストID',
  `cast_back_price` int(11) NOT NULL COMMENT 'バック対象なら金額',
  PRIMARY KEY (`receipt_detail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=91979 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `receipt_tbl`
-- -----------------------------
CREATE TABLE `receipt_tbl` (
  `receipt_id` bigint(12) NOT NULL COMMENT 'YYMMDDHHMMSS',
  `shop_id` int(4) NOT NULL COMMENT '店舗ID',
  `sheet_no` int(3) NOT NULL COMMENT '座席番号',
  `receipt_day` varchar(30) NOT NULL COMMENT '伝票集計日付',
  `in_date` varchar(30) NOT NULL COMMENT '入店日付',
  `in_time` varchar(30) NOT NULL COMMENT '入店時間',
  `out_date` varchar(30) DEFAULT NULL COMMENT '退店日付',
  `out_time` varchar(30) DEFAULT NULL COMMENT '退店時間',
  `customer_name` varchar(50) NOT NULL COMMENT '顧客名',
  `issuer_id` int(11) NOT NULL COMMENT '伝票の起票者のキャストID',
  `rep_id` int(11) NOT NULL COMMENT '来店ボーナス対象キャストID',
  `payment_type` int(11) NOT NULL COMMENT '支払い方法コード',
  `adjust_price` int(11) NOT NULL COMMENT '割引など調整金額(割引は-入力)',
  PRIMARY KEY (`receipt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `shop_mst`
-- -----------------------------
CREATE TABLE `shop_mst` (
  `shop_id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_name` varchar(50) NOT NULL,
  PRIMARY KEY (`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `system_config`
-- -----------------------------
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `system_logs`
-- -----------------------------
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=41684 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `tax_mst`
-- -----------------------------
CREATE TABLE `tax_mst` (
  `tax_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_type_name` varchar(50) NOT NULL,
  PRIMARY KEY (`tax_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `timecard_tbl`
-- -----------------------------
CREATE TABLE `timecard_tbl` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `cast_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `eigyo_ymd` varchar(8) NOT NULL,
  `in_ymd` varchar(8) NOT NULL,
  `in_time` varchar(4) NOT NULL,
  `out_ymd` varchar(8) NOT NULL,
  `out_time` varchar(4) NOT NULL,
  `break_start_ymd` varchar(8) NOT NULL,
  `break_start_time` varchar(4) NOT NULL,
  `break_end_ymd` varchar(8) NOT NULL,
  `break_end_time` varchar(4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cast_id` (`cast_id`,`shop_id`,`in_ymd`,`in_time`)
) ENGINE=InnoDB AUTO_INCREMENT=3288 DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Table structure for `user_mst`
-- -----------------------------
CREATE TABLE `user_mst` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `type` int(1) NOT NULL COMMENT '1:admin 2:user',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='ユーザーマスタ、1件のみなので直入力';

-- -----------------------------
-- Table structure for `worktime_data_tbl`
-- -----------------------------
CREATE TABLE `worktime_data_tbl` (
  `worktime_data_id` int(11) NOT NULL AUTO_INCREMENT,
  `work_date` date NOT NULL,
  `cast_id` int(11) NOT NULL,
  `check-in_time` time NOT NULL,
  `check-out_time` time NOT NULL,
  `worktime` int(11) NOT NULL,
  `salary` int(11) NOT NULL,
  `tc` int(11) NOT NULL COMMENT '交通費',
  `other_salary` int(11) NOT NULL COMMENT '遠隔、集客ボーナスなど',
  `absence` int(11) NOT NULL,
  `notes` varchar(50) NOT NULL COMMENT '欠勤遅刻の理由',
  PRIMARY KEY (`worktime_data_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;

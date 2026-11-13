-- Bella Vista Dining: schema (MySQL 8.0)
--
-- Structure only. Reference and sample data live in seed.sql.
-- Import order: schema.sql, then seed.sql, into an EMPTY database you have
-- already selected (Docker creates it from MYSQL_DATABASE; on shared hosting,
-- select the database in phpMyAdmin first). No CREATE DATABASE / USE here so
-- the same file works with host-prefixed database names.
--
-- This file always describes the latest full schema. Changes after the
-- Phase 0 freeze also get a numbered file in migrations/ for databases that
-- already exist. See migrations/README.md.

SET NAMES utf8mb4;

CREATE TABLE customers (
  customer_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name    VARCHAR(50)  NOT NULL,
  last_name     VARCHAR(50)  NOT NULL,
  email         VARCHAR(100) NOT NULL,
  phone_number  VARCHAR(20)  NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  KEY idx_customers_email (email)
) ENGINE=InnoDB;

CREATE TABLE table_types (
  table_type_id    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type_name        VARCHAR(50)  NOT NULL,
  capacity_min     TINYINT UNSIGNED NOT NULL,
  capacity_max     TINYINT UNSIGNED NOT NULL,   -- absolute max (VIP = 20)
  base_capacity    TINYINT UNSIGNED NOT NULL,   -- pax covered by the fee (VIP = 15)
  reservation_fee  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  minimum_order    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  extra_charge     DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- per pax above base_capacity
  PRIMARY KEY (table_type_id),
  UNIQUE KEY uq_table_types_name (type_name),
  CONSTRAINT chk_table_type_capacity CHECK (capacity_min > 0 AND capacity_min <= capacity_max),
  CONSTRAINT chk_table_type_base_capacity CHECK (base_capacity >= capacity_min AND base_capacity <= capacity_max),
  CONSTRAINT chk_table_type_fees CHECK (reservation_fee >= 0 AND minimum_order >= 0 AND extra_charge >= 0)
) ENGINE=InnoDB;

CREATE TABLE dining_tables (
  table_id       VARCHAR(10) NOT NULL,          -- S1, F2, VIP1
  table_type_id  TINYINT UNSIGNED NOT NULL,
  status         ENUM('Available','Reserved','Occupied','Clean-up') NOT NULL DEFAULT 'Available',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (table_id),
  CONSTRAINT fk_tables_type FOREIGN KEY (table_type_id) REFERENCES table_types (table_type_id)
) ENGINE=InnoDB;

CREATE TABLE reservations (
  reservation_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_code    CHAR(8)      NOT NULL,       -- shown to customer, used for lookup/cancel
  customer_id       INT UNSIGNED NOT NULL,
  table_id          VARCHAR(10)  NOT NULL,
  reservation_date  DATE         NOT NULL,
  start_time        TIME         NOT NULL,
  end_time          TIME         NOT NULL,
  number_of_guests  TINYINT UNSIGNED NOT NULL,
  extra_fee         DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- VIP pax above 15
  total_fee         DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- base fee + extra_fee
  payment_status    ENUM('Unpaid','Downpayment','Paid','On-site') NOT NULL DEFAULT 'Unpaid',
  status            ENUM('Pending','Confirmed','Checked-in','Completed','Cancelled','No-show') NOT NULL DEFAULT 'Pending',
  terms_accepted_at DATETIME     NOT NULL,
  cancelled_at      DATETIME     NULL,
  fee_refundable    TINYINT(1)   NULL,           -- set on cancellation (24h rule)
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Double-booking guard: NULL once cancelled/no-show, so the slot frees up
  slot_lock TINYINT AS (IF(status IN ('Cancelled','No-show'), NULL, 1)) STORED,
  PRIMARY KEY (reservation_id),
  UNIQUE KEY uq_reservation_ref (reference_code),
  UNIQUE KEY uq_table_slot (table_id, reservation_date, start_time, slot_lock),
  KEY idx_res_date_status (reservation_date, status),
  CONSTRAINT fk_res_customer FOREIGN KEY (customer_id) REFERENCES customers (customer_id),
  CONSTRAINT fk_res_table    FOREIGN KEY (table_id)    REFERENCES dining_tables (table_id),
  CONSTRAINT chk_reservation_guests CHECK (number_of_guests > 0),
  CONSTRAINT chk_reservation_fees CHECK (extra_fee >= 0 AND total_fee >= 0),
  CONSTRAINT chk_reservation_slot CHECK (start_time IN ('10:00:00', '12:00:00', '14:00:00', '16:00:00', '18:00:00', '20:00:00')),
  CONSTRAINT chk_reservation_end CHECK (end_time = ADDTIME(start_time, '02:00:00'))
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id      INT UNSIGNED NULL,
  order_id            INT UNSIGNED NULL,
  payment_type        ENUM('Downpayment','Full') NOT NULL,
  amount              DECIMAL(10,2) NOT NULL,
  method              VARCHAR(30)  NOT NULL,     -- Mock, Stripe, Cash, Card
  provider_reference  VARCHAR(100) NULL,         -- receipt / Stripe payment_intent id
  payment_state       ENUM('Pending','Succeeded','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  KEY idx_payments_res (reservation_id),
  KEY idx_payments_order (order_id),
  UNIQUE KEY uq_payment_provider_reference (provider_reference),
  CONSTRAINT fk_pay_res FOREIGN KEY (reservation_id) REFERENCES reservations (reservation_id),
  CONSTRAINT chk_payment_owner CHECK (reservation_id IS NOT NULL OR order_id IS NOT NULL),
  CONSTRAINT chk_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE menu_categories (
  category_id    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name  VARCHAR(50) NOT NULL,
  image_path     VARCHAR(255) NULL,
  sort_order     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB;

CREATE TABLE menu_items (
  item_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id   TINYINT UNSIGNED NOT NULL,
  item_name     VARCHAR(100) NOT NULL,
  description   TEXT NULL,
  price         DECIMAL(10,2) NOT NULL,
  image_path    VARCHAR(255) NULL,
  is_available  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (item_id),
  KEY idx_items_category (category_id),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES menu_categories (category_id),
  CONSTRAINT chk_menu_item_price CHECK (price > 0)
) ENGINE=InnoDB;

CREATE TABLE orders (
  order_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id  INT UNSIGNED NULL,             -- NULL for walk-ins
  table_id        VARCHAR(10)  NOT NULL,         -- kitchen queue maps orders to tables
  order_source    ENUM('Pre-order','Dine-in','Walk-in') NOT NULL,
  order_date      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  order_status    ENUM('Scheduled','Pending','Preparing','Served','Cancelled') NOT NULL DEFAULT 'Pending',
  served_at       DATETIME     NULL,
  PRIMARY KEY (order_id),
  KEY idx_orders_status_date (order_status, order_date),
  KEY idx_orders_res (reservation_id),
  KEY idx_orders_table (table_id),
  KEY idx_orders_date (order_date),
  CONSTRAINT fk_orders_res   FOREIGN KEY (reservation_id) REFERENCES reservations (reservation_id),
  CONSTRAINT fk_orders_table FOREIGN KEY (table_id)       REFERENCES dining_tables (table_id)
) ENGINE=InnoDB;

ALTER TABLE payments
  ADD CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders (order_id);

CREATE TABLE order_items (
  order_item_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       INT UNSIGNED NOT NULL,
  item_id        INT UNSIGNED NOT NULL,
  item_name      VARCHAR(100) NOT NULL,          -- snapshot at order time
  unit_price     DECIMAL(10,2) NOT NULL,         -- snapshot at order time
  quantity       SMALLINT UNSIGNED NOT NULL,
  subtotal       DECIMAL(10,2) GENERATED ALWAYS AS (unit_price * quantity) STORED,
  special_notes  TEXT NULL,
  PRIMARY KEY (order_item_id),
  KEY idx_oi_order (order_id),
  KEY idx_oi_item (item_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_item  FOREIGN KEY (item_id)  REFERENCES menu_items (item_id),
  CONSTRAINT chk_order_item_quantity CHECK (quantity > 0),
  CONSTRAINT chk_order_item_unit_price CHECK (unit_price >= 0)
) ENGINE=InnoDB;

CREATE TABLE admin_users (
  admin_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(50)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,          -- password_hash() output, never plain text
  full_name      VARCHAR(100) NOT NULL,
  role           ENUM('Manager','Kitchen Staff') NOT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  last_login     DATETIME NULL,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;

-- Admin login throttling (migration 002): failed attempts per username + IP.
CREATE TABLE admin_login_attempts (
  attempt_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,
  succeeded     TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (attempt_id),
  KEY idx_login_attempts_lookup (username, ip_address, attempted_at)
) ENGINE=InnoDB;

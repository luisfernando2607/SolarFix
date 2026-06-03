-- ============================================================
-- SOLARFIX — Base de Datos MySQL
-- Versión: 1.0
-- Motor: MySQL 8.0+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS solarfix
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE solarfix;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. SUCURSALES
-- ============================================================
CREATE TABLE branches (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  address       VARCHAR(255)  NULL,
  phone         VARCHAR(30)   NULL,
  email         VARCHAR(120)  NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. USUARIOS / TÉCNICOS
-- ============================================================
CREATE TABLE users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id     BIGINT UNSIGNED NULL,
  name          VARCHAR(120)  NOT NULL,
  email         VARCHAR(180)  NOT NULL UNIQUE,
  password      VARCHAR(255)  NOT NULL,
  phone         VARCHAR(30)   NULL,
  role          ENUM('super_admin','admin','technician','receptionist') NOT NULL DEFAULT 'technician',
  commission_type  ENUM('fixed','percent') NULL,
  commission_value DECIMAL(8,2)            NULL,    -- monto fijo o porcentaje
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  remember_token VARCHAR(100) NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    TIMESTAMP     NULL,
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 3. CLIENTES
-- ============================================================
CREATE TABLE clients (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150)  NOT NULL,
  phone         VARCHAR(30)   NOT NULL,               -- también usado para WhatsApp
  email         VARCHAR(180)  NULL,
  address       VARCHAR(255)  NULL,
  id_document   VARCHAR(20)   NULL,                   -- cédula / RUC (Ecuador)
  client_type   ENUM('individual','business','frequent') NOT NULL DEFAULT 'individual',
  notes         TEXT          NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    TIMESTAMP     NULL,
  INDEX idx_clients_phone (phone),
  INDEX idx_clients_name  (name)
) ENGINE=InnoDB;

-- ============================================================
-- 4. CATÁLOGO: MARCAS Y MODELOS DE DISPOSITIVOS
-- ============================================================
CREATE TABLE device_brands (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)   NOT NULL UNIQUE,
  device_type   ENUM('celular','tablet','pc','aire_split','aire_central','otro') NOT NULL,
  logo_path     VARCHAR(255)  NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE device_models (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id      BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(120)  NOT NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT fk_models_brand FOREIGN KEY (brand_id) REFERENCES device_brands(id) ON DELETE CASCADE,
  INDEX idx_models_brand (brand_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. CATÁLOGO: ACCESORIOS
-- ============================================================
CREATE TABLE accessories (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL UNIQUE,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ============================================================
-- 6. ÓRDENES DE SERVICIO  (tabla principal)
-- ============================================================
CREATE TABLE orders (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id           BIGINT UNSIGNED  NULL,
  client_id           BIGINT UNSIGNED  NOT NULL,
  user_id             BIGINT UNSIGNED  NULL,          -- técnico asignado
  created_by          BIGINT UNSIGNED  NULL,          -- quien creó la orden

  -- Numeración
  order_number        VARCHAR(20)      NOT NULL UNIQUE,  -- ej: SF-0001, SF-QTO-0042

  -- Dispositivo
  device_type         ENUM('celular','tablet','pc','aire_split','aire_central','otro') NOT NULL,
  brand_id            BIGINT UNSIGNED  NULL,
  model_id            BIGINT UNSIGNED  NULL,
  brand_text          VARCHAR(100)     NULL,          -- si no está en catálogo
  model_text          VARCHAR(100)     NULL,
  serial_imei         VARCHAR(100)     NULL,

  -- Estado físico y descripción
  physical_condition  TEXT             NULL,
  declared_fault      TEXT             NOT NULL,
  diagnosis           TEXT             NULL,
  work_done           TEXT             NULL,

  -- Seguridad del dispositivo
  unlock_type         ENUM('pin','pattern','none','unknown') NOT NULL DEFAULT 'unknown',
  unlock_value        TEXT             NULL,          -- cifrado AES-256 en app layer

  -- Estado del flujo
  status              ENUM(
                        'received',
                        'diagnosing',
                        'waiting_approval',
                        'repairing',
                        'ready',
                        'delivered',
                        'closed_no_repair',
                        'warranty'
                      ) NOT NULL DEFAULT 'received',

  -- Fechas
  entry_date          DATE             NOT NULL,
  estimated_delivery  DATE             NULL,
  delivery_date       DATE             NULL,

  -- Finanzas
  diagnosis_cost      DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  labor_cost          DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  parts_cost          DECIMAL(10,2)    NOT NULL DEFAULT 0.00,  -- calculado de order_parts
  surcharge_percent   DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
  surcharge_amount    DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  total_amount        DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  amount_paid         DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  balance_due         DECIMAL(10,2)    NOT NULL DEFAULT 0.00,

  -- Garantía emitida
  warranty_days       SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  -- Vinculación si es orden de garantía
  parent_order_id     BIGINT UNSIGNED  NULL,          -- apunta a la orden original

  notes               TEXT             NULL,
  created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          TIMESTAMP        NULL,

  CONSTRAINT fk_orders_branch   FOREIGN KEY (branch_id)       REFERENCES branches(id)       ON DELETE SET NULL,
  CONSTRAINT fk_orders_client   FOREIGN KEY (client_id)       REFERENCES clients(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_orders_user     FOREIGN KEY (user_id)         REFERENCES users(id)          ON DELETE SET NULL,
  CONSTRAINT fk_orders_created  FOREIGN KEY (created_by)      REFERENCES users(id)          ON DELETE SET NULL,
  CONSTRAINT fk_orders_brand    FOREIGN KEY (brand_id)        REFERENCES device_brands(id)  ON DELETE SET NULL,
  CONSTRAINT fk_orders_model    FOREIGN KEY (model_id)        REFERENCES device_models(id)  ON DELETE SET NULL,
  CONSTRAINT fk_orders_parent   FOREIGN KEY (parent_order_id) REFERENCES orders(id)         ON DELETE SET NULL,

  INDEX idx_orders_client   (client_id),
  INDEX idx_orders_user     (user_id),
  INDEX idx_orders_status   (status),
  INDEX idx_orders_branch   (branch_id),
  INDEX idx_orders_entry    (entry_date)
) ENGINE=InnoDB;

-- ============================================================
-- 7. ACCESORIOS POR ORDEN  (pivot)
-- ============================================================
CREATE TABLE order_accessories (
  order_id      BIGINT UNSIGNED NOT NULL,
  accessory_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (order_id, accessory_id),
  CONSTRAINT fk_oacc_order FOREIGN KEY (order_id)     REFERENCES orders(id)      ON DELETE CASCADE,
  CONSTRAINT fk_oacc_acc   FOREIGN KEY (accessory_id) REFERENCES accessories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 8. FOTOGRAFÍAS POR ORDEN
-- ============================================================
CREATE TABLE order_photos (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      BIGINT UNSIGNED NOT NULL,
  stage         ENUM('reception','process','delivery') NOT NULL DEFAULT 'reception',
  file_path     VARCHAR(255)   NOT NULL,
  caption       VARCHAR(200)   NULL,
  taken_by      BIGINT UNSIGNED NULL,
  created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photos_order FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_photos_user  FOREIGN KEY (taken_by)  REFERENCES users(id)  ON DELETE SET NULL,
  INDEX idx_photos_order (order_id)
) ENGINE=InnoDB;

-- ============================================================
-- 9. FIRMAS DIGITALES
-- ============================================================
CREATE TABLE order_signatures (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      BIGINT UNSIGNED NOT NULL,
  stage         ENUM('reception','delivery') NOT NULL,
  signature_path VARCHAR(255)  NOT NULL,              -- PNG generado desde canvas
  signed_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address    VARCHAR(45)    NULL,
  CONSTRAINT fk_sigs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  UNIQUE KEY uq_sig_stage (order_id, stage)
) ENGINE=InnoDB;

-- ============================================================
-- 10. PAGOS POR ORDEN
-- ============================================================
CREATE TABLE order_payments (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      BIGINT UNSIGNED NOT NULL,
  amount        DECIMAL(10,2)  NOT NULL,
  method        ENUM('cash','transfer','card','other') NOT NULL DEFAULT 'cash',
  reference     VARCHAR(120)   NULL,                  -- nro. de transferencia, etc.
  notes         VARCHAR(255)   NULL,
  paid_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  registered_by BIGINT UNSIGNED NULL,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id)      REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_user  FOREIGN KEY (registered_by) REFERENCES users(id)  ON DELETE SET NULL,
  INDEX idx_payments_order (order_id)
) ENGINE=InnoDB;

-- ============================================================
-- 11. HISTORIAL DE ESTADOS DE LA ORDEN
-- ============================================================
CREATE TABLE order_status_history (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      BIGINT UNSIGNED NOT NULL,
  from_status   VARCHAR(40)    NULL,
  to_status     VARCHAR(40)    NOT NULL,
  changed_by    BIGINT UNSIGNED NULL,
  notes         VARCHAR(255)   NULL,
  changed_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_order FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user  FOREIGN KEY (changed_by) REFERENCES users(id)  ON DELETE SET NULL,
  INDEX idx_history_order (order_id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. GARANTÍAS
-- ============================================================
CREATE TABLE warranties (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NOT NULL UNIQUE,
  warranty_type   ENUM('labor','parts','full') NOT NULL DEFAULT 'full',
  start_date      DATE           NOT NULL,
  end_date        DATE           NOT NULL,
  status          ENUM('active','expiring','expired','claimed') NOT NULL DEFAULT 'active',
  conditions      TEXT           NULL,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_warranties_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_warranties_end    (end_date),
  INDEX idx_warranties_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 13. INVENTARIO DE REPUESTOS
-- ============================================================
CREATE TABLE spare_parts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id       BIGINT UNSIGNED NULL,
  code            VARCHAR(60)    NULL UNIQUE,
  name            VARCHAR(200)   NOT NULL,
  category        VARCHAR(100)   NULL,
  compatible_brand VARCHAR(100)  NULL,
  compatible_model VARCHAR(100)  NULL,
  cost_price      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  sale_price      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  stock_quantity  INT            NOT NULL DEFAULT 0,
  min_stock       INT            NOT NULL DEFAULT 1,
  location        VARCHAR(100)   NULL,               -- estante / caja física
  is_active       TINYINT(1)     NOT NULL DEFAULT 1,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_parts_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  INDEX idx_parts_category (category),
  INDEX idx_parts_branch   (branch_id)
) ENGINE=InnoDB;

-- ============================================================
-- 14. REPUESTOS USADOS EN ORDEN  (pivot con precio snapshot)
-- ============================================================
CREATE TABLE order_parts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NOT NULL,
  spare_part_id   BIGINT UNSIGNED NOT NULL,
  quantity        INT             NOT NULL DEFAULT 1,
  unit_price      DECIMAL(10,2)   NOT NULL,           -- precio al momento del uso
  subtotal        DECIMAL(10,2)   NOT NULL,
  CONSTRAINT fk_oparts_order FOREIGN KEY (order_id)     REFERENCES orders(id)      ON DELETE CASCADE,
  CONSTRAINT fk_oparts_part  FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE RESTRICT,
  INDEX idx_oparts_order (order_id),
  INDEX idx_oparts_part  (spare_part_id)
) ENGINE=InnoDB;

-- ============================================================
-- 15. MOVIMIENTOS DE INVENTARIO
-- ============================================================
CREATE TABLE spare_part_movements (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  spare_part_id   BIGINT UNSIGNED NOT NULL,
  type            ENUM('in','out','adjustment') NOT NULL,
  quantity        INT             NOT NULL,           -- positivo o negativo
  reason          VARCHAR(200)    NULL,               -- compra, uso en orden, ajuste, etc.
  order_id        BIGINT UNSIGNED NULL,               -- si el movimiento es por una orden
  unit_cost       DECIMAL(10,2)   NULL,
  registered_by   BIGINT UNSIGNED NULL,
  moved_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_movements_part  FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE CASCADE,
  CONSTRAINT fk_movements_order FOREIGN KEY (order_id)      REFERENCES orders(id)      ON DELETE SET NULL,
  CONSTRAINT fk_movements_user  FOREIGN KEY (registered_by) REFERENCES users(id)       ON DELETE SET NULL,
  INDEX idx_movements_part  (spare_part_id),
  INDEX idx_movements_order (order_id)
) ENGINE=InnoDB;

-- ============================================================
-- 16. CHECKLISTS (plantillas por tipo de equipo)
-- ============================================================
CREATE TABLE checklists (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_type     ENUM('celular','tablet','pc','aire_split','aire_central','otro') NOT NULL,
  stage           ENUM('reception','diagnosis','delivery') NOT NULL DEFAULT 'reception',
  name            VARCHAR(120)   NOT NULL,
  is_active       TINYINT(1)     NOT NULL DEFAULT 1,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_checklist (device_type, stage)
) ENGINE=InnoDB;

CREATE TABLE checklist_items (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  checklist_id    BIGINT UNSIGNED NOT NULL,
  label           VARCHAR(150)   NOT NULL,
  sort_order      SMALLINT       NOT NULL DEFAULT 0,
  is_required     TINYINT(1)     NOT NULL DEFAULT 1,
  CONSTRAINT fk_citems_checklist FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
  INDEX idx_citems_checklist (checklist_id)
) ENGINE=InnoDB;

CREATE TABLE order_checklist_results (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NOT NULL,
  checklist_item_id BIGINT UNSIGNED NOT NULL,
  result          ENUM('ok','damaged','not_applicable','pending') NOT NULL DEFAULT 'pending',
  notes           VARCHAR(255)   NULL,
  CONSTRAINT fk_cresult_order FOREIGN KEY (order_id)          REFERENCES orders(id)         ON DELETE CASCADE,
  CONSTRAINT fk_cresult_item  FOREIGN KEY (checklist_item_id) REFERENCES checklist_items(id) ON DELETE CASCADE,
  UNIQUE KEY uq_result (order_id, checklist_item_id)
) ENGINE=InnoDB;

-- ============================================================
-- 17. AGENDA DE CITAS
-- ============================================================
CREATE TABLE appointments (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id       BIGINT UNSIGNED NULL,
  client_id       BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NULL,               -- técnico asignado
  order_id        BIGINT UNSIGNED NULL,               -- orden vinculada (opcional)
  type            ENUM('reception','home_visit','installation','maintenance','followup') NOT NULL DEFAULT 'reception',
  title           VARCHAR(150)   NOT NULL,
  address         VARCHAR(255)   NULL,
  scheduled_at    DATETIME       NOT NULL,
  duration_min    SMALLINT       NOT NULL DEFAULT 60,
  status          ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes           TEXT           NULL,
  reminder_sent   TINYINT(1)     NOT NULL DEFAULT 0,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appt_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES clients(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_appt_user   FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE SET NULL,
  CONSTRAINT fk_appt_order  FOREIGN KEY (order_id)  REFERENCES orders(id)   ON DELETE SET NULL,
  INDEX idx_appt_scheduled  (scheduled_at),
  INDEX idx_appt_user       (user_id),
  INDEX idx_appt_client     (client_id)
) ENGINE=InnoDB;

-- ============================================================
-- 18. COMISIONES
-- ============================================================
CREATE TABLE commissions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  order_id        BIGINT UNSIGNED NOT NULL UNIQUE,
  order_total     DECIMAL(10,2)  NOT NULL,
  commission_type ENUM('fixed','percent') NOT NULL,
  commission_value DECIMAL(8,2)  NOT NULL,
  commission_amount DECIMAL(10,2) NOT NULL,
  is_paid         TINYINT(1)     NOT NULL DEFAULT 0,
  paid_at         TIMESTAMP      NULL,
  period          VARCHAR(7)     NULL,                -- YYYY-MM para agrupación
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comm_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_comm_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  INDEX idx_comm_user   (user_id),
  INDEX idx_comm_period (period)
) ENGINE=InnoDB;

-- ============================================================
-- 19. LOG DE MENSAJES WHATSAPP
-- ============================================================
CREATE TABLE whatsapp_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NULL,
  client_id       BIGINT UNSIGNED NOT NULL,
  trigger_event   VARCHAR(80)    NOT NULL,            -- 'order_received', 'ready', 'warranty_expiring', etc.
  phone_to        VARCHAR(30)    NOT NULL,
  message_body    TEXT           NOT NULL,
  sent_by         BIGINT UNSIGNED NULL,
  sent_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wa_order  FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE SET NULL,
  CONSTRAINT fk_wa_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wa_user   FOREIGN KEY (sent_by)   REFERENCES users(id)   ON DELETE SET NULL,
  INDEX idx_wa_order  (order_id),
  INDEX idx_wa_client (client_id)
) ENGINE=InnoDB;

-- ============================================================
-- 20. CONFIGURACIÓN DEL SISTEMA
-- ============================================================
CREATE TABLE settings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id       BIGINT UNSIGNED NULL,               -- NULL = global
  `key`           VARCHAR(80)    NOT NULL,
  `value`         TEXT           NULL,
  `group`         VARCHAR(60)    NOT NULL DEFAULT 'general',
  CONSTRAINT fk_settings_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  UNIQUE KEY uq_setting (branch_id, `key`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATOS INICIALES (Seeders)
-- ============================================================

-- Sucursal principal
INSERT INTO branches (name, address, phone, email) VALUES
('Matriz', 'Av. Principal 123', '0999999999', 'info@solarfix.ec');

-- Usuario super administrador (password: 'password' — cambiar en producción)
INSERT INTO users (branch_id, name, email, password, role) VALUES
(1, 'Administrador', 'admin@solarfix.ec',
 '$2y$12$placeholder_change_this_hash_in_production_env000000', 'super_admin');

-- Accesorios base
INSERT INTO accessories (name) VALUES
('Solo celular'), ('Funda protectora'), ('Cargador'),
('Cable USB'), ('Cargador y cable'), ('Auriculares'),
('Batería adicional'), ('Control remoto'), ('Caja del equipo'),
('Kit completo (cargador + cable + funda)');

-- Marcas de celulares
INSERT INTO device_brands (name, device_type) VALUES
('Samsung', 'celular'), ('Motorola', 'celular'),
('iPhone (Apple)', 'celular'), ('Xiaomi', 'celular'),
('Redmi', 'celular'), ('POCO', 'celular'),
('Huawei', 'celular'), ('Honor', 'celular'),
('LG', 'celular'), ('Nokia', 'celular'),
('Otras marcas', 'celular');

-- Marcas de aires acondicionados
INSERT INTO device_brands (name, device_type) VALUES
('LG', 'aire_split'), ('Samsung', 'aire_split'),
('Midea', 'aire_split'), ('Gree', 'aire_split'),
('Carrier', 'aire_split'), ('Daikin', 'aire_split'),
('Mirage', 'aire_split'), ('Klimaire', 'aire_split'),
('Otras marcas', 'aire_split');

-- Checklists base — Celular / Recepción
INSERT INTO checklists (device_type, stage, name) VALUES
('celular', 'reception', 'Checklist recepción celular'),
('celular', 'diagnosis', 'Checklist diagnóstico celular'),
('aire_split', 'reception', 'Checklist recepción aire split'),
('aire_split', 'diagnosis', 'Checklist diagnóstico aire split'),
('pc', 'reception', 'Checklist recepción PC / Laptop'),
('pc', 'diagnosis', 'Checklist diagnóstico PC / Laptop');

-- Ítems: celular recepción (id checklist = 1)
INSERT INTO checklist_items (checklist_id, label, sort_order) VALUES
(1, 'Pantalla (sin rayaduras / sin golpes)',  1),
(1, 'Vidrio trasero / carcasa',              2),
(1, 'Botones físicos (encendido, volumen)',   3),
(1, 'Puerto de carga (sin daño visible)',     4),
(1, 'SIM card presente',                     5),
(1, 'Accesorios coinciden con los declarados',6);

-- Ítems: celular diagnóstico (id checklist = 2)
INSERT INTO checklist_items (checklist_id, label, sort_order) VALUES
(2, 'Pantalla / táctil funciona',            1),
(2, 'Cámara trasera',                        2),
(2, 'Cámara frontal',                        3),
(2, 'Altavoz / bocina',                      4),
(2, 'Micrófono',                             5),
(2, 'Puerto de carga (carga correctamente)', 6),
(2, 'Batería (mantiene carga)',              7),
(2, 'Conectividad WiFi / datos',             8),
(2, 'Software (enciende, sin bootloop)',      9),
(2, 'Huella / Face ID',                      10);

-- Ítems: aire split recepción (id checklist = 3)
INSERT INTO checklist_items (checklist_id, label, sort_order) VALUES
(3, 'Unidad interior sin daño visible',      1),
(3, 'Unidad exterior sin daño visible',      2),
(3, 'Control remoto presente y funciona',    3),
(3, 'Instalación eléctrica en buen estado',  4),
(3, 'Tubería / drenaje sin obstrucción',     5);

-- Ítems: aire split diagnóstico (id checklist = 4)
INSERT INTO checklist_items (checklist_id, label, sort_order) VALUES
(4, 'Filtros (limpieza)',                    1),
(4, 'Nivel de gas refrigerante',             2),
(4, 'Compresor (arranca y funciona)',         3),
(4, 'Ventilador unidad interior',            4),
(4, 'Ventilador unidad exterior',            5),
(4, 'Drenaje (sin obstrucción)',             6),
(4, 'Ruidos anómalos',                       7),
(4, 'Temperatura alcanzada (enfría/calienta)',8),
(4, 'Termostato / tarjeta electrónica',      9),
(4, 'Cableado eléctrico interno',            10);

-- Ítems: PC recepción (id checklist = 5)
INSERT INTO checklist_items (checklist_id, label, sort_order) VALUES
(5, 'Carcasa / chasis sin daño físico',      1),
(5, 'Pantalla sin rayaduras graves',         2),
(5, 'Teclado completo y sin teclas sueltas', 3),
(5, 'Puertos USB / HDMI sin daño visible',   4),
(5, 'Cargador / fuente de poder presente',   5);

-- Ítems: PC diagnóstico (id checklist = 6)
INSERT INTO checklist_items (checklist_id, label, sort_order) VALUES
(6, 'Enciende y llega al sistema operativo', 1),
(6, 'Pantalla (sin líneas / parpadeo)',       2),
(6, 'Teclado funciona',                      3),
(6, 'Touchpad funciona',                     4),
(6, 'Almacenamiento (HDD / SSD detectado)',  5),
(6, 'RAM (cantidad y velocidad)',            6),
(6, 'Batería (carga y mantiene)',            7),
(6, 'Puertos USB funcionan',                 8),
(6, 'WiFi / Bluetooth',                      9),
(6, 'Temperatura bajo carga (no sobrecalienta)', 10);

-- Configuración global inicial
INSERT INTO settings (`key`, `value`, `group`) VALUES
('company_name',         'SolarFix',                    'general'),
('company_phone',        '0999999999',                  'general'),
('company_email',        'info@solarfix.ec',            'general'),
('company_address',      'Quito, Ecuador',              'general'),
('default_warranty_days','30',                          'orders'),
('order_prefix',         'SF',                         'orders'),
('low_stock_alert_days', '3',                          'inventory'),
('warranty_alert_days',  '15',                         'warranty'),
('diagnosis_cost_default','0',                         'orders'),
('currency_symbol',      '$',                          'general');

-- ============================================================
-- VISTAS ÚTILES
-- ============================================================

-- Vista resumen de órdenes con cliente y técnico
CREATE OR REPLACE VIEW v_orders_summary AS
SELECT
  o.id,
  o.order_number,
  o.status,
  o.device_type,
  COALESCE(db.name, o.brand_text) AS brand,
  COALESCE(dm.name, o.model_text) AS model,
  o.entry_date,
  o.estimated_delivery,
  o.delivery_date,
  o.total_amount,
  o.amount_paid,
  o.balance_due,
  c.name    AS client_name,
  c.phone   AS client_phone,
  u.name    AS technician_name,
  b.name    AS branch_name
FROM orders o
JOIN clients c ON c.id = o.client_id
LEFT JOIN users u ON u.id = o.user_id
LEFT JOIN branches b ON b.id = o.branch_id
LEFT JOIN device_brands db ON db.id = o.brand_id
LEFT JOIN device_models dm ON dm.id = o.model_id
WHERE o.deleted_at IS NULL;

-- Vista stock crítico
CREATE OR REPLACE VIEW v_low_stock AS
SELECT
  sp.id,
  sp.code,
  sp.name,
  sp.category,
  sp.stock_quantity,
  sp.min_stock,
  (sp.min_stock - sp.stock_quantity) AS units_needed,
  b.name AS branch_name
FROM spare_parts sp
LEFT JOIN branches b ON b.id = sp.branch_id
WHERE sp.stock_quantity <= sp.min_stock
  AND sp.is_active = 1;

-- Vista garantías próximas a vencer o activas
CREATE OR REPLACE VIEW v_active_warranties AS
SELECT
  w.id,
  w.warranty_type,
  w.start_date,
  w.end_date,
  w.status,
  DATEDIFF(w.end_date, CURDATE()) AS days_remaining,
  o.order_number,
  o.device_type,
  COALESCE(db.name, o.brand_text) AS brand,
  COALESCE(dm.name, o.model_text) AS model,
  c.name  AS client_name,
  c.phone AS client_phone
FROM warranties w
JOIN orders o ON o.id = w.order_id
JOIN clients c ON c.id = o.client_id
LEFT JOIN device_brands db ON db.id = o.brand_id
LEFT JOIN device_models dm ON dm.id = o.model_id
WHERE w.status IN ('active', 'expiring');

-- Vista comisiones pendientes por técnico
CREATE OR REPLACE VIEW v_pending_commissions AS
SELECT
  u.id   AS user_id,
  u.name AS technician,
  COUNT(co.id)          AS orders_count,
  SUM(co.commission_amount) AS total_commission,
  co.period
FROM commissions co
JOIN users u ON u.id = co.user_id
WHERE co.is_paid = 0
GROUP BY u.id, u.name, co.period;


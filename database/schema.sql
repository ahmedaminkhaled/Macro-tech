-- =====================================================
-- Projet Programmation Web 2025-2026
-- Application: Gestion du Stock d’un Magasin Informatique
-- Environment: XAMPP (Apache + PHP + MySQL/MariaDB)
-- =====================================================

CREATE DATABASE IF NOT EXISTS store_app_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE store_app_db;

-- -----------------------------------------------------
-- 1) Categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_categories_name UNIQUE (name)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 2) Products
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) NOT NULL,
    designation VARCHAR(150) NOT NULL,
    description TEXT NULL,
    brand VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    photo_path VARCHAR(255) NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_products_reference UNIQUE (reference),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_products_price_positive CHECK (price > 0),
    CONSTRAINT chk_products_quantity_non_negative CHECK (quantity >= 0)
) ENGINE=InnoDB;

CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_designation ON products(designation);
CREATE INDEX idx_products_brand ON products(brand);
CREATE INDEX idx_products_price ON products(price);

-- -----------------------------------------------------
-- 2.1) Product Comments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS product_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_comments_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_product_comments_product ON product_comments(product_id);
CREATE INDEX idx_product_comments_created ON product_comments(created_at);

-- -----------------------------------------------------
-- 3) Sales (header)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_sales_total_non_negative CHECK (total_amount >= 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 4) Sale Items (details)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sale_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,

    CONSTRAINT fk_sale_items_sale
        FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_sale_items_quantity_positive CHECK (quantity > 0),
    CONSTRAINT chk_sale_items_unit_price_positive CHECK (unit_price > 0),
    CONSTRAINT chk_sale_items_line_total_non_negative CHECK (line_total >= 0)
) ENGINE=InnoDB;

CREATE INDEX idx_sale_items_sale ON sale_items(sale_id);
CREATE INDEX idx_sale_items_product ON sale_items(product_id);

-- -----------------------------------------------------
-- 5) Stock Movements (audit)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    movement_type ENUM('IN', 'OUT', 'ADJUST') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) NULL,
    related_sale_item_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_stock_movements_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movements_sale_item
        FOREIGN KEY (related_sale_item_id) REFERENCES sale_items(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_stock_movements_product ON stock_movements(product_id);
CREATE INDEX idx_stock_movements_type ON stock_movements(movement_type);

-- -----------------------------------------------------
-- 6) Triggers: business constraints at DB level
-- -----------------------------------------------------

-- Category duplicate protection (case-insensitive)
DELIMITER $$
CREATE TRIGGER trg_categories_before_insert
BEFORE INSERT ON categories
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM categories
        WHERE LOWER(name) = LOWER(NEW.name)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category already exists';
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_categories_before_update
BEFORE UPDATE ON categories
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM categories
        WHERE LOWER(name) = LOWER(NEW.name)
          AND id <> OLD.id
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category already exists';
    END IF;
END$$
DELIMITER ;

-- Product reference is immutable
DELIMITER $$
CREATE TRIGGER trg_products_before_update_reference
BEFORE UPDATE ON products
FOR EACH ROW
BEGIN
    IF NEW.reference <> OLD.reference THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Product reference cannot be modified';
    END IF;
END$$
DELIMITER ;

-- Product can be deleted only if quantity = 0
DELIMITER $$
CREATE TRIGGER trg_products_before_delete_quantity
BEFORE DELETE ON products
FOR EACH ROW
BEGIN
    IF OLD.quantity <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Product can be deleted only when quantity is 0';
    END IF;
END$$
DELIMITER ;

-- Sale item insert: check stock and auto-calculate line_total
DELIMITER $$
CREATE TRIGGER trg_sale_items_before_insert
BEFORE INSERT ON sale_items
FOR EACH ROW
BEGIN
    DECLARE v_stock INT;

    SELECT quantity INTO v_stock
    FROM products
    WHERE id = NEW.product_id
    FOR UPDATE;

    IF v_stock IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid product';
    END IF;

    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quantity must be > 0';
    END IF;

    IF NEW.quantity > v_stock THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;

    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_sale_items_after_insert
AFTER INSERT ON sale_items
FOR EACH ROW
BEGIN
    UPDATE products
    SET quantity = quantity - NEW.quantity
    WHERE id = NEW.product_id;

    INSERT INTO stock_movements(product_id, movement_type, quantity, reason, related_sale_item_id)
    VALUES (NEW.product_id, 'OUT', NEW.quantity, 'Sale', NEW.id);

    UPDATE sales s
    SET s.total_amount = (
        SELECT COALESCE(SUM(si.line_total), 0)
        FROM sale_items si
        WHERE si.sale_id = s.id
    )
    WHERE s.id = NEW.sale_id;
END$$
DELIMITER ;

-- Sale item update: adjust stock safely
DELIMITER $$
CREATE TRIGGER trg_sale_items_before_update
BEFORE UPDATE ON sale_items
FOR EACH ROW
BEGIN
    DECLARE v_stock INT;

    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quantity must be > 0';
    END IF;

    IF NEW.product_id = OLD.product_id THEN
        SELECT quantity INTO v_stock
        FROM products
        WHERE id = NEW.product_id
        FOR UPDATE;

        IF (v_stock + OLD.quantity) < NEW.quantity THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock for update';
        END IF;
    ELSE
        SELECT quantity INTO v_stock
        FROM products
        WHERE id = NEW.product_id
        FOR UPDATE;

        IF v_stock < NEW.quantity THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock for new product';
        END IF;
    END IF;

    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_sale_items_after_update
AFTER UPDATE ON sale_items
FOR EACH ROW
BEGIN
    IF NEW.product_id = OLD.product_id THEN
        UPDATE products
        SET quantity = quantity + OLD.quantity - NEW.quantity
        WHERE id = NEW.product_id;
    ELSE
        UPDATE products
        SET quantity = quantity + OLD.quantity
        WHERE id = OLD.product_id;

        UPDATE products
        SET quantity = quantity - NEW.quantity
        WHERE id = NEW.product_id;
    END IF;

    UPDATE sales s
    SET s.total_amount = (
        SELECT COALESCE(SUM(si.line_total), 0)
        FROM sale_items si
        WHERE si.sale_id = s.id
    )
    WHERE s.id = NEW.sale_id;
END$$
DELIMITER ;

-- Sale item delete: return stock
DELIMITER $$
CREATE TRIGGER trg_sale_items_after_delete
AFTER DELETE ON sale_items
FOR EACH ROW
BEGIN
    UPDATE products
    SET quantity = quantity + OLD.quantity
    WHERE id = OLD.product_id;

    INSERT INTO stock_movements(product_id, movement_type, quantity, reason, related_sale_item_id)
    VALUES (OLD.product_id, 'IN', OLD.quantity, 'Sale item deleted / rollback', NULL);

    UPDATE sales s
    SET s.total_amount = (
        SELECT COALESCE(SUM(si.line_total), 0)
        FROM sale_items si
        WHERE si.sale_id = s.id
    )
    WHERE s.id = OLD.sale_id;
END$$
DELIMITER ;

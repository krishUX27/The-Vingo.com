-- ============================================================
--  Menu Manager — database.sql
--  Import: phpMyAdmin → Import this file
-- ============================================================

CREATE DATABASE IF NOT EXISTS menu_project
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE menu_project;

-- ── Categories ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
  id   INT          NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Dishes ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS dishes (
  id           INT            NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150)   NOT NULL,
  price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  category_id  INT            NOT NULL,
  image        VARCHAR(255)   DEFAULT NULL,
  availability ENUM('Available','Not Available') NOT NULL DEFAULT 'Available',
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dish_cat
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed categories ───────────────────────────────────────────
INSERT IGNORE INTO categories (name) VALUES
  ('Starters'),('Main Course'),('Desserts'),('Beverages');

-- ── Seed dishes ───────────────────────────────────────────────
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Garlic Bread',          2.99, id, NULL, 'Available'     FROM categories WHERE name='Starters'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Paneer Tikka',          8.99, id, NULL, 'Available'     FROM categories WHERE name='Starters'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Grilled Chicken',      12.99, id, NULL, 'Available'     FROM categories WHERE name='Main Course' LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Dal Makhani',           9.50, id, NULL, 'Available'     FROM categories WHERE name='Main Course' LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Chocolate Lava Cake',   5.99, id, NULL, 'Available'     FROM categories WHERE name='Desserts'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Gulab Jamun',           3.99, id, NULL, 'Not Available' FROM categories WHERE name='Desserts'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Fresh Lemonade',        2.49, id, NULL, 'Available'     FROM categories WHERE name='Beverages'   LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Mango Lassi',           2.99, id, NULL, 'Available'     FROM categories WHERE name='Beverages'   LIMIT 1;

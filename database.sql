CREATE DATABASE IF NOT EXISTS vingo_db;
USE vingo_db;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    availability VARCHAR(50) NOT NULL,
    seasonal BOOLEAN DEFAULT 0,
    image_color VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS seasonal_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    discount_details VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    valid_until DATE,
    image_color VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS restaurants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    db_name VARCHAR(255) NOT NULL,
    admin_email VARCHAR(255) NOT NULL UNIQUE
);

-- Sempty default DB (Content removed as requested)

-- Vingo Menu Management System Database
CREATE DATABASE IF NOT EXISTS vingo_menu_db;
USE vingo_menu_db;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    availability VARCHAR(50) NOT NULL DEFAULT 'Available',
    seasonal BOOLEAN DEFAULT 0,
    image_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: Initial data
-- INSERT INTO menu_items (name, category, price, availability, seasonal, image_url) VALUES 
-- ('Classic Burger', 'Main Course', 12.99, 'Available', 0, 'uploads/burger.jpg'),
-- ('Iced Tea', 'Beverages', 4.50, 'Available', 0, 'uploads/tea.jpg');

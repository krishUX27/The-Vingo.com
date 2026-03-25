# 🍴 Vingo Menu Manager — Quick Start & Multi-Device Setup

Professional SaaS-style digital menu management for restaurants.

## 🚀 One-Click Setup (New!)
If you are moving this project to a new local server (like another PC with XAMPP):
1.  **Copy the project** to the new server's `htdocs/` folder (e.g., `C:/xampp/htdocs/vingo/`).
2.  **Initialize the Database**: Open your browser and navigate to:
    `http://localhost/vingo/public_html/install.php`
3.  **Delete `install.php`**: After success, delete the file for security.

---

## 📱 How to Access on Another Device (Phone/Tablet)
To view the digital menu or manage the dashboard from another device on the same Wi-Fi network:

### 1. Find your Computer's Local IP Address
-   On the host Windows PC, open **Command Prompt** (CMD).
-   Type `ipconfig` and press Enter.
-   Look for **IPv4 Address** (e.g., `192.168.1.15`).

### 2. Access from your Smartphone
-   Connect your phone to the **same Wi-Fi** as the computer.
-   Open your phone's browser and type the URL using the computer's IP:
    -   **Digital Menu**: `http://192.168.1.15/vingo/public_html/menu.php`
    -   **Admin Panel**: `http://192.168.1.15/vingo/public_html/admin/`

### 3. Troubleshooting (Firewall)
If the page doesn't load on your phone:
-   **Windows Firewall**: Ensure **Apache HTTP Server** is allowed to communicate through the Windows Firewall.
-   **XAMPP**: Make sure the Apache and MySQL modules are green (running) in the XAMPP Control Panel.

---

## 📁 Directory Structure
-   `/public_html`: The hosting root for your web server.
-   `/admin`: Management dashboard (Add/Edit dishes and categories).
-   `/includes`: Core database (`db.php`) and configuration.
-   `/assets`: Concentrated CSS/JS/Image resources.
-   `/uploads`: Product dish images.
-   `/qr`: Generated menu QR codes.
-   `/vendor`: Composer dependencies (Dompdf, Endroid QR).

<?php
// admin/menu-items.php
require_once '../includes/functions.php';

// Handle Filters
$categoryFilt = $_GET['category'] ?? 'All';
$availabilityFilt = $_GET['availability'] ?? 'All';

$menuItems = getAllMenuItems($categoryFilt, $availabilityFilt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Card Management | Admin Panel</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #64748b;
            --bg-body: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: #1e293b;
        }

        .admin-sidebar {
            width: 260px;
            background: #fff;
            height: 100vh;
            position: fixed;
            border-right: 1px solid #e2e8f0;
            padding: 2rem 1.5rem;
        }

        .admin-main {
            margin-left: 260px;
            padding: 2rem 3rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { border: 1px solid #e2e8f0; background: #fff; color: var(--secondary); }
        .btn-outline:hover { background: #f8fafc; }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: #fff;
            padding: 1.25rem;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
        }

        .filters select {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #cbd5e1;
            font-size: 0.875rem;
        }

        .table-card {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f1f5f9;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
        }

        .menu-img {
            width: 48px;
            height: 48px;
            border-radius: 0.375rem;
            object-fit: cover;
        }

        .badge {
            padding: 0.25rem 0.625rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-available { background: #dcfce7; color: #166534; }
        .badge-unavailable { background: #fee2e2; color: #991b1b; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }

        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: 1rem;
            width: 100%;
            max-width: 500px;
        }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.875rem; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .qr-section { margin-top: 2rem; text-align: center; }

    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <h2 style="font-weight: 800; color: var(--primary); margin-bottom: 2rem;">VINGO<span style="color: #000;">.</span></h2>
        <nav>
            <a href="#" class="btn btn-primary" style="width: 100%; justify-content: flex-start; margin-bottom: 1rem;">
                🥗 Menu Items
            </a>
            <a href="#" class="btn btn-outline" style="width: 100%; justify-content: flex-start;">
                📊 Dashboard
            </a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="dashboard-header">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700;">Menu Items Management</h1>
                <p style="color: var(--secondary); font-size: 0.875rem;">Manage your digital menu and pricing</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-outline" onclick="window.print()">🖨️ Print Menu</button>
                <button class="btn btn-outline" onclick="showQR()">📲 Get Customer Link</button>
                <button class="btn btn-primary" onclick="toggleModal(true)">➕ Add Menu Item</button>
            </div>
        </div>

        <form class="filters" method="GET">
            <select name="category">
                <option value="All">All Categories</option>
                <option <?php if($categoryFilt == 'Main Course') echo 'selected'; ?>>Main Course</option>
                <option <?php if($categoryFilt == 'Starters') echo 'selected'; ?>>Starters</option>
                <option <?php if($categoryFilt == 'Beverages') echo 'selected'; ?>>Beverages</option>
                <option <?php if($categoryFilt == 'Desserts') echo 'selected'; ?>>Desserts</option>
            </select>
            <select name="availability">
                <option value="All">All Status</option>
                <option <?php if($availabilityFilt == 'Available') echo 'selected'; ?>>Available</option>
                <option <?php if($availabilityFilt == 'Sold Out') echo 'selected'; ?>>Sold Out</option>
            </select>
            <button type="submit" class="btn btn-outline">Apply Filters</button>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Seasonal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($menuItems)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem;">No items found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($menuItems as $item): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <img src="../<?php echo htmlspecialchars($item['image_url'] ?? 'assets/placeholder-food.jpg'); ?>" class="menu-img" alt="">
                                    <span style="font-weight: 500;"><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td style="font-weight: 600;">$<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo ($item['availability'] == 'Available') ? 'badge-available' : 'badge-unavailable'; ?>">
                                    <?php echo htmlspecialchars($item['availability']); ?>
                                </span>
                            </td>
                            <td><?php echo $item['seasonal'] ? '✅ Yes' : '❌ No'; ?></td>
                            <td>
                                <a href="#" style="color: var(--primary); font-size: 0.875rem;">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="qr-section" id="qr-container" style="display: none;">
            <div class="table-card" style="padding: 2rem; display: inline-block;">
                <h3 style="margin-bottom: 1rem;">Public Menu QR Code</h3>
                <img id="qr-img" src="" alt="QR Code">
                <p style="margin-top: 1rem; color: var(--secondary); font-size: 0.875rem;">Scan this to see the public menu</p>
            </div>
        </div>

    </main>

    <!-- Add Item Modal -->
    <div class="modal" id="add-modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 1.5rem;">Add New Menu Item</h2>
            <form action="add-item.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Dish Name</label>
                    <input type="text" name="name" required placeholder="e.g. Classic Margherita">
                </div>
                <div style="display: flex; gap: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label>Category</label>
                        <select name="category" required>
                            <option>Main Course</option>
                            <option>Starters</option>
                            <option>Beverages</option>
                            <option>Desserts</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Price ($)</label>
                        <input type="number" step="0.01" name="price" required placeholder="12.99">
                    </div>
                </div>
                <div class="form-group">
                    <label>Availability</label>
                    <select name="availability">
                        <option value="Available">Available</option>
                        <option value="Sold Out">Sold Out</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="seasonal" style="width: auto;">
                    <label style="margin-bottom: 0;">Seasonal Offer</label>
                </div>
                <div class="form-group">
                    <label>Food Image</label>
                    <input type="file" name="food_image" accept="image/*" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="button" class="btn btn-outline" style="flex: 1;" onclick="toggleModal(false)">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            document.getElementById('add-modal').classList.toggle('active', show);
        }

        function showQR() {
            const qrContainer = document.getElementById('qr-container');
            const qrImg = document.getElementById('qr-img');
            const url = encodeURIComponent(window.location.origin + '/public/menu.php');
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${url}`;
            qrContainer.style.display = 'block';
            qrContainer.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>

<?php
// public/menu.php
require_once '../includes/functions.php';

$menuItems = getAllMenuItems();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Menu | Vingo</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --bg-body: #f1f5f9;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-body);
            color: #1e293b;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .category-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 2.5rem 0 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .category-title::after { content: ""; height: 2px; flex: 1; background: #e2e8f0; }

        .menu-grid {
            display: grid;
            gap: 1.5rem;
        }

        .menu-item {
            background: #fff;
            border-radius: 1rem;
            overflow: hidden;
            display: flex;
            box-shadow: 0 4px 15px -5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .menu-item:hover { transform: translateY(-3px); }

        .item-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
        }

        .item-info {
            padding: 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .item-name { font-weight: 700; font-size: 1.125rem; margin-bottom: 0.25rem; }
        .item-category { font-size: 0.75rem; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .item-price { font-weight: 800; color: #000; font-size: 1.125rem; }

        .item-sold-out {
            filter: grayscale(1);
            opacity: 0.6;
            position: relative;
        }
        .sold-out-badge {
            position: absolute;
            background: #000;
            color: #fff;
            font-size: 0.625rem;
            font-weight: 800;
            padding: 0.25rem 0.5rem;
            border-bottom-right-radius: 0.5rem;
            top: 0; left: 0;
            z-index: 10;
        }

        .seasonal-badge {
            background: #fef3c7; color: #d97706;
            font-size: 0.625rem; font-weight: 700;
            padding: 0.125rem 0.5rem; border-radius: 1rem;
            margin-left: 0.5rem; vertical-align: middle;
        }

        footer { text-align: center; margin-top: 5rem; padding-bottom: 3rem; color: #94a3b8; font-size: 0.75rem; }

    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.025em; color: var(--primary);">VINGO MENU<span style="color: #000;">.</span></h1>
            <p style="color: #64748b; margin-top: 0.5rem; font-size: 0.875rem;">Premium Freshly Cooked Delights</p>
        </header>

        <main>
            <?php 
            $currentCategory = '';
            if (empty($menuItems)): 
            ?>
                <p style="text-align: center; color: #64748b;">No items available right now.</p>
            <?php 
            else:
                foreach ($menuItems as $item): 
                    if ($currentCategory != $item['category']):
                        $currentCategory = $item['category'];
                        echo "<h2 class='category-title'>{$currentCategory}</h2>";
                    endif;
            ?>
                <div class="menu-item <?php echo ($item['availability'] == 'Sold Out') ? 'item-sold-out' : ''; ?>">
                    <?php if ($item['availability'] == 'Sold Out'): ?>
                        <div class="sold-out-badge">SOLD OUT</div>
                    <?php endif; ?>
                    
                    <img src="../<?php echo htmlspecialchars($item['image_url'] ?? 'assets/placeholder-food.jpg'); ?>" class="item-img" alt="">
                    
                    <div class="item-info">
                        <div class="item-category"><?php echo htmlspecialchars($item['category']); ?></div>
                        <div class="item-name">
                            <?php echo htmlspecialchars($item['name']); ?>
                            <?php if ($item['seasonal']): ?><span class="seasonal-badge">SEASONAL</span><?php endif; ?>
                        </div>
                        <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                    </div>
                </div>
            <?php 
                endforeach; 
            endif;
            ?>
        </main>

        <footer>
            &copy; 2026 Vingo. All rights reserved.
        </footer>
    </div>

</body>
</html>

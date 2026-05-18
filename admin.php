<?php
require 'config.php';
require 'auth.php';

requireAdmin();

$stats = [];

$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];

$stats['total_products'] = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];

$sales_result = $conn->query("SELECT COUNT(*) as count, SUM(total_price) as total FROM purchases");
$sales_data = $sales_result->fetch_assoc();
$stats['total_purchases'] = $sales_data['count'];
$stats['total_revenue'] = $sales_data['total'] ?? 0;

$segments = [];
$seg_result = $conn->query("
    SELECT segment_name, COUNT(*) as count
    FROM customer_segments
    GROUP BY segment_name
    ORDER BY count DESC
");
while ($seg = $seg_result->fetch_assoc()) {
    $segments[] = $seg;
}

$top_products = [];
$top_result = $conn->query("
    SELECT p.product_id, p.name, p.price, COUNT(purch.purchase_id) as sales_count, SUM(purch.total_price) as revenue
    FROM products p
    LEFT JOIN purchases purch ON p.product_id = purch.product_id
    GROUP BY p.product_id
    ORDER BY sales_count DESC
    LIMIT 10
");
while ($top = $top_result->fetch_assoc()) {
    $top_products[] = $top;
}

$categories = [];
$cat_result = $conn->query("
    SELECT c.name, COUNT(purch.purchase_id) as sales, SUM(purch.total_price) as revenue, COUNT(DISTINCT p.product_id) as products
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    LEFT JOIN purchases purch ON p.product_id = purch.product_id
    GROUP BY c.category_id
    ORDER BY revenue DESC
");
while ($cat = $cat_result->fetch_assoc()) {
    $categories[] = $cat;
}

$product_message = '';
$product_categories = [];
$category_list = $conn->query("SELECT category_id, name FROM categories ORDER BY name");
while ($category = $category_list->fetch_assoc()) {
    $product_categories[] = $category;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name'] ?? '');
    $product_description = trim($_POST['product_description'] ?? '');
    $product_price = trim($_POST['product_price'] ?? '');
    $product_category = (int)($_POST['product_category'] ?? 0);
    $product_stock = (int)($_POST['product_stock'] ?? 0);
    $product_image = trim($_POST['product_image'] ?? '');
    $product_rating = floatval($_POST['product_rating'] ?? 0);

    if (empty($product_name) || empty($product_description) || empty($product_price) || $product_category <= 0) {
        $product_message = '<p style="color: #d9534f;">Please fill in all required fields.</p>';
    } elseif (!is_numeric($product_price) || $product_price < 0) {
        $product_message = '<p style="color: #d9534f;">Please enter a valid product price.</p>';
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, rating, reviews, stock, image_url, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW())");
        $insert_stmt->bind_param("ssdidis", $product_name, $product_description, $product_price, $product_category, $product_rating, $product_stock, $product_image);
        if ($insert_stmt->execute()) {
            $product_message = '<p style="color: #5cb85c;">Product added successfully and will now appear for users.</p>';
            $stats['total_products'] = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
        } else {
            $product_message = '<p style="color: #d9534f;">Error adding product: ' . htmlspecialchars($insert_stmt->error) . '</p>';
        }
        $insert_stmt->close();
    }
}

$recent_purchases = [];
$recent_result = $conn->query("
    SELECT u.name, p.name as product_name, purch.quantity, purch.total_price, purch.purchase_date
    FROM purchases purch
    INNER JOIN users u ON purch.user_id = u.id
    INNER JOIN products p ON purch.product_id = p.product_id
    ORDER BY purch.purchase_date DESC
    LIMIT 20
");
while ($rec = $recent_result->fetch_assoc()) {
    $recent_purchases[] = $rec;
}

$active_users = [];
$active_result = $conn->query("
    SELECT u.id, u.name, u.email, cs.segment_name as segment, COUNT(purch.purchase_id) as purchases, SUM(purch.total_price) as spent
    FROM users u
    LEFT JOIN customer_segments cs ON u.id = cs.user_id
    LEFT JOIN purchases purch ON u.id = purch.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY spent DESC
    LIMIT 15
");
while ($active = $active_result->fetch_assoc()) {
    $active_users[] = $active;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 2.5em;
            line-height: 1;
        }
        .stat-card p {
            margin: 8px 0 0 0;
            opacity: 0.9;
        }
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .section {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background-color: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover {
            background-color: #f5f5f5;
        }
        .chart-container {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 6px;
        }
        .bar {
            background-color: #3498db;
            height: 30px;
            border-radius: 4px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            padding-left: 10px;
            color: white;
            font-weight: bold;
        }
        .segment-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .segment-item {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            border-left: 4px solid #3498db;
        }
        .segment-item h4 {
            margin: 0 0 5px 0;
        }
        .segment-item p {
            margin: 0;
            font-size: 1.5em;
            font-weight: bold;
            color: #3498db;
        }
    </style>
</head>

<body>

<header>
    <h1>SmartShop</h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="cart.php">Cart</a>
        <a href="admin.php">Dashboard</a>
        <a href="?logout=1" style="float: right; background-color: #e74c3c;">Logout</a>
    </nav>
</header>

<div class="dashboard-container">
    <h2>Admin Dashboard</h2>

    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo $stats['total_users']; ?></h3>
            <p>Total Users</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['total_products']; ?></h3>
            <p>Total Products</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['total_purchases']; ?></h3>
            <p>Total Orders</p>
        </div>
        <div class="stat-card">
            <h3>$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
            <p>Total Revenue</p>
        </div>
    </div>

    <div class="section">
        <h2>Customer Segments</h2>
        <div class="segment-list">
            <?php foreach ($segments as $segment): ?>
            <div class="segment-item">
                <h4><?php echo htmlspecialchars($segment['segment_name']); ?></h4>
                <p><?php echo $segment['count']; ?></p>
                <p style="font-size: 0.8em; color: #666;">customers</p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section">
        <h2>Add New Product</h2>
        <?php echo $product_message; ?>
        <form method="POST" style="display: grid; gap: 15px;">
            <label>Product Name</label>
            <input type="text" name="product_name" placeholder="Enter product name" required>

            <label>Description</label>
            <textarea name="product_description" rows="4" placeholder="Enter product description" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius:4px;"></textarea>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                <div>
                    <label>Price ($)</label>
                    <input type="number" step="0.01" min="0" name="product_price" placeholder="0.00" required>
                </div>
                <div>
                    <label>Category</label>
                    <select name="product_category" required>
                        <option value="">Select category</option>
                        <?php foreach ($product_categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                <div>
                    <label>Stock</label>
                    <input type="number" name="product_stock" min="0" value="0">
                </div>
                <div>
                    <label>Rating</label>
                    <input type="number" step="0.1" min="0" max="5" name="product_rating" value="0">
                </div>
            </div>

            <label>Image URL</label>
            <input type="text" name="product_image" placeholder="Optional image filename or URL">

            <button type="submit" name="add_product" style="width: 180px; padding: 12px 20px; background-color: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer;">Add Product</button>
        </form>
    </div>

    <div class="section">
        <h2>Top Selling Products</h2>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Price</th>
                    <th>Units Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                    <td><?php echo $product['sales_count'] ?? 0; ?></td>
                    <td>$<?php echo number_format($product['revenue'] ?? 0, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Category Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Products</th>
                    <th>Total Orders</th>
                    <th>Revenue</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $max_revenue = max(array_column($categories, 'revenue'));
                foreach ($categories as $category): 
                    $percentage = $max_revenue > 0 ? ($category['revenue'] / $max_revenue * 100) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                    <td><?php echo $category['products']; ?></td>
                    <td><?php echo $category['sales'] ?? 0; ?></td>
                    <td><strong>$<?php echo number_format($category['revenue'] ?? 0, 2); ?></strong></td>
                    <td>
                        <div style="width: 100px; background-color: #ddd; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $percentage; ?>%; background-color: #3498db; height: 20px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Top Customers</h2>
        <table>
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email</th>
                    <th>Segment</th>
                    <th>Purchases</th>
                    <th>Total Spent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <span style="background-color: #3498db; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.9em;">
                            <?php echo htmlspecialchars($user['segment']); ?>
                        </span>
                    </td>
                    <td><?php echo $user['purchases'] ?? 0; ?></td>
                    <td><strong>$<?php echo number_format($user['spent'] ?? 0, 2); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Recent Purchases</h2>
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_purchases as $purchase): ?>
                <tr>
                    <td><?php echo htmlspecialchars($purchase['name']); ?></td>
                    <td><?php echo htmlspecialchars($purchase['product_name']); ?></td>
                    <td><?php echo $purchase['quantity']; ?></td>
                    <td>$<?php echo number_format($purchase['total_price'], 2); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($purchase['purchase_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

</body>
</html>

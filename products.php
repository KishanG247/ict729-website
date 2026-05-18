<?php
require 'config.php';
require 'auth.php';

$products = [];
$category_name = '';
$error = '';

if (!isset($_GET['category_id']) || !is_numeric($_GET['category_id'])) {
    $error = 'Invalid category.';
} else {
    $category_id = (int)$_GET['category_id'];
    
    $cat_result = $conn->query("SELECT name FROM categories WHERE category_id = $category_id");
    if ($cat_result->num_rows === 0) {
        $error = 'Category not found.';
    } else {
        $cat = $cat_result->fetch_assoc();
        $category_name = $cat['name'];
        
        $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY rating DESC");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($product = $result->fetch_assoc()) {
            $products[] = $product;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $category_name ? htmlspecialchars($category_name) : 'Products'; ?> - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .product-card {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 6px;
            background-color: #fafafa;
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .product-card .price {
            font-size: 1.3em;
            color: #e74c3c;
            font-weight: bold;
        }
        .no-products {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>

<body>

<header>
    <h1>SmartShop</h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="recommendation.php">Recommendations</a>
        <?php if (isLoggedIn()): ?>
            <a href="cart.php">Cart</a>
            <a href="profile.php">Profile</a>
            <?php if (isAdmin()): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="?logout=1" style="float: right; background-color: #e74c3c;">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>

<div class="container">
    <h2><?php echo htmlspecialchars($category_name); ?> Products</h2>
    <p><a href="index.php">← Back to Home</a></p>

    <?php if ($error): ?>
        <p style="color: #e74c3c; padding: 15px; background-color: #f2dede; border-radius: 4px;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <?php if (empty($products) && !$error): ?>
        <div class="no-products">
            <p>No products in this category yet.</p>
        </div>
    <?php elseif (!empty($products)): ?>
        <p style="color: #666; margin: 20px 0;">
            Showing <?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?>
        </p>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p><?php echo substr(htmlspecialchars($product['description']), 0, 100) . '...'; ?></p>
                <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                <div style="color: #f39c12; margin: 10px 0;">★ <?php echo $product['rating']; ?> (<?php echo $product['reviews']; ?> reviews)</div>
                <a href="product.php?id=<?php echo $product['product_id']; ?>" style="text-decoration: none;">
                    <button style="width: 100%; cursor: pointer;">View Details</button>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

</body>
</html>

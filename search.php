<?php
require 'config.php';
require 'auth.php';

$products = [];
$query = trim($_GET['q'] ?? '');
$error = '';

if (empty($query)) {
    $error = 'Please enter a search term.';
} elseif (strlen($query) < 2) {
    $error = 'Search term must be at least 2 characters.';
} else {
    $search_term = '%' . $conn->real_escape_string($query) . '%';
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ? ORDER BY rating DESC");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($product = $result->fetch_assoc()) {
        $products[] = $product;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Search Results - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .search-header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .search-header input {
            width: 70%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        .no-results {
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
        <a href="recommendation.php<?php echo !empty($query) ? '?q=' . urlencode($query) : ''; ?>">Recommendations</a>
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
    <div class="search-header">
        <h2>Search Products</h2>
        <form action="search.php" method="GET">
            <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search...">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if ($error): ?>
        <p style="color: #e74c3c; padding: 15px; background-color: #f2dede; border-radius: 4px;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <?php if (empty($products) && !$error): ?>
        <div class="no-results">
            <p>No products found for "<?php echo htmlspecialchars($query); ?>"</p>
            <p><a href="index.php">Browse all products</a></p>
        </div>
    <?php elseif (!empty($products)): ?>
        <p style="color: #666; margin: 20px 0;">
            Found <?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?> for "<?php echo htmlspecialchars($query); ?>"
        </p>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p><?php echo substr(htmlspecialchars($product['description']), 0, 100) . '...'; ?></p>
                <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                <div style="color: #f39c12; margin: 10px 0;">★ <?php echo $product['rating']; ?></div>
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

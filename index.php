<?php
require 'config.php';
require 'auth.php';

$recommendations = [];
$trending = [];
$categories = [];

$cat_result = $conn->query("SELECT * FROM categories");
while ($cat = $cat_result->fetch_assoc()) {
    $categories[] = $cat;
}

if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    $segment_stmt = $conn->prepare("SELECT segment_name FROM customer_segments WHERE user_id = ?");
    $segment_stmt->bind_param("i", $user_id);
    $segment_stmt->execute();
    $segment_result = $segment_stmt->get_result();
    $segment_data = $segment_result->fetch_assoc();
    $user_segment = $segment_data['segment_name'] ?? 'New Customer';
    $segment_stmt->close();
    
    $rec_query = "
        SELECT DISTINCT p.* FROM products p
        WHERE p.category_id IN (
            SELECT DISTINCT pr.category_id FROM products pr
            INNER JOIN purchases purch ON pr.product_id = purch.product_id
            WHERE purch.user_id = $user_id
        )
        AND p.product_id NOT IN (
            SELECT product_id FROM purchases WHERE user_id = $user_id
        )
        LIMIT 6
    ";
    $rec_result = $conn->query($rec_query);
    while ($product = $rec_result->fetch_assoc()) {
        $recommendations[] = $product;
    }
    
    if (count($recommendations) == 0) {
        $rec_result = $conn->query("SELECT * FROM products ORDER BY rating DESC LIMIT 6");
        while ($product = $rec_result->fetch_assoc()) {
            $recommendations[] = $product;
        }
    }
} else {
    $rec_result = $conn->query("SELECT * FROM products ORDER BY rating DESC LIMIT 6");
    while ($product = $rec_result->fetch_assoc()) {
        $recommendations[] = $product;
    }
}

$trending_result = $conn->query("
    SELECT p.*, COUNT(purch.purchase_id) as purchase_count
    FROM products p
    LEFT JOIN purchases purch ON p.product_id = purch.product_id
    GROUP BY p.product_id
    ORDER BY purchase_count DESC
    LIMIT 5
");
while ($product = $trending_result->fetch_assoc()) {
    $trending[] = $product;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SmartShop - Home</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }
        .product-card {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 6px;
            background-color: #fafafa;
            transition: transform 0.3s;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .product-card h3 {
            margin-top: 0;
        }
        .product-card .price {
            font-size: 1.3em;
            color: #e74c3c;
            font-weight: bold;
        }
        .product-card .rating {
            color: #f39c12;
        }
        .user-greeting {
            padding: 15px;
            background-color: #e8f4f8;
            border-radius: 6px;
            margin: 10px;
        }
        .logout-btn {
            background-color: #e74c3c;
            float: right;
            margin-top: 5px;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        .search-box {
            display: flex;
            gap: 10px;
            margin: 20px;
        }
        .search-box input {
            flex: 1;
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
            <a href="?logout=1" class="logout-btn">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<?php if (isLoggedIn()): ?>
    <div class="user-greeting">
        Welcome, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>! 
        (Segment: <?php echo htmlspecialchars($_SESSION['segment'] ?? 'New Customer'); ?>)
    </div>
<?php endif; ?>

<div class="container">
    <section>
        <h2>Search Products</h2>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search products...">
            <button onclick="searchProducts()">Search</button>
        </div>
    </section>

    <section>
        <h2>Product Categories</h2>
        <div class="products-grid">
            <?php foreach ($categories as $category): ?>
                <div class="product-card" onclick="filterByCategory(<?php echo $category['category_id']; ?>)">
                    <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                    <p><?php echo htmlspecialchars($category['description']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <h2><?php echo isLoggedIn() ? 'Recommended For You' : 'Popular Products'; ?></h2>
        <div class="products-grid">
            <?php foreach ($recommendations as $product): ?>
                <div class="product-card">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p><?php echo substr(htmlspecialchars($product['description']), 0, 100) . '...'; ?></p>
                    <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                    <div class="rating">★ <?php echo $product['rating']; ?> (<?php echo $product['reviews']; ?> reviews)</div>
                    <div style="margin-top: 10px;">
                        <a href="product.php?id=<?php echo $product['product_id']; ?>" style="text-decoration: none;">
                            <button style="width: 100%; cursor: pointer;">View Details</button>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <h2>Trending Products</h2>
        <div class="products-grid">
            <?php foreach ($trending as $product): ?>
                <div class="product-card">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                    <div class="rating">★ <?php echo $product['rating']; ?></div>
                    <p style="font-size: 0.9em; color: #666;">Sold: <?php echo $product['purchase_count']; ?> times</p>
                    <a href="product.php?id=<?php echo $product['product_id']; ?>" style="text-decoration: none;">
                        <button style="width: 100%; cursor: pointer;">View</button>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

<script>
    function searchProducts() {
        const query = document.getElementById('searchInput').value;
        if (query.trim()) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    function filterByCategory(categoryId) {
        window.location.href = 'products.php?category_id=' + categoryId;
    }
</script>

</body>
</html>

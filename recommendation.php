<?php
require 'config.php';
require 'auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$recommendations = [];

$query = trim($_GET['q'] ?? '');
$search_filter = '';

if (!empty($query) && strlen($query) >= 2) {
    $search_term = '%' . $conn->real_escape_string($query) . '%';
    $search_filter = " AND (p.name LIKE '$search_term' OR p.description LIKE '$search_term')";
}

$purchase_history = [];
$purch_stmt = $conn->prepare("SELECT product_id FROM purchases WHERE user_id = ?");
$purch_stmt->bind_param("i", $user_id);
$purch_stmt->execute();
$purch_result = $purch_stmt->get_result();
while ($purch = $purch_result->fetch_assoc()) {
    $purchase_history[] = $purch['product_id'];
}
$purch_stmt->close();

if (count($purchase_history) > 0) {
    $ids = implode(',', $purchase_history);
    
    $rec_query = "
        SELECT DISTINCT p.product_id, p.name, p.price, p.rating, p.description, 'Same Category' as reason
        FROM products p
        WHERE p.category_id IN (
            SELECT DISTINCT category_id FROM products WHERE product_id IN ($ids)
        )
        AND p.product_id NOT IN ($ids)
        $search_filter
        ORDER BY p.rating DESC
        LIMIT 4
    ";
    $rec_result = $conn->query($rec_query);
    while ($rec = $rec_result->fetch_assoc()) {
        $recommendations[] = $rec;
    }
}

$segment_stmt = $conn->prepare("SELECT segment_name FROM customer_segments WHERE user_id = ?");
$segment_stmt->bind_param("i", $user_id);
$segment_stmt->execute();
$segment_result = $segment_stmt->get_result();
$segment_data = $segment_result->fetch_assoc();
$segment = isset($segment_data['segment_name']) ? $segment_data['segment_name'] : '';
$segment_stmt->close();

if ($segment && count($recommendations) < 8) {
    $segment_query = "
        SELECT DISTINCT p.product_id, p.name, p.price, p.rating, p.description, 'Popular in Your Segment' as reason
        FROM products p
        INNER JOIN purchases purch ON p.product_id = purch.product_id
        INNER JOIN customer_segments cs ON purch.user_id = cs.user_id
        WHERE cs.segment_name = '$segment'
        AND p.product_id NOT IN (SELECT product_id FROM purchases WHERE user_id = $user_id)
        $search_filter
        GROUP BY p.product_id
        ORDER BY COUNT(purch.purchase_id) DESC
        LIMIT 4
    ";
    $seg_result = $conn->query($segment_query);
    while ($seg = $seg_result->fetch_assoc()) {
        $recommendations[] = $seg;
    }
}

if (count($recommendations) < 12) {
    $top_query = "
        SELECT product_id, name, price, rating, description, 'Top Rated' as reason
        FROM products
        WHERE product_id NOT IN (SELECT product_id FROM purchases WHERE user_id = $user_id)
        $search_filter
        ORDER BY rating DESC
        LIMIT " . (12 - count($recommendations))
    ;
    $top_result = $conn->query($top_query);
    while ($top = $top_result->fetch_assoc()) {
        $recommendations[] = $top;
    }
}

$seen = [];
$final_recommendations = [];
foreach ($recommendations as $rec) {
    if (!in_array($rec['product_id'], $seen)) {
        $final_recommendations[] = $rec;
        $seen[] = $rec['product_id'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Recommendations - SmartShop</title>
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
            padding: 20px;
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
        .reason-badge {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            margin: 10px 0;
        }
        .recommendation-section {
            margin: 30px 0;
        }
        .recommendation-section h3 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
    </style>
</head>

<body>

<header>
    <h1>SmartShop</h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="recommendation.php">Recommendations</a>
        <a href="cart.php">Cart</a>
        <a href="profile.php">Profile</a>
        <?php if (isAdmin()): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <a href="?logout=1" style="float: right; background-color: #e74c3c;">Logout</a>
    </nav>
</header>

<div class="container">
    <section>
        <h2>🎯 <?php echo !empty($query) ? 'Recommendations for "' . htmlspecialchars($query) . '"' : 'Personalized Recommendations For You'; ?></h2>
        
        <!-- Search Form -->
        <div style="background-color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <form action="recommendation.php" method="GET" style="display: flex; gap: 10px; align-items: center;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Filter recommendations by keyword..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <button type="submit" style="padding: 10px 20px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">Filter</button>
                <?php if (!empty($query)): ?>
                    <a href="recommendation.php" style="padding: 10px 20px; background-color: #95a5a6; color: white; text-decoration: none; border-radius: 4px;">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>
        
        <p style="color: #666;">Based on your purchase history and customer segment: <strong><?php echo htmlspecialchars($_SESSION['segment']); ?></strong></p>
        
        <?php if (empty($final_recommendations)): ?>
            <p style="text-align: center; padding: 40px; color: #666;">
                No personalized recommendations yet. <a href="index.php">Browse all products</a>
            </p>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($final_recommendations as $product): ?>
                <div class="product-card">
                    <div class="reason-badge"><?php echo htmlspecialchars($product['reason']); ?></div>
                    
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    
                    <p style="font-size: 0.9em; color: #666;">
                        <?php echo substr(htmlspecialchars($product['description']), 0, 80) . '...'; ?>
                    </p>
                    
                    <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                    <div class="rating">★ <?php echo $product['rating']; ?></div>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <a href="product.php?id=<?php echo $product['product_id']; ?>" style="flex: 1; text-decoration: none;">
                            <button style="width: 100%; cursor: pointer;">View</button>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

</body>
</html>

<?php
require 'config.php';
require 'auth.php';

$product = null;
$error = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid product ID.';
} else {
    $product_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = 'Product not found.';
    } else {
        $product = $result->fetch_assoc();
        
        if (isLoggedIn()) {
            $user_id = $_SESSION['user_id'];
            $history_stmt = $conn->prepare("INSERT INTO browsing_history (user_id, product_id) VALUES (?, ?)");
            $history_stmt->bind_param("ii", $user_id, $product_id);
            $history_stmt->execute();
            $history_stmt->close();
        }
    }
    $stmt->close();
}

$cart_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        $cart_message = '<p style="color: #e74c3c;">Please login to add items to cart.</p>';
    } else {
        $user_id = $_SESSION['user_id'];
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        $check_stmt = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $cart_item = $check_result->fetch_assoc();
            $new_qty = $cart_item['quantity'] + $quantity;
            $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
            $update_stmt->bind_param("ii", $new_qty, $cart_item['cart_id']);
            $update_stmt->execute();
            $update_stmt->close();
            $cart_message = '<p style="color: #5cb85c;">Quantity updated in cart!</p>';
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
            $insert_stmt->execute();
            $insert_stmt->close();
            $cart_message = '<p style="color: #5cb85c;">Product added to cart!</p>';
        }
        $check_stmt->close();
    }
}

$buy_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    if (!isLoggedIn()) {
        $buy_message = '<p style="color: #e74c3c;">Please login to make a purchase.</p>';
    } else {
        $user_id = $_SESSION['user_id'];
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($quantity > $product['stock']) {
            $buy_message = '<p style="color: #e74c3c;">Insufficient stock. Only ' . $product['stock'] . ' units available.</p>';
        } elseif ($quantity < 1) {
            $buy_message = '<p style="color: #e74c3c;">Invalid quantity.</p>';
        } else {
            $total_price = $product['price'] * $quantity;
            
            $purchase_stmt = $conn->prepare("INSERT INTO purchases (user_id, product_id, quantity, total_price, purchase_date) VALUES (?, ?, ?, ?, NOW())");
            $purchase_stmt->bind_param("iiid", $user_id, $product_id, $quantity, $total_price);
            
            if ($purchase_stmt->execute()) {
                $update_stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
                $update_stock_stmt->bind_param("ii", $quantity, $product_id);
                $update_stock_stmt->execute();
                $update_stock_stmt->close();
                
                updateCustomerSegment($conn, $user_id);
                
                $buy_message = '<p style="color: #5cb85c;">Purchase successful! Thank you for your order.</p>';
                
                $refresh_stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
                $refresh_stmt->bind_param("i", $product_id);
                $refresh_stmt->execute();
                $product = $refresh_stmt->get_result()->fetch_assoc();
                $refresh_stmt->close();
            } else {
                $buy_message = '<p style="color: #e74c3c;">Purchase failed. Please try again.</p>';
            }
            $purchase_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Product'; ?> - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .product-details {
            max-width: 800px;
            margin: 20px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-image {
            width: 100%;
            max-width: 300px;
            height: auto;
            margin: 20px 0;
            border-radius: 6px;
        }
        .price {
            font-size: 2em;
            color: #e74c3c;
            font-weight: bold;
            margin: 15px 0;
        }
        .rating {
            color: #f39c12;
            font-size: 1.1em;
            margin: 10px 0;
        }
        .description {
            line-height: 1.6;
            color: #555;
            margin: 20px 0;
        }
        .quantity-selector {
            margin: 20px 0;
        }
        .quantity-selector input {
            width: 60px;
            padding: 5px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .action-buttons button {
            flex: 1;
            padding: 12px;
            font-size: 1em;
            border-radius: 4px;
        }
        .buy-now-btn {
            background-color: #e74c3c;
        }
        .buy-now-btn:hover {
            background-color: #c0392b;
        }
        .add-cart-btn {
            background-color: #3498db;
        }
        .add-cart-btn:hover {
            background-color: #2980b9;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .error-message {
            background-color: #f2dede;
            color: #d9534f;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
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

<div style="max-width: 1000px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <p><a href="index.php">← Back to Home</a></p>
    <?php else: ?>
        <div class="product-details">
            <a href="index.php">← Back to Home</a>
            
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            
            <img src="images/<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22300%22%3E%3Crect fill=%22%23ddd%22 width=%22300%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2220%22 fill=%22%23999%22%3EProduct Image%3C/text%3E%3C/svg%3E'">
            
            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
            <div class="rating">★ <?php echo $product['rating']; ?> out of 5 (<?php echo $product['reviews']; ?> reviews)</div>
            
            <div class="description">
                <h3>Description</h3>
                <p><?php echo htmlspecialchars($product['description']); ?></p>
            </div>
            
            <div style="color: #666; margin: 15px 0;">
                <strong>Stock Available:</strong> <?php echo $product['stock']; ?> units
            </div>
            
            <?php if ($cart_message): echo $cart_message; endif; ?>
            <?php if ($buy_message): echo $buy_message; endif; ?>
            
            <form method="POST">
                <div class="quantity-selector">
                    <label>Quantity:</label>
                    <input type="number" name="quantity" min="1" max="<?php echo $product['stock']; ?>" value="1" required>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" name="add_to_cart" class="add-cart-btn">🛒 Add to Cart</button>
                    <button type="submit" name="buy_now" class="buy-now-btn">💳 Buy Now</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

</body>
</html>

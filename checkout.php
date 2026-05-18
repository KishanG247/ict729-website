<?php
require 'config.php';
require 'auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$cart_items = [];
$total = 0;
$error = '';
$warning = '';
$success = false;

$stmt = $conn->prepare("
    SELECT c.cart_id, c.quantity, p.product_id, p.name, p.price, p.stock
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.user_id = ?
    ORDER BY c.cart_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($item = $result->fetch_assoc()) {
    if ($item['quantity'] > $item['stock']) {
        $warning = "Warning: Insufficient stock for " . htmlspecialchars($item['name']) . ". Available: " . $item['stock'] . ". You may need to adjust quantities.";
    }
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $total += $item['subtotal'];
    $cart_items[] = $item;
}
$stmt->close();

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (empty($error)) {
        $stock_check_error = '';
        foreach ($cart_items as $item) {
            $current_stock_stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
            $current_stock_stmt->bind_param("i", $item['product_id']);
            $current_stock_stmt->execute();
            $current_stock = $current_stock_stmt->get_result()->fetch_assoc()['stock'];
            $current_stock_stmt->close();
            
            if ($item['quantity'] > $current_stock) {
                $stock_check_error = "Insufficient stock for " . htmlspecialchars($item['name']) . ". Available: " . $current_stock;
                break;
            }
        }
        
        if (!empty($stock_check_error)) {
            $error = $stock_check_error;
        } else {
            $conn->begin_transaction();
            
            try {
                $order_stmt = $conn->prepare("INSERT INTO purchases (user_id, product_id, quantity, total_price, purchase_date) VALUES (?, ?, ?, ?, NOW())");
                
                foreach ($cart_items as $item) {
                    $order_stmt->bind_param("iiid", $user_id, $item['product_id'], $item['quantity'], $item['subtotal']);
                    $order_stmt->execute();
                    
                    $update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
                    $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                    $update_stock->execute();
                    $update_stock->close();
                }
                
                $order_stmt->close();
                
                $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $clear_cart->bind_param("i", $user_id);
                $clear_cart->execute();
                $clear_cart->close();
                
                updateCustomerSegment($conn, $user_id);
                
                $conn->commit();
                $success = true;
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Order failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Checkout - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout-container {
            max-width: 1000px;
            margin: 20px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
        }
        .order-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            height: fit-content;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .order-total {
            font-size: 1.3em;
            font-weight: bold;
            color: #e74c3c;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
        }
        .checkout-form {
            background-color: #fafafa;
            padding: 20px;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        .place-order-btn {
            background-color: #27ae60;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 6px;
            font-size: 1.2em;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        .place-order-btn:hover {
            background-color: #229954;
        }
        .error-message {
            background-color: #f2dede;
            color: #d9534f;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .back-to-cart {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }
        .back-to-cart:hover {
            text-decoration: underline;
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

<div class="checkout-container">
    <a href="cart.php" class="back-to-cart">← Back to Cart</a>
    <h2>Checkout</h2>
    
    <?php if ($success): ?>
        <div class="success-message">
            <h3>Order Placed Successfully!</h3>
            <p>Thank you for your purchase. Your order has been processed.</p>
            <p><a href="index.php">Continue Shopping</a></p>
        </div>
    <?php elseif ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($warning): ?>
        <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ffeaa7;">
            <?php echo htmlspecialchars($warning); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$success): ?>
        <div class="checkout-grid">
            <div class="checkout-form">
                <h3>Billing & Shipping Information</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Shipping Address</label>
                        <textarea id="address" name="address" rows="3" placeholder="Enter your full shipping address" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="zip">ZIP Code</label>
                        <input type="text" id="zip" name="zip" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="country">Country</label>
                        <select id="country" name="country" required>
                            <option value="">Select Country</option>
                            <option value="US">United States</option>
                            <option value="CA">Canada</option>
                            <option value="UK">United Kingdom</option>
                            <option value="AU">Australia</option>
                            <!-- Add more countries as needed -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="expiry">Expiry Date</label>
                            <input type="text" id="expiry" name="expiry" placeholder="MM/YY" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" name="cvv" placeholder="123" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="place_order" class="place-order-btn">Place Order</button>
                </form>
            </div>
            
            <div class="order-summary">
                <h3>Order Summary</h3>
                <?php foreach ($cart_items as $item): ?>
                    <div class="order-item">
                        <span><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                        <span>$<?php echo number_format($item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                
                <div class="order-total">
                    Total: $<?php echo number_format($total, 2); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

</body>
</html>
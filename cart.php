<?php
require 'config.php';
require 'auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$cart_items = [];
$total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $cart_id = (int)$_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?");
            $update_stmt->bind_param("iii", $quantity, $cart_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            $delete_stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
            $delete_stmt->bind_param("ii", $cart_id, $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        header("Location: cart.php");
        exit();
    } elseif (isset($_POST['remove_item'])) {
        $cart_id = (int)$_POST['cart_id'];
        $delete_stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $cart_id, $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        header("Location: cart.php");
        exit();
    }
}

$stmt = $conn->prepare("
    SELECT c.cart_id, c.quantity, p.product_id, p.name, p.price, p.image_url, p.stock
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.user_id = ?
    ORDER BY c.cart_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($item = $result->fetch_assoc()) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $total += $item['subtotal'];
    $cart_items[] = $item;
}
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-container {
            max-width: 1000px;
            margin: 20px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            background-color: #fafafa;
        }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 20px;
        }
        .item-details {
            flex: 1;
        }
        .item-price {
            font-weight: bold;
            color: #e74c3c;
            font-size: 1.2em;
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        .quantity-controls input {
            width: 60px;
            text-align: center;
        }
        .subtotal {
            font-weight: bold;
            color: #27ae60;
        }
        .cart-total {
            text-align: right;
            font-size: 1.5em;
            font-weight: bold;
            color: #e74c3c;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }
        .checkout-btn-link {
            background-color: #27ae60;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1.2em;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            width: 100%;
            text-align: center;
        }
        .checkout-btn-link:hover {
            background-color: #229954;
            color: white;
            text-decoration: none;
        }
        .empty-cart {
            text-align: center;
            padding: 60px;
            color: #666;
        }
        .remove-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .remove-btn:hover {
            background-color: #c0392b;
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

<div class="cart-container">
    <h2>Shopping Cart</h2>
    
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <h3>Your cart is empty</h3>
            <p><a href="index.php">Continue shopping</a></p>
        </div>
    <?php else: ?>
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item">
                <img src="images/<?php echo htmlspecialchars($item['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22%3E%3Crect fill=%22%23ddd%22 width=%2280%22 height=%2280%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2210%22 fill=%22%23999%22%3EImage%3C/text%3E%3C/svg%3E'">
                
                <div class="item-details">
                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                    <div class="item-price">$<?php echo number_format($item['price'], 2); ?> each</div>
                    
                    <form method="POST" class="quantity-controls">
                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                        <label>Qty:</label>
                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                               min="0" max="<?php echo $item['stock']; ?>" required>
                        <button type="submit" name="update_quantity">Update</button>
                        <button type="submit" name="remove_item" class="remove-btn">Remove</button>
                    </form>
                    
                    <div class="subtotal">Subtotal: $<?php echo number_format($item['subtotal'], 2); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="cart-total">
            Total: $<?php echo number_format($total, 2); ?>
        </div>
        
        <a href="checkout.php" class="checkout-btn-link">Proceed to Checkout</a>
    <?php endif; ?>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

</body>
</html>
<?php
require 'config.php';
require 'auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    
    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $user_id);
        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $user_message = '<p style="color: #5cb85c; padding: 10px; background-color: #dff0d8; border-radius: 4px;">Profile updated successfully!</p>';
        }
        $stmt->close();
    }
}

$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$segment_stmt = $conn->prepare("SELECT * FROM customer_segments WHERE user_id = ?");
$segment_stmt->bind_param("i", $user_id);
$segment_stmt->execute();
$segment = $segment_stmt->get_result()->fetch_assoc();
$segment_stmt->close();

$purchases = [];
$purchase_result = $conn->query("
    SELECT p.product_id, p.name, p.price, purch.quantity, purch.total_price, purch.purchase_date
    FROM purchases purch
    INNER JOIN products p ON purch.product_id = p.product_id
    WHERE purch.user_id = $user_id
    ORDER BY purch.purchase_date DESC
");
while ($purchase = $purchase_result->fetch_assoc()) {
    $purchases[] = $purchase;
}

$browsing = [];
$browse_result = $conn->query("
    SELECT DISTINCT p.product_id, p.name, p.price, p.rating, bh.viewed_at
    FROM browsing_history bh
    INNER JOIN products p ON bh.product_id = p.product_id
    WHERE bh.user_id = $user_id
    ORDER BY bh.viewed_at DESC
    LIMIT 10
");
while ($browse = $browse_result->fetch_assoc()) {
    $browsing[] = $browse;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Profile - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 20px auto;
        }
        .profile-section {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .info-item {
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 6px;
        }
        .info-item label {
            display: block;
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        .info-item p {
            margin: 0;
            color: #333;
        }
        .segment-badge {
            display: inline-block;
            padding: 8px 12px;
            background-color: #3498db;
            color: white;
            border-radius: 20px;
            font-weight: bold;
        }
        .edit-form {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .edit-form input {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 2em;
        }
        .stat-card p {
            margin: 5px 0 0 0;
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

<div class="profile-container">
    
    <div class="profile-section">
        <h2>User Profile</h2>
        
        <?php echo $user_message; ?>
        
        <div class="profile-info">
            <div class="info-item">
                <label>Name</label>
                <p><?php echo htmlspecialchars($user['name']); ?></p>
            </div>
            <div class="info-item">
                <label>Email</label>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="info-item">
                <label>Customer Segment</label>
                <div style="margin-top: 5px;">
                    <span class="segment-badge"><?php echo htmlspecialchars($segment['segment_name'] ?? 'New Customer'); ?></span>
                </div>
            </div>
            <div class="info-item">
                <label>Member Since</label>
                <p><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>
        
        <button onclick="toggleEditForm()">✏️ Edit Profile</button>
        
        <div id="editForm" class="edit-form" style="display: none; margin-top: 15px;">
            <h3>Edit Profile</h3>
            <form method="POST">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                <button type="submit" name="update_profile">Save Changes</button>
                <button type="button" onclick="toggleEditForm()">Cancel</button>
            </form>
        </div>
    </div>

    <?php if ($segment): ?>
    <div class="profile-section">
        <h2>Segment Analysis</h2>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $segment['purchase_count']; ?></h3>
                <p>Total Purchases</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>$<?php echo number_format($segment['total_purchases'], 2); ?></h3>
                <p>Total Spent</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3><?php echo $segment['segment_name']; ?></h3>
                <p>Your Segment</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3><?php echo $segment['last_purchase_date'] ? date('M d, Y', strtotime($segment['last_purchase_date'])) : 'N/A'; ?></h3>
                <p>Last Purchase</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="profile-section">
        <h2>Purchase History</h2>
        
        <?php if (empty($purchases)): ?>
            <p style="color: #666; text-align: center; padding: 20px;">No purchases yet. <a href="index.php">Start shopping</a></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase): ?>
                    <tr>
                        <td>
                            <a href="product.php?id=<?php echo $purchase['product_id']; ?>" style="text-decoration: none; color: #3498db;">
                                <?php echo htmlspecialchars($purchase['name']); ?>
                            </a>
                        </td>
                        <td>$<?php echo number_format($purchase['price'], 2); ?></td>
                        <td><?php echo $purchase['quantity']; ?></td>
                        <td><strong>$<?php echo number_format($purchase['total_price'], 2); ?></strong></td>
                        <td><?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="profile-section">
        <h2>Recently Browsed Products</h2>
        
        <?php if (empty($browsing)): ?>
            <p style="color: #666; text-align: center; padding: 20px;">No browsing history yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Rating</th>
                        <th>Viewed</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($browsing as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td>★ <?php echo $item['rating']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['viewed_at'])); ?></td>
                        <td>
                            <a href="product.php?id=<?php echo $item['product_id']; ?>" style="text-decoration: none;">
                                <button style="padding: 5px 10px; font-size: 0.9em;">View</button>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

<script>
    function toggleEditForm() {
        const form = document.getElementById('editForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
</script>

</body>
</html>

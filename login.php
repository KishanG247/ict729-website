<?php
require 'config.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            
            if ($user['status'] != 1) {
                $error = 'Your account is inactive. Please contact support.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $segment_stmt = $conn->prepare("SELECT segment_name FROM customer_segments WHERE user_id = ?");
                $segment_stmt->bind_param("i", $user['id']);
                $segment_stmt->execute();
                $segment_result = $segment_stmt->get_result();
                $segment_data = $segment_result->fetch_assoc();
                $_SESSION['segment'] = $segment_data['segment_name'] ?? 'New Customer';
                $segment_stmt->close();
                
                if ($user['role'] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error = 'Invalid credentials!';
            }
        } else {
            $error = 'Invalid credentials!';
        }
        $stmt->close();
    }
}

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SmartShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        form {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .error {
            color: #d9534f;
            padding: 10px;
            background-color: #f2dede;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .demo-users h4 {
            margin-top: 0;
        }
        .demo-users p {
            margin: 5px 0;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

<header>
    <h1>SmartShop</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="register.php">Register</a>
    </nav>
</header>

<section>
    <h2>Login</h2>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>

    <p style="text-align: center;">Don't have an account? <a href="register.php">Register</a></p>

   
</section>

<footer>
    <p>© 2026 SmartShop</p>
</footer>

</body>
</html>

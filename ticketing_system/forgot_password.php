<?php
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('index.php');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, username, full_name FROM users WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $token_stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $token_stmt->bind_param("iss", $user['id'], $token, $expires_at);
            
            if ($token_stmt->execute()) {
                // In a real application, you would send an email here
                // For demo purposes, we'll just show the reset link
                $reset_link = BASE_URL . "reset_password.php?token=" . $token;
                $success = "Password reset instructions have been sent to your email. For demo purposes, use this link: <a href='$reset_link'>Reset Password</a>";
            } else {
                $error = 'Error generating reset token. Please try again.';
            }
            $token_stmt->close();
        } else {
            // Don't reveal if email exists or not for security
            $success = "If an account with that email exists, password reset instructions have been sent.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">Forgot Password</h1>
            <p style="text-align: center; margin-bottom: 2rem; color: #666;">
                Enter your email address and we'll send you instructions to reset your password.
            </p>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required placeholder="Enter your email address">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Send Reset Instructions
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>

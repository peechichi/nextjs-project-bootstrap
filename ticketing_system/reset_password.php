<?php
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('index.php');
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$success = '';
$error = '';
$valid_token = false;

if (empty($token)) {
    $error = 'Invalid reset token';
} else {
    // Verify token
    $stmt = $conn->prepare("
        SELECT prt.user_id, u.username, u.full_name 
        FROM password_reset_tokens prt 
        JOIN users u ON prt.user_id = u.id 
        WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = FALSE AND u.status = 'active'
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        $valid_token = true;
        $user_id = $user['user_id'];
    } else {
        $error = 'Invalid or expired reset token';
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            // Mark token as used
            $token_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token = ?");
            $token_stmt->bind_param("s", $token);
            $token_stmt->execute();
            $token_stmt->close();
            
            $success = 'Password reset successfully! You can now login with your new password.';
            $valid_token = false; // Hide form after successful reset
        } else {
            $error = 'Error updating password. Please try again.';
        }
        $update_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">Reset Password</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($valid_token && !$success): ?>
                <p style="text-align: center; margin-bottom: 2rem; color: #666;">
                    Hello <?php echo htmlspecialchars($user['full_name']); ?>! Please enter your new password.
                </p>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               required minlength="6" placeholder="Enter new password">
                        <small style="color: #666;">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               required placeholder="Confirm new password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if (!$valid_token && !$success): ?>
                <div class="text-center mt-3">
                    <a href="forgot_password.php">Request New Reset Link</a>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-3">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>

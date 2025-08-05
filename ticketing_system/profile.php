<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department = trim($_POST['department']);
        
        if (empty($full_name) || empty($email)) {
            $error = 'Full name and email are required';
        } else {
            // Check if email is already used by another user
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->bind_param("si", $email, $_SESSION['user_id']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = 'Email is already used by another user';
            } else {
                $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, department = ? WHERE id = ?");
                $update_stmt->bind_param("ssssi", $full_name, $email, $phone, $department, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $success = 'Profile updated successfully';
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['department'] = $department;
                    
                    // Refresh user data
                    $user['full_name'] = $full_name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $user['department'] = $department;
                } else {
                    $error = 'Error updating profile';
                }
                $update_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long';
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pwd_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pwd_stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                
                if ($pwd_stmt->execute()) {
                    $success = 'Password changed successfully';
                } else {
                    $error = 'Error changing password';
                }
                $pwd_stmt->close();
            } else {
                $error = 'Current password is incorrect';
            }
        }
    }
}

// Get user's ticket statistics
$ticket_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_tickets,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tickets,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_tickets,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_tickets
    FROM tickets 
    WHERE created_by = ?
");
$ticket_stats_stmt->bind_param("i", $_SESSION['user_id']);
$ticket_stats_stmt->execute();
$ticket_stats = $ticket_stats_stmt->get_result()->fetch_assoc();
$ticket_stats_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">My Profile</h1>
                <p>Manage your account settings and view your ticket statistics</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <div>
                    <!-- Profile Information -->
                    <div class="card" style="margin-bottom: 2rem;">
                        <h3>Profile Information</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" id="username" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small style="color: #666;">Username cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" id="department" name="department" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['department']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" id="role" class="form-control" 
                                       value="<?php echo ucfirst($user['role']); ?>" disabled>
                                <small style="color: #666;">Role is managed by administrators</small>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="card">
                        <h3>Change Password</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password *</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" 
                                       required minlength="6">
                                <small style="color: #666;">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                        </form>
                    </div>
                </div>
                
                <div>
                    <!-- Account Information -->
                    <div class="card" style="margin-bottom: 2rem;">
                        <h3>Account Information</h3>
                        <table style="width: 100%;">
                            <tr>
                                <td style="padding: 0.5rem; font-weight: bold;">Member Since:</td>
                                <td style="padding: 0.5rem;"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 0.5rem; font-weight: bold;">Last Updated:</td>
                                <td style="padding: 0.5rem;"><?php echo date('M j, Y', strtotime($user['updated_at'])); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 0.5rem; font-weight: bold;">Status:</td>
                                <td style="padding: 0.5rem;">
                                    <span class="badge <?php echo $user['status'] == 'active' ? 'badge-approved' : 'badge-rejected'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Ticket Statistics -->
                    <div class="card">
                        <h3>My Ticket Statistics</h3>
                        <div style="text-align: center;">
                            <div style="margin-bottom: 1rem;">
                                <div class="stat-number" style="font-size: 2rem; color: #667eea;">
                                    <?php echo $ticket_stats['total_tickets']; ?>
                                </div>
                                <div class="stat-label">Total Tickets</div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                                <div>
                                    <div style="font-size: 1.5rem; font-weight: bold; color: #ffc107;">
                                        <?php echo $ticket_stats['pending_tickets']; ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #666;">Pending</div>
                                </div>
                                
                                <div>
                                    <div style="font-size: 1.5rem; font-weight: bold; color: #28a745;">
                                        <?php echo $ticket_stats['approved_tickets']; ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #666;">Approved</div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem;">
                                <div style="font-size: 1.2rem; font-weight: bold; color: #dc3545;">
                                    <?php echo $ticket_stats['rejected_tickets']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #666;">Rejected</div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem; text-align: center;">
                            <a href="create_ticket.php" class="btn btn-primary btn-sm">Create New Ticket</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
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

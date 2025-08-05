<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    redirect('index.php');
}

$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        // Add new user
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $department = trim($_POST['department']);
        $phone = trim($_POST['phone']);
        
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $error = 'Please fill in all required fields';
        } else {
            // Check if username or email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = 'Username or email already exists';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, department, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $username, $email, $hashed_password, $full_name, $role, $department, $phone);
                
                if ($stmt->execute()) {
                    $success = 'User added successfully';
                } else {
                    $error = 'Error adding user: ' . $conn->error;
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['update_status'])) {
        // Update user status
        $user_id = (int)$_POST['user_id'];
        $status = $_POST['status'];
        
        if ($user_id != $_SESSION['user_id']) { // Prevent admin from deactivating themselves
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $user_id);
            
            if ($stmt->execute()) {
                $success = 'User status updated successfully';
            } else {
                $error = 'Error updating user status';
            }
            $stmt->close();
        } else {
            $error = 'You cannot deactivate your own account';
        }
    } elseif (isset($_POST['reset_password'])) {
        // Reset user password
        $user_id = (int)$_POST['user_id'];
        $new_password = 'password123'; // Default password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success = "Password reset successfully. New password: $new_password";
        } else {
            $error = 'Error resetting password';
        }
        $stmt->close();
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Manage Users</h1>
                <button onclick="toggleAddForm()" class="btn btn-primary">Add New User</button>
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
            
            <!-- Add User Form -->
            <div id="addUserForm" style="display: none; border: 1px solid #ddd; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                <h3>Add New User</h3>
                <form method="POST" action="">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-select">
                                <option value="user">User</option>
                                <option value="approver">Approver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" id="department" name="department" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 mt-2">
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                        <button type="button" onclick="toggleAddForm()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                            <td>
                                <span class="badge <?php echo $user['status'] == 'active' ? 'badge-approved' : 'badge-rejected'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $user['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" name="update_status" 
                                                    class="btn btn-sm <?php echo $user['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                    onclick="return confirm('Are you sure you want to <?php echo $user['status'] == 'active' ? 'deactivate' : 'activate'; ?> this user?')">
                                                <?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="reset_password" 
                                                    class="btn btn-sm btn-secondary"
                                                    onclick="return confirm('Are you sure you want to reset this user\'s password?')">
                                                Reset Password
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-info">Current User</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleAddForm() {
            const form = document.getElementById('addUserForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>

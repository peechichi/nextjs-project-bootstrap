<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    redirect('index.php');
}

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$success = '';
$error = '';

// Get categories
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get approvers (admin and approver role users)
$approvers = $conn->query("SELECT id, full_name, department FROM users WHERE status = 'active' AND role IN ('admin', 'approver') ORDER BY full_name");

// Handle approver assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['assign_approver'])) {
        $cat_id = (int)$_POST['category_id'];
        $user_id = (int)$_POST['user_id'];
        $approval_level = (int)$_POST['approval_level'];
        
        if ($cat_id && $user_id && $approval_level) {
            // Check if assignment already exists for this level
            $check_stmt = $conn->prepare("SELECT id FROM category_approvers WHERE category_id = ? AND user_id = ? AND approval_level = ?");
            $check_stmt->bind_param("iii", $cat_id, $user_id, $approval_level);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = 'This approver is already assigned to this category at this level';
            } else {
                $stmt = $conn->prepare("INSERT INTO category_approvers (category_id, user_id, approval_level) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $cat_id, $user_id, $approval_level);
                
                if ($stmt->execute()) {
                    $success = 'Approver assigned successfully to Level ' . $approval_level;
                } else {
                    $error = 'Error assigning approver: ' . $conn->error;
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['remove_approver'])) {
        $assignment_id = (int)$_POST['assignment_id'];
        
        $stmt = $conn->prepare("DELETE FROM category_approvers WHERE id = ?");
        $stmt->bind_param("i", $assignment_id);
        
        if ($stmt->execute()) {
            $success = 'Approver removed successfully';
        } else {
            $error = 'Error removing approver';
        }
        $stmt->close();
    }
}

// Get current assignments
$assignments_query = "
    SELECT ca.id, c.name as category_name, c.id as category_id, u.full_name as approver_name, u.department, ca.approval_level
    FROM category_approvers ca
    JOIN categories c ON ca.category_id = c.id
    JOIN users u ON ca.user_id = u.id
    ORDER BY c.name, ca.approval_level, u.full_name
";
$assignments = $conn->query($assignments_query);

// If specific category is selected, get its assignments
$selected_assignments = null;
if ($category_id) {
    $selected_stmt = $conn->prepare("
        SELECT ca.id, u.full_name as approver_name, u.department, ca.approval_level
        FROM category_approvers ca
        JOIN users u ON ca.user_id = u.id
        WHERE ca.category_id = ?
        ORDER BY ca.approval_level, u.full_name
    ");
    $selected_stmt->bind_param("i", $category_id);
    $selected_stmt->execute();
    $selected_assignments = $selected_stmt->get_result();
    $selected_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Category Approvers - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Manage Category Approvers</h1>
                <a href="manage_categories.php" class="btn btn-secondary">Back to Categories</a>
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
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Assign New Approver -->
                <div>
                    <h3>Assign New Approver</h3>
                    <form method="POST" action="" style="border: 1px solid #ddd; padding: 1rem; border-radius: 5px;">
                        <div class="form-group">
                            <label for="category_id" class="form-label">Category *</label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?> 
                                        (<?php echo htmlspecialchars($category['department']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_id" class="form-label">Approver *</label>
                            <select id="user_id" name="user_id" class="form-select" required>
                                <option value="">Select Approver</option>
                                <?php while ($approver = $approvers->fetch_assoc()): ?>
                                    <option value="<?php echo $approver['id']; ?>">
                                        <?php echo htmlspecialchars($approver['full_name']); ?> 
                                        (<?php echo htmlspecialchars($approver['department']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="approval_level" class="form-label">Approval Level *</label>
                            <select id="approval_level" name="approval_level" class="form-select" required>
                                <option value="1">Level 1 (First Approver)</option>
                                <option value="2">Level 2 (Second Approver)</option>
                                <option value="3">Level 3 (Third Approver)</option>
                            </select>
                            <small style="color: #666;">Level 1 approvers must approve before Level 2, and so on.</small>
                        </div>
                        
                        <button type="submit" name="assign_approver" class="btn btn-primary">Assign Approver</button>
                    </form>
                    
                    <?php if ($category_id && $selected_assignments): ?>
                        <h4 style="margin-top: 2rem;">Current Approvers for Selected Category</h4>
                        <div style="border: 1px solid #ddd; border-radius: 5px; max-height: 300px; overflow-y: auto;">
                            <?php if ($selected_assignments->num_rows > 0): ?>
                                <?php while ($assignment = $selected_assignments->fetch_assoc()): ?>
                                    <div style="padding: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($assignment['approver_name']); ?></strong>
                                            <span class="approval-level <?php echo $assignment['approval_level'] == 1 ? 'active' : 'completed'; ?>">
                                                Level <?php echo $assignment['approval_level']; ?>
                                            </span>
                                            <br><small><?php echo htmlspecialchars($assignment['department']); ?></small>
                                        </div>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <button type="submit" name="remove_approver" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to remove this approver?')">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="padding: 1rem; text-align: center; color: #666;">No approvers assigned to this category</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- All Assignments -->
                <div>
                    <h3>All Category Assignments</h3>
                    <div style="border: 1px solid #ddd; border-radius: 5px; max-height: 500px; overflow-y: auto;">
                        <?php if ($assignments->num_rows > 0): ?>
                            <?php 
                            $current_category = '';
                            while ($assignment = $assignments->fetch_assoc()): 
                            ?>
                                <?php if ($current_category != $assignment['category_name']): ?>
                                    <?php if ($current_category != ''): ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="background: #f8f9fa; padding: 0.5rem 1rem; font-weight: bold; border-bottom: 1px solid #ddd;">
                                        <?php echo htmlspecialchars($assignment['category_name']); ?>
                                    </div>
                                    <div>
                                    <?php $current_category = $assignment['category_name']; ?>
                                <?php endif; ?>
                                
                                <div style="padding: 0.75rem 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <?php echo htmlspecialchars($assignment['approver_name']); ?>
                                        <span class="approval-level <?php echo $assignment['approval_level'] == 1 ? 'active' : 'completed'; ?>">
                                            Level <?php echo $assignment['approval_level']; ?>
                                        </span>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($assignment['department']); ?></small>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <a href="?category_id=<?php echo $assignment['category_id']; ?>" 
                                           class="btn btn-sm btn-secondary">View Category</a>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <button type="submit" name="remove_approver" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to remove this approver?')">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p style="padding: 2rem; text-align: center; color: #666;">No approver assignments found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

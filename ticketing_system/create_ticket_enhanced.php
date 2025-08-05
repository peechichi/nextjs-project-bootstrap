<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Get categories
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'];
    $priority = $_POST['priority'];
    
    if (empty($title) || empty($description) || empty($category_id)) {
        $error = 'Please fill in all required fields';
    } else {
        // Generate ticket number
        $ticket_number = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Check if ticket number already exists
        $check_stmt = $conn->prepare("SELECT id FROM tickets WHERE ticket_number = ?");
        $check_stmt->bind_param("s", $ticket_number);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $ticket_number = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        $check_stmt->close();
        
        // Get category department
        $cat_stmt = $conn->prepare("SELECT department FROM categories WHERE id = ?");
        $cat_stmt->bind_param("i", $category_id);
        $cat_stmt->execute();
        $cat_result = $cat_stmt->get_result();
        $department = $cat_result->fetch_assoc()['department'];
        $cat_stmt->close();
        
        // Check if category requires approval
        $requires_approval = categoryRequiresApproval($category_id);
        
        // Set initial status and assignment based on approval requirement
        if ($requires_approval) {
            $initial_status = STATUS_NEW;
            $assigned_to = null;
        } else {
            // Auto-assign to available technician
            $technician = getAvailableTechnician();
            $initial_status = STATUS_NEW;
            $assigned_to = $technician ? $technician['id'] : null;
        }
        
        // Insert ticket
        $stmt = $conn->prepare("INSERT INTO tickets (ticket_number, title, description, category_id, priority, status, requires_approval, created_by, assigned_to, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssissiiis", $ticket_number, $title, $description, $category_id, $priority, $initial_status, $requires_approval, $_SESSION['user_id'], $assigned_to, $department);
        
        if ($stmt->execute()) {
            $ticket_id = $conn->insert_id;
            
            // Add initial comment
            $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, action_type) VALUES (?, ?, 'Ticket created', 'status_change')");
            $comment_stmt->bind_param("ii", $ticket_id, $_SESSION['user_id']);
            $comment_stmt->execute();
            $comment_stmt->close();
            
            // Send notifications
            notifyStakeholders($ticket_id, 'ticket_created');
            
            if ($assigned_to) {
                notifyStakeholders($ticket_id, 'ticket_assigned');
            }
            
            if ($requires_approval) {
                // Update status to pending approval and notify approvers
                $update_stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE id = ?");
                $pending_approval = STATUS_PENDING_APPROVAL;
                $update_stmt->bind_param("si", $pending_approval, $ticket_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                notifyStakeholders($ticket_id, 'approval_request');
                $success = "Ticket created successfully! Ticket Number: $ticket_number. Your ticket is pending approval.";
            } else {
                $success = "Ticket created successfully! Ticket Number: $ticket_number. Your ticket has been assigned to " . ($technician ? $technician['full_name'] : 'our support team') . ".";
            }
            
            // Clear form
            $_POST = array();
        } else {
            $error = 'Error creating ticket: ' . $conn->error;
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
    <title>Create Ticket - Enhanced Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Create New Ticket</h1>
                <p>Submit your request and we'll route it to the appropriate team</p>
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
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="title" class="form-label">Title *</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                           required maxlength="200" placeholder="Brief description of your request">
                </div>
                
                <div class="form-group">
                    <label for="category_id" class="form-label">Category *</label>
                    <select id="category_id" name="category_id" class="form-select" required onchange="updateCategoryInfo()">
                        <option value="">Select Category</option>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    data-department="<?php echo htmlspecialchars($category['department']); ?>"
                                    data-description="<?php echo htmlspecialchars($category['description']); ?>"
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?> 
                                (<?php echo htmlspecialchars($category['department']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="category-info" style="margin-top: 0.5rem; padding: 0.5rem; background: #f8f9fa; border-radius: 5px; display: none;">
                        <small id="category-description"></small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="priority" class="form-label">Priority</label>
                    <select id="priority" name="priority" class="form-select">
                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low - General inquiries, minor issues</option>
                        <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium - Standard requests</option>
                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High - Important issues affecting work</option>
                        <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent - Critical issues requiring immediate attention</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description *</label>
                    <textarea id="description" name="description" class="form-textarea" 
                              required placeholder="Please provide detailed description of your request including:
- What you're trying to accomplish
- Steps you've already taken
- Any error messages you've encountered
- When the issue started (if applicable)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>What happens next?</strong>
                    <ul style="margin: 0.5rem 0 0 1rem;">
                        <li><strong>Categories requiring approval:</strong> Your ticket will be reviewed by the appropriate department before assignment</li>
                        <li><strong>Categories without approval:</strong> Your ticket will be automatically assigned to an available technician</li>
                        <li>You'll receive email notifications for all status updates</li>
                        <li>You can track your ticket progress in the dashboard</li>
                    </ul>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateCategoryInfo() {
            const select = document.getElementById('category_id');
            const infoDiv = document.getElementById('category-info');
            const descriptionSpan = document.getElementById('category-description');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const description = option.getAttribute('data-description');
                const department = option.getAttribute('data-department');
                
                descriptionSpan.textContent = `${description} (Department: ${department})`;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>

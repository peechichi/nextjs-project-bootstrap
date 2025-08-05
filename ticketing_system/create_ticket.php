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
        
        // Insert ticket
        $stmt = $conn->prepare("INSERT INTO tickets (ticket_number, title, description, category_id, priority, created_by, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisss", $ticket_number, $title, $description, $category_id, $priority, $_SESSION['user_id'], $department);
        
        if ($stmt->execute()) {
            $ticket_id = $conn->insert_id;
            
            // Add initial comment
            $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, action_type) VALUES (?, ?, 'Ticket created', 'status_change')");
            $comment_stmt->bind_param("ii", $ticket_id, $_SESSION['user_id']);
            $comment_stmt->execute();
            $comment_stmt->close();
            
            $success = "Ticket created successfully! Ticket Number: $ticket_number";
            
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
    <title>Create Ticket - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Create New Ticket</h1>
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
                           required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label for="category_id" class="form-label">Category *</label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?> 
                                (<?php echo htmlspecialchars($category['department']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority" class="form-label">Priority</label>
                    <select id="priority" name="priority" class="form-select">
                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description *</label>
                    <textarea id="description" name="description" class="form-textarea" 
                              required placeholder="Please provide detailed description of your request..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

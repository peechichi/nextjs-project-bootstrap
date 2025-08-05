<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    redirect('index.php');
}

$success = '';
$error = '';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        // Add new category
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $department = trim($_POST['department']);
        
        if (empty($name)) {
            $error = 'Category name is required';
        } else {
            // Check if category name already exists
            $check_stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
            $check_stmt->bind_param("s", $name);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = 'Category name already exists';
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (name, description, department) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $description, $department);
                
                if ($stmt->execute()) {
                    $success = 'Category added successfully';
                } else {
                    $error = 'Error adding category: ' . $conn->error;
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['update_status'])) {
        // Update category status
        $category_id = (int)$_POST['category_id'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE categories SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $category_id);
        
        if ($stmt->execute()) {
            $success = 'Category status updated successfully';
        } else {
            $error = 'Error updating category status';
        }
        $stmt->close();
    } elseif (isset($_POST['update_category'])) {
        // Update category details
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $department = trim($_POST['department']);
        
        if (empty($name)) {
            $error = 'Category name is required';
        } else {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, department = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $description, $department, $category_id);
            
            if ($stmt->execute()) {
                $success = 'Category updated successfully';
            } else {
                $error = 'Error updating category: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get all categories with ticket counts
$categories_query = "
    SELECT c.*, 
           COUNT(t.id) as ticket_count,
           COUNT(CASE WHEN t.status = 'pending' THEN 1 END) as pending_count
    FROM categories c 
    LEFT JOIN tickets t ON c.id = t.category_id 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
";
$categories = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Manage Categories</h1>
                <div class="d-flex gap-2">
                    <button onclick="toggleAddForm()" class="btn btn-primary">Add New Category</button>
                    <a href="manage_category_approvers.php" class="btn btn-secondary">Manage Approvers</a>
                </div>
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
            
            <!-- Add Category Form -->
            <div id="addCategoryForm" style="display: none; border: 1px solid #ddd; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                <h3>Add New Category</h3>
                <form method="POST" action="">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="name" class="form-label">Category Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" id="department" name="department" class="form-control" 
                                   placeholder="e.g., IT, HR, Finance">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-textarea" 
                                  placeholder="Brief description of this category..."></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                        <button type="button" onclick="toggleAddForm()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
            
            <!-- Categories Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Description</th>
                        <th>Total Tickets</th>
                        <th>Pending</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($category['department']); ?></td>
                            <td><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?><?php echo strlen($category['description']) > 100 ? '...' : ''; ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo $category['ticket_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-warning"><?php echo $category['pending_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $category['status'] == 'active' ? 'badge-approved' : 'badge-rejected'; ?>">
                                    <?php echo ucfirst($category['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($category['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($category['description'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($category['department'], ENT_QUOTES); ?>')" 
                                            class="btn btn-sm btn-primary">Edit</button>
                                    
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $category['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" name="update_status" 
                                                class="btn btn-sm <?php echo $category['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                onclick="return confirm('Are you sure you want to <?php echo $category['status'] == 'active' ? 'deactivate' : 'activate'; ?> this category?')">
                                            <?php echo $category['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    
                                    <a href="manage_category_approvers.php?category_id=<?php echo $category['id']; ?>" 
                                       class="btn btn-sm btn-secondary">Approvers</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
            <h3>Edit Category</h3>
            <form method="POST" action="">
                <input type="hidden" id="edit_category_id" name="category_id">
                
                <div class="form-group">
                    <label for="edit_name" class="form-label">Category Name *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_department" class="form-label">Department</label>
                    <input type="text" id="edit_department" name="department" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="edit_description" class="form-label">Description</label>
                    <textarea id="edit_description" name="description" class="form-textarea"></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleAddForm() {
            const form = document.getElementById('addCategoryForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function editCategory(id, name, description, department) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_department').value = department;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>

<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    redirect('index.php');
}

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticket_id) {
    redirect('index.php');
}

// Get ticket details
$stmt = $conn->prepare("
    SELECT t.*, c.name as category_name, u.full_name as creator_name 
    FROM tickets t 
    JOIN categories c ON t.category_id = c.id 
    JOIN users u ON t.created_by = u.id 
    WHERE t.id = ?
");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$ticket = $result->fetch_assoc()) {
    redirect('index.php');
}
$stmt->close();

// Get available users for assignment (admin and approvers)
$users = $conn->query("SELECT id, full_name, role, department FROM users WHERE status = 'active' AND role IN ('admin', 'approver') ORDER BY full_name");

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assigned_to = $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null;
    $comment = trim($_POST['comment']);
    
    // Update ticket assignment
    if ($assigned_to) {
        $update_stmt = $conn->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $assigned_to, $ticket_id);
    } else {
        $update_stmt = $conn->prepare("UPDATE tickets SET assigned_to = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $ticket_id);
    }
    
    if ($update_stmt->execute()) {
        // Get assignee name
        $assignee_name = 'Unassigned';
        if ($assigned_to) {
            $name_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
            $name_stmt->bind_param("i", $assigned_to);
            $name_stmt->execute();
            $name_result = $name_stmt->get_result();
            if ($name_row = $name_result->fetch_assoc()) {
                $assignee_name = $name_row['full_name'];
            }
            $name_stmt->close();
        }
        
        // Add comment to history
        $history_comment = $comment ? $comment : "Ticket assigned to " . $assignee_name;
        $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, action_type) VALUES (?, ?, ?, 'assignment')");
        $comment_stmt->bind_param("iis", $ticket_id, $_SESSION['user_id'], $history_comment);
        $comment_stmt->execute();
        $comment_stmt->close();
        
        $success = "Ticket assigned successfully";
        
        // Update ticket info for display
        $ticket['assigned_to'] = $assigned_to;
        
        // Redirect after 2 seconds
        header("refresh:2;url=view_ticket.php?id=" . $ticket_id);
    } else {
        $error = 'Error assigning ticket: ' . $conn->error;
    }
    $update_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Ticket - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Assign Ticket</h1>
                <p>Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - <?php echo htmlspecialchars($ticket['title']); ?></p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <br><small>Redirecting to ticket view...</small>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <h3>Ticket Information</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Category:</td>
                            <td style="padding: 0.5rem;"><?php echo htmlspecialchars($ticket['category_name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Priority:</td>
                            <td style="padding: 0.5rem;">
                                <span class="badge priority-<?php echo $ticket['priority']; ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Status:</td>
                            <td style="padding: 0.5rem;">
                                <span class="badge badge-<?php echo $ticket['status']; ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Created By:</td>
                            <td style="padding: 0.5rem;"><?php echo htmlspecialchars($ticket['creator_name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Created At:</td>
                            <td style="padding: 0.5rem;"><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></td>
                        </tr>
                    </table>
                    
                    <h4 style="margin-top: 1rem;">Description</h4>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>
                </div>
                
                <div>
                    <h3>Assignment</h3>
                    
                    <?php if (!$success): ?>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="assigned_to" class="form-label">Assign To</label>
                                <select id="assigned_to" name="assigned_to" class="form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo ($ticket['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name']); ?> 
                                            (<?php echo ucfirst($user['role']); ?> - <?php echo htmlspecialchars($user['department']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="comment" class="form-label">Comment (Optional)</label>
                                <textarea id="comment" name="comment" class="form-textarea" 
                                          placeholder="Add a comment about this assignment..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Assign Ticket</button>
                                <a href="view_ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

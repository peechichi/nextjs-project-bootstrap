<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticket_id) {
    redirect('index.php');
}

// Get ticket details
$stmt = $conn->prepare("
    SELECT t.*, c.name as category_name, 
           creator.full_name as creator_name, creator.email as creator_email,
           assignee.full_name as assignee_name,
           approver.full_name as approver_name
    FROM tickets t 
    JOIN categories c ON t.category_id = c.id 
    JOIN users creator ON t.created_by = creator.id
    LEFT JOIN users assignee ON t.assigned_to = assignee.id
    LEFT JOIN users approver ON t.approved_by = approver.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$ticket = $result->fetch_assoc()) {
    redirect('index.php');
}
$stmt->close();

// Check if user can view this ticket
if ($_SESSION['role'] != 'admin' && $ticket['created_by'] != $_SESSION['user_id']) {
    // Check if user is an approver for this category
    $approver_stmt = $conn->prepare("SELECT 1 FROM category_approvers WHERE category_id = ? AND user_id = ?");
    $approver_stmt->bind_param("ii", $ticket['category_id'], $_SESSION['user_id']);
    $approver_stmt->execute();
    if ($approver_stmt->get_result()->num_rows == 0) {
        redirect('index.php');
    }
    $approver_stmt->close();
}

// Get ticket comments
$comments_stmt = $conn->prepare("
    SELECT tc.*, u.full_name as user_name 
    FROM ticket_comments tc 
    JOIN users u ON tc.user_id = u.id 
    WHERE tc.ticket_id = ? 
    ORDER BY tc.created_at ASC
");
$comments_stmt->bind_param("i", $ticket_id);
$comments_stmt->execute();
$comments = $comments_stmt->get_result();
$comments_stmt->close();

$success = '';
$error = '';

// Handle new comment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment']);
    
    if (!empty($comment)) {
        $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
        $comment_stmt->bind_param("iis", $ticket_id, $_SESSION['user_id'], $comment);
        
        if ($comment_stmt->execute()) {
            $success = 'Comment added successfully';
            // Refresh comments
            $comments_stmt = $conn->prepare("
                SELECT tc.*, u.full_name as user_name 
                FROM ticket_comments tc 
                JOIN users u ON tc.user_id = u.id 
                WHERE tc.ticket_id = ? 
                ORDER BY tc.created_at ASC
            ");
            $comments_stmt->bind_param("i", $ticket_id);
            $comments_stmt->execute();
            $comments = $comments_stmt->get_result();
            $comments_stmt->close();
        } else {
            $error = 'Error adding comment';
        }
        $comment_stmt->close();
    }
}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-between align-center">
                    <div>
                        <h1 class="card-title">Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?></h1>
                        <p><?php echo htmlspecialchars($ticket['title']); ?></p>
                    </div>
                    <div>
                        <span class="badge badge-<?php echo $ticket['status']; ?>">
                            <?php echo ucfirst($ticket['status']); ?>
                        </span>
                        <span class="badge priority-<?php echo $ticket['priority']; ?>">
                            <?php echo ucfirst($ticket['priority']); ?>
                        </span>
                    </div>
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
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <div>
                    <h3>Description</h3>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>
                    
                    <h3>Comments & History</h3>
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                        <?php if ($comments->num_rows > 0): ?>
                            <?php while ($comment = $comments->fetch_assoc()): ?>
                                <div style="padding: 1rem; border-bottom: 1px solid #eee;">
                                    <div class="d-flex justify-between align-center mb-1">
                                        <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                        <small><?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?></small>
                                    </div>
                                    <?php if ($comment['action_type'] == 'status_change'): ?>
                                        <em style="color: #666;">
                                            <?php if ($comment['old_status'] && $comment['new_status']): ?>
                                                Status changed from <?php echo ucfirst($comment['old_status']); ?> to <?php echo ucfirst($comment['new_status']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($comment['comment']); ?>
                                            <?php endif; ?>
                                        </em>
                                    <?php else: ?>
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="padding: 1rem; text-align: center; color: #666;">No comments yet</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add Comment Form -->
                    <form method="POST" action="" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label for="comment" class="form-label">Add Comment</label>
                            <textarea id="comment" name="comment" class="form-textarea" 
                                      placeholder="Enter your comment..." required></textarea>
                        </div>
                        <button type="submit" name="add_comment" class="btn btn-primary">Add Comment</button>
                    </form>
                </div>
                
                <div>
                    <h3>Ticket Details</h3>
                    <table style="width: 100%; margin-bottom: 2rem;">
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Category:</td>
                            <td style="padding: 0.5rem;"><?php echo htmlspecialchars($ticket['category_name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Department:</td>
                            <td style="padding: 0.5rem;"><?php echo htmlspecialchars($ticket['department']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Created By:</td>
                            <td style="padding: 0.5rem;"><?php echo htmlspecialchars($ticket['creator_name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Created At:</td>
                            <td style="padding: 0.5rem;"><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></td>
                        </tr>
                        <?php if ($ticket['assigned_to']): ?>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Assigned To:</td>
                            <td style="padding: 0.5rem;"><?php echo htmlspecialchars($ticket['assignee_name']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($ticket['approved_by']): ?>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Final Approved By:</td>
                            <td style="padding: 0.5rem;"><?php echo htmlspecialchars($ticket['approver_name']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($ticket['closed_at']): ?>
                        <tr>
                            <td style="padding: 0.5rem; font-weight: bold;">Closed At:</td>
                            <td style="padding: 0.5rem;"><?php echo date('M j, Y g:i A', strtotime($ticket['closed_at'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <!-- Approval Progress -->
                    <h3>Approval Progress</h3>
                    <div style="margin-bottom: 2rem;">
                        <?php 
                        $approval_levels_array = [];
                        while ($level = $approval_levels->fetch_assoc()) {
                            $approval_levels_array[] = $level;
                        }
                        
                        $completed_approvals_array = [];
                        while ($approval = $completed_approvals->fetch_assoc()) {
                            $completed_approvals_array[$approval['approval_level']] = $approval;
                        }
                        
                        foreach ($approval_levels_array as $level): 
                            $is_completed = isset($completed_approvals_array[$level['approval_level']]);
                            $is_current = ($ticket['current_approval_level'] == $level['approval_level'] && !in_array($ticket['status'], ['approved', 'rejected', 'cancelled', 'closed']));
                        ?>
                            <div style="display: flex; align-items: center; margin-bottom: 1rem; padding: 1rem; border: 1px solid #ddd; border-radius: 5px; background: <?php echo $is_completed ? '#d4edda' : ($is_current ? '#fff3cd' : '#f8f9fa'); ?>;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $is_completed ? '#28a745' : ($is_current ? '#ffc107' : '#6c757d'); ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 1rem;">
                                    <?php echo $is_completed ? '✓' : $level['approval_level']; ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong>Level <?php echo $level['approval_level']; ?> Approval</strong>
                                    <br>
                                    <span style="color: #666;">Approver: <?php echo htmlspecialchars($level['approver_name']); ?></span>
                                    <?php if ($is_completed): ?>
                                        <br>
                                        <small style="color: #28a745;">
                                            ✓ Approved on <?php echo date('M j, Y g:i A', strtotime($completed_approvals_array[$level['approval_level']]['approved_at'])); ?>
                                            <?php if ($completed_approvals_array[$level['approval_level']]['comments']): ?>
                                                <br>Comment: <?php echo htmlspecialchars($completed_approvals_array[$level['approval_level']]['comments']); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php elseif ($is_current): ?>
                                        <br>
                                        <small style="color: #856404;">⏳ Pending approval</small>
                                    <?php else: ?>
                                        <br>
                                        <small style="color: #6c757d;">⏸ Waiting for previous level</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex" style="flex-direction: column; gap: 0.5rem;">
                        <?php if ($_SESSION['role'] == 'admin' || 
                                  ($ticket['created_by'] == $_SESSION['user_id'] && $ticket['status'] == 'pending')): ?>
                            <a href="process_ticket.php?id=<?php echo $ticket['id']; ?>&action=cancel" 
                               class="btn btn-warning btn-sm"
                               onclick="return confirm('Are you sure you want to cancel this ticket?')">Cancel Ticket</a>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <a href="assign_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-secondary btn-sm">Assign Ticket</a>
                        <?php endif; ?>
                        
                        <a href="index.php" class="btn btn-primary btn-sm">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

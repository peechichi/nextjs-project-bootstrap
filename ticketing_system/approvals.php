<?php
require_once 'config.php';

// Check if user is logged in and has approval rights
if (!isLoggedIn() || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'approver')) {
    redirect('index.php');
}

$success = '';
$error = '';

// Get tickets that need approval at current user's level
if ($_SESSION['role'] == 'admin') {
    // Admin can see all tickets that need approval
    $tickets_query = "
        SELECT t.*, c.name as category_name, u.full_name as creator_name,
               ca.approval_level,
               CASE 
                   WHEN t.status = 'pending' THEN 1
                   WHEN t.status = 'level1_approved' THEN 2
                   WHEN t.status = 'level2_approved' THEN 3
                   ELSE 0
               END as required_level
        FROM tickets t 
        JOIN categories c ON t.category_id = c.id 
        JOIN users u ON t.created_by = u.id 
        JOIN category_approvers ca ON c.id = ca.category_id
        WHERE t.status IN ('pending', 'level1_approved', 'level2_approved')
        AND ca.user_id = ?
        AND ca.approval_level = CASE 
            WHEN t.status = 'pending' THEN 1
            WHEN t.status = 'level1_approved' THEN 2
            WHEN t.status = 'level2_approved' THEN 3
        END
        ORDER BY t.created_at DESC
    ";
    $stmt = $conn->prepare($tickets_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $tickets = $stmt->get_result();
} else {
    // Approvers can only see tickets for their assigned categories and levels
    $stmt = $conn->prepare("
        SELECT t.*, c.name as category_name, u.full_name as creator_name,
               ca.approval_level,
               CASE 
                   WHEN t.status = 'pending' THEN 1
                   WHEN t.status = 'level1_approved' THEN 2
                   WHEN t.status = 'level2_approved' THEN 3
                   ELSE 0
               END as required_level
        FROM tickets t 
        JOIN categories c ON t.category_id = c.id 
        JOIN users u ON t.created_by = u.id 
        JOIN category_approvers ca ON c.id = ca.category_id
        WHERE t.status IN ('pending', 'level1_approved', 'level2_approved')
        AND ca.user_id = ?
        AND ca.approval_level = CASE 
            WHEN t.status = 'pending' THEN 1
            WHEN t.status = 'level1_approved' THEN 2
            WHEN t.status = 'level2_approved' THEN 3
        END
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $tickets = $stmt->get_result();
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ticket_id = (int)$_POST['ticket_id'];
    $action = $_POST['action'];
    $comment = trim($_POST['comment']);
    
    if ($ticket_id && in_array($action, ['approve', 'reject'])) {
        // Verify user can approve this ticket
        $can_approve = false;
        
        if ($_SESSION['role'] == 'admin') {
            $can_approve = true;
        } else {
            $check_stmt = $conn->prepare("
                SELECT 1 FROM tickets t 
                JOIN category_approvers ca ON t.category_id = ca.category_id 
                WHERE t.id = ? AND ca.user_id = ? AND ca.approval_level = t.current_approval_level
            ");
            $check_stmt->bind_param("ii", $ticket_id, $_SESSION['user_id']);
            $check_stmt->execute();
            $can_approve = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
        }
        
        if ($can_approve) {
            // Get ticket details to determine current status and category
            $ticket_stmt = $conn->prepare("SELECT status, category_id, current_approval_level FROM tickets WHERE id = ?");
            $ticket_stmt->bind_param("i", $ticket_id);
            $ticket_stmt->execute();
            $ticket_info = $ticket_stmt->get_result()->fetch_assoc();
            $ticket_stmt->close();
            
            if ($action == 'reject') {
                // Rejection at any level closes the ticket
                $new_status = 'rejected';
                $closed_at = 'NOW()';
                
                $update_stmt = $conn->prepare("UPDATE tickets SET status = ?, approved_by = ?, closed_at = $closed_at WHERE id = ?");
                $update_stmt->bind_param("sii", $new_status, $_SESSION['user_id'], $ticket_id);
                
                if ($update_stmt->execute()) {
                    // Record the rejection
                    $approval_stmt = $conn->prepare("INSERT INTO ticket_approvals (ticket_id, approver_id, approval_level, status, comments) VALUES (?, ?, ?, 'rejected', ?)");
                    $approval_stmt->bind_param("iiis", $ticket_id, $_SESSION['user_id'], $ticket_info['current_approval_level'], $comment);
                    $approval_stmt->execute();
                    $approval_stmt->close();
                    
                    // Add comment to history
                    $history_comment = $comment ? $comment : 'Ticket rejected at level ' . $ticket_info['current_approval_level'];
                    $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, action_type, old_status, new_status) VALUES (?, ?, ?, 'status_change', ?, ?)");
                    $comment_stmt->bind_param("iisss", $ticket_id, $_SESSION['user_id'], $history_comment, $ticket_info['status'], $new_status);
                    $comment_stmt->execute();
                    $comment_stmt->close();
                    
                    $success = "Ticket rejected successfully";
                }
                $update_stmt->close();
                
            } else { // approve
                // Get max approval level for this category
                $max_level = getMaxApprovalLevel($ticket_info['category_id']);
                $current_level = $ticket_info['current_approval_level'];
                
                // Record the approval
                $approval_stmt = $conn->prepare("INSERT INTO ticket_approvals (ticket_id, approver_id, approval_level, status, comments) VALUES (?, ?, ?, 'approved', ?)");
                $approval_stmt->bind_param("iiis", $ticket_id, $_SESSION['user_id'], $current_level, $comment);
                $approval_stmt->execute();
                $approval_stmt->close();
                
                if ($current_level >= $max_level) {
                    // Final approval - ticket is fully approved
                    $new_status = 'approved';
                    $closed_at = 'NOW()';
                    $next_level = $current_level;
                } else {
                    // Move to next approval level
                    $next_level = $current_level + 1;
                    if ($next_level == 2) {
                        $new_status = 'level1_approved';
                    } elseif ($next_level == 3) {
                        $new_status = 'level2_approved';
                    } else {
                        $new_status = 'approved';
                    }
                    $closed_at = 'NULL';
                }
                
                $update_stmt = $conn->prepare("UPDATE tickets SET status = ?, current_approval_level = ?, approved_by = ?, closed_at = $closed_at WHERE id = ?");
                $update_stmt->bind_param("siii", $new_status, $next_level, $_SESSION['user_id'], $ticket_id);
                
                if ($update_stmt->execute()) {
                    // Add comment to history
                    $history_comment = $comment ? $comment : "Approved at level $current_level" . ($new_status == 'approved' ? ' - Final approval' : " - Moved to level $next_level");
                    $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, action_type, old_status, new_status) VALUES (?, ?, ?, 'status_change', ?, ?)");
                    $comment_stmt->bind_param("iisss", $ticket_id, $_SESSION['user_id'], $history_comment, $ticket_info['status'], $new_status);
                    $comment_stmt->execute();
                    $comment_stmt->close();
                    
                    if ($new_status == 'approved') {
                        $success = "Ticket fully approved and closed successfully";
                    } else {
                        $success = "Ticket approved at level $current_level and moved to next approval level";
                    }
                } else {
                    $error = 'Error updating ticket status';
                }
                $update_stmt->close();
            }
            
            // Refresh the tickets list
            $stmt = $conn->prepare("
                SELECT t.*, c.name as category_name, u.full_name as creator_name,
                       ca.approval_level,
                       CASE 
                           WHEN t.status = 'pending' THEN 1
                           WHEN t.status = 'level1_approved' THEN 2
                           WHEN t.status = 'level2_approved' THEN 3
                           ELSE 0
                       END as required_level
                FROM tickets t 
                JOIN categories c ON t.category_id = c.id 
                JOIN users u ON t.created_by = u.id 
                JOIN category_approvers ca ON c.id = ca.category_id
                WHERE t.status IN ('pending', 'level1_approved', 'level2_approved')
                AND ca.user_id = ?
                AND ca.approval_level = CASE 
                    WHEN t.status = 'pending' THEN 1
                    WHEN t.status = 'level1_approved' THEN 2
                    WHEN t.status = 'level2_approved' THEN 3
                END
                ORDER BY t.created_at DESC
            ");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $tickets = $stmt->get_result();
        } else {
            $error = 'You do not have permission to approve this ticket';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvals - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Pending Approvals</h1>
                <p>Review and approve/reject pending tickets at your approval level</p>
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
            
            <?php if ($tickets->num_rows > 0): ?>
                <?php while ($ticket = $tickets->fetch_assoc()): ?>
                    <div class="card" style="margin-bottom: 1rem; border-left: 4px solid #ffc107;">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                            <div>
                                <h3 style="margin-bottom: 0.5rem;">
                                    <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" style="text-decoration: none; color: #333;">
                                        #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - <?php echo htmlspecialchars($ticket['title']); ?>
                                    </a>
                                </h3>
                                
                                <div class="mb-2">
                                    <span class="badge priority-<?php echo $ticket['priority']; ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                    <span class="badge badge-<?php echo $ticket['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                    <span class="approval-level active">
                                        Level <?php echo $ticket['approval_level']; ?> Approval
                                    </span>
                                    <br>
                                    <span style="color: #666;">
                                        Category: <?php echo htmlspecialchars($ticket['category_name']); ?>
                                    </span>
                                    <span style="margin-left: 1rem; color: #666;">
                                        Created by: <?php echo htmlspecialchars($ticket['creator_name']); ?>
                                    </span>
                                </div>
                                
                                <p style="color: #666; margin-bottom: 1rem;">
                                    <?php echo nl2br(htmlspecialchars(substr($ticket['description'], 0, 200))); ?>
                                    <?php if (strlen($ticket['description']) > 200): ?>...<?php endif; ?>
                                </p>
                                
                                <small style="color: #999;">
                                    Created: <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                </small>
                            </div>
                            
                            <div>
                                <form method="POST" action="" style="border: 1px solid #ddd; padding: 1rem; border-radius: 5px;">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="comment_<?php echo $ticket['id']; ?>" class="form-label">Comment (Optional)</label>
                                        <textarea id="comment_<?php echo $ticket['id']; ?>" name="comment" 
                                                  class="form-textarea" style="min-height: 80px;"
                                                  placeholder="Add a comment about your decision..."></textarea>
                                    </div>
                                    
                                    <div class="d-flex gap-1">
                                        <button type="submit" name="action" value="approve" 
                                                class="btn btn-success btn-sm"
                                                onclick="return confirm('Are you sure you want to approve this ticket at level <?php echo $ticket['approval_level']; ?>?')">
                                            Approve Level <?php echo $ticket['approval_level']; ?>
                                        </button>
                                        <button type="submit" name="action" value="reject" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to reject this ticket?')">
                                            Reject
                                        </button>
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                           class="btn btn-secondary btn-sm">View Details</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center" style="padding: 3rem;">
                    <h3 style="color: #666;">No pending tickets for approval</h3>
                    <p style="color: #999;">All tickets have been processed or there are no tickets assigned to your approval level.</p>
                    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh page every 30 seconds to check for new tickets
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>

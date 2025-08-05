<?php
require_once 'config.php';

// Check if user is logged in and is technician or admin
if (!isLoggedIn() || ($_SESSION['role'] != 'technician' && $_SESSION['role'] != 'admin')) {
    redirect('index.php');
}

$success = '';
$error = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $new_status = $_POST['new_status'];
    $comment = trim($_POST['comment']);
    
    // Get current ticket info
    $ticket_stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ? AND assigned_to = ?");
    $ticket_stmt->bind_param("ii", $ticket_id, $_SESSION['user_id']);
    $ticket_stmt->execute();
    $ticket = $ticket_stmt->get_result()->fetch_assoc();
    $ticket_stmt->close();
    
    if ($ticket) {
        $old_status = $ticket['status'];
        $solved_at = ($new_status == STATUS_SOLVED) ? 'NOW()' : 'NULL';
        $closed_at = ($new_status == STATUS_CLOSED) ? 'NOW()' : 'NULL';
        
        // Calculate SLA if ticket is being solved
        $sla_duration = null;
        if ($new_status == STATUS_SOLVED) {
            $sla_duration = calculateSLA($ticket['created_at'], date('Y-m-d H:i:s'));
        }
        
        // Update ticket status
        if ($sla_duration !== null) {
            $update_stmt = $conn->prepare("UPDATE tickets SET status = ?, solved_at = $solved_at, closed_at = $closed_at, sla_duration_hours = ? WHERE id = ?");
            $update_stmt->bind_param("sdi", $new_status, $sla_duration, $ticket_id);
        } else {
            $update_stmt = $conn->prepare("UPDATE tickets SET status = ?, solved_at = $solved_at, closed_at = $closed_at WHERE id = ?");
            $update_stmt->bind_param("si", $new_status, $ticket_id);
        }
        
        if ($update_stmt->execute()) {
            // Add comment to history
            $history_comment = $comment ? $comment : "Status updated to " . ucfirst(str_replace('_', ' ', $new_status));
            $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, action_type, old_status, new_status) VALUES (?, ?, ?, 'status_change', ?, ?)");
            $comment_stmt->bind_param("iisss", $ticket_id, $_SESSION['user_id'], $history_comment, $old_status, $new_status);
            $comment_stmt->execute();
            $comment_stmt->close();
            
            // Send notifications
            notifyStakeholders($ticket_id, 'status_changed', ['old_status' => $old_status, 'new_status' => $new_status]);
            
            if ($new_status == STATUS_SOLVED) {
                notifyStakeholders($ticket_id, 'ticket_resolved');
            }
            
            $success = "Ticket status updated successfully";
        } else {
            $error = "Error updating ticket status";
        }
        $update_stmt->close();
    } else {
        $error = "Ticket not found or not assigned to you";
    }
}

// Get technician's assigned tickets
$user_filter = ($_SESSION['role'] == 'admin') ? '' : "AND t.assigned_to = {$_SESSION['user_id']}";
$tickets_query = "
    SELECT t.*, c.name as category_name, u.full_name as creator_name,
           CASE 
               WHEN t.sla_duration_hours IS NOT NULL THEN t.sla_duration_hours
               WHEN t.status IN ('solved', 'closed') THEN NULL
               ELSE TIMESTAMPDIFF(HOUR, t.created_at, NOW())
           END as current_sla_hours
    FROM tickets t 
    JOIN categories c ON t.category_id = c.id 
    JOIN users u ON t.created_by = u.id 
    WHERE t.status NOT IN ('closed', 'cancelled') $user_filter
    ORDER BY 
        CASE t.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        t.created_at ASC
";
$tickets = $conn->query($tickets_query);

// Get statistics
$stats_filter = ($_SESSION['role'] == 'admin') ? '' : "AND assigned_to = {$_SESSION['user_id']}";
$stats = [];

$result = $conn->query("SELECT COUNT(*) as total FROM tickets WHERE status NOT IN ('closed', 'cancelled') $stats_filter");
$stats['active_tickets'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as open FROM tickets WHERE status = 'open' $stats_filter");
$stats['open_tickets'] = $result->fetch_assoc()['open'];

$result = $conn->query("SELECT COUNT(*) as pending FROM tickets WHERE status = 'pending' $stats_filter");
$stats['pending_tickets'] = $result->fetch_assoc()['pending'];

$result = $conn->query("SELECT COUNT(*) as solved_today FROM tickets WHERE status = 'solved' AND DATE(solved_at) = CURDATE() $stats_filter");
$stats['solved_today'] = $result->fetch_assoc()['solved_today'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header_enhanced.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">
                    <?php echo ($_SESSION['role'] == 'admin') ? 'All Active Tickets' : 'My Assigned Tickets'; ?>
                </h1>
                <p>Manage and resolve tickets efficiently</p>
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
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_tickets']; ?></div>
                    <div class="stat-label">Active Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['open_tickets']; ?></div>
                    <div class="stat-label">Open Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_tickets']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['solved_today']; ?></div>
                    <div class="stat-label">Solved Today</div>
                </div>
            </div>
        </div>
        
        <!-- Tickets List -->
        <?php if ($tickets->num_rows > 0): ?>
            <?php while ($ticket = $tickets->fetch_assoc()): ?>
                <div class="card" style="margin-bottom: 1rem; border-left: 4px solid <?php 
                    echo $ticket['priority'] == 'urgent' ? '#dc3545' : 
                         ($ticket['priority'] == 'high' ? '#fd7e14' : 
                          ($ticket['priority'] == 'medium' ? '#ffc107' : '#28a745')); 
                ?>;">
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
                                
                                <?php if ($ticket['current_sla_hours'] !== null): ?>
                                    <span class="sla-indicator <?php 
                                        echo $ticket['current_sla_hours'] <= 4 ? 'sla-good' : 
                                             ($ticket['current_sla_hours'] <= 24 ? 'sla-warning' : 'sla-critical'); 
                                    ?>">
                                        SLA: <?php echo round($ticket['current_sla_hours'], 1); ?>h
                                    </span>
                                <?php endif; ?>
                                
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
                                    <label for="new_status_<?php echo $ticket['id']; ?>" class="form-label">Update Status</label>
                                    <select id="new_status_<?php echo $ticket['id']; ?>" name="new_status" class="form-select">
                                        <?php 
                                        $status_options = getStatusOptions($ticket['requires_approval']);
                                        foreach ($status_options as $value => $label):
                                            if ($value != $ticket['status']): // Don't show current status
                                        ?>
                                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="comment_<?php echo $ticket['id']; ?>" class="form-label">Comment (Optional)</label>
                                    <textarea id="comment_<?php echo $ticket['id']; ?>" name="comment" 
                                              class="form-textarea" style="min-height: 80px;"
                                              placeholder="Add notes about the status change..."></textarea>
                                </div>
                                
                                <div class="d-flex gap-1">
                                    <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                        Update Status
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
            <div class="card">
                <div class="text-center" style="padding: 3rem;">
                    <h3 style="color: #666;">No active tickets assigned</h3>
                    <p style="color: #999;">Great job! You're all caught up. New tickets will appear here when assigned.</p>
                    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh page every 60 seconds to check for new assignments
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>

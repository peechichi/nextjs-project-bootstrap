<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$ticket_id || !$action) {
    redirect('index.php');
}

// Get ticket details
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$ticket = $result->fetch_assoc()) {
    redirect('index.php');
}
$stmt->close();

$success = '';
$error = '';

// Check permissions
$can_process = false;

switch ($action) {
    case 'cancel':
        // User can cancel their own pending tickets, admin can cancel any
        if (($_SESSION['role'] == 'admin') || 
            ($ticket['created_by'] == $_SESSION['user_id'] && $ticket['status'] == 'pending')) {
            $can_process = true;
        }
        break;
        
    case 'close':
        // Admin or approvers can close approved tickets
        if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'approver') {
            if ($ticket['status'] == 'approved') {
                $can_process = true;
            }
        }
        break;
        
    case 'reopen':
        // Admin can reopen closed/cancelled tickets
        if ($_SESSION['role'] == 'admin') {
            if (in_array($ticket['status'], ['closed', 'cancelled'])) {
                $can_process = true;
            }
        }
        break;
}

if (!$can_process) {
    redirect('view_ticket.php?id=' . $ticket_id);
}

// Process the action
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comment = trim($_POST['comment']);
    $old_status = $ticket['status'];
    $new_status = '';
    $closed_at = 'NULL';
    
    switch ($action) {
        case 'cancel':
            $new_status = 'cancelled';
            $closed_at = 'NOW()';
            break;
        case 'close':
            $new_status = 'closed';
            $closed_at = 'NOW()';
            break;
        case 'reopen':
            $new_status = 'pending';
            $closed_at = 'NULL';
            break;
    }
    
    if ($new_status) {
        // Update ticket status
        $update_stmt = $conn->prepare("UPDATE tickets SET status = ?, closed_at = $closed_at WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $ticket_id);
        
        if ($update_stmt->execute()) {
            // Add comment to history
            $history_comment = $comment ? $comment : ucfirst($action) . 'ed by ' . $_SESSION['full_name'];
            $comment_stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, action_type, old_status, new_status) VALUES (?, ?, ?, 'status_change', ?, ?)");
            $comment_stmt->bind_param("iisss", $ticket_id, $_SESSION['user_id'], $history_comment, $old_status, $new_status);
            $comment_stmt->execute();
            $comment_stmt->close();
            
            $success = "Ticket " . $action . "ed successfully";
            
            // Redirect after 2 seconds
            header("refresh:2;url=view_ticket.php?id=" . $ticket_id);
        } else {
            $error = 'Error processing ticket: ' . $conn->error;
        }
        $update_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Ticket - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo ucfirst($action); ?> Ticket</h1>
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
            
            <?php if (!$success): ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> You are about to <?php echo $action; ?> this ticket. This action will change the ticket status.
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="comment" class="form-label">Comment (Optional)</label>
                        <textarea id="comment" name="comment" class="form-textarea" 
                                  placeholder="Add a comment explaining why you are <?php echo $action; ?>ing this ticket..."></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">
                            Confirm <?php echo ucfirst($action); ?>
                        </button>
                        <a href="view_ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

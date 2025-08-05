<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// Get user's notifications
$stmt = $conn->prepare("
    SELECT n.*, t.ticket_number 
    FROM notifications n 
    JOIN tickets t ON n.ticket_id = t.id 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 50
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Get unread count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$unread_stmt->bind_param("i", $_SESSION['user_id']);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread_count'];
$unread_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-between align-center">
                    <div>
                        <h1 class="card-title">Notifications</h1>
                        <p><?php echo $unread_count; ?> unread notifications</p>
                    </div>
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" action="" style="display: inline;">
                            <button type="submit" name="mark_all_read" class="btn btn-secondary btn-sm">
                                Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="max-height: 600px; overflow-y: auto;">
                <?php if ($notifications->num_rows > 0): ?>
                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <div class="d-flex justify-between align-center">
                                <div style="flex: 1;">
                                    <div class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </div>
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <div class="notification-time">
                                        Ticket: #<?php echo htmlspecialchars($notification['ticket_number']); ?> • 
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="view_ticket.php?id=<?php echo $notification['ticket_id']; ?>" 
                                       class="btn btn-sm btn-primary">View Ticket</a>
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm btn-secondary">
                                                Mark Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center" style="padding: 3rem;">
                        <h3 style="color: #666;">No notifications</h3>
                        <p style="color: #999;">You're all caught up! New notifications will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

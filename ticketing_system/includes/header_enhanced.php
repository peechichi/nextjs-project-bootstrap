<?php
// Get unread notifications count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$unread_stmt->bind_param("i", $_SESSION['user_id']);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread_count'];
$unread_stmt->close();
?>

<header class="header">
    <div class="header-content">
        <div class="logo">
            <a href="index.php" style="color: white; text-decoration: none;">Enhanced Ticketing System</a>
        </div>
        
        <nav>
            <ul class="nav-menu">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="create_ticket_enhanced.php">Create Ticket</a></li>
                
                <?php if ($_SESSION['role'] == 'technician'): ?>
                    <li><a href="my_assignments.php">My Assignments</a></li>
                    <li><a href="technician_dashboard.php">Tech Dashboard</a></li>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'approver' || $_SESSION['role'] == 'admin'): ?>
                    <li><a href="approvals.php">Approvals</a></li>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li><a href="manage_users.php">Users</a></li>
                    <li><a href="manage_categories.php">Categories</a></li>
                    <li><a href="report_enhanced.php">Reports</a></li>
                <?php endif; ?>
                
                <li style="position: relative;">
                    <a href="notifications.php">
                        Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <span class="badge badge-<?php echo $_SESSION['role']; ?>"><?php echo ucfirst($_SESSION['role']); ?></span>
            <a href="profile.php" class="btn btn-sm btn-secondary">Profile</a>
            <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </div>
</header>

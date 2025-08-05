<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get dashboard statistics
$stats = [];

// Total tickets
$result = $conn->query("SELECT COUNT(*) as total FROM tickets");
$stats['total_tickets'] = $result->fetch_assoc()['total'];

// Pending tickets
$result = $conn->query("SELECT COUNT(*) as pending FROM tickets WHERE status = 'pending'");
$stats['pending_tickets'] = $result->fetch_assoc()['pending'];

// My tickets (if not admin)
if ($_SESSION['role'] != 'admin') {
    $stmt = $conn->prepare("SELECT COUNT(*) as my_tickets FROM tickets WHERE created_by = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['my_tickets'] = $result->fetch_assoc()['my_tickets'];
    $stmt->close();
} else {
    $stats['my_tickets'] = $stats['total_tickets'];
}

// Closed tickets this month
$result = $conn->query("SELECT COUNT(*) as closed FROM tickets WHERE status = 'closed' AND MONTH(closed_at) = MONTH(CURRENT_DATE()) AND YEAR(closed_at) = YEAR(CURRENT_DATE())");
$stats['closed_this_month'] = $result->fetch_assoc()['closed'];

// Get recent tickets
$limit = $_SESSION['role'] == 'admin' ? '' : "WHERE created_by = {$_SESSION['user_id']}";
$recent_tickets_query = "
    SELECT t.*, c.name as category_name, u.full_name as creator_name 
    FROM tickets t 
    JOIN categories c ON t.category_id = c.id 
    JOIN users u ON t.created_by = u.id 
    $limit
    ORDER BY t.created_at DESC 
    LIMIT 10
";
$recent_tickets = $conn->query($recent_tickets_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_tickets']; ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_tickets']; ?></div>
                    <div class="stat-label">Pending Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['my_tickets']; ?></div>
                    <div class="stat-label"><?php echo $_SESSION['role'] == 'admin' ? 'All Tickets' : 'My Tickets'; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['closed_this_month']; ?></div>
                    <div class="stat-label">Closed This Month</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Tickets</h2>
                <a href="create_ticket.php" class="btn btn-primary">Create New Ticket</a>
            </div>
            
            <?php if ($recent_tickets->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ticket = $recent_tickets->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['category_name']); ?></td>
                                <td>
                                    <span class="badge priority-<?php echo $ticket['priority']; ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $ticket['status']; ?>">
                                        <?php echo ucfirst($ticket['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['creator_name']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></td>
                                <td>
                                    <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No tickets found.</p>
            <?php endif; ?>
        </div>
        
        <div class="d-flex gap-2">
            <a href="create_ticket.php" class="btn btn-primary">Create Ticket</a>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="manage_users.php" class="btn btn-secondary">Manage Users</a>
                <a href="manage_categories.php" class="btn btn-secondary">Manage Categories</a>
                <a href="report.php" class="btn btn-warning">Reports</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] == 'approver' || $_SESSION['role'] == 'admin'): ?>
                <a href="approvals.php" class="btn btn-success">Approvals</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

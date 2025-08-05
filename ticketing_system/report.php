<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    redirect('index.php');
}

// Get date range from form or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Build WHERE clause for filters
$where_conditions = ["t.closed_at IS NOT NULL"];
$params = [];
$param_types = "";

if ($start_date) {
    $where_conditions[] = "DATE(t.closed_at) >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}

if ($end_date) {
    $where_conditions[] = "DATE(t.closed_at) <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if ($category_filter) {
    $where_conditions[] = "t.category_id = ?";
    $params[] = $category_filter;
    $param_types .= "i";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get ticket closing time statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_closed,
        AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) as avg_hours,
        MIN(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) as min_hours,
        MAX(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) as max_hours,
        AVG(CASE WHEN t.status = 'approved' THEN TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at) END) as avg_approved_hours,
        COUNT(CASE WHEN t.status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN t.status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(CASE WHEN t.status = 'cancelled' THEN 1 END) as cancelled_count
    FROM tickets t 
    $where_clause
";

if (!empty($params)) {
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bind_param($param_types, ...$params);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
} else {
    $stats_result = $conn->query($stats_query);
}

$stats = $stats_result->fetch_assoc();

// Get detailed ticket list with closing times
$details_query = "
    SELECT 
        t.ticket_number,
        t.title,
        t.status,
        t.priority,
        c.name as category_name,
        u.full_name as creator_name,
        approver.full_name as approver_name,
        t.created_at,
        t.closed_at,
        TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at) as hours_to_close,
        TIMESTAMPDIFF(DAY, t.created_at, t.closed_at) as days_to_close
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN users u ON t.created_by = u.id
    LEFT JOIN users approver ON t.approved_by = approver.id
    $where_clause
    ORDER BY t.closed_at DESC
    LIMIT 100
";

if (!empty($params)) {
    $details_stmt = $conn->prepare($details_query);
    $details_stmt->bind_param($param_types, ...$params);
    $details_stmt->execute();
    $details_result = $details_stmt->get_result();
} else {
    $details_result = $conn->query($details_query);
}

// Get category-wise statistics
$category_stats_query = "
    SELECT 
        c.name as category_name,
        COUNT(*) as total_tickets,
        AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) as avg_hours,
        COUNT(CASE WHEN t.status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN t.status = 'rejected' THEN 1 END) as rejected_count
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    $where_clause
    GROUP BY c.id, c.name
    ORDER BY total_tickets DESC
";

if (!empty($params)) {
    $category_stats_stmt = $conn->prepare($category_stats_query);
    $category_stats_stmt->bind_param($param_types, ...$params);
    $category_stats_stmt->execute();
    $category_stats_result = $category_stats_stmt->get_result();
} else {
    $category_stats_result = $conn->query($category_stats_query);
}

// Get monthly trend data
$monthly_query = "
    SELECT 
        DATE_FORMAT(t.closed_at, '%Y-%m') as month,
        COUNT(*) as total_closed,
        AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) as avg_hours
    FROM tickets t
    $where_clause
    GROUP BY DATE_FORMAT(t.closed_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";

if (!empty($params)) {
    $monthly_stmt = $conn->prepare($monthly_query);
    $monthly_stmt->bind_param($param_types, ...$params);
    $monthly_stmt->execute();
    $monthly_result = $monthly_stmt->get_result();
} else {
    $monthly_result = $conn->query($monthly_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Ticket Reports & Analytics</h1>
                <p>Analyze ticket closing times and performance metrics</p>
            </div>
            
            <!-- Filters -->
            <form method="GET" action="" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id" class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">All Categories</option>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                        <a href="report.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
            
            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_closed'] ?: 0; ?></div>
                    <div class="stat-label">Total Closed Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['avg_hours'] ? round($stats['avg_hours'], 1) : 0; ?>h</div>
                    <div class="stat-label">Average Closing Time</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['approved_count'] ?: 0; ?></div>
                    <div class="stat-label">Approved Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['rejected_count'] ?: 0; ?></div>
                    <div class="stat-label">Rejected Tickets</div>
                </div>
            </div>
            
            <?php if ($stats['total_closed'] > 0): ?>
                <!-- Additional Metrics -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Performance Metrics</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2rem;">
                        <div>
                            <h4>Closing Time Range</h4>
                            <p><strong>Fastest:</strong> <?php echo round($stats['min_hours'], 1); ?> hours</p>
                            <p><strong>Slowest:</strong> <?php echo round($stats['max_hours'], 1); ?> hours</p>
                            <p><strong>Average (Approved):</strong> <?php echo $stats['avg_approved_hours'] ? round($stats['avg_approved_hours'], 1) : 'N/A'; ?> hours</p>
                        </div>
                        
                        <div>
                            <h4>Resolution Rate</h4>
                            <p><strong>Approved:</strong> <?php echo round(($stats['approved_count'] / $stats['total_closed']) * 100, 1); ?>%</p>
                            <p><strong>Rejected:</strong> <?php echo round(($stats['rejected_count'] / $stats['total_closed']) * 100, 1); ?>%</p>
                            <p><strong>Cancelled:</strong> <?php echo round(($stats['cancelled_count'] / $stats['total_closed']) * 100, 1); ?>%</p>
                        </div>
                        
                        <div>
                            <h4>Time Categories</h4>
                            <?php
                            $fast_count = 0;
                            $medium_count = 0;
                            $slow_count = 0;
                            
                            // Reset result pointer for counting
                            if (!empty($params)) {
                                $count_stmt = $conn->prepare($details_query);
                                $count_stmt->bind_param($param_types, ...$params);
                                $count_stmt->execute();
                                $count_result = $count_stmt->get_result();
                            } else {
                                $count_result = $conn->query($details_query);
                            }
                            
                            while ($ticket = $count_result->fetch_assoc()) {
                                if ($ticket['hours_to_close'] <= 24) $fast_count++;
                                elseif ($ticket['hours_to_close'] <= 72) $medium_count++;
                                else $slow_count++;
                            }
                            ?>
                            <p><strong>Fast (≤24h):</strong> <?php echo $fast_count; ?> tickets</p>
                            <p><strong>Medium (24-72h):</strong> <?php echo $medium_count; ?> tickets</p>
                            <p><strong>Slow (>72h):</strong> <?php echo $slow_count; ?> tickets</p>
                        </div>
                    </div>
                </div>
                
                <!-- Category Statistics -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Category Performance</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Tickets</th>
                                <th>Avg. Closing Time</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cat_stat = $category_stats_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat_stat['category_name']); ?></td>
                                    <td><?php echo $cat_stat['total_tickets']; ?></td>
                                    <td><?php echo round($cat_stat['avg_hours'], 1); ?> hours</td>
                                    <td><span class="badge badge-approved"><?php echo $cat_stat['approved_count']; ?></span></td>
                                    <td><span class="badge badge-rejected"><?php echo $cat_stat['rejected_count']; ?></span></td>
                                    <td><?php echo round(($cat_stat['approved_count'] / $cat_stat['total_tickets']) * 100, 1); ?>%</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Monthly Trend -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Monthly Trend</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Tickets Closed</th>
                                <th>Avg. Closing Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($monthly = $monthly_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($monthly['month'] . '-01')); ?></td>
                                    <td><?php echo $monthly['total_closed']; ?></td>
                                    <td><?php echo round($monthly['avg_hours'], 1); ?> hours</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Detailed Ticket List -->
                <div class="card">
                    <h3>Detailed Ticket List (Last 100)</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Closed</th>
                                <th>Time to Close</th>
                                <th>Approver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ticket = $details_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="view_ticket.php?id=<?php echo $ticket['ticket_number']; ?>" style="text-decoration: none;">
                                            <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($ticket['title'], 0, 50)); ?><?php echo strlen($ticket['title']) > 50 ? '...' : ''; ?></td>
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
                                    <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($ticket['closed_at'])); ?></td>
                                    <td>
                                        <strong><?php echo $ticket['days_to_close']; ?>d <?php echo $ticket['hours_to_close'] % 24; ?>h</strong>
                                        <br><small>(<?php echo $ticket['hours_to_close']; ?> hours)</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['approver_name']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center" style="padding: 3rem;">
                    <h3 style="color: #666;">No closed tickets found</h3>
                    <p style="color: #999;">Try adjusting your date range or category filter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

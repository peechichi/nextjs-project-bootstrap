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
$where_conditions = ["t.solved_at IS NOT NULL"];
$params = [];
$param_types = "";

if ($start_date) {
    $where_conditions[] = "DATE(t.solved_at) >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}

if ($end_date) {
    $where_conditions[] = "DATE(t.solved_at) <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if ($category_filter) {
    $where_conditions[] = "t.category_id = ?";
    $params[] = $category_filter;
    $param_types .= "i";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get ticket resolution statistics (SLA calculated from created to solved)
$stats_query = "
    SELECT 
        COUNT(*) as total_solved,
        AVG(t.sla_duration_hours) as avg_sla_hours,
        MIN(t.sla_duration_hours) as min_sla_hours,
        MAX(t.sla_duration_hours) as max_sla_hours,
        COUNT(CASE WHEN t.requires_approval = 1 THEN 1 END) as approval_tickets,
        COUNT(CASE WHEN t.requires_approval = 0 THEN 1 END) as direct_tickets,
        AVG(CASE WHEN t.requires_approval = 1 THEN t.sla_duration_hours END) as avg_approval_sla,
        AVG(CASE WHEN t.requires_approval = 0 THEN t.sla_duration_hours END) as avg_direct_sla
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

// Get detailed ticket list with SLA times
$details_query = "
    SELECT 
        t.ticket_number,
        t.title,
        t.status,
        t.priority,
        t.requires_approval,
        c.name as category_name,
        u.full_name as creator_name,
        tech.full_name as technician_name,
        t.created_at,
        t.solved_at,
        t.sla_duration_hours,
        CASE 
            WHEN t.sla_duration_hours <= 4 THEN 'Excellent'
            WHEN t.sla_duration_hours <= 24 THEN 'Good'
            WHEN t.sla_duration_hours <= 72 THEN 'Fair'
            ELSE 'Needs Improvement'
        END as sla_rating
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN users u ON t.created_by = u.id
    LEFT JOIN users tech ON t.assigned_to = tech.id
    $where_clause
    ORDER BY t.solved_at DESC
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

// Get category-wise performance
$category_stats_query = "
    SELECT 
        c.name as category_name,
        c.department,
        COUNT(*) as total_tickets,
        AVG(t.sla_duration_hours) as avg_sla_hours,
        COUNT(CASE WHEN t.requires_approval = 1 THEN 1 END) as approval_required,
        COUNT(CASE WHEN t.requires_approval = 0 THEN 1 END) as direct_assignment,
        COUNT(CASE WHEN t.sla_duration_hours <= 24 THEN 1 END) as within_24h,
        COUNT(CASE WHEN t.sla_duration_hours > 72 THEN 1 END) as over_72h
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    $where_clause
    GROUP BY c.id, c.name, c.department
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

// Get technician performance
$tech_performance_query = "
    SELECT 
        u.full_name as technician_name,
        u.department,
        COUNT(*) as tickets_solved,
        AVG(t.sla_duration_hours) as avg_sla_hours,
        COUNT(CASE WHEN t.sla_duration_hours <= 24 THEN 1 END) as fast_resolution,
        COUNT(CASE WHEN t.priority = 'urgent' THEN 1 END) as urgent_tickets
    FROM tickets t
    JOIN users u ON t.assigned_to = u.id
    $where_clause AND u.role = 'technician'
    GROUP BY u.id, u.full_name, u.department
    HAVING tickets_solved > 0
    ORDER BY tickets_solved DESC
";

if (!empty($params)) {
    $tech_stmt = $conn->prepare($tech_performance_query);
    $tech_stmt->bind_param($param_types, ...$params);
    $tech_stmt->execute();
    $tech_result = $tech_stmt->get_result();
} else {
    $tech_result = $conn->query($tech_performance_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Reports - Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header_enhanced.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Enhanced Ticket Reports & Analytics</h1>
                <p>Comprehensive analysis with revised SLA calculations (Created → Solved)</p>
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
                        <a href="report_enhanced.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
            
            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_solved'] ?: 0; ?></div>
                    <div class="stat-label">Total Solved Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['avg_sla_hours'] ? round($stats['avg_sla_hours'], 1) : 0; ?>h</div>
                    <div class="stat-label">Average Resolution Time</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['approval_tickets'] ?: 0; ?></div>
                    <div class="stat-label">Approval Required</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['direct_tickets'] ?: 0; ?></div>
                    <div class="stat-label">Direct Assignment</div>
                </div>
            </div>
            
            <?php if ($stats['total_solved'] > 0): ?>
                <!-- Enhanced Metrics -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Performance Metrics</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2rem;">
                        <div>
                            <h4>Resolution Time Range</h4>
                            <p><strong>Fastest:</strong> <?php echo round($stats['min_sla_hours'], 1); ?> hours</p>
                            <p><strong>Slowest:</strong> <?php echo round($stats['max_sla_hours'], 1); ?> hours</p>
                            <p><strong>Approval Avg:</strong> <?php echo $stats['avg_approval_sla'] ? round($stats['avg_approval_sla'], 1) : 'N/A'; ?> hours</p>
                            <p><strong>Direct Avg:</strong> <?php echo $stats['avg_direct_sla'] ? round($stats['avg_direct_sla'], 1) : 'N/A'; ?> hours</p>
                        </div>
                        
                        <div>
                            <h4>Workflow Distribution</h4>
                            <p><strong>Approval Required:</strong> <?php echo round(($stats['approval_tickets'] / $stats['total_solved']) * 100, 1); ?>%</p>
                            <p><strong>Direct Assignment:</strong> <?php echo round(($stats['direct_tickets'] / $stats['total_solved']) * 100, 1); ?>%</p>
                        </div>
                        
                        <div>
                            <h4>SLA Performance</h4>
                            <?php
                            $excellent = $good = $fair = $poor = 0;
                            
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
                                if ($ticket['sla_duration_hours'] <= 4) $excellent++;
                                elseif ($ticket['sla_duration_hours'] <= 24) $good++;
                                elseif ($ticket['sla_duration_hours'] <= 72) $fair++;
                                else $poor++;
                            }
                            ?>
                            <p><strong>Excellent (≤4h):</strong> <?php echo $excellent; ?> tickets</p>
                            <p><strong>Good (4-24h):</strong> <?php echo $good; ?> tickets</p>
                            <p><strong>Fair (24-72h):</strong> <?php echo $fair; ?> tickets</p>
                            <p><strong>Poor (>72h):</strong> <?php echo $poor; ?> tickets</p>
                        </div>
                    </div>
                </div>
                
                <!-- Category Performance -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Category Performance</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Department</th>
                                <th>Total Tickets</th>
                                <th>Avg SLA (hours)</th>
                                <th>Approval Required</th>
                                <th>Direct Assignment</th>
                                <th>Within 24h</th>
                                <th>Over 72h</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cat_stat = $category_stats_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat_stat['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cat_stat['department']); ?></td>
                                    <td><?php echo $cat_stat['total_tickets']; ?></td>
                                    <td><?php echo round($cat_stat['avg_sla_hours'], 1); ?></td>
                                    <td><span class="badge badge-warning"><?php echo $cat_stat['approval_required']; ?></span></td>
                                    <td><span class="badge badge-info"><?php echo $cat_stat['direct_assignment']; ?></span></td>
                                    <td><span class="badge badge-approved"><?php echo $cat_stat['within_24h']; ?></span></td>
                                    <td><span class="badge badge-rejected"><?php echo $cat_stat['over_72h']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Technician Performance -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Technician Performance</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Technician</th>
                                <th>Department</th>
                                <th>Tickets Solved</th>
                                <th>Avg SLA (hours)</th>
                                <th>Fast Resolution (≤24h)</th>
                                <th>Urgent Tickets</th>
                                <th>Performance Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tech = $tech_result->fetch_assoc()): ?>
                                <?php 
                                $performance_rating = $tech['avg_sla_hours'] <= 24 ? 'Excellent' : 
                                                     ($tech['avg_sla_hours'] <= 48 ? 'Good' : 'Needs Improvement');
                                $rating_class = $tech['avg_sla_hours'] <= 24 ? 'badge-approved' : 
                                               ($tech['avg_sla_hours'] <= 48 ? 'badge-warning' : 'badge-rejected');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tech['technician_name']); ?></td>
                                    <td><?php echo htmlspecialchars($tech['department']); ?></td>
                                    <td><?php echo $tech['tickets_solved']; ?></td>
                                    <td><?php echo round($tech['avg_sla_hours'], 1); ?></td>
                                    <td><?php echo $tech['fast_resolution']; ?></td>
                                    <td><?php echo $tech['urgent_tickets']; ?></td>
                                    <td><span class="badge <?php echo $rating_class; ?>"><?php echo $performance_rating; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Detailed Ticket List -->
                <div class="card">
                    <h3>Detailed Resolution History (Last 100)</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Workflow</th>
                                <th>Created</th>
                                <th>Solved</th>
                                <th>SLA Time</th>
                                <th>Rating</th>
                                <th>Technician</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ticket = $details_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($ticket['title'], 0, 30)); ?><?php echo strlen($ticket['title']) > 30 ? '...' : ''; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['category_name']); ?></td>
                                    <td>
                                        <span class="badge priority-<?php echo $ticket['priority']; ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $ticket['requires_approval'] ? 'badge-warning' : 'badge-info'; ?>">
                                            <?php echo $ticket['requires_approval'] ? 'Approval' : 'Direct'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($ticket['solved_at'])); ?></td>
                                    <td><strong><?php echo round($ticket['sla_duration_hours'], 1); ?>h</strong></td>
                                    <td>
                                        <span class="sla-indicator <?php 
                                            echo $ticket['sla_duration_hours'] <= 4 ? 'sla-good' : 
                                                 ($ticket['sla_duration_hours'] <= 24 ? 'sla-warning' : 'sla-critical'); 
                                        ?>">
                                            <?php echo $ticket['sla_rating']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['technician_name']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center" style="padding: 3rem;">
                    <h3 style="color: #666;">No solved tickets found</h3>
                    <p style="color: #999;">Try adjusting your date range or category filter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

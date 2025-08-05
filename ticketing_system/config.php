<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ticketing_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Session configuration
session_start();

// Base URL
define('BASE_URL', 'http://localhost/ticketing_system/');

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');
define('ROLE_APPROVER', 'approver');
define('ROLE_TECHNICIAN', 'technician');

// Ticket status
define('STATUS_NEW', 'new');
define('STATUS_PENDING_APPROVAL', 'pending_approval');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_OPEN', 'open');
define('STATUS_PENDING', 'pending');
define('STATUS_SOLVED', 'solved');
define('STATUS_CLOSED', 'closed');
define('STATUS_CANCELLED', 'cancelled');

// Function to get next approval level for a category
function getNextApprovalLevel($category_id, $current_level) {
    global $conn;
    $stmt = $conn->prepare("SELECT MIN(approval_level) as next_level FROM category_approvers WHERE category_id = ? AND approval_level > ?");
    $stmt->bind_param("ii", $category_id, $current_level);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['next_level'];
}

// Function to get max approval level for a category
function getMaxApprovalLevel($category_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT MAX(approval_level) as max_level FROM category_approvers WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['max_level'] ?: 1;
}

// Function to check if user can approve at specific level
function canApproveAtLevel($user_id, $category_id, $level) {
    global $conn;
    $stmt = $conn->prepare("SELECT 1 FROM category_approvers WHERE user_id = ? AND category_id = ? AND approval_level = ?");
    $stmt->bind_param("iii", $user_id, $category_id, $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $can_approve = $result->num_rows > 0;
    $stmt->close();
    return $can_approve;
}

// Function to check if category requires approval
function categoryRequiresApproval($category_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as approver_count FROM category_approvers WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['approver_count'] > 0;
}

// Function to get available technician for auto-assignment
function getAvailableTechnician() {
    global $conn;
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name, COUNT(t.id) as ticket_count 
        FROM users u 
        LEFT JOIN tickets t ON u.id = t.assigned_to AND t.status NOT IN ('solved', 'closed', 'cancelled')
        WHERE u.role = 'technician' AND u.status = 'active'
        GROUP BY u.id, u.full_name
        ORDER BY ticket_count ASC, RAND()
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $technician = $result->fetch_assoc();
    $stmt->close();
    return $technician;
}

// Function to calculate SLA duration
function calculateSLA($created_at, $solved_at) {
    if (!$solved_at) return null;
    
    $created = new DateTime($created_at);
    $solved = new DateTime($solved_at);
    $interval = $created->diff($solved);
    
    // Convert to hours
    $hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
    return round($hours, 2);
}

// Function to create notification
function createNotification($ticket_id, $user_id, $type, $title, $message) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (ticket_id, user_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $ticket_id, $user_id, $type, $title, $message);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to send email notification (placeholder - implement with actual email service)
function sendEmailNotification($email, $subject, $message) {
    // Placeholder for email functionality
    // In production, integrate with services like PHPMailer, SendGrid, etc.
    error_log("Email notification: To: $email, Subject: $subject, Message: $message");
    return true;
}

// Function to get status options based on approval requirement
function getStatusOptions($requires_approval = true) {
    if ($requires_approval) {
        return [
            'new' => 'New',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'open' => 'Open',
            'pending' => 'Pending',
            'solved' => 'Solved',
            'closed' => 'Closed'
        ];
    } else {
        return [
            'new' => 'New',
            'open' => 'Open',
            'pending' => 'Pending',
            'solved' => 'Solved',
            'closed' => 'Closed'
        ];
    }
}

// Function to get next status in workflow
function getNextStatus($current_status, $requires_approval = true) {
    if ($requires_approval) {
        $workflow = [
            'new' => 'pending_approval',
            'pending_approval' => 'approved',
            'approved' => 'open',
            'open' => 'pending',
            'pending' => 'solved',
            'solved' => 'closed'
        ];
    } else {
        $workflow = [
            'new' => 'open',
            'open' => 'pending',
            'pending' => 'solved',
            'solved' => 'closed'
        ];
    }
    
    return isset($workflow[$current_status]) ? $workflow[$current_status] : $current_status;
}

// Function to notify stakeholders about ticket events
function notifyStakeholders($ticket_id, $event_type, $additional_data = []) {
    global $conn;
    
    // Get ticket details
    $stmt = $conn->prepare("
        SELECT t.*, c.name as category_name, 
               creator.email as creator_email, creator.full_name as creator_name,
               assignee.email as assignee_email, assignee.full_name as assignee_name
        FROM tickets t 
        JOIN categories c ON t.category_id = c.id 
        JOIN users creator ON t.created_by = creator.id
        LEFT JOIN users assignee ON t.assigned_to = assignee.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$ticket) return false;
    
    $notifications = [];
    
    switch ($event_type) {
        case 'ticket_created':
            $title = "New Ticket Created: #{$ticket['ticket_number']}";
            $message = "A new ticket has been created: {$ticket['title']}";
            
            // Notify creator
            $notifications[] = ['user_id' => $ticket['created_by'], 'email' => $ticket['creator_email']];
            
            // Notify admin
            $admin_stmt = $conn->prepare("SELECT id, email FROM users WHERE role = 'admin' AND status = 'active'");
            $admin_stmt->execute();
            $admins = $admin_stmt->get_result();
            while ($admin = $admins->fetch_assoc()) {
                $notifications[] = ['user_id' => $admin['id'], 'email' => $admin['email']];
            }
            $admin_stmt->close();
            break;
            
        case 'ticket_assigned':
            $title = "Ticket Assigned: #{$ticket['ticket_number']}";
            $message = "Ticket has been assigned to {$ticket['assignee_name']}";
            
            // Notify assignee
            if ($ticket['assigned_to']) {
                $notifications[] = ['user_id' => $ticket['assigned_to'], 'email' => $ticket['assignee_email']];
            }
            
            // Notify creator
            $notifications[] = ['user_id' => $ticket['created_by'], 'email' => $ticket['creator_email']];
            break;
            
        case 'status_changed':
            $old_status = $additional_data['old_status'] ?? '';
            $new_status = $additional_data['new_status'] ?? '';
            $title = "Ticket Status Updated: #{$ticket['ticket_number']}";
            $message = "Ticket status changed from " . ucfirst(str_replace('_', ' ', $old_status)) . " to " . ucfirst(str_replace('_', ' ', $new_status));
            
            // Notify creator and assignee
            $notifications[] = ['user_id' => $ticket['created_by'], 'email' => $ticket['creator_email']];
            if ($ticket['assigned_to']) {
                $notifications[] = ['user_id' => $ticket['assigned_to'], 'email' => $ticket['assignee_email']];
            }
            break;
            
        case 'approval_request':
            $title = "Approval Required: #{$ticket['ticket_number']}";
            $message = "Ticket requires your approval: {$ticket['title']}";
            
            // Notify approvers for this category
            $approver_stmt = $conn->prepare("
                SELECT u.id, u.email FROM category_approvers ca 
                JOIN users u ON ca.user_id = u.id 
                WHERE ca.category_id = ? AND u.status = 'active'
            ");
            $approver_stmt->bind_param("i", $ticket['category_id']);
            $approver_stmt->execute();
            $approvers = $approver_stmt->get_result();
            while ($approver = $approvers->fetch_assoc()) {
                $notifications[] = ['user_id' => $approver['id'], 'email' => $approver['email']];
            }
            $approver_stmt->close();
            break;
            
        case 'ticket_resolved':
            $title = "Ticket Resolved: #{$ticket['ticket_number']}";
            $message = "Ticket has been marked as solved: {$ticket['title']}";
            
            // Notify creator
            $notifications[] = ['user_id' => $ticket['created_by'], 'email' => $ticket['creator_email']];
            break;
    }
    
    // Send notifications
    foreach ($notifications as $notification) {
        // Create in-app notification
        createNotification($ticket_id, $notification['user_id'], $event_type, $title, $message);
        
        // Send email notification
        sendEmailNotification($notification['email'], $title, $message);
    }
    
    return true;
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}
?>

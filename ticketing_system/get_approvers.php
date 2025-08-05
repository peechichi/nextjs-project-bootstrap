<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if (!$category_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Category ID is required']);
    exit;
}

// Get approvers for the specified category
$stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.department 
    FROM category_approvers ca 
    JOIN users u ON ca.user_id = u.id 
    WHERE ca.category_id = ? AND u.status = 'active'
    ORDER BY u.full_name
");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

$approvers = [];
while ($row = $result->fetch_assoc()) {
    $approvers[] = $row;
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($approvers);
?>

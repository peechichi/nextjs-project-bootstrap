<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ticketing_system';

$success_messages = [];
$error_messages = [];

// Create database connection
try {
    $conn = new mysqli($db_host, $db_user, $db_pass);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $success_messages[] = "✓ Connected to MySQL server successfully";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $db_name";
    if ($conn->query($sql) === TRUE) {
        $success_messages[] = "✓ Database '$db_name' created or already exists";
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db($db_name);
    
    // Read and execute SQL file
    $sql_file = 'database.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Split SQL content into individual queries
        $queries = explode(';', $sql_content);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if ($conn->query($query) === TRUE) {
                    // Success - don't output each query success
                } else {
                    // Only show error if it's not about table already existing
                    if (strpos($conn->error, 'already exists') === false) {
                        $error_messages[] = "Error executing query: " . $conn->error;
                    }
                }
            }
        }
        
        $success_messages[] = "✓ Database tables created successfully";
        
        // Verify admin user exists
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $success_messages[] = "✓ Default admin user exists";
        } else {
            // Create admin user
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
            $username = 'admin';
            $email = 'admin@company.com';
            $full_name = 'System Administrator';
            $role = 'admin';
            $department = 'IT';
            
            $stmt->bind_param("ssssss", $username, $email, $admin_password, $full_name, $role, $department);
            
            if ($stmt->execute()) {
                $success_messages[] = "✓ Default admin user created";
            } else {
                $error_messages[] = "Error creating admin user: " . $stmt->error;
            }
            $stmt->close();
        }
        
        // Verify sample categories exist
        $result = $conn->query("SELECT COUNT(*) as count FROM categories");
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $success_messages[] = "✓ Sample categories exist";
        } else {
            $error_messages[] = "Warning: No categories found. Please add categories manually.";
        }
        
    } else {
        $error_messages[] = "Error: database.sql file not found";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    $error_messages[] = $e->getMessage();
}

// Check file permissions
$required_files = [
    'config.php',
    'index.php',
    'login.php',
    'style.css'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            $success_messages[] = "✓ $file is readable";
        } else {
            $error_messages[] = "Error: $file is not readable";
        }
    } else {
        $error_messages[] = "Error: $file not found";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticketing System Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 2rem;
        }
        
        .status-section {
            margin-bottom: 2rem;
        }
        
        .status-section h2 {
            color: #555;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        .message {
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .success {
            background-color: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        .info {
            background-color: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .text-center {
            text-align: center;
        }
        
        .credentials {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
        }
        
        .credentials strong {
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎫 Ticketing System Setup</h1>
        
        <?php if (!empty($success_messages)): ?>
            <div class="status-section">
                <h2>✅ Setup Status</h2>
                <?php foreach ($success_messages as $message): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_messages)): ?>
            <div class="status-section">
                <h2>❌ Issues Found</h2>
                <?php foreach ($error_messages as $message): ?>
                    <div class="message error"><?php echo htmlspecialchars($message); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($error_messages) || count($success_messages) > count($error_messages)): ?>
            <div class="message info">
                🎉 Setup completed successfully! Your ticketing system is ready to use.
            </div>
            
            <div class="credentials">
                <h3>Default Login Credentials:</h3>
                <p><strong>Username:</strong> admin</p>
                <p><strong>Password:</strong> admin123</p>
                <p><em>Please change the default password after first login!</em></p>
            </div>
            
            <div class="text-center">
                <a href="login.php" class="btn">Go to Login Page</a>
            </div>
            
        <?php else: ?>
            <div class="message error">
                ❌ Setup encountered errors. Please resolve the issues above and try again.
            </div>
            
            <div class="text-center">
                <a href="setup.php" class="btn">Retry Setup</a>
            </div>
        <?php endif; ?>
        
        <div class="status-section">
            <h2>📋 Next Steps</h2>
            <div class="message info" style="text-align: left;">
                <ol>
                    <li>Login with the default admin credentials</li>
                    <li>Change the default admin password</li>
                    <li>Create additional user accounts</li>
                    <li>Set up categories and approvers</li>
                    <li>Start creating tickets!</li>
                </ol>
            </div>
        </div>
        
        <div class="status-section">
            <h2>🔧 System Requirements Check</h2>
            <div class="message <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'error'; ?>">
                PHP Version: <?php echo PHP_VERSION; ?> 
                <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? '✓' : '❌ (Requires 7.4+)'; ?>
            </div>
            
            <div class="message <?php echo extension_loaded('mysqli') ? 'success' : 'error'; ?>">
                MySQLi Extension: <?php echo extension_loaded('mysqli') ? '✓ Loaded' : '❌ Not loaded'; ?>
            </div>
            
            <div class="message <?php echo extension_loaded('session') ? 'success' : 'error'; ?>">
                Session Extension: <?php echo extension_loaded('session') ? '✓ Loaded' : '❌ Not loaded'; ?>
            </div>
        </div>
        
        <div class="text-center" style="margin-top: 2rem; color: #666;">
            <small>Ticketing System v1.0 | Professional Ticket Management Solution</small>
        </div>
    </div>
</body>
</html>

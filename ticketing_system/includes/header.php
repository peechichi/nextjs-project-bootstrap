<header class="header">
    <div class="header-content">
        <div class="logo">
            <a href="index.php" style="color: white; text-decoration: none;">Ticketing System</a>
        </div>
        
        <nav>
            <ul class="nav-menu">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="create_ticket.php">Create Ticket</a></li>
                
                <?php if ($_SESSION['role'] == 'approver' || $_SESSION['role'] == 'admin'): ?>
                    <li><a href="approvals.php">Approvals</a></li>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li><a href="manage_users.php">Users</a></li>
                    <li><a href="manage_categories.php">Categories</a></li>
                    <li><a href="report.php">Reports</a></li>
                <?php endif; ?>
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

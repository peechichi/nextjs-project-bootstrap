-- Create database
CREATE DATABASE IF NOT EXISTS ticketing_system;
USE ticketing_system;

-- Users table with technician role
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'approver', 'technician') DEFAULT 'user',
    department VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    department VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Category approvers table with approval levels
CREATE TABLE category_approvers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    approval_level INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category_approver_level (category_id, user_id, approval_level)
);

-- Tickets table with enhanced status flow
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('new', 'pending_approval', 'approved', 'rejected', 'open', 'pending', 'solved', 'closed', 'cancelled') DEFAULT 'new',
    current_approval_level INT DEFAULT 1,
    requires_approval BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    assigned_to INT NULL,
    approved_by INT NULL,
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    solved_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    sla_duration_hours DECIMAL(10,2) NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Ticket comments/history table
CREATE TABLE ticket_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    action_type ENUM('comment', 'status_change', 'assignment') DEFAULT 'comment',
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Password reset tokens table
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ticket approvals table to track multi-level approvals
CREATE TABLE ticket_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    approver_id INT NOT NULL,
    approval_level INT NOT NULL,
    status ENUM('approved', 'rejected') NOT NULL,
    comments TEXT,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id),
    UNIQUE KEY unique_ticket_level_approver (ticket_id, approval_level, approver_id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('ticket_created', 'ticket_assigned', 'status_changed', 'approval_request', 'ticket_resolved') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_email BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default users (password: admin123 for all)
INSERT INTO users (username, email, password, full_name, role, department) VALUES 
('admin', 'admin@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'IT'),
('tech1', 'tech1@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Technician', 'technician', 'IT'),
('tech2', 'tech2@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Technician', 'technician', 'IT'),
('approver1', 'approver1@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Approver', 'approver', 'HR'),
('user1', 'user1@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob User', 'user', 'Sales');

-- Insert sample categories
INSERT INTO categories (name, description, department) VALUES 
('IT Support', 'Technical support and IT related issues - No approval needed', 'IT'),
('HR Request', 'Human resources related requests - Requires approval', 'HR'),
('Finance', 'Financial and accounting requests - Requires approval', 'Finance'),
('Facilities', 'Office facilities and maintenance - Requires approval', 'Admin'),
('General Support', 'General support requests - No approval needed', 'IT');

-- Insert sample approver assignments (only for categories that need approval)
INSERT INTO category_approvers (category_id, user_id, approval_level) VALUES 
(2, 4, 1),  -- HR Request - Level 1 approver (Jane Approver)
(3, 4, 1),  -- Finance - Level 1 approver (Jane Approver)
(4, 1, 1);  -- Facilities - Level 1 approver (Admin)

-- Note: Categories 1 (IT Support) and 5 (General Support) have no approvers
-- These will auto-assign to available technicians

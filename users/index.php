<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

$conn = getDBConnection();
$page_title = 'User Accounts';
$page_subtitle = 'Manage hospital staff system accounts and permissions';
$page_active = 'users';

$message = '';
$error = '';
$action = $_GET['action'] ?? '';
$user_id_param = intval($_GET['id'] ?? 0);

// DELETE user
if ($action === 'delete' && $user_id_param) {
    if ($user_id_param === intval($_SESSION['user_id'])) {
        $error = "You cannot delete your own active session account.";
    } else {
        $stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id_param);
        $stmt->execute();
        $user_to_del = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user_to_del) {
            $del = $conn->prepare("DELETE FROM users WHERE id=?");
            $del->bind_param("i", $user_id_param);
            if ($del->execute()) {
                logActivity($conn, $_SESSION['user_id'], 'User Account Deleted', "Deleted user account: " . $user_to_del['username'] . " (" . $user_to_del['full_name'] . ")");
                $message = "User account '" . htmlspecialchars($user_to_del['username']) . "' deleted successfully.";
            } else {
                $error = "Cannot delete user account. It may have linked activity records.";
            }
            $del->close();
        }
    }
}

// ADD user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'add') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'staff';
    $status = $_POST['status'] ?? 'active';

    if (empty($username) || empty($full_name) || empty($password)) {
        $error = 'Please fill in all required fields (Username, Full Name, Password).';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and Confirm Password do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check username uniqueness
        $check = $conn->prepare("SELECT id FROM users WHERE username=?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Username '$username' is already taken. Please choose another.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $hashed_password, $full_name, $email, $role, $status);
            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user_id'], 'User Account Created', "Created user account for $full_name ($username) with role: $role");
                $message = "User account for '$full_name' created successfully.";
            } else {
                $error = "Failed to create user account. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// EDIT user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'edit') {
    $edit_id = intval($_POST['edit_id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'] ?? 'staff';
    $status = $_POST['status'] ?? 'active';

    if ($edit_id === intval($_SESSION['user_id']) && $status === 'inactive') {
        $error = "You cannot deactivate your own active session account.";
    } elseif ($edit_id === intval($_SESSION['user_id']) && $role !== 'admin') {
        $error = "You cannot demote your own admin account.";
    } elseif (empty($full_name)) {
        $error = "Full Name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $full_name, $email, $role, $status, $edit_id);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'User Account Updated', "Updated user ID #$edit_id ($full_name)");
            $message = "User account updated successfully.";
        } else {
            $error = "Failed to update user account.";
        }
        $stmt->close();
    }
}

// RESET PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'reset_password') {
    $reset_id = intval($_POST['reset_id']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if (empty($new_password) || strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_new_password) {
        $error = "New password confirmation does not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $reset_id);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Password Reset', "Reset password for user ID #$reset_id");
            $message = "Password reset successfully.";
        } else {
            $error = "Failed to reset password.";
        }
        $stmt->close();
    }
}

// Filters & Pagination
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = ["1=1"];
$params = [];
$types = '';

if ($search !== '') {
    $where_clauses[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= 'sss';
}

if (!empty($role_filter)) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = implode(' AND ', $where_clauses);

// Count total
$count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE $where_sql");
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['cnt'];
$count_stmt->close();
$total_pages = ceil($total_rows / $limit);

// Fetch users
$query = "SELECT * FROM users WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt_users = $conn->prepare($query);
if ($types) {
    $bind_types = $types . 'ii';
    $bind_params = array_merge($params, [$limit, $offset]);
    $stmt_users->bind_param($bind_types, ...$bind_params);
} else {
    $stmt_users->bind_param('ii', $limit, $offset);
}
$stmt_users->execute();
$users_result = $stmt_users->get_result();
$stmt_users->close();

// Statistics
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$admin_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$staff_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='staff'")->fetch_assoc()['c'];
$active_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='active'")->fetch_assoc()['c'];

// View/Edit target
$edit_user = null;
if (($action === 'edit' || $action === 'reset_pw') && $user_id_param) {
    $u_stmt = $conn->prepare("SELECT id, username, full_name, email, role, status, created_at FROM users WHERE id=?");
    $u_stmt->bind_param("i", $user_id_param);
    $u_stmt->execute();
    $edit_user = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Accounts - Gerona Hospital IBMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="content-body">

                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-users-gear"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value"><?= number_format($total_users) ?></div>
                            <div class="stat-label">Total Accounts</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value"><?= number_format($admin_count) ?></div>
                            <div class="stat-label">Administrators</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-user-nurse"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value"><?= number_format($staff_count) ?></div>
                            <div class="stat-label">Hospital Staff</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value"><?= number_format($active_count) ?></div>
                            <div class="stat-label">Active Users</div>
                        </div>
                    </div>
                </div>

                <!-- Control Bar & Search Filters -->
                <div class="card card-filters">
                    <div class="card-body">
                        <form method="GET" action="" class="filter-form">
                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" class="form-control" placeholder="Search username, full name, or email..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <select name="role" class="form-control filter-select">
                                <option value="">All Roles</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                            </select>

                            <select name="status" class="form-control filter-select">
                                <option value="">All Statuses</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>

                            <?php if ($search || $role_filter || $status_filter): ?>
                            <a href="/hmsystem/users/index.php" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                            <?php endif; ?>

                            <div style="margin-left: auto;">
                                <button type="button" class="btn btn-success" onclick="openModal('addUserModal')">
                                    <i class="fas fa-user-plus"></i> Add New User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header flex-between">
                        <h3><i class="fas fa-users-cog"></i> User Accounts List</h3>
                        <span class="badge badge-info">Total Records: <?= $total_rows ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User Details</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created Date</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users_result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-user-slash fa-3x text-muted"></i>
                                                <p class="mt-2 text-muted">No user accounts found matching the criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php while ($u = $users_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                                                    <div class="avatar-circle" style="width: 38px; height: 38px; border-radius: 50%; background: <?= $u['role'] === 'admin' ? '#4f46e5' : '#0ea5e9' ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong style="display: block; color: var(--text-color, #1e293b);"><?= htmlspecialchars($u['full_name']) ?></strong>
                                                        <small class="text-muted"><i class="fas fa-envelope"></i> <?= htmlspecialchars($u['email'] ?: 'No email') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code>@<?= htmlspecialchars($u['username']) ?></code>
                                                <?php if (intval($u['id']) === intval($_SESSION['user_id'])): ?>
                                                    <span class="badge badge-sm badge-info" style="margin-left: 4px;">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-secondary' ?>">
                                                    <i class="fas <?= $u['role'] === 'admin' ? 'fa-user-shield' : 'fa-user-nurse' ?>"></i>
                                                    <?= ucfirst($u['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-pill <?= $u['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                                    <i class="fas <?= $u['status'] === 'active' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                                    <?= ucfirst($u['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y h:i A', strtotime($u['created_at'])) ?></td>
                                            <td class="text-right">
                                                <div class="btn-group">
                                                    <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit User">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="?action=reset_pw&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning" title="Reset Password">
                                                        <i class="fas fa-key"></i> Password
                                                    </a>
                                                    <?php if (intval($u['id']) !== intval($_SESSION['user_id'])): ?>
                                                    <a href="?action=delete&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete user account \'<?= htmlspecialchars($u['username']) ?>\'?')" title="Delete Account">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete self">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer pagination-container" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px;">
                            <div class="pagination-info text-muted">
                                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> accounts
                            </div>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>" class="btn btn-sm btn-secondary">&laquo; Prev</a>
                                <?php endif; ?>
                                
                                <span class="btn btn-sm btn-primary disabled">Page <?= $page ?> of <?= $total_pages ?></span>

                                <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>" class="btn btn-sm btn-secondary">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- MODAL: Add User -->
    <div class="modal" id="addUserModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4><i class="fas fa-user-plus"></i> Create New Staff / Admin Account</h4>
                    <button type="button" class="close-btn" onclick="closeModal('addUserModal')">&times;</button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="form_action" value="add">
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Dr. Maria Santos">
                        </div>
                        
                        <div class="grid-2">
                            <div class="form-group mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" required placeholder="e.g. msantos">
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="e.g. msantos@geronahospital.ph">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="password-field-wrapper">
                                    <input type="password" id="user_password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 characters">
                                    <i class="fas fa-eye password-toggle-icon" data-target="user_password" title="Toggle Password Visibility"></i>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="password-field-wrapper">
                                    <input type="password" id="user_confirm_password" name="confirm_password" class="form-control" required minlength="6" placeholder="Re-enter password">
                                    <i class="fas fa-eye password-toggle-icon" data-target="user_confirm_password" title="Toggle Password Visibility"></i>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group mb-3">
                                <label class="form-label">System Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-control" required>
                                    <option value="staff">Staff (Standard Access)</option>
                                    <option value="admin">Administrator (Full Access)</option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Account Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Edit User -->
    <?php if ($action === 'edit' && $edit_user): ?>
    <div class="modal active" id="editUserModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4><i class="fas fa-user-edit"></i> Edit Account Details - @<?= htmlspecialchars($edit_user['username']) ?></h4>
                    <a href="/hmsystem/users/index.php" class="close-btn">&times;</a>
                </div>
                <form method="POST" action="/hmsystem/users/index.php">
                    <input type="hidden" name="form_action" value="edit">
                    <input type="hidden" name="edit_id" value="<?= $edit_user['id'] ?>">
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label class="form-label">Username (Read-only)</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($edit_user['username']) ?>" disabled>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($edit_user['full_name']) ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_user['email']) ?>">
                        </div>

                        <div class="grid-2">
                            <div class="form-group mb-3">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-control" required <?= intval($edit_user['id']) === intval($_SESSION['user_id']) ? 'disabled' : '' ?>>
                                    <option value="staff" <?= $edit_user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                </select>
                                <?php if (intval($edit_user['id']) === intval($_SESSION['user_id'])): ?>
                                    <input type="hidden" name="role" value="admin">
                                    <small class="text-muted">You cannot change your own admin role.</small>
                                <?php endif; ?>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required <?= intval($edit_user['id']) === intval($_SESSION['user_id']) ? 'disabled' : '' ?>>
                                    <option value="active" <?= $edit_user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $edit_user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <?php if (intval($edit_user['id']) === intval($_SESSION['user_id'])): ?>
                                    <input type="hidden" name="status" value="active">
                                    <small class="text-muted">You cannot deactivate your own account.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="/hmsystem/users/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL: Reset Password -->
    <?php if ($action === 'reset_pw' && $edit_user): ?>
    <div class="modal active" id="resetPasswordModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4><i class="fas fa-key"></i> Reset Password for <?= htmlspecialchars($edit_user['full_name']) ?></h4>
                    <a href="/hmsystem/users/index.php" class="close-btn">&times;</a>
                </div>
                <form method="POST" action="/hmsystem/users/index.php">
                    <input type="hidden" name="form_action" value="reset_password">
                    <input type="hidden" name="reset_id" value="<?= $edit_user['id'] ?>">
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            You are resetting the password for <strong>@<?= htmlspecialchars($edit_user['username']) ?></strong>. 
                        </p>
                        <div class="form-group mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="password-field-wrapper">
                                <input type="password" id="reset_new_password" name="new_password" class="form-control" required minlength="6" placeholder="Enter new password (min. 6 chars)">
                                <i class="fas fa-eye password-toggle-icon" data-target="reset_new_password" title="Toggle Password Visibility"></i>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="password-field-wrapper">
                                <input type="password" id="reset_confirm_new_password" name="confirm_new_password" class="form-control" required minlength="6" placeholder="Re-type new password">
                                <i class="fas fa-eye password-toggle-icon" data-target="reset_confirm_new_password" title="Toggle Password Visibility"></i>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="/hmsystem/users/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-shield-alt"></i> Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        }
    </script>
</body>
</html>

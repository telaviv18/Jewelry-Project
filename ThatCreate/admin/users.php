<?php
// Admin users management page - accessible only by admin
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin('../index.php');

// Connect to database
$database = new Database();
$conn = $database->connect();

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = (int)$_POST['role'];
    
    // Validate role
    if (!in_array($new_role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_STAFF, ROLE_CUSTOMER])) {
        $_SESSION['error_message'] = 'Invalid role selected.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $result = $stmt->execute([$new_role, $user_id]);
        
        if ($result) {
            $_SESSION['success_message'] = 'User role updated successfully.';
        } else {
            $_SESSION['error_message'] = 'Failed to update user role.';
        }
    }
    
    redirect('users.php');
}

// Handle user status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $current_user = get_logged_in_user();
    
    // Don't allow deactivating self
    if ($user_id === $current_user['id']) {
        $_SESSION['error_message'] = 'You cannot deactivate your own account.';
    } else {
        // Get current status
        $stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $new_status = ($user['status'] == 'active') ? 'inactive' : 'active';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $result = $stmt->execute([$new_status, $user_id]);
            
            if ($result) {
                $_SESSION['success_message'] = 'User status updated successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to update user status.';
            }
        } else {
            $_SESSION['error_message'] = 'User not found.';
        }
    }
    
    redirect('users.php');
}

// Handle search and filters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$role = isset($_GET['role']) ? (int)$_GET['role'] : 0;
$status = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role > 0) {
    $conditions[] = "role = ?";
    $params[] = $role;
}

if (!empty($status)) {
    $conditions[] = "status = ?";
    $params[] = $status;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $limit);

// Get users with pagination
$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
           (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status != ?) as total_spent
    FROM users u
    $where_clause
    ORDER BY u.id DESC
    LIMIT ?, ?
";

$all_params = array_merge([ORDER_CANCELLED], $params, [$offset, $limit]);
$stmt = $conn->prepare($sql);
$stmt->execute($all_params);
$users = $stmt->fetchAll();

// Current user for comparison
$current_user = get_logged_in_user();

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Users Management</h1>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?= $search; ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="0">All Roles</option>
                        <option value="<?= ROLE_ADMIN; ?>" <?= $role === ROLE_ADMIN ? 'selected' : ''; ?>>Administrators</option>
                        <option value="<?= ROLE_MANAGER; ?>" <?= $role === ROLE_MANAGER ? 'selected' : ''; ?>>Managers</option>
                        <option value="<?= ROLE_STAFF; ?>" <?= $role === ROLE_STAFF ? 'selected' : ''; ?>>Staff</option>
                        <option value="<?= ROLE_CUSTOMER; ?>" <?= $role === ROLE_CUSTOMER ? 'selected' : ''; ?>>Customers</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users List -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No users found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id']; ?></td>
                                    <td><?= $user['name']; ?></td>
                                    <td><?= $user['email']; ?></td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] == ROLE_ADMIN ? 'danger' : ($user['role'] == ROLE_MANAGER ? 'warning' : ($user['role'] == ROLE_STAFF ? 'info' : 'success')); ?>">
                                            <?= get_role_name($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?= ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?= $user['order_count']; ?></td>
                                    <td><?= format_currency($user['total_spent'] ?? 0); ?></td>
                                    <td><?= format_date($user['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <!-- Role change options -->
                                                <li><h6 class="dropdown-header">Change Role</h6></li>
                                                <li>
                                                    <form action="" method="POST" class="px-3 py-1">
                                                        <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                                        <div class="input-group input-group-sm">
                                                            <select name="role" class="form-select form-select-sm">
                                                                <option value="<?= ROLE_ADMIN; ?>" <?= $user['role'] == ROLE_ADMIN ? 'selected' : ''; ?>>Administrator</option>
                                                                <option value="<?= ROLE_MANAGER; ?>" <?= $user['role'] == ROLE_MANAGER ? 'selected' : ''; ?>>Manager</option>
                                                                <option value="<?= ROLE_STAFF; ?>" <?= $user['role'] == ROLE_STAFF ? 'selected' : ''; ?>>Staff</option>
                                                                <option value="<?= ROLE_CUSTOMER; ?>" <?= $user['role'] == ROLE_CUSTOMER ? 'selected' : ''; ?>>Customer</option>
                                                            </select>
                                                            <button type="submit" name="update_role" class="btn btn-sm btn-primary">Update</button>
                                                        </div>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <!-- View orders -->
                                                <li>
                                                    <a class="dropdown-item" href="orders.php?search=<?= $user['email']; ?>">
                                                        <i class="fas fa-shopping-bag me-2"></i>View Orders
                                                    </a>
                                                </li>
                                                
                                                <!-- Toggle status -->
                                                <?php if ($user['id'] != $current_user['id']): ?>
                                                    <li>
                                                        <a class="dropdown-item <?= $user['status'] == 'active' ? 'text-danger' : 'text-success'; ?>" 
                                                           href="?toggle_status=1&id=<?= $user['id']; ?>"
                                                           onclick="return confirm('Are you sure you want to <?= $user['status'] == 'active' ? 'deactivate' : 'activate'; ?> this user?')">
                                                            <i class="fas <?= $user['status'] == 'active' ? 'fa-user-slash' : 'fa-user-check'; ?> me-2"></i>
                                                            <?= $user['status'] == 'active' ? 'Deactivate User' : 'Activate User'; ?>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <?= get_pagination($total_pages, $page, 'users.php?search=' . urlencode($search) . '&role=' . $role . '&status=' . urlencode($status)); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>

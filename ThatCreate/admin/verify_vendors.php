<?php
// Start session and include necessary files
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Restrict access to admins only
require_admin();

// Connect to the database
$database = new Database();
$conn = $database->connect();

// Get all vendors
$stmt = $conn->prepare("SELECT * FROM vendors");
$stmt->execute();
$vendors = $stmt->fetchAll();

// Include header
require_once 'includes/admin_header.php';
?>

<div class="container">
    <h1>All Vendors</h1>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Company Name</th>
                <th>Email</th>
                <th>Verified</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vendors as $vendor): ?>
                <tr>
                    <td><?= $vendor['id']; ?></td>
                    <td><?= htmlspecialchars($vendor['company_name']); ?></td>
                    <td><?= htmlspecialchars($vendor['business_email']); ?></td>
                    <td><?= $vendor['verified'] ? 'Yes' : 'No'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
// Include footer
require_once 'includes/admin_footer.php';
?>

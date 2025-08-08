<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Reviews Management';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $review_id = (int)$_POST['review_id'];
        $new_status = $_POST['status'];
        
        try {
            $stmt = getDBConnection()->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $review_id])) {
                $_SESSION['admin_success'] = 'Review status updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update review status.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating review: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/reviews.php');
    }
    
    if (isset($_POST['delete_review'])) {
        $review_id = (int)$_POST['review_id'];
        try {
            $stmt = getDBConnection()->prepare("DELETE FROM reviews WHERE id = ?");
            if ($stmt->execute([$review_id])) {
                $_SESSION['admin_success'] = 'Review deleted successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to delete review.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error deleting review: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/reviews.php');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$rating_filter = $_GET['rating'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR r.comment LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status_filter) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($rating_filter) {
    $where_conditions[] = "r.rating = ?";
    $params[] = $rating_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get reviews
    $sql = "
        SELECT r.*, p.name as product_name, p.slug as product_slug,
               u.first_name, u.last_name, u.email
        FROM reviews r 
        LEFT JOIN products p ON r.product_id = p.id 
        LEFT JOIN users u ON r.user_id = u.id 
        {$where_clause}
        ORDER BY r.created_at DESC
    ";
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    
} catch (Exception $e) {
    $reviews = [];
    $_SESSION['admin_error'] = 'Error loading reviews: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Reviews Management</h2>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Report
        </button>
        <a href="<?php echo ADMIN_URL; ?>/export.php?type=reviews&format=csv" class="btn btn-outline-success">
            <i class="fas fa-download me-2"></i>Export CSV
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Product, customer or review content">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Rating</label>
                <select class="form-select" name="rating">
                    <option value="">All Ratings</option>
                    <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                    <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                    <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                    <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                    <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="<?php echo ADMIN_URL; ?>/reviews.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reviews Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Reviews (<?php echo count($reviews); ?> items)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($reviews)): ?>
            <div class="text-center py-4">
                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                <h5>No reviews found</h5>
                <p class="text-muted">Customer reviews will appear here when submitted.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Review</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['product_name']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($review['first_name']): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($review['email']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Anonymous</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-1">(<?php echo $review['rating']; ?>)</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="max-width: 300px;">
                                        <?php echo htmlspecialchars(substr($review['comment'], 0, 100)); ?>
                                        <?php if (strlen($review['comment']) > 100): ?>
                                            <span class="text-muted">...</span>
                                            <button class="btn btn-link btn-sm p-0" onclick="showFullReview('<?php echo htmlspecialchars($review['comment']); ?>')">
                                                Read more
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $color = $status_colors[$review['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($review['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($review['created_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $review['product_slug']; ?>" 
                                           class="btn btn-outline-info" title="View Product" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-success update-status-btn" 
                                                data-id="<?php echo $review['id']; ?>"
                                                data-status="<?php echo $review['status']; ?>"
                                                data-product="<?php echo htmlspecialchars($review['product_name']); ?>"
                                                data-customer="<?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this review?')">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" name="delete_review" class="btn btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Review Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="update_review_id" name="review_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="update_product_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" class="form-control" id="update_customer_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="update_status" class="form-label">Status</label>
                        <select class="form-select" id="update_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Full Review Modal -->
<div class="modal fade" id="fullReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Full Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="fullReviewText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handle update status modal
document.addEventListener('DOMContentLoaded', function() {
    const updateButtons = document.querySelectorAll('.update-status-btn');
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('update_review_id').value = this.dataset.id;
            document.getElementById('update_product_name').value = this.dataset.product;
            document.getElementById('update_customer_name').value = this.dataset.customer;
            document.getElementById('update_status').value = this.dataset.status;
        });
    });
});

// Show full review
function showFullReview(comment) {
    document.getElementById('fullReviewText').textContent = comment;
    new bootstrap.Modal(document.getElementById('fullReviewModal')).show();
}

// Export reviews function
function exportReviews() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '<?php echo ADMIN_URL; ?>/reviews.php?' + params.toString();
}
</script>

<?php include 'includes/footer.php'; ?>

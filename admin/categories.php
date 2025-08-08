<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Categories Management';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)));
        
        if (empty($name)) {
            $_SESSION['admin_error'] = 'Category name is required.';
        } else {
            try {
                $stmt = getDBConnection()->prepare("
                    INSERT INTO categories (name, slug, description, status) 
                    VALUES (?, ?, ?, 'active')
                ");
                if ($stmt->execute([$name, $slug, $description])) {
                    $_SESSION['admin_success'] = 'Category added successfully!';
                } else {
                    $_SESSION['admin_error'] = 'Failed to add category.';
                }
            } catch (Exception $e) {
                $_SESSION['admin_error'] = 'Error adding category: ' . $e->getMessage();
            }
        }
        redirect(ADMIN_URL . '/categories.php');
    }
    
    if (isset($_POST['edit_category'])) {
        $id = (int)$_POST['category_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $status = $_POST['status'];
        $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)));
        
        if (empty($name)) {
            $_SESSION['admin_error'] = 'Category name is required.';
        } else {
            try {
                $stmt = getDBConnection()->prepare("
                    UPDATE categories 
                    SET name = ?, slug = ?, description = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                if ($stmt->execute([$name, $slug, $description, $status, $id])) {
                    $_SESSION['admin_success'] = 'Category updated successfully!';
                } else {
                    $_SESSION['admin_error'] = 'Failed to update category.';
                }
            } catch (Exception $e) {
                $_SESSION['admin_error'] = 'Error updating category: ' . $e->getMessage();
            }
        }
        redirect(ADMIN_URL . '/categories.php');
    }
    
    if (isset($_POST['delete_category'])) {
        $id = (int)$_POST['category_id'];
        try {
            // Check if category has products
            $stmt = getDBConnection()->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $product_count = $stmt->fetch()['count'];
            
            if ($product_count > 0) {
                $_SESSION['admin_error'] = "Cannot delete category. It has {$product_count} products assigned to it.";
            } else {
                $stmt = getDBConnection()->prepare("DELETE FROM categories WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $_SESSION['admin_success'] = 'Category deleted successfully!';
                } else {
                    $_SESSION['admin_error'] = 'Failed to delete category.';
                }
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error deleting category: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/categories.php');
    }
}

try {
    // Get categories with product count
    $stmt = getDBConnection()->query("
        SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $_SESSION['admin_error'] = 'Error loading categories: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Categories Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="fas fa-plus me-2"></i>Add New Category
    </button>
</div>

<!-- Categories Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Categories (<?php echo count($categories); ?> items)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($categories)): ?>
            <div class="text-center py-4">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <h5>No categories found</h5>
                <p class="text-muted">Create your first product category.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-2"></i>Add First Category
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($category['slug']); ?></code>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(substr($category['description'], 0, 50)); ?>
                                    <?php if (strlen($category['description']) > 50): ?>...<?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $category['product_count']; ?> products</span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $category['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($category['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($category['created_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary edit-category-btn" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($category['description']); ?>"
                                                data-status="<?php echo $category['status']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="<?php echo SITE_URL; ?>/products.php?category=<?php echo $category['slug']; ?>" 
                                           class="btn btn-outline-info" title="View Products" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($category['product_count'] == 0): ?>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this category?')">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" name="delete_category" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" disabled title="Cannot delete - has products">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="add_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_category_id" name="category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle edit category modal
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-category-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_category_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_description').value = this.dataset.description;
            document.getElementById('edit_status').value = this.dataset.status;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

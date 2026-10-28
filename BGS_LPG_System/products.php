
<?php
require_once 'includes/auth.php';

$page_title = 'Products';
$error = '';
$success = '';
$modal_to_open = '';
$modal_product_id = '';
$edit_form_data = [];
$add_form_data = [];

function product_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD PRODUCT
    if ($action === 'add') {
        $product_name = trim($_POST['product_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '');

        $add_form_data = [
            'product_name' => $product_name,
            'category' => $category,
            'price' => $price
        ];

        if (
            $product_name === '' ||
            $category === '' ||
            $price === '' ||
            !is_numeric($price) ||
            (float)$price < 0
        ) {
            $error = 'Please enter a valid product name, category, and price.';
            $modal_to_open = 'add';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO products (product_name, category, price)
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$product_name, $category, $price]);

                header('Location: products.php?success=added');
                exit;
            } catch (PDOException $e) {
                $error = 'Unable to add the product. Please check your database.';
                $modal_to_open = 'add';
            }
        }
    }

    // EDIT PRODUCT
    elseif ($action === 'edit') {
        $id = trim($_POST['id'] ?? '');
        $product_name = trim($_POST['product_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '');

        $modal_product_id = $id;
        $modal_to_open = 'edit';

        $edit_form_data = [
            'id' => $id,
            'product_name' => $product_name,
            'category' => $category,
            'price' => $price
        ];

        if (
            $id === '' ||
            !ctype_digit($id) ||
            $product_name === '' ||
            $category === '' ||
            $price === '' ||
            !is_numeric($price) ||
            (float)$price < 0
        ) {
            $error = 'Please enter valid product information.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE products
                     SET product_name = ?, category = ?, price = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $product_name,
                    $category,
                    $price,
                    $id
                ]);

                if ($stmt->rowCount() > 0) {
                    header('Location: products.php?success=updated');
                    exit;
                }

                // A rowCount of zero can mean the values did not change.
                $check = $pdo->prepare(
                    "SELECT COUNT(*) FROM products WHERE id = ?"
                );
                $check->execute([$id]);

                if ((int)$check->fetchColumn() > 0) {
                    header('Location: products.php?success=updated');
                    exit;
                }

                $error = 'The selected product could not be found.';
            } catch (PDOException $e) {
                $error = 'Unable to update the product. Please check your database.';
            }
        }
    }

    // DELETE PRODUCT
    elseif ($action === 'delete') {
        $id = trim($_POST['id'] ?? '');
        $modal_product_id = $id;
        $modal_to_open = 'delete';

        if ($id === '' || !ctype_digit($id)) {
            $error = 'Invalid product selected.';
        } else {
            try {
                // Do not delete a product referenced by branch inventory.
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM branch_inventory
                     WHERE product_id = ?"
                );
                $stmt->execute([$id]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $error = 'This product has inventory records and cannot be deleted. Remove or reassign those records first.';
                } else {
                    $stmt = $pdo->prepare(
                        "DELETE FROM products WHERE id = ?"
                    );
                    $stmt->execute([$id]);

                    if ($stmt->rowCount() > 0) {
                        header('Location: products.php?success=deleted');
                        exit;
                    }

                    $error = 'The selected product could not be found.';
                }
            } catch (PDOException $e) {
                $error = 'Unable to delete this product because it may be used in existing records.';
            }
        }
    }
}

// Success messages
if (isset($_GET['success'])) {
    $messages = [
        'added' => 'Product added successfully.',
        'updated' => 'Product updated successfully.',
        'deleted' => 'Product deleted successfully.'
    ];

    $success = $messages[$_GET['success']] ?? '';
}

// Load products
$stmt = $pdo->query(
    "SELECT id, product_name, category, price
     FROM products
     ORDER BY product_name ASC"
);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Find the product involved in a failed edit/delete
$modal_product = null;

if ($modal_product_id !== '' && ctype_digit($modal_product_id)) {
    $stmt = $pdo->prepare(
        "SELECT id, product_name, category, price
         FROM products
         WHERE id = ?"
    );
    $stmt->execute([$modal_product_id]);
    $modal_product = $stmt->fetch(PDO::FETCH_ASSOC);
}

require 'includes/header.php';
?>

<style>
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a[href="products.php"],
.layout > .sidebar a[href="products.php"]:hover {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: bold !important;
    border-left: none !important;
    border-radius: 8px;
}

.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.products-header h1 {
    margin: 0;
    color: #20253B;
    font-size: 2em;
    font-weight: 700;
}

.products-header .muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

.action-btn,
.product-btn {
    cursor: pointer;
    padding: 8px 13px;
    border: none;
    border-radius: 6px;
    color: #FFFFFF;
    text-decoration: none;
    display: inline-block;
    font: inherit;
    font-size: 14px;
    font-weight: 600;
    transition: background-color .2s ease;
}

.btn-add {
    background-color: #1D4ED8;
}

.btn-add:hover {
    background-color: #1E40AF;
}

.btn-edit {
    background-color: #2563EB;
}

.btn-edit:hover {
    background-color: #1D4ED8;
}

.btn-delete {
    background-color: #DC2626;
}

.btn-delete:hover {
    background-color: #991B1B;
}

.btn-cancel {
    background-color: #E2E8F0;
    color: #334155;
}

.btn-cancel:hover {
    background-color: #CBD5E1;
}

.notice-success,
.notice-error {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 16px;
    opacity: 1;
    transition: opacity .5s ease;
}

.notice-success {
    background-color: #DCFCE7;
    color: #166534;
}

.notice-error {
    background-color: #FEE2E2;
    color: #991B1B;
}

.notice-fade {
    opacity: 0;
}

.product-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.product-table {
    width: 100%;
    border-collapse: collapse;
    font: inherit;
}

.product-table th,
.product-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
    font-size: 16px;
}

.product-table th {
    background-color: #F1F5F9;
    color: #20253B;
    font-weight: 600;
    text-align: left;
}

.product-table tr:last-child td {
    border-bottom: none;
}

.product-table td:last-child,
.product-table th:last-child {
    text-align: center;
}

.product-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.product-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(15, 23, 42, .60);
    padding: 20px;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.product-modal.show {
    display: flex !important;
}

.product-modal-content {
    background: #FFFFFF;
    width: 100%;
    max-width: 520px;
    max-height: 95vh;
    overflow-y: auto;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
    animation: productModalIn .18s ease-out;
}

.product-modal-content.modal-small {
    max-width: 460px;
}

@keyframes productModalIn {
    from {
        opacity: 0;
        transform: translateY(-12px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.product-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 18px 22px;
    background-color: #122CBB;
    color: #FFFFFF;
}

.product-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
}

.product-modal-close {
    border: none;
    background: transparent;
    color: #FFFFFF;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    padding: 0 3px;
}

.product-modal-body {
    padding: 24px;
}

.product-modal-body p {
    margin: 0 0 18px;
    color: #64748B;
    line-height: 1.5;
    font-size: 16px;
}

.product-form .form-field {
    margin-bottom: 16px;
}

.product-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #20253B;
    font-size: 16px;
}

.product-form input,
.product-form select {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background: #FFFFFF;
    color: #20253B;
    font: inherit;
    font-size: 16px;
}

.product-form input:focus,
.product-form select:focus {
    outline: none;
    border-color: #2563EB;
}

.product-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.delete-warning {
    padding: 14px;
    margin: 12px 0 18px;
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 8px;
    color: #991B1B;
    line-height: 1.5;
}

.delete-product-name {
    color: #20253B;
    font-weight: 700;
    overflow-wrap: anywhere;
}

@media (max-width: 700px) {
    .products-header {
        align-items: flex-start;
    }

    .product-table th,
    .product-table td {
        padding: 12px 8px;
    }

    .product-modal {
        padding: 14px;
    }

    .product-modal-body {
        padding: 18px;
    }
}
</style>

<!-- Page heading -->
<div class="products-header">
    <div>
        <h1>Products</h1>
        <p class="muted">
            Manage the master list of products sold by BGS LPG Store.
            Branch stock is managed separately in branch inventory.
        </p>
    </div>

    <button type="button" class="product-btn btn-add" id="openAddProduct">
        + Add Product
    </button>
</div>

<?php if ($success !== ''): ?>
    <div class="notice-success"><?= product_escape($success) ?></div>
<?php endif; ?>

<?php if ($error !== '' && $modal_to_open !== 'delete'): ?>
    <div class="notice-error"><?= product_escape($error) ?></div>
<?php endif; ?>

<!-- Product table -->
<section class="panel">
    <div class="products-header" style="margin-bottom:12px;">
        <h2 style="margin:0;">Product List</h2>
        <span>Total Products: <?= count($products) ?></span>
    </div>

    <?php if (empty($products)): ?>
        <p>No products found. Click + Add Product to add your first product.</p>
    <?php else: ?>
        <div class="product-table-wrap">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= product_escape($product['product_name']) ?></td>
                        <td><?= product_escape($product['category']) ?></td>
                        <td>₱<?= number_format((float)$product['price'], 2) ?></td>
                        <td>
                            <div class="product-actions">
                                <button
                                    type="button"
                                    class="action-btn btn-edit js-edit-product"
                                    data-id="<?= product_escape($product['id']) ?>"
                                    data-name="<?= product_escape($product['product_name']) ?>"
                                    data-category="<?= product_escape($product['category']) ?>"
                                    data-price="<?= product_escape($product['price']) ?>"
                                >Edit</button>

                                <button
                                    type="button"
                                    class="action-btn btn-delete js-delete-product"
                                    data-id="<?= product_escape($product['id']) ?>"
                                    data-name="<?= product_escape($product['product_name']) ?>"
                                >Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Add Product modal -->
<div class="product-modal" id="productAddModal" role="dialog"
     aria-modal="true" aria-labelledby="addProductModalTitle">
    <div class="product-modal-content">
        <div class="product-modal-header">
            <h3 id="addProductModalTitle">Add New Product</h3>
            <button type="button" class="product-modal-close"
                    data-close-modal="productAddModal"
                    aria-label="Close">&times;</button>
        </div>

        <div class="product-modal-body">
            <p>Enter the details of the new product below.</p>

            <form method="post" class="product-form" id="addProductForm">
                <input type="hidden" name="action" value="add">

                <div class="form-field">
                    <label for="add_product_name">Product Name</label>
                    <input type="text" id="add_product_name"
                           name="product_name" required maxlength="255"
                           placeholder="Enter product name"
                           value="<?= product_escape($add_form_data['product_name'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="add_category">Category</label>
                    <select id="add_category" name="category" required>
                        <option value="">Select category</option>
                        <?php
                        $categories = ['Cylinder', 'Accessory', 'Fuel Canister'];
                        foreach ($categories as $cat):
                        ?>
                            <option value="<?= product_escape($cat) ?>"
                                <?= (($add_form_data['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                                <?= product_escape($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="add_price">Price (₱)</label>
                    <input type="number" id="add_price" name="price"
                           min="0" step="0.01" required placeholder="0.00"
                           value="<?= product_escape($add_form_data['price'] ?? '') ?>">
                </div>

                <div class="product-modal-footer">
                    <button type="button" class="product-btn btn-cancel"
                            data-close-modal="productAddModal">Cancel</button>
                    <button type="submit" class="product-btn btn-add">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product modal -->
<div class="product-modal" id="productEditModal" role="dialog"
     aria-modal="true" aria-labelledby="editProductModalTitle">
    <div class="product-modal-content">
        <div class="product-modal-header">
            <h3 id="editProductModalTitle">Edit Product</h3>
            <button type="button" class="product-modal-close"
                    data-close-modal="productEditModal"
                    aria-label="Close">&times;</button>
        </div>

        <div class="product-modal-body">
            <p>Update the selected product's information below.</p>

            <form method="post" class="product-form" id="editProductForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_product_id">

                <div class="form-field">
                    <label for="edit_product_name">Product Name</label>
                    <input type="text" id="edit_product_name"
                           name="product_name" required maxlength="255"
                           placeholder="Enter product name">
                </div>

                <div class="form-field">
                    <label for="edit_category">Category</label>
                    <select id="edit_category" name="category" required>
                        <option value="">Select category</option>
                        <option value="Cylinder">Cylinder</option>
                        <option value="Accessory">Accessory</option>
                        <option value="Fuel Canister">Fuel Canister</option>
                    </select>
                </div>

                <div class="form-field">
                    <label for="edit_price">Price (₱)</label>
                    <input type="number" id="edit_price" name="price"
                           min="0" step="0.01" required placeholder="0.00">
                </div>

                <div class="product-modal-footer">
                    <button type="button" class="product-btn btn-cancel"
                            data-close-modal="productEditModal">Cancel</button>
                    <button type="submit" class="product-btn btn-edit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="product-modal" id="productDeleteModal" role="dialog"
     aria-modal="true" aria-labelledby="deleteProductModalTitle">
    <div class="product-modal-content modal-small">
        <div class="product-modal-header">
            <h3 id="deleteProductModalTitle">Delete Product</h3>
            <button type="button" class="product-modal-close"
                    data-close-modal="productDeleteModal"
                    aria-label="Close">&times;</button>
        </div>

        <div class="product-modal-body">
            <p>Are you sure you want to delete this product?</p>

            <div class="delete-warning">
                Product: <span class="delete-product-name" id="deleteProductName"></span>
                <br>
                This action cannot be undone.
            </div>

            <?php if ($modal_to_open === 'delete' && $error !== ''): ?>
                <div class="notice-error"><?= product_escape($error) ?></div>
            <?php endif; ?>

            <form method="post" id="deleteProductForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_product_id">

                <div class="product-modal-footer">
                    <button type="button" class="product-btn btn-cancel"
                            data-close-modal="productDeleteModal">Cancel</button>
                    <button type="submit" class="product-btn btn-delete">
                        Yes, Delete Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    function getElement(id) {
        return document.getElementById(id);
    }

    function openProductModal(modalId) {
        const modal = getElement(modalId);

        if (!modal) {
            console.error('Cannot find modal:', modalId);
            return;
        }

        document.querySelectorAll('.product-modal').forEach(function (item) {
            item.classList.remove('show');
        });

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeProductModal(modalId) {
        const modal = getElement(modalId);

        if (modal) {
            modal.classList.remove('show');
        }

        if (!document.querySelector('.product-modal.show')) {
            document.body.style.overflow = '';
        }
    }

    // Add Product
    getElement('openAddProduct').addEventListener('click', function () {
        getElement('addProductForm').reset();
        openProductModal('productAddModal');
        getElement('add_product_name').focus();
    });

    // Edit Product: read values from safe HTML data attributes.
    document.querySelectorAll('.js-edit-product').forEach(function (button) {
        button.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const category = this.dataset.category;
            const price = this.dataset.price;

            getElement('edit_product_id').value = id;
            getElement('edit_product_name').value = name;
            getElement('edit_price').value = price;

            const categorySelect = getElement('edit_category');
            let optionExists = false;

            Array.from(categorySelect.options).forEach(function (option) {
                if (option.value === category) {
                    optionExists = true;
                }
            });

            // Keep an existing category selectable even if it is not in the list.
            if (!optionExists && category !== '') {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = category;
                categorySelect.appendChild(option);
            }

            categorySelect.value = category;
            openProductModal('productEditModal');
        });
    });

    // Delete Product
    document.querySelectorAll('.js-delete-product').forEach(function (button) {
        button.addEventListener('click', function () {
            getElement('delete_product_id').value = this.dataset.id;
            getElement('deleteProductName').textContent = this.dataset.name;
            openProductModal('productDeleteModal');
        });
    });

    // Close buttons
    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeProductModal(this.dataset.closeModal);
        });
    });

    // Close when clicking the dark backdrop.
    document.querySelectorAll('.product-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeProductModal(modal.id);
            }
        });
    });

    // Close popups with Escape.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.product-modal.show').forEach(function (modal) {
                modal.classList.remove('show');
            });

            document.body.style.overflow = '';
        }
    });

    // Reopen a modal if PHP validation or database handling fails.
    <?php if ($modal_to_open === 'add'): ?>
        openProductModal('productAddModal');
    <?php elseif ($modal_to_open === 'edit' && $modal_product_id !== ''): ?>
        getElement('edit_product_id').value = <?= json_encode((string)$modal_product_id) ?>;
        getElement('edit_product_name').value = <?= json_encode($edit_form_data['product_name'] ?? '') ?>;
        getElement('edit_price').value = <?= json_encode((string)($edit_form_data['price'] ?? '')) ?>;

        (function () {
            const categorySelect = getElement('edit_category');
            const category = <?= json_encode($edit_form_data['category'] ?? '') ?>;
            let found = false;

            Array.from(categorySelect.options).forEach(function (option) {
                if (option.value === category) found = true;
            });

            if (!found && category !== '') {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = category;
                categorySelect.appendChild(option);
            }

            categorySelect.value = category;
        })();

        openProductModal('productEditModal');
    <?php elseif ($modal_to_open === 'delete' && $modal_product !== null): ?>
        getElement('delete_product_id').value = <?= json_encode((string)$modal_product['id']) ?>;
        getElement('deleteProductName').textContent = <?= json_encode($modal_product['product_name']) ?>;
        openProductModal('productDeleteModal');
    <?php endif; ?>

    // Fade notifications after 2.5 seconds.
    document.querySelectorAll('.notice-success, .notice-error').forEach(function (notice) {
        setTimeout(function () {
            notice.classList.add('notice-fade');

            setTimeout(function () {
                notice.remove();
            }, 500);
        }, 2500);
    });
})();
</script>

<?php require 'includes/footer.php'; ?>

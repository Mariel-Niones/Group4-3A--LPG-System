<?php
require_once 'includes/auth.php';
require_owner();

$page_title = 'Branches';
$error = '';
$success = '';
$modal_to_open = '';
$modal_branch_id = '';
$modal_branch_name = '';

function branch_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $branch_name = trim($_POST['branch_name'] ?? '');
    $id = trim($_POST['id'] ?? '');

    // ADD BRANCH
    if ($action === 'add') {
        if ($branch_name === '') {
            $error = 'Please enter a branch name.';
            $modal_to_open = 'add';
        } else {
            try {
                // Generate the next ID: b1, b2, b3, ...
                $stmt = $pdo->query("SELECT id FROM branches");
                $numbers = [];

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (preg_match('/^b(\d+)$/', (string)$row['id'], $matches)) {
                        $numbers[] = (int)$matches[1];
                    }
                }

                $next_number = empty($numbers) ? 1 : max($numbers) + 1;
                $new_id = 'b' . $next_number;

                $stmt = $pdo->prepare(
                    "INSERT INTO branches (id, branch_name) VALUES (?, ?)"
                );
                $stmt->execute([$new_id, $branch_name]);

                header('Location: branches.php?success=added');
                exit;
            } catch (PDOException $e) {
                $error = 'Unable to add the branch. Check whether the branch name already exists or whether the database structure is correct.';
                $modal_to_open = 'add';
            }
        }
    }

    // EDIT BRANCH
    elseif ($action === 'edit') {
        $modal_branch_id = $id;
        $modal_branch_name = $branch_name;
        $modal_to_open = 'edit';

        if ($id === '' || $branch_name === '') {
            $error = 'Please enter a valid branch name.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE branches SET branch_name = ? WHERE id = ?"
                );
                $stmt->execute([$branch_name, $id]);

                $check = $pdo->prepare(
                    "SELECT COUNT(*) FROM branches WHERE id = ?"
                );
                $check->execute([$id]);

                if ((int)$check->fetchColumn() > 0) {
                    header('Location: branches.php?success=updated');
                    exit;
                }

                $error = 'The selected branch could not be found.';
            } catch (PDOException $e) {
                $error = 'Unable to update the branch. Check whether the branch name already exists.';
            }
        }
    }

    // DELETE BRANCH
    elseif ($action === 'delete') {
        $modal_branch_id = $id;
        $modal_branch_name = trim($_POST['branch_name'] ?? '');
        $modal_to_open = 'delete';

        if ($id === '') {
            $error = 'Invalid branch selected.';
        } else {
            try {
                // Verify that the branch exists.
                $check = $pdo->prepare(
                    "SELECT branch_name FROM branches WHERE id = ?"
                );
                $check->execute([$id]);
                $existing_branch = $check->fetch(PDO::FETCH_ASSOC);

                if (!$existing_branch) {
                    $error = 'The selected branch could not be found.';
                } else {
                    $modal_branch_name = $existing_branch['branch_name'];

                    // Delete the branch.
                    // Database constraints may prevent deleting branches
                    // that are still used by inventory or staff records.
                    $stmt = $pdo->prepare(
                        "DELETE FROM branches WHERE id = ?"
                    );
                    $stmt->execute([$id]);

                    if ($stmt->rowCount() > 0) {
                        header('Location: branches.php?success=deleted');
                        exit;
                    }

                    $error = 'The selected branch could not be deleted.';
                }
            } catch (PDOException $e) {
                $error = 'This branch cannot be deleted because it may still be used by inventory, staff, or other records.';
            }
        }
    }
}

// Success messages
if (isset($_GET['success'])) {
    $messages = [
        'added' => 'Branch added successfully.',
        'updated' => 'Branch updated successfully.',
        'deleted' => 'Branch deleted successfully.'
    ];

    $success = $messages[$_GET['success']] ?? '';
}

// Load branches
$branches = $pdo->query(
    "SELECT id, branch_name FROM branches ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Get the branch name for a failed delete if needed
if ($modal_to_open === 'delete' && $modal_branch_id !== '' && $modal_branch_name === '') {
    $stmt = $pdo->prepare(
        "SELECT branch_name FROM branches WHERE id = ?"
    );
    $stmt->execute([$modal_branch_id]);
    $modal_branch_name = (string)($stmt->fetchColumn() ?: '');
}

require 'includes/header.php';
?>

<style>
.layout > .content {
    font-size: 16px;
}

/* Sidebar */
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a {
    color: #FFFFFF !important;
}

.layout > .sidebar a:hover,
.layout > .sidebar a[href="branches.php"],
.layout > .sidebar a[href="branches.php"]:hover {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
}

.layout > .sidebar a[href="branches.php"] {
    font-weight: bold !important;
    border-left: none !important;
    border-radius: 8px;
}

/* Logout */
.layout > .sidebar a[href="logout.php"],
.layout a[href="logout.php"] {
    background-color: #DC2626 !important;
    color: #FFFFFF !important;
    margin-top: 15px;
    text-align: center;
    font-weight: bold;
    border-radius: 8px;
}

.layout > .sidebar a[href="logout.php"]:hover,
.layout a[href="logout.php"]:hover {
    background-color: #991B1B !important;
}

/* Page heading */
.users-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.users-header h1 {
    margin: 0;
    color: #20253B;
    font-size: 2em;
    font-weight: 700;
}

.users-header .muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

/* Buttons */
.branch-btn {
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

.branch-btn-add,
.branch-btn-save {
    background-color: #1D4ED8;
}

.branch-btn-add:hover,
.branch-btn-save:hover {
    background-color: #1E40AF;
}

.branch-btn-edit {
    background-color: #2563EB;
}

.branch-btn-edit:hover {
    background-color: #1D4ED8;
}

.branch-btn-delete {
    background-color: #DC2626;
}

.branch-btn-delete:hover {
    background-color: #991B1B;
}

.branch-btn-cancel {
    background-color: #E2E8F0;
    color: #334155;
}

.branch-btn-cancel:hover {
    background-color: #CBD5E1;
}

.users-header > .branch-btn-add {
    padding: 11px 12px;
    font-size: 14px;
}

/* Notifications */
.branch-alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 16px;
    opacity: 1;
    transition: opacity .5s ease;
}

.branch-alert.fade-out {
    opacity: 0;
}

.branch-error {
    background-color: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.branch-success {
    background-color: #DCFCE7;
    color: #166534;
    border: 1px solid #BBF7D0;
}

/* Branch table */
.branch-panel {
    background-color: #FFFFFF;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, .07);
    margin-bottom: 24px;
}

.branch-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.branch-heading h2,
.branch-heading h3 {
    margin: 0;
    color: #20253B;
    font-size: 1.17em;
    font-weight: 600;
}

.branch-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.branch-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font: inherit;
}

.branch-table th,
.branch-table td {
    padding: 14px 20px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
    overflow-wrap: break-word;
    font-size: 16px;
}

.branch-table th:first-child,
.branch-table td:first-child {
    width: 60%;
    text-align: left;
    padding-left: 30px;
}

.branch-table th:last-child,
.branch-table td:last-child {
    width: 40%;
    text-align: center;
}

.branch-table th {
    background-color: #F1F5F9;
    color: #20253B;
    font-weight: 600;
}

.branch-table tr:last-child td {
    border-bottom: none;
}

.branch-table td[colspan] {
    text-align: center;
    padding: 24px 12px;
    color: #64748B;
}

.branch-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.branch-actions form {
    margin: 0;
}

/* Popup modals */
.branch-modal {
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

.branch-modal.show {
    display: flex !important;
}

.branch-modal-content {
    background: #FFFFFF;
    width: 100%;
    max-width: 520px;
    max-height: 95vh;
    overflow-y: auto;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
    animation: branchModalIn .18s ease-out;
}

.branch-modal-content.modal-small {
    max-width: 460px;
}

@keyframes branchModalIn {
    from {
        opacity: 0;
        transform: translateY(-12px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.branch-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 18px 22px;
    background-color: #122CBB;
    color: #FFFFFF;
}

.branch-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
}

.branch-modal-close {
    border: none;
    background: transparent;
    color: #FFFFFF;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    padding: 0 3px;
}

.branch-modal-body {
    padding: 24px;
}

.branch-modal-body p {
    margin: 0 0 18px;
    color: #64748B;
    line-height: 1.5;
    font-size: 16px;
}

.branch-form .edit-field {
    margin-bottom: 18px;
}

.branch-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #20253B;
    font-size: 16px;
}

.branch-form input {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background: #FFFFFF;
    color: #20253B;
    font: inherit;
}

.branch-form input:focus {
    outline: none;
    border-color: #1D4ED8;
    box-shadow: 0 0 0 3px rgba(29, 78, 216, .12);
}

.branch-modal-footer {
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

.delete-branch-name {
    color: #20253B;
    font-weight: 700;
    overflow-wrap: anywhere;
}

@media (max-width: 700px) {
    .users-header {
        align-items: flex-start;
    }

    .branch-panel {
        padding: 16px;
    }

    .branch-table th,
    .branch-table td {
        padding: 12px 8px;
    }

    .branch-table th:first-child,
    .branch-table td:first-child {
        width: 50%;
        padding-left: 10px;
    }

    .branch-table th:last-child,
    .branch-table td:last-child {
        width: 50%;
    }

    .branch-actions {
        gap: 6px;
    }

    .branch-actions .branch-btn {
        padding: 7px 15px;
    }

    .branch-modal {
        padding: 14px;
    }

    .branch-modal-body {
        padding: 18px;
    }
}

</style>

<!-- Page heading -->
<div class="users-header">
    <div>
        <h1>Branch Management</h1>
        <p class="muted">Manage your store branches.</p>
    </div>

    <button
        type="button"
        class="branch-btn branch-btn-add"
        id="openAddBranch"
    >
        + Add Branch
    </button>
</div>

<!-- Notifications -->
<?php if ($success !== ''): ?>
    <div class="branch-alert branch-success">
        <?= branch_escape($success) ?>
    </div>
<?php endif; ?>

<?php if ($error !== '' && $modal_to_open !== 'delete'): ?>
    <div class="branch-alert branch-error">
        <?= branch_escape($error) ?>
    </div>
<?php endif; ?>

<!-- Branch list -->
<section class="branch-panel">
    <div class="branch-heading">
        <h3>Branch List</h3>
        <span>Total Branches: <?= count($branches) ?></span>
    </div>

    <div class="branch-table-wrap">
        <table class="branch-table">
            <thead>
                <tr>
                    <th>Branch Name</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($branches)): ?>
                    <tr>
                        <td colspan="2">No branches found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td><?= branch_escape($branch['branch_name']) ?></td>
                            <td>
                                <div class="branch-actions">
                                    <button
                                        type="button"
                                        class="branch-btn branch-btn-edit js-edit-branch"
                                        data-id="<?= branch_escape($branch['id']) ?>"
                                        data-name="<?= branch_escape($branch['branch_name']) ?>"
                                    >Edit</button>

                                    <button
                                        type="button"
                                        class="branch-btn branch-btn-delete js-delete-branch"
                                        data-id="<?= branch_escape($branch['id']) ?>"
                                        data-name="<?= branch_escape($branch['branch_name']) ?>"
                                    >Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Add Branch modal -->
<div class="branch-modal" id="branchAddModal" role="dialog"
     aria-modal="true" aria-labelledby="addBranchModalTitle">
    <div class="branch-modal-content">
        <div class="branch-modal-header">
            <h3 id="addBranchModalTitle">Add New Branch</h3>
            <button type="button" class="branch-modal-close"
                    data-close-modal="branchAddModal" aria-label="Close">&times;</button>
        </div>

        <div class="branch-modal-body">
            <p>Enter the name of the new store branch below.</p>

            <form method="post" class="branch-form" id="addBranchForm">
                <input type="hidden" name="action" value="add">

                <div class="edit-field">
                    <label for="new_branch_name">Branch Name</label>
                    <input type="text" id="new_branch_name" name="branch_name"
                           placeholder="Enter branch name" maxlength="100" required
                           value="<?= branch_escape($_POST['branch_name'] ?? '') ?>">
                </div>

                <div class="branch-modal-footer">
                    <button type="button" class="branch-btn branch-btn-cancel"
                            data-close-modal="branchAddModal">Cancel</button>
                    <button type="submit" class="branch-btn branch-btn-save">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch modal -->
<div class="branch-modal" id="branchEditModal" role="dialog"
     aria-modal="true" aria-labelledby="editBranchModalTitle">
    <div class="branch-modal-content">
        <div class="branch-modal-header">
            <h3 id="editBranchModalTitle">Edit Branch</h3>
            <button type="button" class="branch-modal-close"
                    data-close-modal="branchEditModal" aria-label="Close">&times;</button>
        </div>

        <div class="branch-modal-body">
            <p>Update the selected branch name below.</p>

            <form method="post" class="branch-form" id="editBranchForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_branch_id">

                <div class="edit-field">
                    <label for="edit_branch_name">Branch Name</label>
                    <input type="text" id="edit_branch_name" name="branch_name"
                           maxlength="100" required>
                </div>

                <div class="branch-modal-footer">
                    <button type="button" class="branch-btn branch-btn-cancel"
                            data-close-modal="branchEditModal">Cancel</button>
                    <button type="submit" class="branch-btn branch-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="branch-modal" id="branchDeleteModal" role="dialog"
     aria-modal="true" aria-labelledby="deleteBranchModalTitle">
    <div class="branch-modal-content modal-small">
        <div class="branch-modal-header">
            <h3 id="deleteBranchModalTitle">Delete Branch</h3>
            <button type="button" class="branch-modal-close"
                    data-close-modal="branchDeleteModal" aria-label="Close">&times;</button>
        </div>

        <div class="branch-modal-body">
            <p>Are you sure you want to delete this branch?</p>

            <div class="delete-warning">
                Branch: <span class="delete-branch-name" id="deleteBranchName"></span>
                <br>
                This action cannot be undone.
            </div>

            <?php if ($modal_to_open === 'delete' && $error !== ''): ?>
                <div class="branch-alert branch-error">
                    <?= branch_escape($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" id="deleteBranchForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_branch_id">
                <input type="hidden" name="branch_name" id="delete_branch_name">

                <div class="branch-modal-footer">
                    <button type="button" class="branch-btn branch-btn-cancel"
                            data-close-modal="branchDeleteModal">Cancel</button>
                    <button type="submit" class="branch-btn branch-btn-delete">
                        Yes, Delete Branch
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

    function openBranchModal(modalId) {
        const modal = getElement(modalId);

        if (!modal) {
            return;
        }

        document.querySelectorAll('.branch-modal').forEach(function (item) {
            item.classList.remove('show');
        });

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeBranchModal(modalId) {
        const modal = getElement(modalId);

        if (modal) {
            modal.classList.remove('show');
        }

        if (!document.querySelector('.branch-modal.show')) {
            document.body.style.overflow = '';
        }
    }

    // Add Branch
    getElement('openAddBranch').addEventListener('click', function () {
        getElement('addBranchForm').reset();
        openBranchModal('branchAddModal');
        getElement('new_branch_name').focus();
    });

    // Edit Branch
    document.querySelectorAll('.js-edit-branch').forEach(function (button) {
        button.addEventListener('click', function () {
            getElement('edit_branch_id').value = this.dataset.id;
            getElement('edit_branch_name').value = this.dataset.name;

            openBranchModal('branchEditModal');
            getElement('edit_branch_name').focus();
            getElement('edit_branch_name').select();
        });
    });

    // Delete Branch: open confirmation popup instead of browser confirm().
    document.querySelectorAll('.js-delete-branch').forEach(function (button) {
        button.addEventListener('click', function () {
            getElement('delete_branch_id').value = this.dataset.id;
            getElement('delete_branch_name').value = this.dataset.name;
            getElement('deleteBranchName').textContent = this.dataset.name;

            openBranchModal('branchDeleteModal');
        });
    });

    // Close popup buttons
    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeBranchModal(this.dataset.closeModal);
        });
    });

    // Close when clicking outside a popup
    document.querySelectorAll('.branch-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeBranchModal(modal.id);
            }
        });
    });

    // Close popups using Escape
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.branch-modal.show').forEach(function (modal) {
                modal.classList.remove('show');
            });

            document.body.style.overflow = '';
        }
    });

    // Reopen popup if validation or deletion fails
    <?php if ($modal_to_open === 'add'): ?>
        openBranchModal('branchAddModal');
    <?php elseif ($modal_to_open === 'edit' && $modal_branch_id !== ''): ?>
        getElement('edit_branch_id').value = <?= json_encode($modal_branch_id) ?>;
        getElement('edit_branch_name').value = <?= json_encode($modal_branch_name) ?>;
        openBranchModal('branchEditModal');
    <?php elseif ($modal_to_open === 'delete'): ?>
        getElement('delete_branch_id').value = <?= json_encode($modal_branch_id) ?>;
        getElement('delete_branch_name').value = <?= json_encode($modal_branch_name) ?>;
        getElement('deleteBranchName').textContent = <?= json_encode($modal_branch_name) ?>;
        openBranchModal('branchDeleteModal');
    <?php endif; ?>

    // Fade notifications after 2.5 seconds
    document.querySelectorAll('.branch-alert').forEach(function (notice) {
        setTimeout(function () {
            notice.classList.add('fade-out');

            setTimeout(function () {
                notice.remove();
            }, 500);
        }, 2500);
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
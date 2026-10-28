
<?php
require 'includes/auth.php';

if (!is_owner()) {
    header('Location: index.php');
    exit;
}

$page_title = 'Registered Staff';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$branches = $pdo->query(
    "SELECT id, branch_name FROM branches ORDER BY branch_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query(
    "SELECT id, staff_name, branch_name
     FROM users
     WHERE role = 'staff' AND active = 1
     ORDER BY staff_name ASC"
);
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query(
    "SELECT id, staff_name, branch_name
     FROM users
     WHERE role = 'staff' AND active = 0
     ORDER BY staff_name ASC"
);
$deactivatedStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<style>
/* GENERAL CONTENT */
.layout > .sidebar {
    background-color: #1D4ED8 !important;
    width: 220px;
    padding: 20px 12px;
    box-shadow: 1px 0 5px #0001;
    flex-shrink: 0;
}

.layout > .sidebar a {
    display: block;
    padding: 11px 14px;
    margin: 3px 0;
    border-radius: 8px;
    text-decoration: none;
    color: #FFFFFF !important;
}

.layout > .sidebar a:hover,
.layout > .sidebar a.active {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    border-left: none !important;
    font-weight: bold;
}

.layout > .sidebar a[href="logout.php"],
.layout a[href="logout.php"] {
    background-color: #DC2626 !important;
    color: #FFFFFF !important;
    margin-top: 15px;
    text-align: center;
    font-weight: bold;
    border-radius: 8px;
}

.layout > .content {
    font-size: 16px;
    font-family: inherit;
}

/* PAGE HEADER */
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

.muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

/* BUTTONS */
.register-btn,
.action-btn,
.modal-btn {
    cursor: pointer;
    padding: 11px 13px;
    border: none;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    transition: background-color .2s ease;
}

.register-btn {
    background-color: #1D4ED8;
    color: #FFFFFF;
    white-space: nowrap;
}

.register-btn:hover {
    background-color: #1E40AF;
}

.action-btn {
    display: inline-block;
    color: #FFFFFF;
    white-space: nowrap;
}

.action-btn:disabled,
.modal-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.edit-btn {
    background-color: #2563EB;
}

.edit-btn:hover {
    background-color: #1D4ED8;
}

.delete-btn {
    background-color: #DC2626;
}

.delete-btn:hover {
    background-color: #991B1B;
}

.activate-btn {
    background-color: #16A34A;
}

.activate-btn:hover {
    background-color: #15803D;
}

.modal-btn-cancel {
    background-color: #E2E8F0;
    color: #334155;
}

.modal-btn-cancel:hover {
    background-color: #CBD5E1;
}

.modal-btn-submit {
    background-color: #1D4ED8;
    color: #FFFFFF;
}

.modal-btn-delete {
    background-color: #DC2626;
    color: #FFFFFF;
}

/* SEARCH BAR */
.staff-toolbar {
    display: grid;
    grid-template-columns: 1fr minmax(240px, 430px) 1fr;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.branch-search {
    grid-column: 2;
    position: relative;
    width: 100%;
}

.branch-search input {
    box-sizing: border-box;
    width: 100%;
    padding: 10px 15px 10px 42px;
    border: 1px solid #618dc4;
    border-radius: 6px;
    font-family: inherit;
    font-size: 16px;
    outline: none;
    background-color: #FFFFFF;
    color: #20253B;
}

.branch-search input:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px #1D4ED81F;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748B;
    pointer-events: none;
}

.search-result {
    grid-column: 3;
    text-align: right;
    color: #64748B;
    font-size: 16px;
}

/* STAFF TABLE */
.users-panel {
    background-color: #FFFFFF;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px #0000000D;
    margin-bottom: 16px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.users-panel table,
.deactivated-table {
    width: 100%;
    border-collapse: collapse;
    font-family: inherit;
    font-size: 16px;
}

.users-panel th,
.users-panel td,
.deactivated-table th,
.deactivated-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
    font-size: 16px;
}

.users-panel th,
.deactivated-table th {
    background-color: #F1F5F9;
    color: #20253B;
    font-weight: 600;
    text-align: left;
}

.users-panel tr:last-child td,
.deactivated-table tr:last-child td {
    border-bottom: none;
}

.users-panel th:last-child,
.users-panel td:last-child,
.deactivated-table th:last-child,
.deactivated-table td:last-child {
    text-align: center;
}

.empty-users {
    text-align: center !important;
    color: #64748B;
    padding: 25px !important;
    font-size: 16px;
}

.action-buttons {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

/* NOTIFICATIONS */
.alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    background-color: #DCFCE7;
    color: #166534;
    border: 1px solid #BBF7D0;
    font-size: 16px;
    opacity: 1;
    transition: opacity .5s ease;
}

.alert.error {
    background-color: #FEE2E2;
    color: #991B1B;
    border-color: #FECACA;
}

.alert.fade-out {
    opacity: 0;
}

/* MODALS */
.staff-modal {
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

.staff-modal.show {
    display: flex !important;
}

.staff-modal-content {
    background-color: #FFFFFF;
    width: 100%;
    max-width: 520px;
    max-height: 95vh;
    overflow-y: auto;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
    animation: staffModalIn .18s ease-out;
}

.staff-modal-content.wide {
    max-width: 950px;
}

.staff-modal-content.small {
    max-width: 460px;
}

@keyframes staffModalIn {
    from {
        opacity: 0;
        transform: translateY(-12px) scale(.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.staff-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 18px 22px;
    background-color: #122CBB;
    color: #FFFFFF;
    border-radius: 14px 14px 0 0;
}

.staff-modal-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
}

.staff-modal-close {
    border: none;
    background: transparent;
    color: #FFFFFF;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    padding: 0 3px;
}

.staff-modal-body {
    padding: 24px;
}

.staff-modal-body > p {
    margin: 0 0 18px;
    color: #64748B;
    line-height: 1.5;
    font-size: 16px;
}

/* MODAL FORMS */
.staff-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 20px;
}

.staff-field {
    min-width: 0;
}

.staff-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #20253B;
    font-size: 16px;
}

.staff-field input,
.staff-field select {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background-color: #FFFFFF;
    color: #20253B;
    font-family: inherit;
    font-size: 16px;
}

.staff-field input:focus,
.staff-field select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px #1D4ED81F;
}

.staff-modal-footer {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #E2E8F0;
}

/* DEACTIVATION CONFIRMATION */
.delete-details {
    padding: 14px;
    margin: 12px 0 18px;
    background-color: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    line-height: 1.8;
    font-size: 16px;
}

.delete-warning {
    padding: 14px;
    margin: 12px 0 18px;
    background-color: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 8px;
    color: #991B1B;
    line-height: 1.5;
    font-size: 16px;
}

/* RESPONSIVE LAYOUT */
@media (max-width: 800px) {
    .staff-toolbar {
        grid-template-columns: 1fr;
    }

    .branch-search,
    .search-result {
        grid-column: 1;
        text-align: left;
    }
}

@media (max-width: 700px) {
    .users-header {
        align-items: flex-start;
    }

    .users-panel {
        padding: 12px;
    }

    .users-panel table,
    .deactivated-table {
        min-width: 620px;
    }

    .staff-modal {
        padding: 14px;
    }

    .staff-modal-body {
        padding: 18px;
    }
}

@media (max-width: 600px) {
    .users-header {
        flex-direction: column;
    }

    .staff-form {
        grid-template-columns: 1fr;
    }

    .staff-modal-footer {
        grid-column: 1;
    }
}
</style>
</style>

<div class="users-header">
    <div>
        <h1>Registered Staff</h1>
        <p class="muted">Manage staff accounts and their assigned branches.</p>
    </div>
    <button type="button" class="register-btn" onclick="openModal('registerModal')">
        + Register Staff
    </button>
</div>

<div id="ajaxNotification" class="alert" role="status"
     aria-live="polite" style="display:none"></div>

<?php
if (isset($_GET['registered'])) {
    echo '<div class="alert page-alert">Staff account registered successfully.</div>';
} elseif (isset($_GET['updated'])) {
    echo '<div class="alert page-alert">Staff information updated successfully.</div>';
} elseif (isset($_GET['deleted'])) {
    echo '<div class="alert page-alert">Staff account deactivated successfully.</div>';
} elseif (isset($_GET['activated'])) {
    echo '<div class="alert page-alert">Staff account activated successfully.</div>';
} elseif (isset($_GET['error'])) {
    echo '<div class="alert error page-alert">The requested action could not be completed.</div>';
}
?>

<div class="staff-toolbar">
    <div></div>
    <div class="branch-search">
        <input type="search" id="branchSearch"
               placeholder="Search staff or branch..."
               aria-label="Search staff or branch">
    </div>
    <div class="search-result" id="searchResult"></div>
</div>

<section class="users-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Branch Name</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody id="activeStaffBody">
            <?php if (!$staff): ?>
                <tr class="empty-row"><td colspan="3" class="empty-users">No active staff accounts found.</td></tr>
            <?php else: foreach ($staff as $row): ?>
                <tr data-staff-id="<?= (int)$row['id'] ?>"
                    data-name="<?= h($row['staff_name']) ?>"
                    data-branch="<?= h($row['branch_name'] ?? '') ?>">
                    <td class="staff-name"><?= h($row['staff_name']) ?></td>
                    <td class="staff-branch"><?= h($row['branch_name'] ?? 'No branch assigned') ?></td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="action-btn edit-btn"
                                onclick="openEditModal(this.closest('tr'), true)">Edit</button>
                            <button type="button" class="action-btn delete-btn"
                                onclick="openDeleteModal(this.closest('tr'))">Deactivate</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div style="display:flex;justify-content:flex-end;margin:14px 0 24px">
    <button type="button" class="register-btn" style="background:#475569"
            onclick="openModal('deactivatedModal')">
        Show Deactivated Staff
    </button>
</div>

<!-- DEACTIVATED STAFF POPUP -->
<div class="staff-modal" id="deactivatedModal" role="dialog"
     aria-modal="true" aria-labelledby="deactivatedTitle"
     onclick="closeModalOutside(event,'deactivatedModal')">
    <div class="staff-modal-content wide">
        <div class="staff-modal-header">
            <h2 id="deactivatedTitle">Deactivated Staff</h2>
            <button type="button" class="staff-modal-close"
                    onclick="closeModal('deactivatedModal')" aria-label="Close">&times;</button>
        </div>
        <div class="staff-modal-body">
            <p>Edit a deactivated account or activate it to restore login access.</p>
            <div class="table-wrap">
                <table class="deactivated-table">
                    <thead>
                        <tr><th>Staff Name</th><th>Branch Name</th><th>ACTIONS</th></tr>
                    </thead>
                    <tbody id="deactivatedStaffBody">
                    <?php if (!$deactivatedStaff): ?>
                        <tr class="empty-row"><td colspan="3" class="empty-users">No deactivated staff accounts found.</td></tr>
                    <?php else: foreach ($deactivatedStaff as $row): ?>
                        <tr data-deactivated-staff-id="<?= (int)$row['id'] ?>"
                            data-name="<?= h($row['staff_name']) ?>"
                            data-branch="<?= h($row['branch_name'] ?? '') ?>">
                            <td class="staff-name"><?= h($row['staff_name']) ?></td>
                            <td class="staff-branch"><?= h($row['branch_name'] ?? 'No branch assigned') ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="action-btn edit-btn"
                                        onclick="openEditModal(this.closest('tr'), false)">Edit</button>
                                    <button type="button" class="action-btn activate-btn"
                                        onclick="activateStaff(this, <?= (int)$row['id'] ?>)">Activate</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="staff-modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel"
                        onclick="closeModal('deactivatedModal')">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- REGISTER MODAL -->
<div class="staff-modal" id="registerModal" role="dialog" aria-modal="true"
     aria-labelledby="registerTitle" onclick="closeModalOutside(event,'registerModal')">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2 id="registerTitle">Register Staff</h2>
            <button type="button" class="staff-modal-close"
                    onclick="closeModal('registerModal')" aria-label="Close">&times;</button>
        </div>
        <div class="staff-modal-body">
            <p>Create a staff account and assign a branch.</p>
            <form method="POST" action="register.php" id="registerStaffForm" class="staff-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <div class="staff-field">
                    <label for="reg_staff_name">Staff Name</label>
                    <input id="reg_staff_name" name="staff_name" maxlength="100" required>
                </div>
                <div class="staff-field">
                    <label for="reg_branch_name">Branch</label>
                    <select id="reg_branch_name" name="branch_name" required>
                        <option value="">Select a branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= h($branch['branch_name']) ?>"><?= h($branch['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="staff-field">
                    <label for="reg_password">Password</label>
                    <input type="password" id="reg_password" name="password"
                           minlength="8" autocomplete="new-password" required>
                </div>
                <div class="staff-field">
                    <label for="reg_confirm_password">Confirm Password</label>
                    <input type="password" id="reg_confirm_password" name="confirm_password"
                           minlength="8" autocomplete="new-password" required>
                </div>
                <div class="staff-modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel"
                            onclick="closeModal('registerModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-submit">Register Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="staff-modal" id="editModal" role="dialog" aria-modal="true"
     aria-labelledby="editTitle" onclick="closeModalOutside(event,'editModal')">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2 id="editTitle">Edit Staff</h2>
            <button type="button" class="staff-modal-close"
                    onclick="closeModal('editModal')" aria-label="Close">&times;</button>
        </div>
        <div class="staff-modal-body">
            <p>Update staff information. Leave the password fields blank to keep the current password.</p>
            <form method="POST" action="edit_staff.php" id="editStaffForm" class="staff-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <input type="hidden" name="id" id="edit_staff_id">
                <input type="hidden" id="edit_return_active" value="1">
                <div class="staff-field">
                    <label for="edit_staff_name">Staff Name</label>
                    <input id="edit_staff_name" name="staff_name" maxlength="100" required>
                </div>
                <div class="staff-field">
                    <label for="edit_branch_name">Branch</label>
                    <select id="edit_branch_name" name="branch_name" required>
                        <option value="">Select a branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= h($branch['branch_name']) ?>"><?= h($branch['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="staff-field">
                    <label for="edit_password">New Password (optional)</label>
                    <input type="password" id="edit_password" name="password"
                           minlength="8" autocomplete="new-password">
                </div>
                <div class="staff-field">
                    <label for="edit_confirm_password">Confirm New Password</label>
                    <input type="password" id="edit_confirm_password" name="confirm_password"
                           minlength="8" autocomplete="new-password">
                </div>
                <div class="staff-modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel"
                            onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DEACTIVATION CONFIRMATION -->
<div class="staff-modal" id="deleteModal" role="dialog" aria-modal="true"
     aria-labelledby="deleteTitle" onclick="closeModalOutside(event,'deleteModal')">
    <div class="staff-modal-content small">
        <div class="staff-modal-header">
            <h2 id="deleteTitle">Deactivate Staff</h2>
            <button type="button" class="staff-modal-close"
                    onclick="closeModal('deleteModal')" aria-label="Close">&times;</button>
        </div>
        <div class="staff-modal-body">
            <p>Are you sure you want to deactivate this account?</p>
            <div class="delete-details">
                <strong>Staff Name:</strong> <span id="delete_staff_name"></span><br>
                <strong>Branch:</strong> <span id="delete_branch_name"></span>
            </div>
            <p class="delete-warning">This account will no longer be able to log in. It will not be permanently deleted.</p>
            <form id="deleteStaffForm">
                <input type="hidden" name="id" id="delete_staff_id">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <div class="staff-modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel"
                            onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" id="confirmDeactivateBtn"
                            class="modal-btn modal-btn-delete">Yes, Deactivate Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = <?= json_encode($csrf_token) ?>;

let notificationTimer = null;
let previousEditModal = '';

// MODALS
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('show');

    if (!document.querySelector('.staff-modal.show')) {
        document.body.style.overflow = '';
    }
}

function closeModalOutside(event, id) {
    if (event.target.id === id) closeModal(id);
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.staff-modal.show').forEach(modal => closeModal(modal.id));
    }
});

// IN-PAGE NOTIFICATION
function showNotification(message, isError = false) {
    const box = document.getElementById('ajaxNotification');

    box.textContent = message;
    box.style.display = 'block';
    box.classList.toggle('error', isError);
    box.classList.remove('fade-out');

    if (notificationTimer) clearTimeout(notificationTimer);

    notificationTimer = setTimeout(() => {
        box.classList.add('fade-out');
        setTimeout(() => {
            box.style.display = 'none';
            box.classList.remove('fade-out');
        }, 250);
    }, 3500);
}

// ESCAPE TEXT BEFORE INSERTING USER DATA INTO HTML
function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#039;'
    })[char]);
}

function getId(row) {
    return row.dataset.staffId || row.dataset.deactivatedStaffId;
}

function makeActiveRow(id, name, branch) {
    const tr = document.createElement('tr');
    tr.dataset.staffId = id;
    tr.dataset.name = name;
    tr.dataset.branch = branch;
    tr.innerHTML = `
        <td class="staff-name"></td>
        <td class="staff-branch"></td>
        <td><div class="action-buttons">
            <button type="button" class="action-btn edit-btn">Edit</button>
            <button type="button" class="action-btn delete-btn">Deactivate</button>
        </div></td>`;
    tr.querySelector('.staff-name').textContent = name;
    tr.querySelector('.staff-branch').textContent = branch || 'No branch assigned';
    tr.querySelector('.edit-btn').addEventListener('click', () => openEditModal(tr, true));
    tr.querySelector('.delete-btn').addEventListener('click', () => openDeleteModal(tr));
    return tr;
}

function makeInactiveRow(id, name, branch) {
    const tr = document.createElement('tr');
    tr.dataset.deactivatedStaffId = id;
    tr.dataset.name = name;
    tr.dataset.branch = branch;
    tr.innerHTML = `
        <td class="staff-name"></td>
        <td class="staff-branch"></td>
        <td><div class="action-buttons">
            <button type="button" class="action-btn edit-btn">Edit</button>
            <button type="button" class="action-btn activate-btn">Activate</button>
        </div></td>`;
    tr.querySelector('.staff-name').textContent = name;
    tr.querySelector('.staff-branch').textContent = branch || 'No branch assigned';
    tr.querySelector('.edit-btn').addEventListener('click', () => openEditModal(tr, false));
    tr.querySelector('.activate-btn').addEventListener('click', function() {
        activateStaff(this, id);
    });
    return tr;
}

function refreshEmptyTables() {
    const active = document.getElementById('activeStaffBody');
    const inactive = document.getElementById('deactivatedStaffBody');

    if (!active.querySelector('tr[data-staff-id]')) {
        active.innerHTML = '<tr class="empty-row"><td colspan="3" class="empty-users">No active staff accounts found.</td></tr>';
    }
    if (!inactive.querySelector('tr[data-deactivated-staff-id]')) {
        inactive.innerHTML = '<tr class="empty-row"><td colspan="3" class="empty-users">No deactivated staff accounts found.</td></tr>';
    }

    filterStaff();
}

// Move a row between tables only after server success.
function updateStaffRow(id, name, branch, isActive) {
    const active = document.getElementById('activeStaffBody');
    const inactive = document.getElementById('deactivatedStaffBody');

    active.querySelectorAll('tr[data-staff-id]').forEach(row => {
        if (String(row.dataset.staffId) === String(id)) row.remove();
    });

    inactive.querySelectorAll('tr[data-deactivated-staff-id]').forEach(row => {
        if (String(row.dataset.deactivatedStaffId) === String(id)) row.remove();
    });

    active.querySelector('.empty-row')?.remove();
    inactive.querySelector('.empty-row')?.remove();

    const target = isActive ? active : inactive;
    target.appendChild(isActive
        ? makeActiveRow(id, name, branch)
        : makeInactiveRow(id, name, branch));

    refreshEmptyTables();
}

// SEARCH STAFF OR BRANCH
function filterStaff() {
    const query = document.getElementById('branchSearch').value.trim().toLowerCase();
    const rows = document.querySelectorAll('#activeStaffBody tr[data-staff-id]');
    let visible = 0;

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const branch = row.dataset.branch || '';
        const matches = (name + ' ' + branch).toLowerCase().includes(query);
        row.style.display = matches ? '' : 'none';
        if (matches) visible++;
    });

    document.getElementById('searchResult').textContent =
        query ? `${visible} matching staff` : `${rows.length} active staff`;
}
document.getElementById('branchSearch').addEventListener('input', filterStaff);

// OPEN EDIT MODAL FOR ACTIVE OR DEACTIVATED ROW
function openEditModal(row, isActive) {
    document.getElementById('edit_staff_id').value = getId(row);
    document.getElementById('edit_staff_name').value = row.dataset.name || '';
    document.getElementById('edit_branch_name').value = row.dataset.branch || '';
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_confirm_password').value = '';
    document.getElementById('edit_return_active').value = isActive ? '1' : '0';

    previousEditModal = isActive ? '' : 'deactivatedModal';
    closeModal('deactivatedModal');
    closeModal('registerModal');
    closeModal('deleteModal');
    openModal('editModal');
}

// OPEN DEACTIVATION MODAL
function openDeleteModal(row) {
    document.getElementById('delete_staff_id').value = getId(row);
    document.getElementById('delete_staff_name').textContent = row.dataset.name || '';
    document.getElementById('delete_branch_name').textContent =
        row.dataset.branch || 'No branch assigned';
    openModal('deleteModal');
}

// AJAX HELPER
async function postAction(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    });

    let result;
    try {
        result = await response.json();
    } catch {
        throw new Error('The server returned an invalid response. Check PHP errors and file paths.');
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'The operation failed.');
    }

    return result;
}

// ACTIVATE ACCOUNT
async function activateStaff(button, id) {
    if (button.disabled) return;

    const row = button.closest('tr');
    const name = row.dataset.name || '';
    const branch = row.dataset.branch || '';

    button.disabled = true;
    button.textContent = 'Activating...';

    const data = new FormData();
    data.append('id', id);
    data.append('csrf_token', csrfToken);

    try {
        const result = await postAction('activate_staff.php', data);

        updateStaffRow(id, name, branch, true);
        closeModal('deactivatedModal');
        showNotification(result.message || 'Staff account activated successfully.');
    } catch (error) {
        button.disabled = false;
        button.textContent = 'Activate';
        showNotification(error.message, true);
    }
}

// EDIT ACCOUNT WITHOUT PAGE RELOAD
document.getElementById('editStaffForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const form = this;
    const button = form.querySelector('[type="submit"]');
    const id = document.getElementById('edit_staff_id').value;
    const name = document.getElementById('edit_staff_name').value.trim();
    const branch = document.getElementById('edit_branch_name').value;
    const isActive = document.getElementById('edit_return_active').value === '1';
    const password = document.getElementById('edit_password').value;
    const confirmPassword = document.getElementById('edit_confirm_password').value;

    if (password !== confirmPassword) {
        showNotification('The passwords do not match.', true);
        return;
    }

    button.disabled = true;
    button.textContent = 'Saving...';

    try {
        const result = await postAction('edit_staff.php', new FormData(form));
        updateStaffRow(id, name, branch, isActive);
        closeModal('editModal');

        if (!isActive) openModal('deactivatedModal');

        showNotification(result.message || 'Staff information updated successfully.');
    } catch (error) {
        showNotification(error.message, true);
    } finally {
        button.disabled = false;
        button.textContent = 'Save Changes';
    }
});

// DEACTIVATE ACCOUNT WITHOUT PAGE RELOAD
document.getElementById('deleteStaffForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const form = this;
    const button = document.getElementById('confirmDeactivateBtn');
    const id = document.getElementById('delete_staff_id').value;
    const row = [...document.querySelectorAll('#activeStaffBody tr[data-staff-id]')]
        .find(item => String(item.dataset.staffId) === String(id));

    if (!row) {
        showNotification('Staff row not found. Refresh the page and try again.', true);
        return;
    }

    const name = row.dataset.name || '';
    const branch = row.dataset.branch || '';

    button.disabled = true;
    button.textContent = 'Deactivating...';

    try {
        const result = await postAction('delete_staff.php', new FormData(form));
        updateStaffRow(id, name, branch, false);
        closeModal('deleteModal');
        showNotification(result.message || 'Staff account deactivated successfully.');
    } catch (error) {
        showNotification(error.message, true);
    } finally {
        button.disabled = false;
        button.textContent = 'Yes, Deactivate Account';
    }
});

// CLIENT-SIDE PASSWORD CHECKS
document.getElementById('registerStaffForm').addEventListener('submit', function(event) {
    const password = document.getElementById('reg_password').value;
    const confirmPassword = document.getElementById('reg_confirm_password').value;

    if (password !== confirmPassword) {
        event.preventDefault();
        showNotification('The registration passwords do not match.', true);
    }
});

// HIDE SERVER REDIRECT NOTIFICATIONS
document.querySelectorAll('.page-alert').forEach(box => {
    setTimeout(() => {
        box.classList.add('fade-out');
        setTimeout(() => box.remove(), 250);
    }, 3500);
});

filterStaff();
</script>

<?php require 'includes/footer.php'; ?>

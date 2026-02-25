<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'student';
    $phone    = trim($_POST['phone'] ?? '');
    $sid      = trim($_POST['student_id'] ?? '');

    if ($action === 'add') {
        $password = trim($_POST['password'] ?? '');
        if (!$fullname || !$username || !$email || !$password) {
            $error = 'All required fields must be filled.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username,password,fullname,email,role,phone,student_id) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("sssssss", $username, $hashed, $fullname, $email, $role, $phone, $sid);
            if ($stmt->execute()) {
                logAction($conn, 'User Created', "Created user: $username ($role)");
                $msg = "User '$username' created successfully!";
            } else {
                $error = 'Error: Username or email already exists.';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['user_id'];
        $password = trim($_POST['password'] ?? '');
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET fullname=?,username=?,email=?,role=?,phone=?,student_id=?,password=? WHERE id=?");
            $stmt->bind_param("sssssssi", $fullname, $username, $email, $role, $phone, $sid, $hashed, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET fullname=?,username=?,email=?,role=?,phone=?,student_id=? WHERE id=?");
            $stmt->bind_param("ssssssi", $fullname, $username, $email, $role, $phone, $sid, $id);
        }
        $stmt->execute();
        logAction($conn, 'User Updated', "Updated user #$id: $username");
        $msg = 'User updated successfully!';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['user_id'];
        if ($id == $_SESSION['user_id']) { $error = 'Cannot delete your own account.'; }
        else {
            $conn->query("DELETE FROM users WHERE id=$id");
            logAction($conn, 'User Deleted', "Deleted user #$id");
            $msg = 'User deleted.';
        }
    }
}

$search = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$role_f = $conn->real_escape_string($_GET['role'] ?? '');
$where  = "WHERE 1=1";
if ($search) $where .= " AND (fullname LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%')";
if ($role_f) $where .= " AND role='$role_f'";
$users  = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | ANU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content flex-grow-1">
        <div class="topbar d-flex justify-content-between align-items-center">
            <h1><i class="bi bi-people me-2"></i>User Management</h1>
            <button class="btn btn-anu btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-person-plus me-1"></i> Add User
            </button>
        </div>
        <div class="p-4 fade-in-up">
            <?php if ($msg):  ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control" placeholder="Search name, username, email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="super_admin" <?= $role_f==='super_admin'?'selected':'' ?>>Super Admin</option>
                        <option value="admin"       <?= $role_f==='admin'      ?'selected':'' ?>>Admin</option>
                        <option value="student"     <?= $role_f==='student'    ?'selected':'' ?>>Student</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-anu"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="users.php" class="btn btn-outline-secondary ms-1">Clear</a>
                </div>
            </form>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Student ID</th><th>Created</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php $i=1; while ($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($u['fullname']) ?></td>
                                <td>@<?= htmlspecialchars($u['username']) ?></td>
                                <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge <?= $u['role']==='super_admin'?'bg-danger':($u['role']==='admin'?'bg-warning text-dark':'bg-success') ?>">
                                        <?= str_replace('_',' ', ucfirst($u['role'])) ?>
                                    </span>
                                </td>
                                <td class="small"><?= htmlspecialchars($u['student_id'] ?: '—') ?></td>
                                <td class="small text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary py-0 px-2 me-1" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)"><i class="bi bi-pencil"></i></button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm btn-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--anu-gradient);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full Name <span class="text-danger">*</span></label><input type="text" name="fullname" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Username <span class="text-danger">*</span></label><input type="text" name="username" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required></div>
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="student">Student</option>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Student ID</label><input type="text" name="student_id" class="form-control" placeholder="e.g. ANU/2024/001"></div>
                        <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+254..."></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-anu"><i class="bi bi-save me-1"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--anu-gradient);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="fullname" id="editFullname" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Username</label><input type="text" name="username" id="editUsername" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="editEmail" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">New Password <span class="text-muted small">(leave blank to keep)</span></label><input type="password" name="password" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Role</label><select name="role" id="editRole" class="form-select"><option value="student">Student</option><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select></div>
                        <div class="col-md-4"><label class="form-label">Student ID</label><input type="text" name="student_id" id="editStudentId" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-anu"><i class="bi bi-save me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editUser(u) {
    document.getElementById('editId').value        = u.id;
    document.getElementById('editFullname').value  = u.fullname;
    document.getElementById('editUsername').value  = u.username;
    document.getElementById('editEmail').value     = u.email;
    document.getElementById('editRole').value      = u.role;
    document.getElementById('editStudentId').value = u.student_id || '';
    document.getElementById('editPhone').value     = u.phone || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>

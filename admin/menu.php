<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireAdmin();

$msg = '';
$error = '';

// Add or Edit menu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $type   = $_POST['type'] ?? '';
    $date   = $_POST['date'] ?? '';
    $desc   = trim($_POST['description'] ?? '');
    $price  = (float)($_POST['price'] ?? 0);
    $avail  = isset($_POST['available']) ? 1 : 0;

    if ($action === 'add') {
        if (!$name || !$type || !$date) {
            $error = 'Please fill all required fields.';
        } else {
            $uid = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO menus (name,type,date,description,price,available,created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssdii", $name, $type, $date, $desc, $price, $avail, $uid);
            $stmt->execute();
            logAction($conn, 'Menu Added', "Added menu: $name ($type) on $date");
            $msg = 'Menu item added successfully!';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['menu_id'];
        $stmt = $conn->prepare("UPDATE menus SET name=?,type=?,date=?,description=?,price=?,available=? WHERE id=?");
        $stmt->bind_param("ssssdii", $name, $type, $date, $desc, $price, $avail, $id);
        $stmt->execute();
        logAction($conn, 'Menu Updated', "Updated menu #$id: $name");
        $msg = 'Menu updated successfully!';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['menu_id'];
        $conn->query("DELETE FROM menus WHERE id=$id");
        logAction($conn, 'Menu Deleted', "Deleted menu #$id");
        $msg = 'Menu item deleted.';
    }
}

// Fetch menus
$type_f = $_GET['type'] ?? '';
$date_f = $_GET['date'] ?? '';
$where  = "WHERE 1=1";
if ($type_f) $where .= " AND type='" . $conn->real_escape_string($type_f) . "'";
if ($date_f) $where .= " AND date='" . $conn->real_escape_string($date_f) . "'";
$menus = $conn->query("SELECT * FROM menus $where ORDER BY date DESC, type ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management | ANU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php $page_title = '<i class="bi bi-journal-text me-2"></i>Menu Management'; include '../includes/topbar.php'; ?>

        <div class="p-4 fade-in-up">
            <?php if ($msg):  ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold text-danger"><i class="bi bi-journal-text me-2"></i>Menu Items</h5>
                <button class="btn btn-anu" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Menu Item
                </button>
            </div>

            <!-- Filter -->
            <form method="GET" class="row g-2 mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Meal Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="Breakfast" <?= $type_f==='Breakfast'?'selected':'' ?>>Breakfast</option>
                        <option value="Lunch"     <?= $type_f==='Lunch'    ?'selected':'' ?>>Lunch</option>
                        <option value="Dinner"    <?= $type_f==='Dinner'   ?'selected':'' ?>>Dinner</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_f) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-anu"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="menu.php" class="btn btn-outline-secondary ms-1">Clear</a>
                </div>
            </form>

            <!-- Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th><th>Meal Name</th><th>Type</th><th>Date</th>
                                <th>Price (KES)</th><th>Description</th><th>Available</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; while ($m = $menus->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($m['name']) ?></td>
                                <td><span class="menu-badge <?= strtolower($m['type']) ?>"><?= $m['type'] ?></span></td>
                                <td><?= date('d M Y', strtotime($m['date'])) ?></td>
                                <td><?= number_format($m['price'], 2) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($m['description'] ?: '—') ?></td>
                                <td>
                                    <span class="badge <?= $m['available'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $m['available'] ? 'Yes' : 'No' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary py-0 px-2 me-1" onclick="editMenu(<?= htmlspecialchars(json_encode($m)) ?>)" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this menu item?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="menu_id" value="<?= $m['id'] ?>">
                                        <button class="btn btn-sm btn-danger py-0 px-2" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#ff0000,#fac823);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Meal Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Ugali & Beef Stew" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="">Select...</option>
                                <option value="Breakfast">Breakfast</option>
                                <option value="Lunch">Lunch</option>
                                <option value="Dinner">Dinner</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (KES)</label>
                        <input type="number" name="price" class="form-control" step="0.01" value="0" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Short description..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available" id="avail1" checked>
                        <label class="form-check-label" for="avail1">Available for booking</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-anu"><i class="bi bi-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#ff0000,#fac823);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="menu_id" id="editId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Meal Name</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" id="editType" class="form-select" required>
                                <option value="Breakfast">Breakfast</option>
                                <option value="Lunch">Lunch</option>
                                <option value="Dinner">Dinner</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" id="editDate" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (KES)</label>
                        <input type="number" name="price" id="editPrice" class="form-control" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDesc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available" id="editAvail">
                        <label class="form-check-label" for="editAvail">Available for booking</label>
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

<?php include '../includes/scripts.php'; ?>
<script>
function editMenu(m) {
    document.getElementById('editId').value    = m.id;
    document.getElementById('editName').value  = m.name;
    document.getElementById('editType').value  = m.type;
    document.getElementById('editDate').value  = m.date;
    document.getElementById('editPrice').value = m.price;
    document.getElementById('editDesc').value  = m.description || '';
    document.getElementById('editAvail').checked = m.available == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>

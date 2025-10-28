<?php
session_start();
ob_start();
include '../includes/db.php';
include 'partials/menu.php';

$form_errors = [];
$form_data = [];
$success_msg = '';

if (!isset($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = [];
}
function add_flash_message($type, $msg) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'msg' => $msg];
}

// Define departments array
$departments = [
    'Information Technology',
    'Information System', 
    'Software Engineering',
    'Computer Science',
    'Accounting',
    'Management',
    'Economics'
];

// =================== ADD ADMIN ===================
if (isset($_POST['add_admin'])) {
    $name = trim($_POST['name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']);
    $department_name = trim($_POST['department_name']);
    $password_raw = $_POST['password'];

    // Save form data for refill
    $form_data = compact('name', 'last_name', 'username', 'email', 'phone', 'role', 'department_name');

    // VALIDATION
    if (empty($name) || !preg_match("/^[a-zA-Z]+$/", $name)) {
        $form_errors[] = "First name is required and must contain letters only.";
    }
    if (empty($last_name) || !preg_match("/^[a-zA-Z]+$/", $last_name)) {
        $form_errors[] = "Last name is required and must contain letters only.";
    }
    if (empty($username)) {
        $form_errors[] = "Username is required.";
    } else {
        // Check username uniqueness
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            $form_errors[] = "Username '$username' is already taken.";
        }
    }
    if (empty($email)) {
        $form_errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_errors[] = "Invalid email format.";
    } else {
        // Check email uniqueness
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            $form_errors[] = "Email '$email' is already registered.";
        }
    }
    if (empty($phone) || !preg_match("/^\d{10}$/", $phone)) {
        $form_errors[] = "Phone number is required and must be exactly 10 digits.";
    }
    $valid_roles =  ['system_admin','registrar_admin', 'department_admin', 'cafeteria_admin' ,'library_admin', 'dormitory_admin','personal_protector'];
    if (empty($role) || !in_array($role, $valid_roles)) {
        $form_errors[] = "Please select a valid role.";
    }
    
    // Department validation - required only for department_admin role
    if ($role === 'department_admin') {
        if (empty($department_name)) {
            $form_errors[] = "Department name is required for Department Admin role.";
        } elseif (!in_array($department_name, $departments)) {
            $form_errors[] = "Please select a valid department.";
        }
    } else {
        // For non-department admins, set department_name to NULL
        $department_name = null;
    }
    
    if (empty($password_raw) || strlen($password_raw) < 8) {
        $form_errors[] = "Password is required and must be at least 8 characters long.";
    }

    // If no errors, insert admin
    if (empty($form_errors)) {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin (name, last_name, username, email, phone, role, department_name, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $name, $last_name, $username, $email, $phone, $role, $department_name, $password);
        $stmt->execute();
        $stmt->close();

        add_flash_message('success', 'Admin added successfully.');
        header("Location: manage-admins.php");
        exit();
    }
}

// =================== UPDATE ADMIN ===================
if (isset($_POST['update_admin'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']);
    $department_name = trim($_POST['department_name']);
    $password_raw = $_POST['password'];

    // Save form data for refill if errors
    $form_data = compact('name', 'last_name', 'username', 'email', 'phone', 'role', 'department_name');

    // VALIDATION for update (similar but allow current record username/email)
    if (empty($name) || !preg_match("/^[a-zA-Z]+$/", $name)) {
        $form_errors[] = "First name is required and must contain letters only.";
    }
    if (empty($last_name) || !preg_match("/^[a-zA-Z]+$/", $last_name)) {
        $form_errors[] = "Last name is required and must contain letters only.";
    }
    if (empty($username)) {
        $form_errors[] = "Username is required.";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            $form_errors[] = "Username '$username' is already taken by another admin.";
        }
    }
    if (empty($email)) {
        $form_errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_errors[] = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            $form_errors[] = "Email '$email' is already registered by another admin.";
        }
    }
    if (empty($phone) || !preg_match("/^\d{10}$/", $phone)) {
        $form_errors[] = "Phone number is required and must be exactly 10 digits.";
    }
    $valid_roles =  ['system_admin', 'department_admin','registrar_admin', 'cafeteria_admin' ,'library_admin', 'dormitory_admin','personal_protector'];
    if (empty($role) || !in_array($role, $valid_roles)) {
        $form_errors[] = "Please select a valid role.";
    }
    
    // Department validation - required only for department_admin role
    if ($role === 'department_admin') {
        if (empty($department_name)) {
            $form_errors[] = "Department name is required for Department Admin role.";
        } elseif (!in_array($department_name, $departments)) {
            $form_errors[] = "Please select a valid department.";
        }
    } else {
        // For non-department admins, set department_name to NULL
        $department_name = null;
    }
    
    if (!empty($password_raw) && strlen($password_raw) < 8) {
        $form_errors[] = "Password must be at least 8 characters long if you want to update it.";
    }

    if (empty($form_errors)) {
        $password = !empty($password_raw) ? password_hash($password_raw, PASSWORD_DEFAULT) : null;
        if ($password) {
            $stmt = $conn->prepare("UPDATE admin SET name=?, last_name=?, username=?, email=?, phone=?, role=?, department_name=?, password=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $name, $last_name, $username, $email, $phone, $role, $department_name, $password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE admin SET name=?, last_name=?, username=?, email=?, phone=?, role=?, department_name=? WHERE id=?");
            $stmt->bind_param("sssssssi", $name, $last_name, $username, $email, $phone, $role, $department_name, $id);
        }
        $stmt->execute();
        $stmt->close();

        add_flash_message('success', 'Admin updated successfully.');
        header("Location: manage-admins.php");
        exit();
    } else {
        // For update form refill
        $edit_admin = ['id'=>$id] + $form_data;
    }
}

// =================== DELETE ADMIN ===================
if (isset($_GET['delete_admin'])) {
    $id = (int)$_GET['delete_admin'];
    $conn->query("DELETE FROM admin WHERE id=$id");
    add_flash_message('success', 'Admin deleted successfully.');
    header("Location: manage-admins.php");
    exit();
}

// =================== SEARCH FUNCTIONALITY ===================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search) {
    // Check if search term is a numeric ID
    if (is_numeric($search)) {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
        $stmt->bind_param("i", $search);
    } else {
        // If not numeric, return empty result
        $stmt = $conn->prepare("SELECT * FROM admin WHERE 1=0");
    }
    $stmt->execute();
    $admins = $stmt->get_result();
} else {
    $admins = $conn->query("SELECT * FROM admin ORDER BY id DESC");
}

// =================== EDIT ADMIN ===================
if (!isset($edit_admin) && isset($_GET['edit_admin'])) {
    $id = (int)$_GET['edit_admin'];
    $edit_admin = $conn->query("SELECT * FROM admin WHERE id=$id")->fetch_assoc();
}

// Show form if errors or editing
$showAddForm = !empty($form_errors) || isset($edit_admin);

?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Manage Admins</title>
    <style>
        body { font-family: Arial, sans-serif; background: white; margin: 0; padding: 0; }
        .ma-header { background: #008B8B; color: white; padding: 15px; text-align: center; position: relative; }
        .ma-logout-btn { position: absolute; right: 20px; top: 15px; background: #ff4444; color: white; padding: 8px 12px; text-decoration: none; border-radius: 3px; }
        .ma-logout-btn:hover { background: #cc0000; }
        .ma-container { max-width: 1200px; margin: 20px auto; background: white; padding: 20px; border-radius: 5px; }
        .ma-top-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .ma-add-btn { background: #008B8B; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .ma-add-btn:hover { background: #006B6B; }
        .ma-search-box { display: flex; align-items: center; gap: 5px; }
        .ma-search-box input { padding: 6px 10px; width: 200px; border: 1px solid #ddd; border-radius: 4px; }
        .ma-search-btn { background: #008B8B; color: white; padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; }
        .ma-search-btn:hover { background: #006B6B; }
        .ma-form-container { max-width: 400px; margin: 20px auto; padding: 20px; background: #0b105aff; border-radius: 8px; display: none; }
        .ma-form-container h2 { color: white; text-align: center; }
        .ma-form-container input, 
        .ma-form-container select { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; }
        .ma-form-container button { background: #008B8B; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        .ma-form-container button:hover { background: #006B6B; }
        .ma-table { width: 100%; border-collapse: collapse; }
        .ma-table th, 
        .ma-table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        .ma-table th { background: #555; color: white; }
        .ma-table tr:nth-child(even) { background: #f9f9f9; }
        .ma-action-icons img { width: 24px; cursor: pointer; margin: 0 5px; }
        .ma-messages { max-width: 400px; margin: 10px auto; padding: 10px; border-radius: 5px; }
        .ma-error-msg { background: #fdd; color: #a33; border: 1px solid #a33; }
        .ma-success-msg { background: #dfd; color: #383; border: 1px solid #383; }
        .department-field { display: none; }
    </style>
</head>
<body>
    <div class="ma-header">
        <h1>Manage Admins</h1>
    </div>

    <div class="ma-container">
        <div class="ma-top-actions" style="<?= $showAddForm ? 'display:none;' : '' ?>">
            <button class="ma-add-btn" onclick="showAddForm()">+ Add Admin</button>
            <form method="GET" class="ma-search-box">
                <input type="text" name="search" placeholder="Search by ID only" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="ma-search-btn">Search</button>
            </form>
        </div>

        <!-- Flash messages -->
        <?php if (!empty($_SESSION['flash_messages'])): ?>
            <div class="ma-messages">
                <?php foreach ($_SESSION['flash_messages'] as $msg): ?>
                    <div class="<?= $msg['type'] === 'success' ? 'ma-success-msg' : 'ma-error-msg' ?>">
                        <?= htmlspecialchars($msg['msg']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php $_SESSION['flash_messages'] = []; ?>
        <?php endif; ?>

        <div class="ma-form-container" id="addForm" style="<?= $showAddForm ? 'display:block;' : 'display:none;' ?>">
            <h2><?= isset($edit_admin) ? "Update Admin" : "Add Admin" ?></h2>

            <?php if (!empty($form_errors)): ?>
                <div class="ma-messages">
                    <div class="ma-error-msg">
                        <ul>
                            <?php foreach ($form_errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php if (isset($edit_admin)): ?>
                    <input type="hidden" name="id" value="<?= $edit_admin['id'] ?>">
                <?php endif; ?>
                <input type="text" name="name" placeholder="First Name" value="<?= htmlspecialchars($form_data['name'] ?? $edit_admin['name'] ?? '') ?>" required>
                <input type="text" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($form_data['last_name'] ?? $edit_admin['last_name'] ?? '') ?>" required>
                <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($form_data['username'] ?? $edit_admin['username'] ?? '') ?>" required>
                <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($form_data['email'] ?? $edit_admin['email'] ?? '') ?>" required>
                <input type="text" name="phone" placeholder="Phone" value="<?= htmlspecialchars($form_data['phone'] ?? $edit_admin['phone'] ?? '') ?>" required>

                <select name="role" id="roleSelect" required onchange="toggleDepartmentField()">
                    <option value="">Select Role</option>
                    <?php
                    $roles = ['system_admin', 'department_admin','registrar_admin', 'cafeteria_admin' ,'library_admin', 'dormitory_admin','personal_protector'];
                    $selectedRole = $form_data['role'] ?? $edit_admin['role'] ?? '';
                    foreach ($roles as $r) {
                        $sel = ($r === $selectedRole) ? 'selected' : '';
                        echo "<option value=\"$r\" $sel>$r</option>";
                    }
                    ?>
                </select>

                <!-- Department Field - Only shown for department_admin role -->
                <div id="departmentField" class="department-field">
                    <select name="department_name" id="departmentSelect">
                        <option value="">Select Department</option>
                        <?php
                        $selectedDept = $form_data['department_name'] ?? $edit_admin['department_name'] ?? '';
                        foreach ($departments as $dept) {
                            $sel = ($dept === $selectedDept) ? 'selected' : '';
                            echo "<option value=\"$dept\" $sel>$dept</option>";
                        }
                        ?>
                    </select>
                </div>

                <input type="password" name="password" placeholder="<?= isset($edit_admin) ? 'Leave blank to keep current password' : 'Password' ?>">
                <button type="submit" name="<?= isset($edit_admin) ? 'update_admin' : 'add_admin' ?>">
                    <?= isset($edit_admin) ? 'Update Admin' : 'Add Admin' ?>
                </button>
            </form>
        </div>

        <table class="ma-table" id="adminTable" style="<?= $showAddForm ? 'display:none;' : '' ?>">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Department</th>
                <th>Update</th>
                <th>Delete</th>
            </tr>
            <?php if ($admins->num_rows > 0): ?>
                <?php while ($a = $admins->fetch_assoc()): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars($a['name'].' '.$a['last_name']) ?></td>
                    <td><?= htmlspecialchars($a['username']) ?></td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td><?= htmlspecialchars($a['phone']) ?></td>
                    <td><?= htmlspecialchars($a['role']) ?></td>
                    <td><?= !empty($a['department_name']) ? htmlspecialchars($a['department_name']) : '-' ?></td>
                    <td class="ma-action-icons">
                        <a href="?edit_admin=<?= $a['id'] ?>"><img src="../images/update.png" title="Update"></a>
                    </td>
                    <td class="ma-action-icons">
                        <a href="?delete_admin=<?= $a['id'] ?>" onclick="return confirm('Delete this admin?')">
                            <img src="../images/delete.png" title="Delete">
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">No admins found<?= $search ? " with ID: $search" : '' ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <script>
        function showAddForm() {
            document.getElementById('addForm').style.display = 'block';
            document.getElementById('adminTable').style.display = 'none';
            document.querySelector('.ma-top-actions').style.display = 'none';
            // Reset department field visibility when showing form
            toggleDepartmentField();
        }

        function toggleDepartmentField() {
            const roleSelect = document.getElementById('roleSelect');
            const departmentField = document.getElementById('departmentField');
            const departmentSelect = document.getElementById('departmentSelect');
            
            if (roleSelect.value === 'department_admin') {
                departmentField.style.display = 'block';
                departmentSelect.required = true;
            } else {
                departmentField.style.display = 'none';
                departmentSelect.required = false;
                departmentSelect.value = '';
            }
        }

        // Initialize department field visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleDepartmentField();
        });
    </script>

    <?php include 'partials/footer.php'; ?>
</body>
</html>
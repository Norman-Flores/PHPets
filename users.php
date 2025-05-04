<?php
session_start();

$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] != 1) {
    header("Location: index.php");
    exit;
}

$loggedInUserID = $_SESSION['user_id'];
$error = '';
$editUserID = null;
$editUserData = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['add_user'])) {
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $usertype = intval($_POST['usertype'] ?? 3);
        $address = trim($_POST['address'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        if (!$fname || !$lname || !$email || !$password || !$address || !$contact) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM USER WHERE Email = ? OR ContactNumber = ?");
            $stmt->bind_param("ss", $email, $contact);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                $error = "Email or contact number already exists.";
            }
        }

        if (!$error) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO USER (FName, LName, Email, Password, UserType, Address, ContactNumber) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiss", $fname, $lname, $email, $passwordHash, $usertype, $address, $contact);
            $stmt->execute();
            $stmt->close();
            header("Location: users.php");
            exit();
        }
    } elseif (isset($_POST['edit_user'], $_POST['user_id'])) {
        $userID = intval($_POST['user_id']);
        if ($userID === $loggedInUserID) {
            $error = "You cannot edit your own account here.";
        } else {
            $fname = trim($_POST['fname'] ?? '');
            $lname = trim($_POST['lname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $usertype = intval($_POST['usertype'] ?? 3);
            $address = trim($_POST['address'] ?? '');
            $contact = trim($_POST['contact'] ?? '');

            if (!$fname || !$lname || !$email || !$address || !$contact) {
                $error = "Please fill in all required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM USER WHERE (Email = ? OR ContactNumber = ?) AND UserID != ?");
                $stmt->bind_param("ssi", $email, $contact, $userID);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();

                if ($count > 0) {
                    $error = "Email or contact number already used by another user.";
                }
            }

            if (!$error) {
                $stmt = $conn->prepare("UPDATE USER SET FName=?, LName=?, Email=?, UserType=?, Address=?, ContactNumber=? WHERE UserID=?");
                $stmt->bind_param("sssissi", $fname, $lname, $email, $usertype, $address, $contact, $userID);
                $stmt->execute();
                $stmt->close();
                header("Location: users.php");
                exit();
            }
        }
    } elseif (isset($_POST['action'], $_POST['user_id'])) {
        $action = $_POST['action'];
        $userID = intval($_POST['user_id']);

        if ($userID === $loggedInUserID) {
            $error = "You cannot modify your own account.";
        } else {
            if ($action === "lock") {
                $stmt = $conn->prepare("UPDATE USER SET IsLocked = TRUE WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $stmt->close();
            } elseif ($action === "unlock") {
                $stmt = $conn->prepare("UPDATE USER SET IsLocked = FALSE, FailedLoginAttempts = 0 WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $stmt->close();
            } elseif ($action === "delete") {
                $stmt = $conn->prepare("DELETE FROM USER WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $stmt->close();
            }
            header("Location: users.php");
            exit();
        }
    }
}

if (isset($_GET['edit'])) {
    $editUserID = intval($_GET['edit']);
    if ($editUserID === $loggedInUserID) {
        $error = "You cannot edit your own account here.";
        $editUserID = null;
    } else {
        $stmt = $conn->prepare("SELECT UserID, FName, LName, Email, UserType, Address, ContactNumber FROM USER WHERE UserID = ?");
        $stmt->bind_param("i", $editUserID);
        $stmt->execute();
        $result = $stmt->get_result();
        $editUserData = $result->fetch_assoc();
        $stmt->close();

        if (!$editUserData) {
            $error = "User not found.";
            $editUserID = null;
        }
    }
}

$usersPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $usersPerPage;

$countResult = $conn->query("SELECT COUNT(*) AS total FROM USER");
$totalUsers = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $usersPerPage);

$sql = "
    SELECT U.UserID, U.FName, U.LName, U.Email, U.Address, U.ContactNumber, U.IsLocked, UT.RoleName
    FROM USER U
    JOIN USERTYPETABLE UT ON U.UserType = UT.UserType
    ORDER BY U.UserID ASC
    LIMIT ? OFFSET ?
";

$isEditing = ($editUserID !== null && $editUserData !== null);

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $usersPerPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PHPets Admin - Manage Users</title>
  <link rel="stylesheet" href="css/admin.css" />
  <link rel="stylesheet" href="css/users.css" />
</head>
<body>

<header class="admin-header">
  <div class="admin-header-wrapper">
    <h1 class="admin-title">Manage Users</h1>
    <nav class="admin-nav">
      <?php
      $links = [
        "admin.php" => "👤 Admin",
        "products.php" => "📦 Products",
        "users.php" => "🧑‍🤝‍🧑 Users",
        "inventory.php" => "📋 Inventory",
        "admin_orders.php" => "🧾 Orders",
        "sales.php" => "📊 View Sales",
        "logout.php" => "🚪 Logout"
      ];
      foreach ($links as $href => $label) {
        $class = basename($_SERVER["PHP_SELF"]) === basename($href) ? "class=\"active\"" : "";
        echo "<a href=\"$href\" $class>$label</a>";
      }
      ?>
    </nav>
  </div>
</header>

<main class="admin-panel">

    <?php if (!empty($error)): ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <button id="showAddUserBtn">➕ Add New User</button>

    <div id="addUserFormContainer" aria-hidden="true">
      <h2>Add New User</h2>
      <form id="addUserForm" method="POST" action="users.php" novalidate>
        <label for="fname">First Name *</label>
        <input type="text" id="fname" name="fname" required>

        <label for="lname">Last Name *</label>
        <input type="text" id="lname" name="lname" required>

        <label for="email">Email *</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password *</label>
        <input type="password" id="password" name="password" required>

        <label for="usertype">User Role *</label>
        <select id="usertype" name="usertype" required>
          <option value="Owner">Admin</option>
          <option value="2" selected>Customer</option>
        </select>

        <label for="address">Address *</label>
        <input type="text" id="address" name="address" required>

        <label for="contact">Contact Number *</label>
        <input type="text" id="contact" name="contact" required>

        <button type="submit" name="add_user">Add User</button>
        <button type="button" id="cancelAddUserBtn">Cancel</button>
      </form>
    </div>

    <div id="editUserFormContainer" aria-hidden="true">
      <h2>Edit User</h2>
      <form method="POST" action="users.php" novalidate>
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($editUserData['UserID'] ?? '') ?>">
        <label for="edit_fname">First Name *</label>
        <input type="text" id="edit_fname" name="fname" required value="<?= htmlspecialchars($editUserData['FName'] ?? '') ?>">

        <label for="edit_lname">Last Name *</label>
        <input type="text" id="edit_lname" name="lname" required value="<?= htmlspecialchars($editUserData['LName'] ?? '') ?>">

        <label for="edit_email">Email *</label>
        <input type="email" id="edit_email" name="email" required value="<?= htmlspecialchars($editUserData['Email'] ?? '') ?>">

        <label for="edit_usertype">User Role *</label>
        <select id="edit_usertype" name="usertype" required>
          <option value="1" <?= (isset($editUserData['UserType']) && $editUserData['UserType'] == 1) ? 'selected' : '' ?>>Admin</option>
          <option value="2" <?= (isset($editUserData['UserType']) && $editUserData['UserType'] == 2) ? 'selected' : '' ?>>Staff</option>
          <option value="3" <?= (isset($editUserData['UserType']) && $editUserData['UserType'] == 3) ? 'selected' : '' ?>>Customer</option>
        </select>

        <label for="edit_address">Address *</label>
        <input type="text" id="edit_address" name="address" required value="<?= htmlspecialchars($editUserData['Address'] ?? '') ?>">

        <label for="edit_contact">Contact Number *</label>
        <input type="text" id="edit_contact" name="contact" required value="<?= htmlspecialchars($editUserData['ContactNumber'] ?? '') ?>">

        <button type="submit" name="edit_user">Update User</button>
        <button type="button" id="cancelEditUserBtn">Cancel</button>
      </form>
    </div>

    <h2>Users List</h2>
    <table>
      <thead>
        <tr>
          <th>UserID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Address</th>
          <th>Contact Number</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="8" style="text-align:center;">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $user): ?>
            <tr>
              <td><?= htmlspecialchars($user['UserID']) ?></td>
              <td><?= htmlspecialchars($user['FName'] . ' ' . $user['LName']) ?></td>
              <td><?= htmlspecialchars($user['Email']) ?></td>
              <td><?= htmlspecialchars($user['RoleName']) ?></td>
              <td><?= htmlspecialchars($user['Address']) ?></td>
              <td><?= htmlspecialchars($user['ContactNumber']) ?></td>
              <td class="<?= $user['IsLocked'] ? 'locked' : '' ?>">
                <?= $user['IsLocked'] ? 'Locked' : 'Active' ?>
              </td>
              <td>
                <?php if ($user['UserID'] !== $loggedInUserID): ?>
                  <a href="users.php?edit=<?= $user['UserID'] ?>" class="btn btn-edit" title="Edit User">Edit</a>

                  <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure?');">
                    <input type="hidden" name="user_id" value="<?= $user['UserID'] ?>" />
                    <?php if ($user['IsLocked']): ?>
                      <button type="submit" name="action" value="unlock" class="btn btn-unlock" title="Unlock User">Unlock</button>
                    <?php else: ?>
                      <button type="submit" name="action" value="lock" class="btn btn-lock" title="Lock User">Lock</button>
                    <?php endif; ?>
                  </form>

                  <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this user? This action cannot be undone.');">
                    <input type="hidden" name="user_id" value="<?= $user['UserID'] ?>" />
                    <button type="submit" name="action" value="delete" class="btn btn-delete" title="Delete User">Delete</button>
                  </form>
                <?php else: ?>
                  <em>(You)</em>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="users.php?page=<?= $p ?>" class="<?= $p == $currentPage ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
  <div class="footer-content">
    <div class="footer-links">
      <ul>
        <li><a href="#">About Us</a></li>
      </ul>
    </div>
  </div>
</footer>

<script src="js/users.js"></script>
<script>
  window.isEditing = <?php echo $isEditing ? 'true' : 'false'; ?>;
</script>

</body>
</html>

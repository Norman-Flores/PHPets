<?php
session_start();

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 1 Jan 2000 00:00:00 GMT");

// DB connection
$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$loggedIn = isset($_SESSION["first_name"]) && isset($_SESSION["last_name"]);

// Redirect non-admins to homepage
if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] != 1) {
  header("Location: index.php");
  exit;
}

// Handle logout
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "logout") {
  session_unset();
  session_destroy();
  header("Location: auth.php");
  exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PHPets - Admin Dashboard</title>
  <link rel="stylesheet" href="css/admin.css" />
</head>
<body>

<header class="admin-header">
  <div class="admin-header-wrapper">
    <h1 class="admin-title">PHPets Admin</h1>
    <nav class="admin-nav">
      <?php
      $links = [
        "admin.php" => "👤 Admin",
        "products.php" => "📦 Products",
        "users.php" => "🧑‍🤝‍🧑 Users",
        "inventory.php" => "📋 Inventory",
        "admin_orders.php" => "🧾 Orders",
        "sales.php" => "📊 View Sales",
        "inquiries.php" => "✉️ Inquiries",
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
  <h1>Welcome, PHPets!</h1>

  <section class="admin-section">
    <h2>🛍️ Manage Products</h2>
    <a href="products.php" class="admin-link">Go to Product Management</a>
  </section>

  <section class="admin-section">
    <h2>👥 Manage Users</h2>
    <a href="users.php" class="admin-link">Go to User Management</a>
  </section>

  <section class="admin-section">
    <h2>📦 Inventory</h2>
    <a href="inventory.php" class="admin-link">Check Inventory</a>
  </section>

  <section class="admin-section">
    <h2>📋 Orders</h2>
    <a href="admin_orders.php" class="admin-link">View Orders</a>
  </section>

  <section class="admin-section">
    <h2>📊 Reports</h2>
    <a href="sales.php" class="admin-link">View Sales & Best Sellers</a>
  </section>

    <section class="admin-section">
    <h2>✉️ Inquiries</h2>
    <a href="inquiries.php" class="admin-link">View Inquiries</a>
  </section>
</main>

<script src="js/index.js"></script>
</body>
</html>

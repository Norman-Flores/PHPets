<?php
session_start();

// Redirect non-admins to homepage
if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] != 1) {
  header("Location: index.php");
  exit;
}

// DB connection
$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle order status update (optional)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["order_id"]) && isset($_POST["order_status"])) {
  $orderID = intval($_POST["order_id"]);
  $orderStatus = $conn->real_escape_string($_POST["order_status"]);

  // Get current ArrivalDate from DB
  $stmt = $conn->prepare("SELECT ArrivalDate FROM ORDERTABLE WHERE OrderID = ?");
  $stmt->bind_param("i", $orderID);
  $stmt->execute();
  $stmt->bind_result($currentArrivalDate);
  $stmt->fetch();
  $stmt->close();

  // If status changed to Delivered and ArrivalDate not set, set to now
  if ($orderStatus === "Delivered" && (empty($currentArrivalDate) || $currentArrivalDate === null || $currentArrivalDate === '0000-00-00 00:00:00')) {
    $arrivalDate = date('Y-m-d H:i:s');
  } else {
    $arrivalDate = $currentArrivalDate ?: null;
  }

  // Update order status and arrival date
  $stmt = $conn->prepare("UPDATE ORDERTABLE SET OrderStatus = ?, ArrivalDate = ? WHERE OrderID = ?");
  if ($arrivalDate === null) {
    // Bind NULL for ArrivalDate
    $null = null;
    $stmt->bind_param("ssi", $orderStatus, $null, $orderID);
  } else {
    $stmt->bind_param("ssi", $orderStatus, $arrivalDate, $orderID);
  }
  $stmt->execute();
  $stmt->close();

  // Redirect to avoid resubmission
  header("Location: admin_orders.php");
  exit;
}

// Fetch orders with user name, payment type, and arrival date
$sql = "
SELECT o.OrderID, o.TotalCost, o.OrderDate, o.ArrivalDate, o.OrderStatus, o.deliveryAddress,
       u.FName, u.LName,
       p.PaymentMethod
FROM ORDERTABLE o
JOIN USER u ON o.UserID = u.UserID
JOIN PAYMENT p ON o.PaymentType = p.PaymentType
ORDER BY o.OrderID
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PHPets Admin - Orders</title>
  <link rel="stylesheet" href="css/admin.css" />
  <link rel="stylesheet" href="css/admin_orders.css" />
</head>
<body>
  <header class="admin-header">
    <div class="admin-header-wrapper">
      <h1 class="admin-title">Admin - Orders</h1>
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
    <h2>🧾 Orders List</h2>

    <?php if ($result && $result->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Order Date</th>
            <th>Arrival Date & Time</th>
            <th>Total Cost</th>
            <th>Payment Type</th>
            <th>Delivery Address</th>
            <th>Status</th>
            <th>Items</th>
            <th>Update Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($order = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($order["OrderID"]) ?></td>
              <td><?= htmlspecialchars($order["FName"] . " " . $order["LName"]) ?></td>
              <td><?= htmlspecialchars(date("Y-m-d H:i", strtotime($order["OrderDate"]))) ?></td>
              <td>
                <?= ($order["ArrivalDate"] && $order["ArrivalDate"] !== '0000-00-00 00:00:00') 
                    ? htmlspecialchars(date("Y-m-d H:i", strtotime($order["ArrivalDate"]))) 
                    : "N/A" ?>
              </td>
              <td>₱<?= number_format($order["TotalCost"], 2) ?></td>
              <td><?= htmlspecialchars($order["PaymentMethod"]) ?></td>
              <td><?= htmlspecialchars($order["deliveryAddress"]) ?></td>
              <td><?= htmlspecialchars($order["OrderStatus"]) ?></td>
              <td>
                <a href="admin_order_details.php?order_id=<?= $order["OrderID"] ?>" class="btn-view" title="View Items">View Items</a>
              </td>
              <td>
                <form method="POST" action="admin_orders.php">
                  <input type="hidden" name="order_id" value="<?= $order["OrderID"] ?>" />
                  <select name="order_status" onchange="this.form.submit()">
                    <?php
                    $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
                    foreach ($statuses as $status) {
                      $selected = ($order["OrderStatus"] === $status) ? "selected" : "";
                      echo "<option value=\"$status\" $selected>$status</option>";
                    }
                    ?>
                  </select>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No orders found.</p>
    <?php endif; ?>
  </main>
</body>
</html>

<?php $conn->close(); ?>

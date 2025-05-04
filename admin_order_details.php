<?php
session_start();

date_default_timezone_set('Asia/Manila'); // Set PHP timezone

// Only admins allowed
if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] != 1) {
  header("Location: index.php");
  exit;
}

$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("Invalid order ID.");
}

$orderID = intval($_GET['order_id']);

// Handle POST update for status and notes
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newStatus = $conn->real_escape_string($_POST['order_status']);
    $adminNotes = isset($_POST['admin_notes']) ? $conn->real_escape_string($_POST['admin_notes']) : '';

    // Get current ArrivalDate and OrderStatus from DB
    $stmt = $conn->prepare("SELECT ArrivalDate, OrderStatus FROM ORDERTABLE WHERE OrderID = ?");
    $stmt->bind_param("i", $orderID);
    $stmt->execute();
    $stmt->bind_result($currentArrivalDate, $currentStatus);
    $stmt->fetch();
    $stmt->close();

    if ($newStatus === "Delivered") {
        // If switching to Delivered and ArrivalDate empty or zero, set to now
        if (empty($currentArrivalDate) || $currentArrivalDate === '0000-00-00 00:00:00' || $currentArrivalDate === null) {
            $arrivalDate = date('Y-m-d H:i:s');
        } else {
            $arrivalDate = $currentArrivalDate;
        }
    } else {
        // If changing from Delivered to something else, clear ArrivalDate
        if ($currentStatus === "Delivered") {
            $arrivalDate = null; // clear it
        } else {
            $arrivalDate = $currentArrivalDate;
        }
    }

    $stmt = $conn->prepare("UPDATE ORDERTABLE SET OrderStatus = ?, AdminNotes = ?, ArrivalDate = ? WHERE OrderID = ?");
    if ($arrivalDate === null) {
        $null = null;
        $stmt->bind_param("sssi", $newStatus, $adminNotes, $null, $orderID);
    } else {
        $stmt->bind_param("sssi", $newStatus, $adminNotes, $arrivalDate, $orderID);
    }
    $stmt->execute();
    $stmt->close();

    header("Location: admin_order_details.php?order_id=$orderID");
    exit;
}

// Fetch order info with payment method and customer info
$stmt = $conn->prepare("
    SELECT O.OrderID, O.TotalCost, O.OrderDate, O.DeliveryAddress, O.OrderStatus, O.AdminNotes, O.ArrivalDate,
           P.PaymentMethod,
           U.FName, U.LName, U.Email, U.ContactNumber
    FROM ORDERTABLE O
    JOIN PAYMENT P ON O.PaymentType = P.PaymentType
    JOIN USER U ON O.UserID = U.UserID
    WHERE O.OrderID = ?
");
$stmt->bind_param("i", $orderID);
$stmt->execute();
$orderResult = $stmt->get_result();

if ($orderResult->num_rows === 0) {
    die("Order not found.");
}

$order = $orderResult->fetch_assoc();
$stmt->close();

// Fetch order items
$stmt = $conn->prepare("
    SELECT OI.ProductID, OI.Quantity, OI.Price, OI.Subtotal, P.ProductName, P.ProductImage
    FROM ORDERITEMS OI
    JOIN PRODUCT P ON OI.ProductID = P.ProductID
    WHERE OI.OrderID = ?
");
$stmt->bind_param("i", $orderID);
$stmt->execute();
$itemsResult = $stmt->get_result();
$orderItems = $itemsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Order Details - PHPets</title>
  <link rel="stylesheet" href="css/admin.css" />
  <link rel="stylesheet" href="css/order_details.css" />
  <link rel="stylesheet" href="css/admin_order_details.css" />
</head>
<body>
<header class="admin-header">
  <div class="admin-header-wrapper">
    <h1 class="admin-title">Admin - Order Details</h1>
    <nav class="admin-nav">
      <a href="admin_orders.php">🧾 Orders</a>
      <a href="admin.php">👤 Dashboard</a>
      <a href="logout.php">🚪 Logout</a>
    </nav>
  </div>
</header>

<main class="admin-panel">
  <section class="order-info admin-order-section">
    <h2>Order #<?= htmlspecialchars($order['OrderID']) ?></h2>
    <p><strong>Order Date:</strong> <?= htmlspecialchars($order['OrderDate']) ?></p>
    <p><strong>Arrival Date & Time:</strong> 
      <?= $order['ArrivalDate'] ? htmlspecialchars(date("Y-m-d H:i:s", strtotime($order['ArrivalDate']))) : "N/A" ?>
    </p>
    <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['PaymentMethod']) ?></p>
    <p><strong>Delivery Address:</strong><br><?= nl2br(htmlspecialchars($order['DeliveryAddress'])) ?></p>
  </section>

  <section class="customer-info admin-order-section">
    <h3>Customer Information</h3>
    <div class="admin-info">
      <p><strong>Name:</strong> <?= htmlspecialchars($order['FName'] . ' ' . $order['LName']) ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($order['Email']) ?></p>
      <p><strong>Contact Number:</strong> <?= htmlspecialchars($order['ContactNumber']) ?></p>
    </div>
  </section>

  <section class="order-items admin-order-section">
    <h3>Items Ordered</h3>
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orderItems as $item): ?>
          <tr>
            <td>
              <img src="images/<?= htmlspecialchars($item['ProductImage']) ?>" alt="<?= htmlspecialchars($item['ProductName']) ?>" class="product-image">
              <?= htmlspecialchars($item['ProductName']) ?>
            </td>
            <td>₱<?= number_format($item['Price'], 2) ?></td>
            <td><?= intval($item['Quantity']) ?></td>
            <td>₱<?= number_format($item['Subtotal'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="3" style="text-align: right; font-weight: bold;">Total:</td>
          <td>₱<?= number_format($order['TotalCost'], 2) ?></td>
        </tr>
      </tbody>
    </table>
  </section>

  <section class="admin-notes admin-order-section">
    <h3>Admin Notes & Status</h3>
    <form method="POST" action="admin_order_details.php?order_id=<?= $orderID ?>">
      <label for="order_status"><strong>Order Status:</strong></label><br>
      <select name="order_status" id="order_status" class="order-status-select" required>
        <?php
        $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
        foreach ($statuses as $status) {
          $selected = ($order['OrderStatus'] === $status) ? "selected" : "";
          echo "<option value=\"$status\" $selected>$status</option>";
        }
        ?>
      </select>

      <br><br>

      <label for="admin_notes"><strong>Internal Notes:</strong></label><br>
      <textarea name="admin_notes" id="admin_notes" placeholder="Add notes for internal use..."><?= htmlspecialchars($order['AdminNotes'] ?? '') ?></textarea>

      <br>
      <button type="submit" class="btn-save">Update Status</button>
    </form>
  </section>

  <div class="back-btn-container">
    <a href="admin_orders.php" class="admin-link">← Back to Orders</a>
  </div>
</main>
</body>
</html>

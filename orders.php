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

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$userID = $_SESSION['user_id'];

// Fetch orders for the user
$stmt = $conn->prepare("
    SELECT O.OrderID, O.TotalCost, O.OrderDate, U.Address AS DeliveryAddress, P.PaymentMethod
    FROM ORDERTABLE O
    JOIN PAYMENT P ON O.PaymentType = P.PaymentType
    JOIN USER U ON O.UserID = U.UserID
    WHERE O.UserID = ?
    ORDER BY O.OrderDate DESC
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Your Orders - PHPets</title>
  <link rel="stylesheet" href="css/orders.css" />
</head>
<body>
<header class="site-header">
  <h1>Your Orders</h1>
</header>

<main>
  <?php if (empty($orders)): ?>
    <p class="no-orders">You have no orders yet. <a href="items.php">Start shopping now!</a></p>
  <?php else: ?>
    <table class="orders-table" aria-label="User Orders">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Total Cost</th>
          <th>Payment Method</th>
          <th>Delivery Address</th>
          <th>Order Date</th>
          <th>Order Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td><?= htmlspecialchars($order['OrderID']) ?></td>
            <td>₱<?= number_format($order['TotalCost'], 2) ?></td>
            <td><?= htmlspecialchars($order['PaymentMethod']) ?></td>
            <td><?= htmlspecialchars($order['DeliveryAddress']) ?></td>
            <td><?= htmlspecialchars($order['OrderDate']) ?></td>
            <td><a href="order_details.php?order_id=<?= $order['OrderID'] ?>">View Details</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="back-btn-container">
      <a href="cart.php" class="back-btn">← Back to Cart</a>
    <a href="items.php" class="back-btn">Back to Shop</a>
  </div>
</main>
</body>
</html>

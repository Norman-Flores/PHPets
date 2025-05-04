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

  if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
      die("Invalid order ID.");
  }

  $orderID = intval($_GET['order_id']);

  // Fetch order info with payment method and check ownership
  $stmt = $conn->prepare("
      SELECT O.OrderID, O.TotalCost, O.OrderDate, O.DeliveryAddress, O.OrderStatus, P.PaymentMethod
      FROM ORDERTABLE O
      JOIN PAYMENT P ON O.PaymentType = P.PaymentType
      WHERE O.OrderID = ? AND O.UserID = ?
  ");
  $stmt->bind_param("ii", $orderID, $userID);
  $stmt->execute();
  $orderResult = $stmt->get_result();

  if ($orderResult->num_rows === 0) {
      die("Order not found or you do not have permission to view this order.");
  }

  $order = $orderResult->fetch_assoc();
  $stmt->close();

  // Fetch order items
  $stmt = $conn->prepare("
      SELECT OI.ProductID, OI.Quantity, OI.Price, OI.Subtotal, P.ProductName, P.ProductImage, O.DeliveryAddress AS DeliveryAddress
      FROM ORDERITEMS OI
      JOIN PRODUCT P ON OI.ProductID = P.ProductID
      JOIN ORDERTABLE O ON OI.OrderID = O.OrderID
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
    <title>Order Details - PHPets</title>
    <link rel="stylesheet" href="css/order_details.css" />
  </head>
  <body>
  <header class="site-header">
    <h1>Order Details</h1>
  </header>

  <main>
    <section class="order-info">
      <h2>Order #<?= htmlspecialchars($order['OrderID']) ?></h2>
      <p><strong>Order Date:</strong> <?= htmlspecialchars($order['OrderDate']) ?></p>
      <p><strong>Status:</strong> <span class="order-status"><?= htmlspecialchars($order['OrderStatus']) ?></span></p>
      <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['PaymentMethod']) ?></p>
      <p><strong>Delivery Address:</strong><br><?=(htmlspecialchars($order['DeliveryAddress'])) ?></p>
    </section>

    <section class="order-items">
      <h2>Items Ordered</h2>
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
                <img src="images/<?= htmlspecialchars($item['ProductImage']) ?>" alt="<?= htmlspecialchars($item['ProductName']) ?>">
                <?= htmlspecialchars($item['ProductName']) ?>
              </td>
              <td>₱<?= number_format($item['Price'], 2) ?></td>
              <td><?= $item['Quantity'] ?></td>
              <td>₱<?= number_format($item['Subtotal'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="total-row">
            <td colspan="3" style="text-align: right;">Total:</td>
            <td>₱<?= number_format($order['TotalCost'], 2) ?></td>
          </tr>
        </tbody>
      </table>
    </section>

    <div class="back-btn-container">
      <a href="orders.php" class="back-btn">Back to Orders</a>
    </div>
  </main>
  </body>
  </html>

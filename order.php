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

// Fetch cart items with product info
$stmt = $conn->prepare("
    SELECT C.CartID, C.ProductID, C.InCartQuantity, P.ProductName, P.Price, P.ProductImage
    FROM CART C
    JOIN PRODUCT P ON C.ProductID = P.ProductID
    WHERE C.UserID = ?
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

$cartItems = $result->fetch_all(MYSQLI_ASSOC);

if (empty($cartItems)) {
    // Redirect if cart is empty
    header("Location: cart.php");
    exit();
}

$total = 0;
foreach ($cartItems as $item) {
    $total += $item['Price'] * $item['InCartQuantity'];
}

// Fetch payment methods
$paymentMethods = [];
$res = $conn->query("SELECT PaymentType, PaymentMethod FROM PAYMENT");
while ($row = $res->fetch_assoc()) {
    $paymentMethods[] = $row;
}

// Fetch user's delivery address
$stmt = $conn->prepare("SELECT Address FROM USER WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

$deliveryAddress = $userData['Address'] ?? '';

$orderPlaced = false;
$error = "";

// Function to update cart count in session
function updateCartCount($conn, $userID) {
    $stmt = $conn->prepare("SELECT SUM(InCartQuantity) AS totalQuantity FROM CART WHERE UserID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->bind_result($totalQuantity);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['cart_count'] = $totalQuantity ? (int)$totalQuantity : 0;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['payment_type'])) {
    $paymentType = intval($_POST['payment_type']);

    $conn->begin_transaction();

    try {
        // 1. Insert into ORDERTABLE with deliveryAddress included
        $stmt = $conn->prepare("INSERT INTO ORDERTABLE (UserID, TotalCost, PaymentType, deliveryAddress) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idis", $userID, $total, $paymentType, $deliveryAddress);
        $stmt->execute();
        $orderID = $conn->insert_id; // Get inserted OrderID
        $stmt->close();

        // 2. Insert each cart item into ORDERITEMS
        $stmt = $conn->prepare("INSERT INTO ORDERITEMS (OrderID, ProductID, Quantity, Price, Subtotal) VALUES (?, ?, ?, ?, ?)");
        foreach ($cartItems as $item) {
            $subtotal = $item['Price'] * $item['InCartQuantity'];
            $stmt->bind_param("iiidd", $orderID, $item['ProductID'], $item['InCartQuantity'], $item['Price'], $subtotal);
            $stmt->execute();
        }
        $stmt->close();

        // 3. Clear cart for user
        $stmt = $conn->prepare("DELETE FROM CART WHERE UserID = ?");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $stmt->close();

        // 4. Update cart count in session after clearing cart
        updateCartCount($conn, $userID);

        $conn->commit();

        $orderPlaced = true;

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to place order: " . $e->getMessage();
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Checkout - PHPets</title>
  <link rel="stylesheet" href="css/order.css" />

</head>
<body>
<header class="site-header">
  <h1>Checkout</h1>
</header>

<main class="cart-container">

  <?php if ($orderPlaced): ?>
    <div class="confirmation-popup">
      <h2>Thank You for Your Order!</h2>
      <p>Your order has been successfully placed.</p>
      <a href="items.php">Continue Shopping</a>
    </div>
  <?php else: ?>
    <?php if (!empty($error)): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <h2>Your Cart Items</h2>
    <table class="cart-table" aria-label="Cart Items">
      <thead>
        <tr>
          <th>Item</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cartItems as $item): 
          $subtotal = $item['Price'] * $item['InCartQuantity'];
        ?>
          <tr>
            <td class="product-info">
              <img src="images/<?= htmlspecialchars($item['ProductImage']) ?>" alt="<?= htmlspecialchars($item['ProductName']) ?>">
              <?= htmlspecialchars($item['ProductName']) ?>
            </td>
            <td>₱<?= number_format($item['Price'], 2) ?></td>
            <td><?= $item['InCartQuantity'] ?></td>
            <td>₱<?= number_format($subtotal, 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="cart-total">
      Total: ₱<?= number_format($total, 2) ?>
    </div>

    <form method="POST" class="checkout-form" aria-label="Checkout Form">
      <label for="payment_type">Select Payment Method:</label>
      <select name="payment_type" id="payment_type" required>
        <option value="" disabled selected>Choose payment method</option>
        <?php foreach ($paymentMethods as $method): ?>
          <option value="<?= $method['PaymentType'] ?>"><?= htmlspecialchars($method['PaymentMethod']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="checkout-btn">Place Order</button>
    </form>

    <div class="back-btn-container">
      <a href="cart.php" class="back-btn">Back to Cart</a>
    </div>
  <?php endif; ?>

</main>
</body>
</html>

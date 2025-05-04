<?php
session_start();

// === Inline DB Connection ===
$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// === Session check ===
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$userID = $_SESSION['user_id'];

// === Function to update cart count in session ===
function updateCartCount($conn, $userID) {
    $stmt = $conn->prepare("SELECT SUM(InCartQuantity) AS totalQuantity FROM CART WHERE UserID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->bind_result($totalQuantity);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['cart_count'] = $totalQuantity ? (int)$totalQuantity : 0;
}

// === Handle Cart Actions ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cart_id"])) {
    $cartID = intval($_POST["cart_id"]);

    if (isset($_POST["action"])) {
        $action = $_POST["action"];
        if ($action === "increase") {
            $stmt = $conn->prepare("UPDATE CART SET InCartQuantity = InCartQuantity + 1 WHERE CartID = ? AND UserID = ?");
        } elseif ($action === "decrease") {
            $stmt = $conn->prepare("UPDATE CART SET InCartQuantity = GREATEST(InCartQuantity - 1, 1) WHERE CartID = ? AND UserID = ?");
        }

        if (isset($stmt)) {
            $stmt->bind_param("ii", $cartID, $userID);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (isset($_POST["remove"])) {
        $stmt = $conn->prepare("DELETE FROM CART WHERE CartID = ? AND UserID = ?");
        $stmt->bind_param("ii", $cartID, $userID);
        $stmt->execute();
        $stmt->close();
    }

    // Update cart count in session after any modification
    updateCartCount($conn, $userID);

    header("Location: cart.php");
    exit;
}

// === Fetch Cart Items ===
$stmt = $conn->prepare("
    SELECT C.CartID, C.InCartQuantity, P.ProductName, P.Price, P.ProductImage
    FROM CART C
    JOIN PRODUCT P ON C.ProductID = P.ProductID
    WHERE C.UserID = ?
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$cartItems = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Also, update cart count on page load (optional, to sync session)
updateCartCount($conn, $userID);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Cart - PHPets</title>
  <link rel="stylesheet" href="css/cart.css">
</head>
<body>

<header class="site-header">
  <h1>Your Shopping Cart</h1>
</header>

<main class="cart-container">
  <?php if (empty($cartItems)): ?>
    <p class="empty-msg">Your cart is empty. <a href="items.php">Browse products</a></p>
  <?php else: ?>
    <table class="cart-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php $total = 0; ?>
        <?php foreach ($cartItems as $item): 
          $subtotal = $item['Price'] * $item['InCartQuantity'];
          $total += $subtotal;
        ?>
          <tr>
            <td>
              <img src="images/<?= htmlspecialchars($item['ProductImage']) ?>" alt="<?= htmlspecialchars($item['ProductName']) ?>">
              <?= htmlspecialchars($item['ProductName']) ?>
            </td>
            <td>₱<?= number_format($item['Price'], 2) ?></td>
            <td>
              <!-- Fixed form structure -->
              <form method="POST" style="display:inline-flex;align-items:center;gap:5px;">
                <input type="hidden" name="cart_id" value="<?= $item['CartID'] ?>">
                <button type="submit" name="action" value="decrease" class="qty-btn">-</button>
                <span class="qty"><?= $item['InCartQuantity'] ?></span>
                <button type="submit" name="action" value="increase" class="qty-btn">+</button>
              </form>
            </td>
            <td>₱<?= number_format($subtotal, 2) ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Remove this item?');">
                <input type="hidden" name="cart_id" value="<?= $item['CartID'] ?>">
                <button type="submit" name="remove" class="remove-btn">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="cart-total">
      <h2>Total: ₱<?= number_format($total, 2) ?></h2>
      </div>
    </div>
  <?php endif; ?>

  <div class="buttons-container" style="margin-top: 20px; text-align: center;">
    <a href="order.php" class="checkout-btn" <?= empty($cartItems) ? 'style="pointer-events:none;opacity:0.5;"' : '' ?>>Place Order</a>
    <a href="orders.php" class="check-orders-btn">Check Orders</a>
    <a href="items.php" class="back-btn">Back to Shop</a>
  </div>
</main>
</main>

</body>
</html>

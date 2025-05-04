<?php
session_start();

$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$loggedIn = isset($_SESSION["first_name"]) && isset($_SESSION["last_name"]);

// Handle Login POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "login") {
    $email = $_POST["email"] ?? '';
    $password = $_POST["password"] ?? '';

    $loginStmt = $conn->prepare("SELECT * FROM USER WHERE Email = ?");
    $loginStmt->execute([$email]);
    $user = $loginStmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user["Password"])) {
            $_SESSION["user_id"] = $user["UserID"];
            $_SESSION["first_name"] = $user["FName"];
            $_SESSION["last_name"] = $user["LName"];
            $_SESSION["email"] = $user["Email"];
            $_SESSION["usertype"] = $user["UserType"];

            if ($user["UserType"] == 1) {
                header("Location: admin.php");
                exit;
            } else {
                header("Location: product.php?id=" . (isset($_GET['id']) ? intval($_GET['id']) : ''));
                exit;
            }
        } else {
            echo "<script>alert('Invalid password');</script>";
        }
    } else {
        echo "<script>alert('User not found');</script>";
    }
}

// Handle Add to Cart POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && isset($_SESSION['user_id'])) {
    $productId = intval($_POST['product_id']);
    $userId = $_SESSION['user_id'];
    $qty = isset($_POST['qty']) ? max(1, intval($_POST['qty'])) : 1;

    // Check if product already in cart
    $cartCheck = $conn->prepare("SELECT CartID, InCartQuantity FROM CART WHERE UserID = ? AND ProductID = ?");
    $cartCheck->execute([$userId, $productId]);
    $cartItem = $cartCheck->fetch(PDO::FETCH_ASSOC);

    if ($cartItem) {
        // Update quantity
        $newQuantity = $cartItem['InCartQuantity'] + $qty;
        $updateStmt = $conn->prepare("UPDATE CART SET InCartQuantity = ? WHERE CartID = ?");
        $updateStmt->execute([$newQuantity, $cartItem['CartID']]);
    } else {
        // Insert new cart item
        $insertStmt = $conn->prepare("INSERT INTO CART (UserID, ProductID, InCartQuantity) VALUES (?, ?, ?)");
        $insertStmt->execute([$userId, $productId, $qty]);
    }

    // Update cart count session
    $_SESSION['cart_count'] = getCartCount($conn, $userId);

    // Redirect to avoid form resubmission
    header("Location: product.php?id={$productId}&added=true");
    exit();
}

// Function to get cart count
function getCartCount($conn, $userId) {
    $stmt = $conn->prepare("SELECT SUM(InCartQuantity) FROM CART WHERE UserID = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// Get product ID from URL
$productID = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch product data
$stmt = $conn->prepare("SELECT * FROM PRODUCT WHERE ProductID = ?");
$stmt->execute([$productID]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// If product not found, show error
if (!$product) {
    echo "<h1>Product not found!</h1>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($product['ProductName']) ?> - PHPets</title>
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/items.css">
  <link rel="stylesheet" href="css/product.css">
  <script>
    function updateQuantity(action) {
      var qtyInput = document.getElementById("qty");
      var currentValue = parseInt(qtyInput.value);
      if (action === 'increase') {
        qtyInput.value = currentValue + 1;
      } else if (action === 'decrease' && currentValue > 1) {
        qtyInput.value = currentValue - 1;
      }
    }
  </script>
</head>
<body>
  <header class="site-header">
    <div class="top-header">
      <a href="index.php" class="logo">
        <img src="images/Logo.png" alt="PHPets Logo">
      </a>
      <form method="GET" action="items.php" class="search-form" role="search" aria-label="Search products">
        <input 
          type="text" 
          name="search" 
          placeholder="Search products..." 
          class="search-input"
          value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
          aria-label="Search products"
        />
        <button type="submit" class="search-button" aria-label="Search">Search</button>
      </form>
      <div class="user-actions">
        <div class="auth-dropdown" id="authDropdown">
          <?php if ($loggedIn): ?>
            <button id="authToggle" aria-haspopup="true" aria-expanded="false">
              <?= htmlspecialchars($_SESSION["first_name"] . " " . $_SESSION["last_name"]) ?> ▼
            </button>
            <div class="auth-panel" id="authPanel" role="menu" aria-hidden="true">
              <form method="POST" action="auth.php">
                <a href="dashboard.php" class="manage-account-btn" role="menuitem">Manage Your Account</a>
                <input type="hidden" name="action" value="logout" />
                <button type="submit" class="login-btn">Logout</button>
              </form>
            </div>
          <?php else: ?>
            <button id="authToggle" aria-haspopup="true" aria-expanded="false">My account ▼</button>
            <div class="auth-panel" id="authPanel" role="menu" aria-hidden="true">
              <form method="POST" action="product.php?id=<?= $productID ?>">
                <input type="hidden" name="action" value="login" />
                <h3>Login to my account</h3>
                <p>Enter your e-mail and password:</p>
                <input type="email" name="email" placeholder="Email" required aria-label="Email" />
                <input type="password" name="password" placeholder="Password" required aria-label="Password" />
                <button type="submit" class="login-btn">Login</button>
              </form>
              <div class="auth-links">
                <p>New customer? <a href="auth.php">Create your account</a></p>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <a href="cart.php" class="cart-button" aria-label="View shopping cart">
          🛒 Cart
          <span class="cart-count" aria-live="polite"><?= isset($_SESSION['cart_count']) ? intval($_SESSION['cart_count']) : 0 ?></span>
        </a>
      </div>
    </div>
    <nav class="category-nav" aria-label="Product categories">
      <ul>
        <li><a href="items.php?category=1">Toys</a></li>
        <li><a href="items.php?category=2">Healthcare</a></li>
        <li><a href="items.php?category=3">Grooming</a></li>
        <li><a href="items.php?category=4">Food</a></li>
        <li><a href="items.php?category=5">Accessories</a></li>
      </ul>
    </nav>
  </header>
  <main>
    <div class="product-detail-container">
      <div class="product-image">
        <img src="images/<?= htmlspecialchars($product['ProductImage']) ?>" alt="<?= htmlspecialchars($product['ProductName']) ?>">
      </div>
      <div class="product-info">
        <h1><?= htmlspecialchars($product['ProductName']) ?></h1>
        <p><?= htmlspecialchars($product['Description']) ?></p>
        <h2>₱<?= number_format($product['Price'], 2) ?></h2>
        <form method="POST" action="product.php?id=<?= $productID ?>">
          <div class="quantity">
            <label for="qty">Quantity:</label>
            <button type="button" onclick="updateQuantity('decrease')">-</button>
            <input type="number" id="qty" name="qty" min="1" value="1">
            <button type="button" onclick="updateQuantity('increase')">+</button>
          </div>
          <input type="hidden" name="product_id" value="<?= $productID ?>">
          <button type="submit" class="add-to-cart">Add to Cart</button>
        </form>
        <a href="items.php" class="back-btn">← Back to Items</a>
        <?php if (isset($_GET['added']) && $_GET['added'] == 'true'): ?>
          <div class="toast">Item added to cart!</div>
        <?php endif; ?>
      </div>
    </div>
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
  <div id="toast" class="toast">Item added to cart!</div>
<script>
    // Show toast if an item was added to the cart
    <?php if (isset($_GET['added']) && $_GET['added'] == 'true'): ?>
      var toast = document.getElementById('toast');
      toast.className = 'toast show';
      setTimeout(function() { toast.className = toast.className.replace('show', ''); }, 3000);
    <?php endif; ?>
  </script>
  <script src="js/index.js"></script>
</body>
</html>

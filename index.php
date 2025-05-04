<?php
session_start();

$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Best Selling Items
    $stmt = $conn->prepare("
      SELECT
        P.ProductID,
        P.ProductName,
        P.ProductImage,
        P.Price,
        SUM(OI.Quantity) AS TotalQuantitySold
      FROM
        PRODUCT P
      JOIN
        ORDERITEMS OI ON P.ProductID = OI.ProductID
      GROUP BY
        P.ProductID, P.ProductName, P.ProductImage, P.Price
      ORDER BY
        TotalQuantitySold DESC
    ");
    $stmt->execute();
    $bestSellingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Limit to 10 items
    $maxItems = 10;
    $displayItems = array_slice($bestSellingItems, 0, $maxItems);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$loggedIn = isset($_SESSION["first_name"]) && isset($_SESSION["last_name"]);

// Handle Login POST using PDO (replacing mysqli logic)
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
                header("Location: index.php");
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
    $productId = $_POST['product_id'];
    $userId = $_SESSION['user_id'];

    // Check if product already in cart
    $cartCheck = $conn->prepare("SELECT CartID, InCartQuantity FROM CART WHERE UserID = ? AND ProductID = ?");
    $cartCheck->execute([$userId, $productId]);
    $cartItem = $cartCheck->fetch(PDO::FETCH_ASSOC);

    if ($cartItem) {
        // Update quantity
        $newQuantity = $cartItem['InCartQuantity'] + 1;
        $updateStmt = $conn->prepare("UPDATE CART SET InCartQuantity = ? WHERE CartID = ?");
        $updateStmt->execute([$newQuantity, $cartItem['CartID']]);
    } else {
        // Insert new cart item
        $insertStmt = $conn->prepare("INSERT INTO CART (UserID, ProductID, InCartQuantity) VALUES (?, ?, 1)");
        $insertStmt->execute([$userId, $productId]);
    }

    // Update cart count session
    $_SESSION['cart_count'] = getCartCount($conn, $userId);

    // Redirect to avoid form resubmission
    header("Location: index.php?added=true");
    exit();
}

// Function to get cart count
function getCartCount($conn, $userId) {
    $stmt = $conn->prepare("SELECT SUM(InCartQuantity) FROM CART WHERE UserID = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PHPets Home</title>
  <link rel="stylesheet" href="css/index.css" />
</head>

<body>

<header class="site-header">
  <div class="header-bg left-bg"></div>
  <div class="header-bg right-bg"></div>

  <div class="top-header">
    <a href="index.php" class="logo" aria-label="PHPets Home">
      <img src="images/Logo.png" alt="PHPets Logo" />
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
              <input type="hidden" name="action" value="logout" />
              <a href="dashboard.php" class="manage-account-btn" role="menuitem">Manage Your Account</a>
              <button type="submit" class="login-btn">Logout</button>
            </form>
          </div>
        <?php else: ?>
          <button id="authToggle" aria-haspopup="true" aria-expanded="false">My account ▼</button>
          <div class="auth-panel" id="authPanel" role="menu" aria-hidden="true">
            <form method="POST" action="index.php">
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

<section class="hero-section">
  <div class="hero-wrapper">
    <div class="hero-text">
      <h1>Welcome to PHPets!</h1>
      <p>Your one-stop pet shop for love, care, and cool toys.</p>
      <a href="items.php" class="hero-btn">Shop Now</a>
    </div>
    <div class="hero-img">
      <video autoplay loop muted playsinline class="hero-video">
        <source src="images/hero_video.mp4" type="video/mp4">
        Your browser does not support the video tag.
      </video>
    </div>
  </div>
</section>

<!-- CAROUSEL SECTION -->
<section class="carousel-section">
  <h2>Best Selling Items</h2>
  <div class="carousel-wrapper">
    <button class="carousel-btn left" aria-label="Previous">&#10094;</button>

    <div class="carousel">
      <div class="carousel-track">
        <?php foreach ($displayItems as $item): ?>
          <div class="carousel-item">
            <a href="product.php?id=<?= urlencode($item['ProductID']) ?>" class="product-link" aria-label="<?= htmlspecialchars($item['ProductName']) ?>">
              <img src="images/<?= htmlspecialchars($item['ProductImage']) ?>" alt="<?= htmlspecialchars($item['ProductName']) ?>">
              <h3><?= htmlspecialchars($item['ProductName']) ?></h3>
            </a>
            <p class="price">$<?= number_format($item['Price'], 2) ?></p>
            <p><?= htmlspecialchars($item['TotalQuantitySold']) ?> Sold</p>

            <form method="POST" action="index.php" class="add-to-cart-form" aria-label="Add <?= htmlspecialchars($item['ProductName']) ?> to cart">
              <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['ProductID']) ?>">
              <button type="submit" class="add-to-cart-btn">Add to Cart</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <button class="carousel-btn right" aria-label="Next">&#10095;</button>
  </div>
</section>

<section class="category-section">
  <h2>Shop by Category</h2>
  <div class="category-grid">
    <div class="category-box">
      <img src="images/toys.webp" alt="Toys">
      <a href="items.php?category=1">Toys</a>
    </div>
    <div class="category-box">
      <img src="images/healthcare.jpeg" alt="Healthcare">
      <a href="items.php?category=2">Healthcare</a>
    </div>
    <div class="category-box">
      <img src="images/grooming.webp" alt="Grooming">
      <a href="items.php?category=3">Grooming</a>
    </div>

    <div class="category-box">
      <img src="images/food.avif" alt="Food">
      <a href="items.php?category=4">Food</a>
    </div>

  </div>
</section>

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

<script src="js/header.js"></script>
<script src="js/index.js"></script>

  <script>
    // Show toast if an item was added to the cart
    <?php if (isset($_GET['added']) && $_GET['added'] == 'true'): ?>
      var toast = document.getElementById('toast');
      toast.className = 'toast show';
      setTimeout(function() { toast.className = toast.className.replace('show', ''); }, 3000);
    <?php endif; ?>
  </script>

</body>

</html>

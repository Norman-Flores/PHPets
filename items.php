<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'PHPets';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// User login status
$loggedIn = isset($_SESSION["first_name"]) && isset($_SESSION["last_name"]);

// Filters
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$productsPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $productsPerPage;

// Fetch categories
$categoryStmt = $pdo->query("SELECT * FROM CATEGORY");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE clause
$whereClauses = [];
$params = [];

if ($categoryFilter !== '') {
    $whereClauses[] = "CategoryID = ?";
    $params[] = $categoryFilter;
}
if ($searchTerm !== '') {
    $whereClauses[] = "ProductName LIKE ?";
    $params[] = "%$searchTerm%";
}

$whereSQL = '';
if ($whereClauses) {
    $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
}

// Count total products
$countQuery = "SELECT COUNT(*) FROM PRODUCT" . $whereSQL;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $productsPerPage);

// Fetch products
$productQuery = "SELECT * FROM PRODUCT" . $whereSQL . " LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($productQuery);

// Bind params
foreach ($params as $index => $param) {
    $stmt->bindValue($index + 1, $param);
}
$stmt->bindValue(count($params) + 1, (int)$productsPerPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Add to Cart POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && isset($_SESSION['user_id'])) {
    $productId = $_POST['product_id'];
    $userId = $_SESSION['user_id'];

    $cartCheck = $pdo->prepare("SELECT CartID, InCartQuantity FROM CART WHERE UserID = ? AND ProductID = ?");
    $cartCheck->execute([$userId, $productId]);
    $cartItem = $cartCheck->fetch(PDO::FETCH_ASSOC);

    if ($cartItem) {
        $newQuantity = $cartItem['InCartQuantity'] + 1;
        $updateStmt = $pdo->prepare("UPDATE CART SET InCartQuantity = ? WHERE CartID = ?");
        $updateStmt->execute([$newQuantity, $cartItem['CartID']]);
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO CART (UserID, ProductID, InCartQuantity) VALUES (?, ?, 1)");
        $insertStmt->execute([$userId, $productId]);
    }

    // Update cart count session
    $_SESSION['cart_count'] = getCartCount($pdo, $userId);

    // Redirect to avoid form resubmission, preserving filters and page
    $redirectUrl = "items.php?page=$currentPage";
    if ($categoryFilter !== '') $redirectUrl .= "&category=" . urlencode($categoryFilter);
    if ($searchTerm !== '') $redirectUrl .= "&search=" . urlencode($searchTerm);
    $redirectUrl .= "&added=true";
    header("Location: $redirectUrl");
    exit();
}

// Function to get cart count
function getCartCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT SUM(InCartQuantity) FROM CART WHERE UserID = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PHPets - Shop Items</title>
  <link rel="stylesheet" href="css/index.css" />
  <link rel="stylesheet" href="css/items.css" />
  <link rel="stylesheet" href="css/button.css" />
</head>

<body>

  <header class="site-header">
    <div class="top-header">
      <a href="index.php" class="logo">
        <img src="images/Logo.png" alt="PHPets Logo">
      </a>
      <div class="search-bar">
    <form method="GET" action="items.php" class="search-form">
      <input type="text" name="search" placeholder="Search for products..." value="<?= htmlspecialchars($searchTerm) ?>" class="search-input" />
      <?php if ($categoryFilter !== ''): ?>
        <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>" />
      <?php endif; ?>
      <button type="submit" class="search-button">Search</button>
    </form>
  </div>      <div class="user-actions">
        <div class="auth-dropdown">
          <?php if ($loggedIn): ?>
            <button id="authToggle">
              <?php echo htmlspecialchars($_SESSION["first_name"] . " " . $_SESSION["last_name"]); ?> ▼
            </button>
            <div class="auth-panel" id="authPanel">
              <form method="POST" action="auth.php">
                <input type="hidden" name="action" value="logout">
                <a href="dashboard.php" class="manage-account-btn" role="menuitem">Manage Your Account</a>
                <button type="submit" class="login-btn">Logout</button>
              </form>
            </div>
          <?php else: ?>
            <button id="authToggle">My account ▼</button>
            <div class="auth-panel" id="authPanel">
              <form method="POST" action="index.php">
                <input type="hidden" name="action" value="login">
                <h3>Login to my account</h3>
                <p>Enter your e-mail and password:</p>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="login-btn">Login</button>
              </form>
              <div class="auth-links">
                <p>New customer? <a href="auth.php">Create your account</a></p>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <a href="cart.php" class="cart-button">
          🛒 Cart
          <span class="cart-count"><?= isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : 0 ?></span>
        </a>

      </div>
    </div>
  </header>

  <main>
  <div class="category-filter">
    <a href="items.php<?= $searchTerm !== '' ? '?search=' . urlencode($searchTerm) : '' ?>" class="<?= $categoryFilter == '' ? 'active' : '' ?>">All</a>
    <?php foreach ($categories as $category): ?>
      <?php
        $urlParams = [];
        if ($searchTerm !== '') $urlParams['search'] = $searchTerm;
        $urlParams['category'] = $category['CategoryID'];
        $url = 'items.php?' . http_build_query($urlParams);
      ?>
      <a href="<?= $url ?>" class="<?= $categoryFilter == $category['CategoryID'] ? 'active' : '' ?>">
        <?= htmlspecialchars($category['CategoryName']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="product-grid">
    <?php if (count($products) === 0): ?>
      <p class="no-results">No products found matching your search.</p>
    <?php endif; ?>

    <?php foreach ($products as $product): ?>
      <div class="product-card">
        <a href="product.php?id=<?= urlencode($product['ProductID']) ?>">
          <img src="images/<?= htmlspecialchars($product['ProductImage']) ?>" alt="<?= htmlspecialchars($product['ProductName']) ?>" />
        </a>
        <h3><?= htmlspecialchars($product['ProductName']) ?></h3>
        <div class="price">₱<?= number_format($product['Price'], 2) ?></div>
        <form method="POST" action="items.php<?= '?' . http_build_query(['page' => $currentPage, 'category' => $categoryFilter, 'search' => $searchTerm]) ?>">
          <input type="hidden" name="product_id" value="<?= $product['ProductID'] ?>" />
          <button type="submit" class="add-to-cart">Add to Cart</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php
          $urlParams = ['page' => $i];
          if ($categoryFilter !== '') $urlParams['category'] = $categoryFilter;
          if ($searchTerm !== '') $urlParams['search'] = $searchTerm;
          $url = 'items.php?' . http_build_query($urlParams);
        ?>
        <a href="<?= $url ?>" class="<?= $i == $currentPage ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
    <button id="scrollToTopBtn" title="Go to top">↑</button>
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

  <script src="js/items.js"></script>
  <script src="js/button.js"></script>
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
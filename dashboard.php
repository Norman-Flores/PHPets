<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

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

$userId = $_SESSION['user_id'];

// Pagination settings
$ordersPerPage = 5;

// Get current page for orders
$currentOrdersPage = isset($_GET['orders_page']) ? max(1, intval($_GET['orders_page'])) : 1;
$ordersOffset = ($currentOrdersPage - 1) * $ordersPerPage;

// Get total orders count
$totalOrdersStmt = $conn->prepare("SELECT COUNT(*) FROM ORDERTABLE WHERE UserID = ?");
$totalOrdersStmt->execute([$userId]);
$totalOrders = $totalOrdersStmt->fetchColumn();
$totalOrdersPages = ceil($totalOrders / $ordersPerPage);

// Fetch paginated orders
$orderStmt = $conn->prepare("SELECT * FROM ORDERTABLE WHERE UserID = ? ORDER BY OrderDate DESC LIMIT ? OFFSET ?");
$orderStmt->bindValue(1, $userId, PDO::PARAM_INT);
$orderStmt->bindValue(2, $ordersPerPage, PDO::PARAM_INT);
$orderStmt->bindValue(3, $ordersOffset, PDO::PARAM_INT);
$orderStmt->execute();
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

// Purchase history pagination (same as orders here)
$currentPurchasePage = isset($_GET['purchase_page']) ? max(1, intval($_GET['purchase_page'])) : 1;
$purchaseOffset = ($currentPurchasePage - 1) * $ordersPerPage;
$totalPurchasePages = $totalOrdersPages; // same total count

$purchaseStmt = $conn->prepare("SELECT * FROM ORDERTABLE WHERE UserID = ? ORDER BY OrderDate DESC LIMIT ? OFFSET ?");
$purchaseStmt->bindValue(1, $userId, PDO::PARAM_INT);
$purchaseStmt->bindValue(2, $ordersPerPage, PDO::PARAM_INT);
$purchaseStmt->bindValue(3, $purchaseOffset, PDO::PARAM_INT);
$purchaseStmt->execute();
$purchaseHistory = $purchaseStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle inquiry form submission
$inquirySuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_subject']) && isset($_POST['inquiry_message'])) {
    $subject = trim($_POST['inquiry_subject']);
    $message = trim($_POST['inquiry_message']);

    if ($subject !== '' && $message !== '') {
        $stmt = $conn->prepare("INSERT INTO INQUIRIES (UserID, Subject, Message) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $subject, $message]);
        $inquirySuccess = true;
    }
}

function renderPagination($currentPage, $totalPages, $pageParam) {
    if ($totalPages <= 1) return '';
    $html = '<nav class="pagination" aria-label="Pagination">';
    if ($currentPage > 1) {
        $html .= '<a href="dashboard.php?' . $pageParam . '=' . ($currentPage -1) . '" class="page-link" aria-label="Previous page">&laquo; Prev</a>';
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="page-link current" aria-current="page">' . $i . '</span>';
        } else {
            $html .= '<a href="dashboard.php?' . $pageParam . '=' . $i . '" class="page-link">' . $i . '</a>';
        }
    }
    if ($currentPage < $totalPages) {
        $html .= '<a href="dashboard.php?' . $pageParam . '=' . ($currentPage +1) . '" class="page-link" aria-label="Next page">Next &raquo;</a>';
    }
    $html .= '</nav>';
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - PHPets</title>
  <link rel="stylesheet" href="css/index.css" />
  <link rel="stylesheet" href="css/dashboard.css" />
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
        <form action="logout.php" method="post" class="logout-form" style="display:inline;">
    <button type="submit" class="logout-btn" aria-label="Logout">Logout</button>
  </form>
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
  <h1>Welcome, <?= htmlspecialchars($_SESSION['first_name']) ?>!</h1>

  <section aria-label="Your orders">
    <h2>Your Orders</h2>
    <?php if (empty($orders)): ?>
      <p>You have no orders yet.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Status</th>
            <th>Total</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><?= htmlspecialchars($order['OrderID']) ?></td>
              <td><?= htmlspecialchars($order['OrderDate']) ?></td>
              <td><?= htmlspecialchars($order['OrderStatus']) ?></td>
              <td>₱<?= number_format($order['TotalCost'], 2) ?></td>
              <td>
<form action="order_details.php" method="get" style="display:inline;">
  <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['OrderID']) ?>">
  <button type="submit" class="view-btn">View</button>
</form>              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?= renderPagination($currentOrdersPage, $totalOrdersPages, 'orders_page') ?>
    <?php endif; ?>
  </section>

  <section aria-label="Purchase history">
    <h2>Purchase History</h2>
    <?php if (empty($purchaseHistory)): ?>
      <p>No purchase history available.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($purchaseHistory as $purchase): ?>
            <tr>
              <td><?= htmlspecialchars($purchase['OrderID']) ?></td>
              <td><?= htmlspecialchars($purchase['OrderDate']) ?></td>
              <td>₱<?= number_format($purchase['TotalCost'], 2) ?></td>
              <td><?= htmlspecialchars($purchase['OrderStatus']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?= renderPagination($currentPurchasePage, $totalPurchasePages, 'purchase_page') ?>
    <?php endif; ?>
  </section>

  <!-- Inquiry toggle button -->
  <button id="toggleInquiryBtn" aria-expanded="false" aria-controls="inquiryForm">
    Have questions? Ask them here!
  </button>

  <!-- Inquiry form (hidden initially) -->
  <section id="inquiryForm" aria-label="Send an inquiry">
    <h2>Send an Inquiry</h2>
    <?php if ($inquirySuccess): ?>
      <div class="success-message">Your inquiry has been sent successfully!</div>
    <?php endif; ?>
    <form method="POST" action="dashboard.php">
      <label for="inquiry_subject">Subject:</label>
      <input type="text" id="inquiry_subject" name="inquiry_subject" required maxlength="255" />

      <label for="inquiry_message">Message:</label>
      <textarea id="inquiry_message" name="inquiry_message" rows="5" required></textarea>

      <button type="submit">Send Inquiry</button>
    </form>
  </section>
</main>

<footer class="site-footer">
  <div class="footer-content">
    <div class="footer-links">
      <ul>
        <li><a href="about.php">About Us</a></li>
        <li><a href="contact.php">Contact</a></li>
        <li><a href="privacy.php">Privacy Policy</a></li>
        <li><a href="terms.php">Terms of Service</a></li>
      </ul>
    </div>
  </div>
</footer>

<script src="js/dashboard.js"></script>

</body>
</html>

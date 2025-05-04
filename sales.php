<?php
session_start();

// DB connection
$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Redirect non-admins to homepage
if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] != 1) {
  header("Location: index.php");
  exit;
}

// --- SALES REPORTS ---

// Total sales per day (last 30 days)
$salesPerDaySql = "
  SELECT DATE(OrderDate) as SaleDate, 
         SUM(TotalCost) as TotalSales, 
         COUNT(OrderID) as OrdersCount
  FROM ORDERTABLE
  WHERE OrderStatus = 'Delivered'
  GROUP BY SaleDate
  ORDER BY SaleDate DESC
  LIMIT 30
";
$salesPerDayResult = $conn->query($salesPerDaySql);

// Best selling products (top 10)
$bestSellersSql = "
  SELECT P.ProductID, P.ProductName, P.ProductImage,
         SUM(OI.Quantity) AS TotalQuantitySold,
         SUM(OI.Subtotal) AS TotalSalesAmount
  FROM ORDERITEMS OI
  JOIN PRODUCT P ON OI.ProductID = P.ProductID
  JOIN ORDERTABLE O ON OI.OrderID = O.OrderID
  WHERE O.OrderStatus = 'Delivered'
  GROUP BY P.ProductID, P.ProductName, P.ProductImage
  ORDER BY TotalQuantitySold DESC
  LIMIT 10
";
$bestSellersResult = $conn->query($bestSellersSql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PHPets Admin - Sales Reports</title>
  <link rel="stylesheet" href="css/admin.css" />
  <link rel="stylesheet" href="css/sales.css" />
</head>
<body>
<header class="admin-header">
  <div class="admin-header-wrapper">
    <h1 class="admin-title">Sales Reports</h1>
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
  <h2 class="section-title">📅 Total Sales - Last 30 Days</h2>
  <?php if ($salesPerDayResult && $salesPerDayResult->num_rows > 0): ?>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Total Sales (₱)</th>
          <th>Number of Orders</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $salesPerDayResult->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['SaleDate']) ?></td>
            <td><?= number_format($row['TotalSales'], 2) ?></td>
            <td><?= intval($row['OrdersCount']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No sales data available.</p>
  <?php endif; ?>

  <h2 class="section-title">🔥 Best Selling Products</h2>
  <?php if ($bestSellersResult && $bestSellersResult->num_rows > 0): ?>
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>Total Quantity Sold</th>
          <th>Total Sales (₱)</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($product = $bestSellersResult->fetch_assoc()): ?>
          <tr>
            <td>
              <img src="images/<?= htmlspecialchars($product['ProductImage']) ?>" alt="<?= htmlspecialchars($product['ProductName']) ?>" class="product-image" />
              <?= htmlspecialchars($product['ProductName']) ?>
            </td>
            <td><?= intval($product['TotalQuantitySold']) ?></td>
            <td><?= number_format($product['TotalSalesAmount'], 2) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No best seller data available.</p>
  <?php endif; ?>
</main>

</body>
</html>

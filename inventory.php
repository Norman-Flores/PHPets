<?php
session_start();

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 1 Jan 2000 00:00:00 GMT");

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

// Handle inventory update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["product_id"]) && isset($_POST["inventory"])) {
  $productID = intval($_POST["product_id"]);
  $inventory = intval($_POST["inventory"]);

  $stmt = $conn->prepare("UPDATE PRODUCT SET Inventory = ? WHERE ProductID = ?");
  $stmt->bind_param("ii", $inventory, $productID);
  $stmt->execute();

  // Redirect to avoid form resubmission
  header("Location: inventory.php");
  exit;
}

// Fetch products ordered by ProductID
$sql = "SELECT p.ProductID, p.ProductName, p.Inventory, c.CategoryName 
        FROM PRODUCT p 
        LEFT JOIN CATEGORY c ON p.CategoryID = c.CategoryID 
        ORDER BY p.ProductID ASC";
$products = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PHPets - Inventory Management</title>

  <link rel="stylesheet" href="css/inventory.css" />
</head>
<body>
  <div class="page-container">
    <header class="admin-header">
      <div class="admin-header-wrapper">
        <h1 class="admin-title">Manage Inventory</h1>
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
      <h1>📦 Inventory Management</h1>

<div class="inventory-filters">
  <div class="filter-buttons">
    <button type="button" class="filter-btn active" data-filter="all">Show All</button>
    <button type="button" class="filter-btn" data-filter="out-of-stock">Out of Stock</button>
    <button type="button" class="filter-btn" data-filter="low-stock">Low Stock (&lt; 10)</button>
  </div>
  <div class="inventory-legend">
    <div class="legend-item out-of-stock-legend">
      <span class="legend-color-box"></span>
      <span>Out of Stock</span>
    </div>
    <div class="legend-item low-stock-legend">
      <span class="legend-color-box"></span>
      <span>Low Stock (&lt; 10)</span>
    </div>
  </div>
</div>


      <?php if ($products && $products->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>Product Name</th>
            <th>Category</th>
            <th>Inventory</th>
            <th>Update</th>
          </tr>
        </thead>
        <tbody>
<?php while ($row = $products->fetch_assoc()): ?>
  <?php
    $rowClass = "";
    if ($row["Inventory"] == 0) {
      $rowClass = "out-of-stock";   // red background
    } elseif ($row["Inventory"] < 10) {
      $rowClass = "low-stock";      // yellow background
    }
  ?>
  <tr class="<?php echo $rowClass; ?>">
    <td data-label="Product Name"><?php echo htmlspecialchars($row["ProductName"]); ?></td>
    <td data-label="Category"><?php echo htmlspecialchars($row["CategoryName"] ?? "Uncategorized"); ?></td>
    <td data-label="Inventory">
      <span class="inventory-text"><?php echo $row["Inventory"]; ?></span>
      <input 
        type="number" 
        name="inventory" 
        class="inventory-input" 
        min="0" 
        value="<?php echo $row["Inventory"]; ?>" 
        style="display:none; width: 60px;"
        form="form-<?php echo $row['ProductID']; ?>"
        required
      >
    </td>
    <td data-label="Update">
      <form method="POST" action="inventory.php" id="form-<?php echo $row['ProductID']; ?>" class="inventory-form" style="margin:0; display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="product_id" value="<?php echo $row["ProductID"]; ?>">
        <button type="button" class="update-btn">Update</button>
        <button type="submit" class="save-btn" style="display:none;">Save</button>
      </form>
    </td>
  </tr>
<?php endwhile; ?>


        </tbody>
      </table>
      <?php else: ?>
        <p>No products found in inventory.</p>
      <?php endif; ?>
    </main>
  </div>

  <script src="js/inventory.js"></script>
</body>
</html>

<?php $conn->close(); ?>

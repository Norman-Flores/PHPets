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


// Handle Create or Update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $productName = $_POST["product_name"];
    $description = $_POST["description"];
    $price = $_POST["price"];
    $inventory = $_POST["inventory"];
    $image = $_POST["product_image"];
    $categoryID = $_POST["category_id"];

    if (!empty($_POST["product_id"])) {
        $productID = $_POST["product_id"];
        $stmt = $conn->prepare("UPDATE PRODUCT SET ProductName=?, Description=?, Price=?, Inventory=?, ProductImage=?, CategoryID=? WHERE ProductID=?");
        $stmt->bind_param("ssdisii", $productName, $description, $price, $inventory, $image, $categoryID, $productID);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO PRODUCT (ProductName, Description, Price, Inventory, ProductImage, CategoryID) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdisi", $productName, $description, $price, $inventory, $image, $categoryID);
        $stmt->execute();
    }
    header("Location: products.php");
    exit;
}

// Handle Delete
if (isset($_GET["delete"])) {
    $deleteID = $_GET["delete"];
    $stmt = $conn->prepare("DELETE FROM PRODUCT WHERE ProductID=?");
    $stmt->bind_param("i", $deleteID);
    $stmt->execute();
    header("Location: products.php");
    exit;
}

// Handle Edit
$editing = false;
$editData = null;
if (isset($_GET["edit"])) {
    $editing = true;
    $editID = $_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM PRODUCT WHERE ProductID=?");
    $stmt->bind_param("i", $editID);
    $stmt->execute();
    $result = $stmt->get_result();
    $editData = $result->fetch_assoc();
}

// Handle Category Filter
$selectedCategory = $_GET["filter_category"] ?? "";

// Fetch categories
$categoryOptions = $conn->query("SELECT * FROM CATEGORY");

// Fetch products
$sql = "SELECT p.ProductID, p.ProductName, p.Description, p.Price, p.Inventory, p.ProductImage, c.CategoryName, p.CategoryID FROM PRODUCT p LEFT JOIN CATEGORY c ON p.CategoryID = c.CategoryID";
if ($selectedCategory !== "") {
    $sql .= " WHERE p.CategoryID = " . intval($selectedCategory);
}
$products = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Products</title>
  <link rel="stylesheet" href="css/admin.css" />
  <link rel="stylesheet" href="css/products.css" />
</head>
<body>

<header class="admin-header">
  <div class="admin-header-wrapper">
    <h1 class="admin-title">Manage Products</h1>
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
<h1>🛍️ Product Management</h1>


<button onclick="toggleForm()">➕ <?php echo $editing ? "Edit Product" : "Add New Product"; ?></button>

<div id="product-form" class="<?php echo $editing ? '' : 'hidden'; ?>">

<h2><?php echo $editing ? "Edit Product" : "Add New Product"; ?></h2>

<form method="POST" action="products.php">
  <?php if ($editing): ?>
    <input type="hidden" name="product_id" value="<?php echo $editData["ProductID"]; ?>">
  <?php endif; ?>
  
  <label>Name: <input type="text" name="product_name" required value="<?php echo $editing ? htmlspecialchars($editData["ProductName"]) : ""; ?>"></label><br>
  <label>Description:<br>
  <textarea name="description" rows="4" cols="50" style="resize: vertical;"><?php echo $editing ? htmlspecialchars($editData["Description"]) : ""; ?></textarea>
</label><br>
<br>
  <label>Price: <input type="number" step="0.01" name="price" required value="<?php echo $editing ? $editData["Price"] : ""; ?>"></label><br>
  <label>Inventory: <input type="number" name="inventory" required value="<?php echo $editing ? $editData["Inventory"] : ""; ?>"></label><br>
  <label>Image URL: <input type="text" name="product_image" value="<?php echo $editing ? htmlspecialchars($editData["ProductImage"]) : ""; ?>"></label><br>
  <label>Category:
    <select name="category_id" required>
      <?php
      $categoryOptions->data_seek(0);
      while ($cat = $categoryOptions->fetch_assoc()) {
        $selected = ($editing && $editData["CategoryID"] == $cat["CategoryID"]) ? "selected" : "";
        echo "<option value=\"{$cat["CategoryID"]}\" $selected>" . htmlspecialchars($cat["CategoryName"]) . "</option>";
      }
      ?>
    </select>
  </label><br>
  <button type="submit"><?php echo $editing ? "Update Product" : "Add Product"; ?></button>
  <?php if ($editing): ?>
    <a href="products.php">Cancel</a>
  <?php elseif (!$editing): ?>
    <a href="products.php">Cancel</a>
  <?php endif; ?>
</form>
</div>

<hr>

<h2>📂 Filter by Category</h2>
<form class="filter-form" method="GET" action="products.php">
  <label>Choose Category:
    <select name="filter_category" onchange="this.form.submit()">
      <option value="">-- All Categories --</option>
      <?php
      $categoryOptions->data_seek(0);
      while ($cat = $categoryOptions->fetch_assoc()) {
        $selected = ($selectedCategory == $cat["CategoryID"]) ? "selected" : "";
        echo "<option value=\"{$cat["CategoryID"]}\" $selected>" . htmlspecialchars($cat["CategoryName"]) . "</option>";
      }
      ?>
    </select>
  </label>
</form>

<h2>📦 Product List</h2>

<table border="1" cellpadding="8" cellspacing="0">
  <tr>
    <th>ID</th>
    <th>Image</th>
    <th>Name</th>
    <th>Description</th>
    <th>Price</th>
    <th>Inventory</th>
    <th>Category</th>
    <th>Actions</th>
  </tr>
  <?php while ($row = $products->fetch_assoc()): ?>
  <tr>
    <td><?php echo $row["ProductID"]; ?></td>
    <td><img src="images/<?php echo $row["ProductImage"]; ?>" alt="Product Image" width="60"></td>
    <td><?php echo htmlspecialchars($row["ProductName"]); ?></td>
    <td><?php echo htmlspecialchars($row["Description"]); ?></td>
    <td>₱<?php echo number_format($row["Price"], 2); ?></td>
    <td><?php echo $row["Inventory"]; ?></td>
    <td><?php echo htmlspecialchars($row["CategoryName"]); ?></td>
    <td>
  <form method="GET" action="products.php" class="action-form">
    <input type="hidden" name="edit" value="<?php echo $row["ProductID"]; ?>">
    <button type="submit" class="btn edit-btn" title="Edit Product">✏️ Edit</button>
  </form>
  <form method="GET" action="products.php" class="action-form" onsubmit="return confirm('Are you sure you want to delete this product?');">
    <input type="hidden" name="delete" value="<?php echo $row["ProductID"]; ?>">
    <button type="submit" class="btn delete-btn" title="Delete Product">🗑️ Delete</button>
  </form>
</td>

  </tr>
  <?php endwhile; ?>
</table>

<script>
function toggleForm() {
  const form = document.getElementById("product-form");
  form.style.display = form.style.display === "none" ? "block" : "none";
}

<?php if ($editing): ?>
document.addEventListener("DOMContentLoaded", function() {
  const form = document.getElementById("product-form");
  form.style.display = "block";
  form.scrollIntoView({ behavior: "smooth", block: "start" });
});
<?php endif; ?>
</script>
</main>
</body>
</html>

<?php $conn->close(); ?>

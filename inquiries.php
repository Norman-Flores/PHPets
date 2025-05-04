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

// Handle inquiry status update (mark as Closed)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["close_inquiry_id"])) {
  $closeId = intval($_POST["close_inquiry_id"]);
  $stmt = $conn->prepare("UPDATE INQUIRIES SET Status = 'Closed' WHERE InquiryID = ?");
  $stmt->bind_param("i", $closeId);
  $stmt->execute();
  $stmt->close();
  header("Location: inquiries.php");
  exit;
}

// Pagination setup
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Get total inquiries count
$totalResult = $conn->query("SELECT COUNT(*) as total FROM INQUIRIES");
$totalRow = $totalResult->fetch_assoc();
$totalInquiries = $totalRow['total'];
$totalPages = ceil($totalInquiries / $perPage);

// Fetch inquiries with user info (using FName, LName)
$sql = "
  SELECT I.InquiryID, I.Subject, I.Message, I.CreatedAt, I.Status,
         U.FName, U.LName, U.Email
  FROM INQUIRIES I
  JOIN USER U ON I.UserID = U.UserID
  ORDER BY I.CreatedAt DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$inquiries = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

function renderPagination($currentPage, $totalPages) {
  if ($totalPages <= 1) return '';
  $html = '<nav class="pagination" aria-label="Inquiries Pagination">';
  if ($currentPage > 1) {
    $html .= '<a href="?page=' . ($currentPage - 1) . '" class="page-link" aria-label="Previous page">&laquo; Prev</a>';
  }
  for ($i = 1; $i <= $totalPages; $i++) {
    if ($i == $currentPage) {
      $html .= '<span class="page-link current" aria-current="page">' . $i . '</span>';
    } else {
      $html .= '<a href="?page=' . $i . '" class="page-link">' . $i . '</a>';
    }
  }
  if ($currentPage < $totalPages) {
    $html .= '<a href="?page=' . ($currentPage + 1) . '" class="page-link" aria-label="Next page">Next &raquo;</a>';
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
  <title>PHPets - Admin Inquiries</title>
  <link rel="stylesheet" href="css/admin.css" />
  <link rel="stylesheet" href="css/inquiries.css" />
</head>
<body>

<header class="admin-header">
  <div class="admin-header-wrapper">
    <h1 class="admin-title">PHPets Admin</h1>
    <nav class="admin-nav">
      <?php
      $links = [
        "admin.php" => "👤 Admin",
        "products.php" => "📦 Products",
        "users.php" => "🧑‍🤝‍🧑 Users",
        "inventory.php" => "📋 Inventory",
        "admin_orders.php" => "🧾 Orders",
        "sales.php" => "📊 View Sales",
        "inquiries.php" => "✉️ Inquiries",
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
  <h1>Customer Inquiries</h1>

  <?php if (empty($inquiries)): ?>
    <p>No inquiries found.</p>
  <?php else: ?>
    <table class="inquiries-table" aria-label="Customer Inquiries">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Subject</th>
          <th>Message</th>
          <th>Created At</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($inquiries as $inq): ?>
          <tr>
            <td><?= htmlspecialchars($inq['InquiryID']) ?></td>
            <td><?= htmlspecialchars($inq['FName'] . ' ' . $inq['LName']) ?></td>
            <td><a href="mailto:<?= htmlspecialchars($inq['Email']) ?>"><?= htmlspecialchars($inq['Email']) ?></a></td>
            <td><?= htmlspecialchars($inq['Subject']) ?></td>
            <td><?= nl2br(htmlspecialchars($inq['Message'])) ?></td>
            <td><?= htmlspecialchars($inq['CreatedAt']) ?></td>
            <td>
              <?php if (strtolower($inq['Status']) === 'open'): ?>
                <span class="status-open">Open</span>
              <?php else: ?>
                <span class="status-closed">Closed</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (strtolower($inq['Status']) === 'open'): ?>
                <form method="POST" class="close-form" onsubmit="return confirm('Mark this inquiry as closed?');">
                  <input type="hidden" name="close_inquiry_id" value="<?= htmlspecialchars($inq['InquiryID']) ?>">
                  <button type="submit" title="Mark as Closed">Close</button>
                </form>
              <?php else: ?>
                &mdash;
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?= renderPagination($page, $totalPages) ?>
  <?php endif; ?>
</main>

</body>
</html>

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

// Handle logout
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "logout") {
    session_unset();
    session_destroy();

    echo "<script>
        alert('You have been logged out.');
        window.location.href = 'auth.php';
    </script>";
    exit;
}

// Determine if user is logged in
$loggedIn = isset($_SESSION["first_name"]) && isset($_SESSION["last_name"]);

// Handle login and register
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    if ($action === "login") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM USER WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row["Password"])) {
            $_SESSION["user_id"] = $row["UserID"];
            $_SESSION["first_name"] = $row["FName"];
            $_SESSION["last_name"] = $row["LName"];
            $_SESSION["email"] = $row["Email"];
            $_SESSION["usertype"] = $row["UserType"];

            if ($row["UserType"] == 1) {
                header("Location: admin.php");
                exit;
            } else {
                header("Location: index.php");
                exit;
            }
        } else {
            echo "<p style='color:red;'>Invalid password.</p>";
        }
    } else {
        echo "<p style='color:red;'>User not found.</p>";
    }
} elseif ($action === "register") {
        $fname = $_POST["firstName"];
        $lname = $_POST["lastName"];
        $address = $_POST["address"];
        $contact = $_POST["contact"];
        $email = $_POST["email"];
        $plainPassword = $_POST["password"];
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);
        $usertype = 2;

        $stmt = $conn->prepare("SELECT * FROM USER WHERE Email = ? OR ContactNumber = ?");
        $stmt->bind_param("ss", $email, $contact);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo "<script>alert('Email or contact number already exists.');</script>";
        } else {
            $stmt = $conn->prepare("INSERT INTO USER (FName, LName, Email, Password, Address, ContactNumber, UserType) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $fname, $lname, $email, $password, $address, $contact, $usertype);

            if ($stmt->execute()) {
                echo "<script>
                    alert('Registration successful!');
                    window.location.href = 'index.php';
                </script>";
            } else {
                echo "<script>alert('Registration failed: " . $stmt->error . "');</script>";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login / Register</title>
  <link rel="stylesheet" href="css/auth.css" />
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
          <button id="authToggle" aria-haspopup="true" aria-expanded="false">My account ▼</button>
          <div class="auth-panel" id="authPanel" role="menu" aria-hidden="true">
            <form method="POST" action="index.php">
              <input type="hidden" name="action" value="login" />
                  <p>Please login or register below.</p>
            </form>
      </div>
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


  <div class="auth-container">
    <div class="auth-box">
      <div class="auth-tabs">
        <button id="loginTab" class="active">Login</button>
        <button id="registerTab">Register</button>
      </div>
      <!-- Login Form -->
      <form id="loginForm" class="auth-form" method="POST" action="auth.php">
        <h2>Login</h2>
        <input type="hidden" name="action" value="login" />
        <input type="email" name="email" placeholder="Email" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
      </form>

      <!-- Register Form -->
<form id="registerForm" class="auth-form hidden" method="POST" action="auth.php">
  <h3>Register</h3>
  <input type="hidden" name="action" value="register" />
  <div class="form-group">
    <input type="text" name="firstName" placeholder="First Name" required />
  </div>
  <div class="form-group">
    <input type="text" name="lastName" placeholder="Last Name" required />
  </div>
  <div class="form-group">
    <input type="text" name="address" placeholder="Address" required />
  </div>
  <div class="form-group">
    <input type="text" name="contact" placeholder="Contact Number" required />
  </div>
  <div class="form-group">
    <input type="email" name="email" placeholder="Email" required />
  </div>
  <div class="form-group">
    <input type="password" name="password" placeholder="Password" required />
  </div>
  <button type="submit">Register</button>
</form>
    </div>
  </div>

  <script src="js/auth.js"></script>
</body>
</html>

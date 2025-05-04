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

if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo "Not logged in";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['product_id'])) {
    $userID = $_SESSION['UserID'];
    $productID = intval($_POST['product_id']);

    // Check if item is already in cart
    $check = $conn->prepare("SELECT * FROM CART WHERE UserID = ? AND ProductID = ?");
    $check->bind_param("ii", $userID, $productID);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Update quantity
        $update = $conn->prepare("UPDATE CART SET InCartQuantity = InCartQuantity + 1 WHERE UserID = ? AND ProductID = ?");
        $update->bind_param("ii", $userID, $productID);
        $update->execute();
    } else {
        // Insert new item
        $insert = $conn->prepare("INSERT INTO CART (UserID, ProductID, InCartQuantity) VALUES (?, ?, 1)");
        $insert->bind_param("ii", $userID, $productID);
        $insert->execute();
    }

    echo "success";
}
?>

<?php
$host = "localhost";
$dbname = "PHPets";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $dbname);

$hashed = password_hash("phpets", PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO USER (FName, LName, Email, Password, Address, ContactNumber, UserType)
VALUES (?, ?, ?, ?, ?, ?, ?)");

$fname = "Group";
$lname = "Four";
$email = "group4@phpets.com";
$address = "Colegio de San Juan de Letran";
$contact = 1234567890;
$usertype = 1;

$stmt->bind_param("ssssssi", $fname, $lname, $email, $hashed, $address, $contact, $usertype);

if ($stmt->execute()) {
  echo "Admin user inserted.";
} else {
  echo "Insert failed: " . $stmt->error;
}

$conn->close();
?>

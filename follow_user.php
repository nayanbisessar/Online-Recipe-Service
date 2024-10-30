<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "config.php";
require_once "session.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['followed_id'])) {
    $followed_id = filter_var($_POST['followed_id'], FILTER_SANITIZE_NUMBER_INT);

    $checkFollowStmt = $db->prepare("SELECT 1 FROM followers WHERE follower_id = ? AND following_id = ?");
    $checkFollowStmt->bind_param("ii", $_SESSION["user_id"], $followed_id);
    $checkFollowStmt->execute();
    $checkFollowStmt->store_result();

    if ($checkFollowStmt->num_rows === 0) {
        $addFollowStmt = $db->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
        $addFollowStmt->bind_param("ii", $_SESSION["user_id"], $followed_id);
        if ($addFollowStmt->execute()) {
            $message = "You are now following this user.";
        } else {
            $message = "Failed to follow the user.";
        }
        $addFollowStmt->close();
    } else {
        $message = "You are already following this user.";
    }
    $checkFollowStmt->close();
} else {
    $message = "Invalid request.";
}

$db->close();

header("Location: ".$_SERVER['HTTP_REFERER']."");
exit;
?>

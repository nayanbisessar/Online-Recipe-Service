<?php
require_once "config.php";
require_once "session.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['followed_id'])) {
    $followed_id = filter_var($_POST['followed_id'], FILTER_SANITIZE_NUMBER_INT);

    // Delete the follow relationship from the database
    $deleteFollowStmt = $db->prepare("DELETE FROM followers WHERE follower_id = ? AND following_id = ?");
    $deleteFollowStmt->bind_param("ii", $_SESSION['user_id'], $followed_id);
    
    if ($deleteFollowStmt->execute()) {
        $message = "Successfully unfollowed the user.";
    } else {
        $message = "Failed to unfollow the user.";
    }
    
    $deleteFollowStmt->close();
} else {
    $message = "Invalid request.";
}

$db->close();

header("Location: ".$_SERVER['HTTP_REFERER']."");
exit;
?>

<?php
require_once "config.php";
require_once "session.php"; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_recipe'])) {
    $recipe_id = filter_var($_POST['recipe_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION["user_id"];

    // Check if the recipe is already saved by the user
    $checkStmt = $db->prepare("SELECT * FROM saved_recipes WHERE user_id = ? AND recipe_id = ?");
    $checkStmt->bind_param("ii", $user_id, $recipe_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows == 0) {

        // Insert a new record into saved_recipes table
        $saveStmt = $db->prepare("INSERT INTO saved_recipes (user_id, recipe_id) VALUES (?, ?)");
        $saveStmt->bind_param("ii", $user_id, $recipe_id);
        $saveStmt->execute();
        $saveStmt->close();
        $message = "Recipe saved successfully.";
    } else {
        $message = "Recipe is already saved.";
    }
    $checkStmt->close();
} else {

    $message = "Invalid request.";
}

header("Location: " . $_SERVER["HTTP_REFERER"] . "?message=" . urlencode($message));
exit;
?>

<?php
require_once "config.php";
require_once "session.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['recipe_id'])) {
    $recipe_id = filter_var($_POST['recipe_id'], FILTER_SANITIZE_NUMBER_INT);
    
    // Delete the saved recipe entry
    $deleteSavedRecipeStmt = $db->prepare("DELETE FROM saved_recipes WHERE user_id = ? AND recipe_id = ?");
    $deleteSavedRecipeStmt->bind_param("ii", $_SESSION["user_id"], $recipe_id);
    if ($deleteSavedRecipeStmt->execute()) {
        $message = "Recipe unsaved successfully.";
    } else {
        $message = "Failed to unsave the recipe.";
    }
    $deleteSavedRecipeStmt->close();
} else {
    $message = "Invalid request.";
}

$db->close();

header("Location: profile.php");
exit;
?>

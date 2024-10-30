<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "config.php";
require_once "session.php"; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['recipe_id'])) {
    $recipe_id = filter_var($_POST['recipe_id'], FILTER_SANITIZE_NUMBER_INT);

    // Delete related recipe ingredients
    $deleteIngredientsStmt = $db->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
    $deleteIngredientsStmt->bind_param("i", $recipe_id);
    $deleteIngredientsStmt->execute();
    $deleteIngredientsStmt->close();

    // Delete related recipe tags
    $deleteTagsStmt = $db->prepare("DELETE FROM recipe_tags WHERE recipe_id = ?");
    $deleteTagsStmt->bind_param("i", $recipe_id);
    $deleteTagsStmt->execute();
    $deleteTagsStmt->close();

    // Delete related ratings
    $deleteRatingsStmt = $db->prepare("DELETE FROM recipe_ratings WHERE recipe_id = ?");
    $deleteRatingsStmt->bind_param("i", $recipe_id);
    $deleteRatingsStmt->execute();
    $deleteRatingsStmt->close();

    // Delete the recipe
    $deleteRecipeStmt = $db->prepare("DELETE FROM recipes WHERE recipe_id = ?");
    $deleteRecipeStmt->bind_param("i", $recipe_id);
    if ($deleteRecipeStmt->execute()) {
        $message = "Recipe deleted successfully.";
        header("Location: index.php");
        exit;
    } else {
        $message = "An error occurred while trying to delete the recipe.";
    }
    $deleteRecipeStmt->close();
} else {
    $message = "Invalid request.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Recipe</title>
</head>
<body>
    <p><?php echo $message; ?></p>
</body>
</html>

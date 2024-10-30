<?php
require_once "config.php";
require_once "session.php";

$message = '';
$recipe_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;


if ($recipe_id) {
    $stmt = $db->prepare("SELECT name, preparation_steps, cooking_time, servings, user_id FROM recipes WHERE recipe_id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($recipe = $result->fetch_assoc()) {

        if ($recipe['user_id'] != $_SESSION["user_id"]) {
            $message = "You do not have permission to edit this recipe.";
            $recipe = null;
        }
    } else {
        $message = "Recipe not found.";
    }
    $stmt->close();
}


$stmt = $db->query("SELECT * FROM tags");
$tags = $stmt->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$current_tags = [];
if ($recipe_id) {
    $stmt = $db->prepare("SELECT tag_id FROM recipe_tags WHERE recipe_id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $current_tags[] = $row['tag_id'];
    }
    $stmt->close();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_recipe'])) {

    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $preparation_steps = filter_var($_POST['preparation_steps'], FILTER_SANITIZE_STRING);
    $cooking_time = filter_var($_POST['cooking_time'], FILTER_VALIDATE_INT);
    $servings = filter_var($_POST['servings'], FILTER_VALIDATE_INT);


    $stmt = $db->prepare("UPDATE recipes SET name = ?, preparation_steps = ?, cooking_time = ?, servings = ? WHERE recipe_id = ? AND user_id = ?");
    $stmt->bind_param("ssiiii", $name, $preparation_steps, $cooking_time, $servings, $recipe_id, $_SESSION["user_id"]);
    if ($stmt->execute()) {
        $message = "Recipe updated successfully.";


        $db->query("DELETE FROM recipe_tags WHERE recipe_id = $recipe_id");
        if(isset($_POST['tags']) && is_array($_POST['tags'])) {
            foreach($_POST['tags'] as $tag_id) {
                $db->query("INSERT INTO recipe_tags (recipe_id, tag_id) VALUES ($recipe_id, $tag_id)");
            }
        }

        header("Location: recipe.php?id=" . $recipe_id);
        exit; 
    } else {
        $message = "Failed to update recipe.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./style/style.css">
    <title>Edit Recipe</title>
</head>
<body>
    <h2>Edit Recipe</h2>
    <?php if (!empty($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <?php if ($recipe): ?>
        <form action="edit_recipe.php?id=<?php echo $recipe_id; ?>" method="post">
            <label for="name">Recipe Name:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($recipe['name']); ?>" required>
            
            <label for="preparation_steps">Preparation Steps:</label>
            <textarea id="preparation_steps" name="preparation_steps" required><?php echo htmlspecialchars($recipe['preparation_steps']); ?></textarea>
            
            <label for="cooking_time">Cooking Time (in minutes):</label>
            <input type="number" id="cooking_time" name="cooking_time" value="<?php echo htmlspecialchars($recipe['cooking_time']); ?>" required>
            
            <label for="servings">Servings:</label>
            <input type="number" id="servings" name="servings" value="<?php echo htmlspecialchars($recipe['servings']); ?>" required>
            
            <label>Select Tags:</label><br>
            <?php foreach($tags as $tag): ?>
                <input type="checkbox" name="tags[]" value="<?php echo $tag['tag_id']; ?>" <?php if (in_array($tag['tag_id'], $current_tags)) echo 'checked'; ?>> <?php echo $tag['name']; ?><br>
            <?php endforeach; ?>
            
            <button type="submit" name="update_recipe">Update Recipe</button>
        </form>
    <?php endif; ?>
</body>
</html>

<?php
require_once "config.php"; 
require_once "session.php"; 

$recipe_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rate_recipe_id'])) {
    $recipe_id = filter_var($_POST['rate_recipe_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION["user_id"];
    $rating = $_POST['rating']; 

    $ratingStmt = $db->prepare("INSERT INTO recipe_ratings (recipe_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?");
    $ratingStmt->bind_param("iiid", $recipe_id, $user_id, $rating, $rating);
    $ratingStmt->execute();

    // Recalculate average rating
    $averageRatingStmt = $db->prepare("SELECT IFNULL(AVG(rating), 0) AS average_rating FROM recipe_ratings WHERE recipe_id = ?");
    $averageRatingStmt->bind_param("i", $recipe_id);
    $averageRatingStmt->execute();
    $averageRatingResult = $averageRatingStmt->get_result();
    $averageRating = $averageRatingResult->fetch_assoc()['average_rating'];
    $averageRatingStmt->close();
}

$recipeStmt = $db->prepare("SELECT name, preparation_steps, cooking_time, user_id, image_url FROM recipes WHERE recipe_id = ?");
$recipeStmt->bind_param("i", $recipe_id);
$recipeStmt->execute();
$recipeResult = $recipeStmt->get_result();
$recipe = $recipeResult->fetch_assoc();
$recipeStmt->close();

$isUserOwner = ($recipe['user_id'] == $_SESSION["user_id"]);

$ingredientsStmt = $db->prepare("SELECT ingredients.name, recipe_ingredients.quantity FROM recipe_ingredients JOIN ingredients ON recipe_ingredients.ingredient_id = ingredients.ingredient_id WHERE recipe_id = ?");
$ingredientsStmt->bind_param("i", $recipe_id);
$ingredientsStmt->execute();
$ingredientsResult = $ingredientsStmt->get_result();
$ingredients = $ingredientsResult->fetch_all(MYSQLI_ASSOC);
$ingredientsStmt->close();

$tagsStmt = $db->prepare("SELECT tags.name FROM tags JOIN recipe_tags ON tags.tag_id = recipe_tags.tag_id WHERE recipe_tags.recipe_id = ?");
$tagsStmt->bind_param("i", $recipe_id);
$tagsStmt->execute();
$tagsResult = $tagsStmt->get_result();
$tags = $tagsResult->fetch_all(MYSQLI_ASSOC);
$tagsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./style/style.css">
    <title>ORS - Recipe Page</title>
</head>
<body>
   <div id="topBar" style="height:40px">
        <a style="display: inline;" id="name" href="index.php">ORS</a>
        <a id="topImg" style="display: inline; float: right; height:inherit;" href="profile.php"><img style="height:inherit;"src="media/user.png"></a>
    </div>

    <p style="text-align:center;font-size: 3em;"><?php echo htmlspecialchars($recipe['name']); ?></p>
    <div class="imgCont">
    <?php if (!empty($recipe['image_url'])): ?>
        <img id="recFoodHead" src="<?php echo htmlspecialchars($recipe['image_url']); ?>" alt="Recipe Image">
    <?php else: ?>
        <img id="recFoodHead" src="media/placeholderFood.jpg" alt="Recipe Image">
    <?php endif; ?>
    </div>

    <div id="recmain">
        <div class="section" id="ingredients">
            <h1>Ingredients</h1>
            <ul>
                <?php foreach ($ingredients as $ingredient): ?>
                    <li><?php echo htmlspecialchars($ingredient['quantity']) . ' ' . htmlspecialchars($ingredient['name']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="section" id="instructions">
            <h1>Directions</h1>
            <?php
            $pattern = '/(?<=\.)\s+(?=\d+\.)/';
            $steps = preg_split($pattern, $recipe['preparation_steps'], -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($steps)) {
                foreach ($steps as $step) {
                    echo nl2br(htmlspecialchars($step)) . "<br><br>";
                }
            } else {
                echo "<p>No preparation steps provided.</p>";
            }
            ?>
        </div>
        <?php if ($isUserOwner): ?>
            <div class="edit-delete-buttons">
                <a href="edit_recipe.php?id=<?php echo $recipe_id; ?>" class="btn">Edit</a>
                <form action="delete_recipe.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this recipe?');">
                    <input type="hidden" name="recipe_id" value="<?php echo $recipe_id; ?>">
                    <input type="submit" value="Delete" class="btn">
                </form>
            </div>
        <?php endif; ?>

        <div class="section" id="tags">
            <h1>Tags</h1>
            <?php foreach ($tags as $tag): ?>
                <div class="tag"><?php echo htmlspecialchars($tag['name']); ?></div>
            <?php endforeach; ?>
        </div>

        <?php if (isset($averageRating)): ?>
            <div class="section" id="average-rating">
                <h1>Average Rating</h1>
                <p><?php echo number_format($averageRating, 1); ?>/5</p>
            </div>
        <?php endif; ?>


        <div class="section" id="rate-recipe">
            <h1>Rate This Recipe</h1>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $recipe_id); ?>" method="POST">
                <input type="hidden" name="rate_recipe_id" value="<?php echo $recipe_id; ?>">
                <select name="rating">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
                <button type="submit">Rate</button>
            </form>
        </div>
        <form action="save_recipe.php" method="POST">
            <input type="hidden" name="recipe_id" value="<?php echo $recipe_id; ?>">
            <button type="submit" name="save_recipe">Save Recipe</button>
        </form>
        <form action="unsave_recipe.php" method="POST">
            <input type="hidden" name="recipe_id" value="<?php echo $recipe_id; ?>">
            <button type="submit" name="unsave_recipe">Unsave Recipe</button>
        </form>
        <form action="follow_user.php" method="POST">
            <input type="hidden" name="followed_id" value="<?php echo $recipe['user_id']; ?>">
            <button type="submit" name="follow_user">Follow User</button>
        </form>
        <form action="unfollow_user.php" method="POST">
            <input type="hidden" name="followed_id" value="<?php echo $recipe['user_id']; ?>">
            <button type="submit" name="unfollow_user">Unfollow User</button>
        </form>
    </div>


    <script>
        function toggleNav() {
            var nav = document.getElementById("mySidenav");
            if (nav.style.width === "250px") {
                nav.style.width = "0";
            } else {
                nav.style.width = "250px";
            }
        }
    </script>
</body>
</html>

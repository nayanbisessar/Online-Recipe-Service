<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "config.php";
require_once "session.php"; 

$message = "";

// Update Login Information
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);


    if (password_verify($confirm_password, $hashed_password)) {
        $stmt = $db->prepare("UPDATE users SET email = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $email, $hashed_password, $_SESSION["user_id"]);
        if ($stmt->execute()) {
            $message = "Login information updated successfully.";
        } else {
            $message = "Failed to update login information.";
        }
        $stmt->close();
    } else {
        $message = "Password and confirm password do not match.";
    }
}

// Update Preferences
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_preferences'])) {
    $dietary_preferences = filter_var($_POST['dietary_preferences'], FILTER_SANITIZE_STRING);
    $cooking_preferences = filter_var($_POST['cooking_preferences'], FILTER_SANITIZE_STRING);

    $stmt = $db->prepare("UPDATE users SET dietary_preferences = ?, cooking_preferences = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $dietary_preferences, $cooking_preferences, $_SESSION["user_id"]);
    if($stmt->execute()) {
        $message = "Preferences updated successfully.";
    } else {
        $message = "Failed to update preferences.";
    }
    $stmt->close();
}

//Add a Recipe
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_recipe'])) {
    $name = trim($_POST['name']);
    $preparation_steps = $_POST['preparation_steps'];
    $cooking_time = filter_var($_POST['cooking_time'], FILTER_VALIDATE_INT);
    $servings = filter_var($_POST['servings'], FILTER_VALIDATE_INT);
    $image_url = isset($_POST['image_url']) ? filter_var($_POST['image_url'], FILTER_VALIDATE_URL) : null; 

    $prep_steps_array = explode("\n", $preparation_steps);
    $valid_prep_steps = true;
    foreach ($prep_steps_array as $step) {
        $step = trim($step);
        if (!empty($step)) {
            if (!preg_match('/^\d+\.\s+.+$/', $step)) {
                $valid_prep_steps = false;
                break;
            }
        }
    }

    if ($cooking_time <= 0 || $servings <= 0) {
        $message .= " Cooking time and servings must be positive, non-zero numbers.";
    } else if (!$valid_prep_steps) { 
        $message .= " Preparation steps must be in the format: 1. (step 1), 2. (step 2), etc.";
    } else {
        try {
            $db->begin_transaction();

            $stmt = $db->prepare("INSERT INTO recipes (name, preparation_steps, cooking_time, servings, user_id, image_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiis", $name, $preparation_steps, $cooking_time, $servings, $_SESSION["user_id"], $image_url);
            $stmt->execute();
            $recipe_id = $stmt->insert_id;
            $stmt->close();


            processTags($db, $recipe_id, $_POST['tags']);

   
            processIngredients($db, $recipe_id, $_POST['ingredient_names'], $_POST['ingredient_quantities']);


            $db->commit();
            $message .= " Recipe added successfully.";
        } catch (mysqli_sql_exception $e) {
            $db->rollback()
            $message .= " Error adding recipe: " . $e->getMessage();
        }
    }
}

// function to process Tags
function processTags($db, $recipe_id, $tags) {
    if (!isset($tags) || !is_array($tags)) {
        return;
    }


    $stmt = $db->prepare("INSERT INTO recipe_tags (recipe_id, tag_id) VALUES (?, ?)");

    foreach ($tags as $tag_id) {
        $tag_id = (int)$tag_id;
        if ($tag_id > 0) {
            $stmt->bind_param("ii", $recipe_id, $tag_id);
            try {
                $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                echo "Error inserting tag with ID $tag_id for recipe ID $recipe_id: " . $e->getMessage() . "<br>";
            }
        }
    }

    $stmt->close();
}

//function to process Ingredients
function processIngredients($db, $recipe_id, $ingredient_names, $ingredient_quantities) {
    if (!isset($ingredient_names) || !is_array($ingredient_names) || !isset($ingredient_quantities) || !is_array($ingredient_quantities)) {

        return;
    }


    $ingredient_stmt = $db->prepare("INSERT INTO ingredients (name) VALUES (?) ON DUPLICATE KEY UPDATE ingredient_id=LAST_INSERT_ID(ingredient_id), name=VALUES(name)");
    

    $recipe_ingredient_stmt = $db->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity) VALUES (?, LAST_INSERT_ID(), ?)");

    foreach ($ingredient_names as $index => $name) {
        $quantity = $ingredient_quantities[$index];

        $ingredient_stmt->bind_param("s", $name);
        $ingredient_stmt->execute();

        $recipe_ingredient_stmt->bind_param("is", $recipe_id, $quantity);
        $recipe_ingredient_stmt->execute();
    }

    $ingredient_stmt->close();
    $recipe_ingredient_stmt->close();
}


// Fetch user information
$stmt = $db->prepare("SELECT email, dietary_preferences, cooking_preferences FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
if($user = $result->fetch_assoc()) {
    $user_email = $user['email'];
    $dietary_preferences = $user['dietary_preferences'];
    $cooking_preferences = $user['cooking_preferences'];
} else {
    $message = "User not found.";
}
$stmt->close();

// Fetch recipes uploaded by the user
$stmt = $db->prepare("SELECT recipe_id, name, cooking_time, image_url FROM recipes WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$recipe_result = $stmt->get_result();
$stmt->close();

// Fetch saved recipes for the user
$stmt = $db->prepare("SELECT recipes.recipe_id, recipes.name, recipes.cooking_time, recipes.image_url FROM saved_recipes JOIN recipes ON saved_recipes.recipe_id = recipes.recipe_id WHERE saved_recipes.user_id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$saved_recipes_result = $stmt->get_result();
$stmt->close();

// Fetch all tags
$stmt = $db->query("SELECT * FROM tags");
$tags = $stmt->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch emails of users you are following
$stmt = $db->prepare("SELECT users.email FROM users JOIN followers ON users.user_id = followers.following_id WHERE followers.follower_id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$following_emails_result = $stmt->get_result();
$stmt->close();

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./style/style.css">
    <title>ORS - Profile</title>
</head>
<body>
    <div id="topBar">
        <a style="display: inline;" id="name" href="index.php">ORS</a>
        <a style ="display: inline; float:right;" href="logout.php" class="btn" role="button">Log Out</a>
    </div>
    
    <?php if(!empty($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <div class = "wrapper1" style = "display: flex;flex-wrap: wrap;gap: 20px;">
        <div id = "logInInfo" style="  background-color: #fff; border-radius: 8px;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 20px;transition: transform 0.3s ease;">
            <h2>Update Login Information</h2>
            <form action="profile.php" method="post">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <button type="submit" name="update_login">Update Login</button>
            </form>
        </div>
        <div id = "updatePref" style="  background-color: #fff; border-radius: 8px;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 20px;transition: transform 0.3s ease;">
            <h2>Update Preferences</h2>
            <form action="profile.php" method="post">
                <label for="dietary_preferences">Dietary Preferences:</label>
                <input type="text" id="dietary_preferences" name="dietary_preferences" value="<?php echo htmlspecialchars($dietary_preferences); ?>">
                <label for="cooking_preferences">Cooking Preferences:</label>
                <input type="text" id="cooking_preferences" name="cooking_preferences" value="<?php echo htmlspecialchars($cooking_preferences); ?>">
                <button type="submit" name="update_preferences">Update Preferences</button>
            </form>
        </div>
    <div><br><br><br>
    
        
        <div class="recipeCards" style="  background-color: #fff; border-radius: 8px;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 20px;transition: transform 0.3s ease;">
        <h2>Recipes Uploaded</h2>
            <?php if ($recipe_result->num_rows > 0): ?>
                <?php while ($recipe = $recipe_result->fetch_assoc()): ?>
                    <div class="recCard">
                        <a href="recipe.php?id=<?php echo $recipe['recipe_id']; ?>">
                            <div class="recImg" style="width:fit-content">
                                <img src="<?php echo !empty($recipe['image_url']) ? htmlspecialchars($recipe['image_url']) : 'media/placeholderFood.jpg'; ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>">
                            </div>
                            <hr>
                            <h3 class="recName"><?php echo htmlspecialchars($recipe['name']); ?></h3>
                            <hr>
                            <div class="recBot">
                                <h4 class="recTime"><?php echo htmlspecialchars($recipe['cooking_time']); ?> mins</h4>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No recipes uploaded.</p>
            <?php endif; ?>
        </div>

   
    
    <div class="recipeCards" style="  background-color: #fff; border-radius: 8px;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 20px;transition: transform 0.3s ease;" >
	<h2>Saved Recipes</h2>
        <?php if ($saved_recipes_result->num_rows > 0): ?>
            <?php while ($saved_recipe = $saved_recipes_result->fetch_assoc()): ?>
                <div class="recCard">
                    <a href="recipe.php?id=<?php echo $saved_recipe['recipe_id']; ?>">
                        <div class="recImg" style="width:fit-content">
                            <img src="<?php echo !empty($saved_recipe['image_url']) ? htmlspecialchars($saved_recipe['image_url']) : 'media/placeholderFood.jpg'; ?>" alt="<?php echo htmlspecialchars($saved_recipe['name']); ?>">
                        </div>
                        <hr>
                        <h3 class="recName"><?php echo htmlspecialchars($saved_recipe['name']); ?></h3>
                        <hr>
                        <div class="recBot">
                            <h4 class="recTime"><?php echo htmlspecialchars($saved_recipe['cooking_time']); ?> mins</h4>
                        </div>
                    </a>
                    <form action="unsave_recipe.php" method="POST">
                        <input type="hidden" name="recipe_id" value="<?php echo $saved_recipe['recipe_id']; ?>">
                        <button type="submit" name="unsave_recipe">Unsave</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No saved recipes.</p>
        <?php endif; ?>
    </div>

    <div id = "emails" style="  background-color: #fff; border-radius: 8px;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 20px;transition: transform 0.3s ease;">
        <h2>Emails of Users You Are Following</h2>
        <ul>
            <?php while ($email_row = $following_emails_result->fetch_assoc()): ?>
                <li><?php echo htmlspecialchars($email_row['email']); ?></li>
            <?php endwhile; ?>
        </ul>
    </div>


	<div id="addRec" style="background-color: #fff; border-radius: 8px;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 20px;transition: transform 0.3s ease;">
    <h2>Add a Recipe</h2>
    <form action="profile.php" method="post">
        <label for="name">Recipe Name:</label>
        <input type="text" id="name" name="name" required>
        <label for="preparation_steps">Preparation Steps:</label>
        <textarea id="preparation_steps" name="preparation_steps" required></textarea>
        <label for="cooking_time">Cooking Time (in minutes):</label>
        <input type="number" id="cooking_time" name="cooking_time" required>
        <label for="servings">Servings:</label>
        <input type="number" id="servings" name="servings" required>
        <label for="image_url">Image URL:</label>
        <input type="text" id="image_url" name="image_url">
        
        <div>
            <h3>Ingredients</h3>
            <div id="ingredients">
                <div>
                    <input type="text" name="ingredient_names[]" placeholder="Ingredient name" required>
                    <input type="text" name="ingredient_quantities[]" placeholder="Quantity (e.g., 2 cups)" required>
                </div>
            </div>
            <button type="button" onclick="addIngredientField()">Add Another Ingredient</button>
        </div>
        
        <label>Select Tags:</label><br>
        <?php foreach($tags as $tag): ?>
            <input type="checkbox" name="tags[]" value="<?php echo $tag['tag_id']; ?>"> <?php echo $tag['name']; ?><br>
        <?php endforeach; ?>
        
        <button type="submit" name="submit_recipe">Submit Recipe</button>
    </form>
</div>

<script>
function addIngredientField() {
    const container = document.getElementById('ingredients');
    const inputHTML = `
        <div>
            <input type="text" name="ingredient_names[]" placeholder="Ingredient name" required>
            <input type="text" name="ingredient_quantities[]" placeholder="Quantity (e.g., 2 cups)" required>
        </div>`;
    container.insertAdjacentHTML('beforeend', inputHTML);
}
</script>

</body>
</html>

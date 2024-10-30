<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "config.php";
require_once "session.php";

// Function to get recipe ID from the database based on recipe name
function getRecipeIdFromDatabase($recipeName) {
    global $db; 

 
    $stmt = $db->prepare("SELECT recipe_id FROM recipes WHERE name = ?");
    $stmt->bind_param("s", $recipeName);
    $stmt->execute();
    $stmt->bind_result($recipeId);


    $stmt->fetch();


    $stmt->close();

    return $recipeId;
}

// Function to retrieve the user ID of the current session
function getCurrentUserId() {
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    } else {
        return null;
    }
}

// Function to insert a recipe with its ingredients into the database
function insertRecipe($recipeInfo, $user_id, $category_id = null) {
    global $db;

    $name = $recipeInfo['title'];
    $preparation_steps = $recipeInfo['instructions'];
    $cooking_time = $recipeInfo['readyInMinutes'];
    $servings = $recipeInfo['servings'];
    $image_url = $recipeInfo['image'];

    if (empty($preparation_steps)) {
        return false;
    }
    $checkStmt = $db->prepare("SELECT recipe_id FROM recipes WHERE name = ? AND preparation_steps = ?");
    $checkStmt->bind_param("ss", $name, $preparation_steps);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo "Recipe with the same name and preparation steps already exists.";
        return false;
    }

    $ingredients = $recipeInfo['extendedIngredients'];

    $stmt = $db->prepare("INSERT INTO recipes (name, preparation_steps, cooking_time, servings, user_id, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssiiiss", $name, $preparation_steps, $cooking_time, $servings, $user_id, $category_id, $image_url);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $recipe_id = $db->insert_id;

        foreach ($ingredients as $ingredient) {
            $ingredient_name = $ingredient['name'];
            $ingredient_stmt = $db->prepare("INSERT INTO ingredients (name) VALUES (?)");
            $ingredient_stmt->bind_param("s", $ingredient_name);
            $ingredient_stmt->execute();


            if ($ingredient_stmt->affected_rows > 0) {
                $ingredient_id = $db->insert_id; 


                $quantity = $ingredient['amount'] . ' ' . $ingredient['unit'];
                $recipe_ingredient_stmt = $db->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity) VALUES (?, ?, ?)");
                $recipe_ingredient_stmt->bind_param("iis", $recipe_id, $ingredient_id, $quantity);
                $recipe_ingredient_stmt->execute();


                if (!$recipe_ingredient_stmt->affected_rows > 0) {
                    echo "Failed to insert recipe ingredient association for '{$ingredient_name}' into the database.";
                    return false;
                }
            } else {
                echo "Failed to insert ingredient '{$ingredient_name}' into the database.";
                return false;
            }
        }

        echo "Recipe, ingredients, and recipe_ingredients inserted successfully.";
        return true;
    } else {
        echo "Error inserting recipe.";
        return false;
    }

    $stmt->close();
}


// Function to make cURL request to get recipe information
function getRecipeInformation($recipeId) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://spoonacular-recipe-food-nutrition-v1.p.rapidapi.com/recipes/$recipeId/information",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "X-RapidAPI-Host: spoonacular-recipe-food-nutrition-v1.p.rapidapi.com",
            "X-RapidAPI-Key: 13129f7262mshbb2cbf40a3cb842p13691bjsn5244d10df76d"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return "cURL Error #:" . $err;
    } else {
        $recipeInfo = json_decode($response, true);

        if (is_array($recipeInfo)) {
            $user_id = getCurrentUserId();
            $recipe_id = insertRecipe($recipeInfo, $user_id);
            return $recipeInfo;
        } else {
            return null;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rate_recipe_id'])) {
    $recipe_id = filter_var($_POST['rate_recipe_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION["user_id"];
    $rating = $_POST['rating'];

    $ratingStmt = $db->prepare("INSERT INTO recipe_ratings (recipe_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?");
    $ratingStmt->bind_param("iiid", $recipe_id, $user_id, $rating, $rating);
    $ratingStmt->execute();
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$search = trim($search);

$filterFollowedUsers = isset($_GET['filter_followed_users']) && $_GET['filter_followed_users'] == '1';

// Function to get the IDs of users that the current user is following
function getFollowedUserIds($userId) {
    global $db;

    $followedUserIds = array();

    $stmt = $db->prepare("SELECT following_id FROM followers WHERE follower_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($followedUserId);

    while ($stmt->fetch()) {
        $followedUserIds[] = $followedUserId;
    }

    $stmt->close();

    return $followedUserIds;
}

$sql = "SELECT r.recipe_id, r.name, r.cooking_time, r.image_url, 
        IFNULL(AVG(rr.rating), 0) AS average_rating 
        FROM recipes r 
        LEFT JOIN recipe_ratings rr ON r.recipe_id = rr.recipe_id";

$searchCondition = !empty($search) ? "r.name LIKE '%$search%'" : "";
$currentUserId = getCurrentUserId();
$filterCondition = $filterFollowedUsers ? "r.user_id IN (SELECT following_id FROM followers WHERE follower_id = $currentUserId)" : "";

$whereClause = "";
if (!empty($searchCondition) && !empty($filterCondition)) {
    $whereClause = "WHERE $searchCondition AND $filterCondition";
} elseif (!empty($searchCondition)) {
    $whereClause = "WHERE $searchCondition";
} elseif (!empty($filterCondition)) {
    $whereClause = "WHERE $filterCondition";
}

$sql .= !empty($whereClause) ? " $whereClause" : "";

$sql .= " GROUP BY r.recipe_id 
          ORDER BY r.created_at DESC";

$result = $db->query($sql);

if ($result->num_rows === 0) {

    $encoded_search = urlencode($search);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://spoonacular-recipe-food-nutrition-v1.p.rapidapi.com/recipes/complexSearch?query=$encoded_search&number=10",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 1,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "X-RapidAPI-Host: spoonacular-recipe-food-nutrition-v1.p.rapidapi.com",
            "X-RapidAPI-Key: 13129f7262mshbb2cbf40a3cb842p13691bjsn5244d10df76d"
        ],
    ]);

    $apiResponse = curl_exec($curl);
    $apiError = curl_error($curl);

    curl_close($curl);

    if ($apiError) {
        echo "cURL Error: $apiError";
    } else {
        $apiData = json_decode($apiResponse, true);

        if (isset($apiData['results']) && !empty($apiData['results'])) {
            $apiRecipes = $apiData['results'];

            foreach ($apiRecipes as $recipe) {

                $recipeInfo = getRecipeInformation($recipe['id']);
                if ($recipeInfo) {
                    $user_id = getCurrentUserId();
                    insertRecipe($recipeInfo, $user_id);
                }
            }
        } else {
            echo "<p>No recipes found.</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./style/style.css">
    <title>ORS - Home</title>
</head>
<body>
    <div id="main">

    <div id="topBar" style="height:40px">
        <a style="display: inline;" id="name" href="index.php">ORS</a>
        <a id="topImg" style="display: inline; float: right; height:inherit;" href="profile.php"><img style="height:inherit;"src="media/user.png"></a>
    </div>
        <h1>Welcome to the Online Recipe Service</h1>

        <form style = "text-align:center" method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">

        
            <input type="text" name="search" placeholder="Search recipes..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit">Search</button>


            <label>
                <input type="checkbox" name="filter_followed_users" value="1" <?php echo isset($_GET['filter_followed_users']) ? 'checked' : ''; ?>>
                Show recipes from followed users only
            </label>
            <button type="submit">Apply Filter</button>
        </form>

        <div class="recipeCards">
            <?php if ($result->num_rows > 0 || isset($apiRecipes)): ?>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="recCard">
                            <a href="recipe.php?id=<?php echo $row['recipe_id']; ?>">
                                <div class="recImg" style="width:fit-content">
                                    <img src="<?php echo !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'media/placeholderFood.jpg'; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                                </div>
                                <hr>
                                <h3 class="recName"><?php echo htmlspecialchars($row['name']); ?></h3>
                                <hr>
                                <div class="recBot">
                                    <div class="likes">
                                        <span>Average Rating: <?php echo number_format($row['average_rating'], 1); ?></span>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                                            <input type="hidden" name="rate_recipe_id" value="<?php echo $row['recipe_id']; ?>">
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
                                    <?php if (isset($row['cooking_time'])): ?>
                                        <h4 class="recTime"><?php echo htmlspecialchars($row['cooking_time']); ?> mins</h4>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>

                <?php if (isset($apiRecipes)): ?>
                    <?php foreach ($apiRecipes as $recipe): ?>
                        <?php
                        $recipe_id = getRecipeIdFromDatabase($recipe['title']);
                        if ($recipe_id === null) {
                            continue;
                        }
                        ?>
                        <a href="recipe.php?id=<?php echo $recipe_id; ?>">
                            <div class="recCard">
                                <?php if (isset($recipe['sourceUrl'])): ?>
                                    <a href="<?php echo $recipe['sourceUrl']; ?>" target="_blank">
                                <?php endif; ?>
                                        <div class="recImg" style="width:fit-content">
                                            <img src="<?php echo $recipe['image']; ?>" alt="<?php echo $recipe['title']; ?>">
                                        </div>
                                        <hr>
                                        <h3 class="recName"><?php echo $recipe['title']; ?></h3>
                                        <hr>
                                        <div class="recBot">
                                            <div class="likes">
                                                <span>Average Rating: N/A</span>
                                            </div>
                                            <?php if (isset($recipe['readyInMinutes'])): ?>
                                                <h4 class="recTime"><?php echo $recipe['readyInMinutes']; ?> mins</h4>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <p>No recipes found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php

require_once "config.php";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])){

    $email = trim($_POST['email']); 
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $dietary_preferences = isset($_POST['dietary_preferences']) ? trim($_POST['dietary_preferences']) : null; 
    $cooking_preferences = isset($_POST['cooking_preferences']) ? trim($_POST['cooking_preferences']) : null; 
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    if($query = $db->prepare("SELECT * FROM users WHERE email = ?")){ 
        $error = '';
        $query->bind_param('s', $email);
        $query->execute();
        $query->store_result(); 
        if($query->num_rows > 0){
            $error .= '<p class ="error"> That email is already registered!</p>';
        } else {

            if(empty($confirm_password)){
                $error .= "<p class = 'error'> Please Confirm Password. </p>";
            } else {
                if($password != $confirm_password){
                    $error .= "<p class='error'> Passwords do not match. </p>";
                } else {
                    if(empty($error)){
                        $insertQuery = $db->prepare("INSERT INTO users (email, password, dietary_preferences, cooking_preferences, created_at) VALUES (?, ?, ?, ?, NOW());");
                        $insertQuery->bind_param("ssss", $email, $password_hash, $dietary_preferences, $cooking_preferences);
                        $result = $insertQuery->execute();
                        if($result){
                            if (session_status() == PHP_SESSION_NONE) {
                                session_start();
                            }
                            $_SESSION["user_id"] = $db->insert_id; 
                            $_SESSION["email"] = $email; 
                            

                            header("location: profile.php");
                            exit;
                        } else {
                            $error .= "<p class= 'error'> There was an error with your registration. </p>";
                        }
                    }
                }
            }
        }
        $query->close();
        if(isset($insertQuery)) {
            $insertQuery->close();
        }
    }
    mysqli_close($db);
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Online Recipe Service Registration</title>
    <link rel="stylesheet" href="./style/login.css">
</head>

<body>
    <div class="main">
        <h1>ORS</h1>
        <h3>Create your account</h3>
        <form action="" method="post">
            <label for="email">
                Email:
            </label>
            <input type="email" id="email" name="email" placeholder="Enter your Email" required>

            <label for="password">
                Password:
            </label>
            <input type="password" id="password" name="password" placeholder="Enter your Password" required>

            <label for="confirm_password">
                Confirm Password:
            </label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>

            <label for="dietary_preferences">
                Dietary Preferences (Optional):
            </label>
            <input type="text" id="dietary_preferences" name="dietary_preferences" placeholder="Enter your Dietary Preferences">

            <label for="cooking_preferences">
                Cooking Preferences (Optional):
            </label>
            <input type="text" id="cooking_preferences" name="cooking_preferences" placeholder="Enter your Cooking Preferences">

            <div class="wrap">
                <button type="submit" name="submit">
                    Submit
                </button>
            </div>
        </form>
        <p>Already have an Account? 
            <a href="login.php" style="text-decoration: none;">
                Login here
            </a>
        </p>
    </div>
</body>

</html>

<?php
	session_start(); 

  
    if(isset($_SESSION["user_id"])){
        header("location: profile.php");
        exit;
    }
    require_once "config.php";



    $error = '';
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])){
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if(empty($email)){
            $error .= '<p class="error">Please enter your email.</p>';
        }

        if(empty($password)){
            $error .= '<p class="error">Please enter your password.</p>';
        }

        if(empty($error)){
            if($query = $db->prepare("SELECT * FROM users WHERE email = ?")){
                $query->bind_param('s', $email);
                $query->execute();
                $result = $query->get_result();
                $row = $result->fetch_assoc();
                
                if($row){
                    if(password_verify($password, $row['password'])){
						$_SESSION["user_id"] = $row['user_id'];
                        $_SESSION["user"] = $row;


                        header("location: profile.php");
                        exit;
                    }else{
                        $error .= '<p class="error">The password you entered is not valid.</p>';
                    }
                }else{
                    $error .= '<p class="error">No account found with that email.</p>';
                }
                $query->close();
            }
        }
        mysqli_close($db);
    }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Online Recipe Service</title>
    <link rel="stylesheet" href="./style/login.css">
</head>
<body>
    <div class="main">
        <h1>ORS</h1>
        <h3>Login to your account</h3>
        <form action="" method="post">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your Email" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your Password" required>

            <div class="wrap form-group">
                <input type="submit" name="submit" class="btn btn-primary" value="Submit">
            </div>
        </form>
        <p>Not registered? <a href="signin.php" style="text-decoration: none;">Create an account</a></p>
    </div>
</body>
</html>

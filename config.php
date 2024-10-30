<?php


    define('SERVER', 'database-1.clcg6k226l02.us-east-2.rds.amazonaws.com');
    define('USERNAME', 'admin'); 
    define('DBPASS', 'OnlyPans');
    define('DBNAME', 'online_recipe_service');

    $db = mysqli_connect(SERVER,USERNAME,DBPASS,DBNAME);

    if($db == false){
        die("ERROR: Connection Error. " . mysqli_connect_error());
    }
?>
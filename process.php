<?php
include 'database.php';
if(isset($_POST['submit'])){
    $user = trim($_POST['user'] ?? '');
    $message = trim($_POST['message'] ?? '');
    date_default_timezone_set('Africa/Tunis');
$time = date("H:i:s");

    if ($user === '' || $message === ''){
        $error = 'please fill in your name and your message';
        header("Location:index.php?error=".urlencode($error));
        exit();
     } else{
        $user = mysqli_real_escape_string($con, $user);
        $message = mysqli_real_escape_string($con, $message);
        $query = "INSERT INTO messange (user, message, time)
         VALUES ('$user', '$message', '$time')";
         if(!mysqli_query($con, $query)){
            die('ERROR:' .mysqli_error($con));
        }else{
            header("Location: index.php");
            exit();
        }
    }
}
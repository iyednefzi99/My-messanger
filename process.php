<?php
include 'database.php';
if(isset($_POST['submit'])){
    $user = mysqli_real_escape_string($con, $_POST['user']);
    $message = mysqli_real_escape_string($con, $_POST['message']);
    date_default_timezone_set('Africa/Tunis');
$time = date("H:i:s");




    if (!isset($user)|| $user =='' || !isset($message) || $message ==''){
        $error = 'please fill in your name and your message';
        header("Location:index.php?error=".urlencode($error));
     } else{
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
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
        $query = "INSERT INTO messange (user, message, time)
         VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($con, $query);
        if(!$stmt){
            die('ERROR:' .mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt, 'sss', $user, $message, $time);
        if(!mysqli_stmt_execute($stmt)){
            die('ERROR:' .mysqli_stmt_error($stmt));
        }else{
            mysqli_stmt_close($stmt);
            header("Location: index.php");
            exit();
        }
    }
}
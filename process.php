<?php
include 'auth.php';
include 'database.php';
require_login();

if(isset($_POST['submit'])){
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        redirect_error('index.php', 'Invalid session, please try again.');
    }

    // L'auteur vient de la session, jamais du formulaire : sinon n'importe
    // qui pourrait poster sous le nom d'un autre.
    $user_id = current_user_id();
    $user    = current_username();
    $message = trim($_POST['message'] ?? '');
    // Le fuseau est fixe une seule fois dans database.php, via config.php.
    $time = date("H:i:s");

    if ($message === ''){
        redirect_error('index.php', 'please fill in your message');
     } elseif (mb_strlen($message) > MESSAGE_MAX_LENGTH){
        // maxlength cote client peut etre contourne : on revalide ici.
        redirect_error('index.php', 'Message too long (max ' . MESSAGE_MAX_LENGTH . ' characters).');
     } else{
        $query = "INSERT INTO messange (user_id, user, message, time)
         VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $query);
        if(!$stmt){
            error_log('Prepare failed: ' . mysqli_error($con));
            redirect_error('index.php', 'Service unavailable, please try again later.');
        }
        mysqli_stmt_bind_param($stmt, 'isss', $user_id, $user, $message, $time);
        if(!mysqli_stmt_execute($stmt)){
            error_log('Insert message failed: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            redirect_error('index.php', 'Service unavailable, please try again later.');
        }else{
            mysqli_stmt_close($stmt);
            header("Location: index.php");
            exit();
        }
    }
}

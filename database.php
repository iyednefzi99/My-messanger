<?php
$con=mysqli_connect("localhost","root","","messanger");

if(mysqli_connect_errno()){
    error_log("Failed to connect to MySQL: ". mysqli_connect_error());
    http_response_code(503);
    die("Service unavailable, please try again later.");
}
<?php include 'database.php' ; ?>
<?php
$query = "SELECT * FROM messange ";
$messange = mysqli_query ($con,$query);
?>
<!doctype html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
        <meta charset="UTF-8">
        <title>MESSANGER</title>
        <link rel="icon" href="media/icons8-facebook-messenger-144.png" type="image/png">
        <link rel="stylesheet" href="css/style1.css">

    </head>
    <body>
        <div id="container">
                <div id="header">
                <img class="media" src="media/icons8-facebook-messenger-100.png" alt="MESSANGER" >
                    <h1>Welcome to my MESSANGER</h1>

                </div>
                   
            
            <div id="main">
              <ul>

              <?php while ($row = mysqli_fetch_assoc ($messange) ): ?>

                <li class="messange"><span><?php echo $row['time'] ?> -</span><b><?php echo $row['user'] ?></b>  :  <?php echo $row['message'] ?></li>
             
                <?php  endwhile; ?>

              </ul>
            </div>

            <div id="send"> 
              <?php if (isset($_GET['error'])): ?>
                <div class="error"><?php echo $_GET['error'] ?></div>
                <?php endif ;?>
                <form action="process.php" method="post">
          <div class="user-box">
            <input type="text" name="user">
            <label>Enter your name</label>
          </div>
          <div class="user-box">
            <input type="text" name="message" >
            <label>Enter your message</label>
          </div>
                <input type="submit" name="submit" class="send-btn"  value="Send">
            </form>
            </div>
 
        </div>

            
    </body>
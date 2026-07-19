<?php include 'auth.php' ; ?>
<?php include 'database.php' ; ?>
<?php
require_login();

// Les messages anterieurs a l'authentification n'ont pas de user_id :
// on retombe sur l'ancienne colonne `user` pour les afficher.
$query = "SELECT m.id, m.time, m.message, COALESCE(u.username, m.user) AS author
          FROM messange m
          LEFT JOIN users u ON m.user_id = u.id
          ORDER BY m.id";
$messange = mysqli_query ($con,$query);
?>
<!doctype html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>MESSANGER</title>
        <link rel="icon" href="media/icons8-facebook-messenger-144.png" type="image/png">
        <link rel="stylesheet" href="css/style1.css">

    </head>
    <body>
        <div id="container">
                <div id="header">
                <img class="media" src="media/icons8-facebook-messenger-100.png" alt="MESSANGER" >
                    <h1>Welcome to my MESSANGER</h1>

                    <div id="session-bar">
                      <span>Logged in as <b><?php echo htmlspecialchars(current_username(), ENT_QUOTES, 'UTF-8') ?></b></span>
                      <form action="logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="submit" class="logout-btn" value="Log out">
                      </form>
                    </div>
                </div>


            <div id="main">
              <ul>

              <?php while ($row = mysqli_fetch_assoc ($messange) ): ?>

                <li class="messange" data-id="<?php echo (int) $row['id'] ?>"><span><?php echo htmlspecialchars($row['time'], ENT_QUOTES, 'UTF-8') ?> -</span><b><?php echo htmlspecialchars($row['author'], ENT_QUOTES, 'UTF-8') ?></b>  :  <?php echo htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8') ?></li>

                <?php  endwhile; ?>

              </ul>
            </div>

            <div id="send">
              <?php if (isset($_GET['error'])): ?>
                <div class="error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif ;?>
                <form action="process.php" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="user-box">
            <input type="text" name="message" maxlength="<?php echo MESSAGE_MAX_LENGTH ?>">
            <label>Enter your message</label>
          </div>
                <input type="submit" name="submit" class="send-btn"  value="Send">
            </form>
            </div>

        </div>

        <script src="js/script.js"></script>
    </body>
</html>

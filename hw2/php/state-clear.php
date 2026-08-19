<?php

session_start();

session_unset();

session_destroy();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP State Cleared</title>
</head>
<body>
<h1>Session Cleared</h1>
<p>Your saved PHP session data has been removed.</p>
<p>

    <a href="state-php-aaron.php">
        Start Again
    </a>

</p>
</body>

</html>
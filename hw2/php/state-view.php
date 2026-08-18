<?php

session_start();

$name =
    $_SESSION["name"] ?? "No name saved";

$message =
    $_SESSION["message"] ?? "No message saved";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View PHP State</title>
</head>

<body>

<h1>Saved PHP Session Data</h1>


<p>

    <strong>Name:</strong>

    <?php echo htmlspecialchars($name); ?>

</p>


<p>

    <strong>Message:</strong>

    <?php echo htmlspecialchars($message); ?>

</p>


<p>

    <a href="state-php-aaron.php">
        Change Data
    </a>

</p>


<p>

    <a href="state-clear.php">
        Clear Data
    </a>

</p>

</body>

</html>
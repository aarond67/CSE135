<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html");

$date = date("D M d H:i:s Y");
$address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hello to Aaron's PHP World</title>
</head>
<body>

    <h1>Hello to Aaron's PHP World</h1>
    <hr>

    <p>Hello World</p>

    <p>This page was generated with the PHP programming language.</p>

    <p>This program was generated at: <?php echo htmlspecialchars($date); ?></p>

    <p>Your current IP Address is:
        <?php echo htmlspecialchars($address); ?>
    </p>

</body>
</html>
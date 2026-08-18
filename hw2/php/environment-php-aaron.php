<?php

header("Cache-Control: no-cache");
header("Content-Type: text/html");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>PHP Environment Variables</title>
</head>

<body>

<h1>PHP Environment Variables</h1>

<table border="1">
    <thead>
        <tr>
            <th>Variable</th>
            <th>Value</th>
        </tr>
    </thead>

    <tbody>

    <?php foreach ($_SERVER as $key => $value): ?>

        <tr>
            <td><?php echo htmlspecialchars($key); ?></td>

            <td>
                <?php
                if (is_array($value)) {
                    echo htmlspecialchars(json_encode($value));
                } else {
                    echo htmlspecialchars((string)$value);
                }
                ?>
            </td>
        </tr>

    <?php endforeach; ?>

    </tbody>
</table>

</body>
</html>
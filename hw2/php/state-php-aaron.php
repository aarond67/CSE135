<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $_SESSION["name"] =
        $_POST["name"] ?? "";

    $_SESSION["message"] =
        $_POST["message"] ?? "";

    header("Location: state-view.php");

    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>PHP State Demo</title>
</head>

<body>

<h1>PHP State Demo</h1>

<p>Enter information to store in your server-side session.</p>


<form method="POST">

    <p>

        <label for="name">Name:</label>

        <input
            type="text"
            id="name"
            name="name"
            required
        >

    </p>


    <p>

        <label for="message">Message:</label>

        <input
            type="text"
            id="message"
            name="message"
            required
        >

    </p>


    <button type="submit">
        Save Data
    </button>

</form>


<p>
    <a href="state-view.php">
        View Saved Data
    </a>
</p>

</body>

</html>
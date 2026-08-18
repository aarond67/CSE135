<?php

header("Cache-Control: no-cache");
header("Content-Type: application/json");

$data = [
    "message" => "Hello World",
    "name" => "Aaron",
    "language" => "PHP",
    "date" => date("D M d H:i:s Y"),
    "ip" => $_SERVER["REMOTE_ADDR"] ?? "Unknown"
];

echo json_encode($data, JSON_PRETTY_PRINT);
?>
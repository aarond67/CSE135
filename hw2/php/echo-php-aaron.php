<?php

header("Cache-Control: no-cache");
header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];

$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

$rawData = file_get_contents("php://input");

$data = [];

if ($method === "GET") {

    $data = $_GET;

}
elseif (strpos($contentType, "application/json") !== false) {

    $decoded = json_decode($rawData, true);

    if (is_array($decoded)) {
        $data = $decoded;
    }

}
elseif (
    $method === "POST" &&
    strpos($contentType, "application/x-www-form-urlencoded") !== false
) {

    $data = $_POST;

}
elseif (
    strpos($contentType, "application/x-www-form-urlencoded") !== false
) {

    parse_str($rawData, $data);

}
else {
    $data = [
        "raw" => $rawData
    ];
}


$response = [
    "message" => "Echo response from PHP",
    "method" => $method,
    "data" => $data,
    "hostname" => gethostname(),
    "date" => date("D M d H:i:s Y"),
    "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "Unknown",
    "ip" => $_SERVER["REMOTE_ADDR"] ?? "Unknown"
];


echo json_encode($response, JSON_PRETTY_PRINT);

?>
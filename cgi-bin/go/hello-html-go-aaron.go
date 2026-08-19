package main

import (
	"fmt"
	"os"
	"time"
)

func main() {
	ip := os.Getenv("REMOTE_ADDR")
	if ip == "" {
		ip = "Unknown"
	}

	currentTime := time.Now().Format("Mon Jan 02 15:04:05 2006")

	fmt.Print("Content-Type: text/html\r\n\r\n")

	fmt.Printf(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aaron's Go World</title>
</head>
<body>

    <h1>Hello to Aaron's Go World</h1>

    <hr>

    <p>Hello World</p>

    <p>This page was generated with the Go programming language.</p>

    <p>This program was generated at: %s</p>

    <p>Your current IP Address is: %s</p>

</body>
</html>
`, currentTime, ip)
}
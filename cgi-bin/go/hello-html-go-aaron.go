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

	fmt.Print("Content-Type: text/html\r\n\r\n")

	fmt.Printf(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hello Go</title>
</head>
<body>
    <h1>Hello from Aaron!</h1>
    <p>Language: Go</p>
    <p>Generated at: %s</p>
    <p>Your IP Address: %s</p>
</body>
</html>
`, time.Now().Format(time.RFC1123), ip)
}
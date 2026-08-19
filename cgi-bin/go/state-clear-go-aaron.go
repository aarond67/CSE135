package main

import "fmt"

func main() {
	fmt.Print("Content-Type: text/html\r\n")
	fmt.Print("Set-Cookie: go_state=; Max-Age=0; Path=/\r\n")
	fmt.Print("\r\n")

	fmt.Print(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aaron's Go State</title>
</head>
<body>

    <h1>Aaron's Go State Cleared</h1>

    <hr>

    <p>This page was generated with the Go programming language.</p>

    <p>Your saved Go data has been cleared.</p>

    <p>
        <a href="/cgi-bin/go/state-go-aaron">
            Return to State Demo
        </a>
    </p>

    <p>
        <a href="/cgi-bin/go/state-view-go-aaron">
            View Saved Data
        </a>
    </p>

</body>
</html>
`)
}
package main

import (
	"fmt"
	"html"
	"io"
	"net/url"
	"os"
)

func main() {
	message := ""

	if os.Getenv("REQUEST_METHOD") == "POST" {
		body, _ := io.ReadAll(os.Stdin)

		values, _ := url.ParseQuery(string(body))
		message = values.Get("message")
	}

	fmt.Print("Content-Type: text/html\r\n")

	if message != "" {
		fmt.Printf(
			"Set-Cookie: go_state=%s; Path=/\r\n",
			url.QueryEscape(message),
		)
	}

	fmt.Print("\r\n")

	fmt.Printf(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Go State Demo</title>
</head>
<body>
    <h1>Go State Demo</h1>

    <form method="POST" action="/cgi-bin/go/state-go-aaron">
        <label for="message">Enter something to save:</label>
        <input type="text" id="message" name="message" required>
        <button type="submit">Save Data</button>
    </form>`)

	if message != "" {
		fmt.Printf(
			"<p>Saved value: %s</p>",
			html.EscapeString(message),
		)
	}

	fmt.Println(`
    <p><a href="/cgi-bin/go/state-view-go-aaron">View Saved Data</a></p>
    <p><a href="/cgi-bin/go/state-clear-go-aaron">Clear Saved Data</a></p>
</body>
</html>`)
}
package main

import (
	"encoding/json"
	"fmt"
	"html"
	"io"
	"net/url"
	"os"
	"time"
)

func main() {
	method := os.Getenv("REQUEST_METHOD")
	contentType := os.Getenv("CONTENT_TYPE")
	userAgent := os.Getenv("HTTP_USER_AGENT")
	ip := os.Getenv("REMOTE_ADDR")
	host := os.Getenv("HTTP_HOST")

	body := ""

	if method == "GET" {
		body = os.Getenv("QUERY_STRING")
	} else {
		data, err := io.ReadAll(os.Stdin)
		if err == nil {
			body = string(data)
		}
	}

	var received interface{} = body

	if contentType == "application/json" {
		var jsonData interface{}

		if err := json.Unmarshal([]byte(body), &jsonData); err == nil {
			received = jsonData
		}
	} else {
		values, err := url.ParseQuery(body)

		if err == nil {
			received = values
		}
	}
	data, _ := json.MarshalIndent(received, "", "    ")
	fmt.Print("Content-Type: text/html\r\n\r\n")
	fmt.Printf(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aaron's Go Echo</title>
</head>
<body>
    <h1>Aaron's Go Echo</h1>
    <hr>
    <p>This page was generated with the Go programming language.</p>
    <p><strong>Hostname:</strong> %s</p>
    <p><strong>Date and Time:</strong> %s</p>
    <p><strong>Method:</strong> %s</p>
    <p><strong>Content Type:</strong> %s</p>
    <p><strong>User Agent:</strong> %s</p>
    <p><strong>IP Address:</strong> %s</p>
    <h2>Received Data</h2>
    <pre>%s</pre>
</body>
</html>
`,
		html.EscapeString(host),
		time.Now().Format("Mon Jan 02 15:04:05 2006"),
		html.EscapeString(method),
		html.EscapeString(contentType),
		html.EscapeString(userAgent),
		html.EscapeString(ip),
		html.EscapeString(string(data)),
	)
}
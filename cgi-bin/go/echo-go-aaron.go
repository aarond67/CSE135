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

	var body string

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

	response := map[string]interface{}{
		"hostname":    host,
		"dateTime":    time.Now().Format(time.RFC3339),
		"method":      method,
		"contentType": contentType,
		"userAgent":   userAgent,
		"ipAddress":   ip,
		"received":    received,
	}

	jsonOutput, _ := json.MarshalIndent(response, "", "    ")

	fmt.Print("Content-Type: text/html\r\n\r\n")

	fmt.Printf(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Go Echo</title>
</head>
<body>
    <h1>Go Echo</h1>

    <p><strong>Method:</strong> %s</p>
    <p><strong>Content Type:</strong> %s</p>
    <p><strong>Hostname:</strong> %s</p>
    <p><strong>Date and Time:</strong> %s</p>
    <p><strong>User Agent:</strong> %s</p>
    <p><strong>IP Address:</strong> %s</p>

    <h2>Received Data</h2>
    <pre>%s</pre>

</body>
</html>`,
		html.EscapeString(method),
		html.EscapeString(contentType),
		html.EscapeString(host),
		html.EscapeString(time.Now().Format(time.RFC1123)),
		html.EscapeString(userAgent),
		html.EscapeString(ip),
		html.EscapeString(string(jsonOutput)),
	)
}
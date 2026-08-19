package main

import (
	"fmt"
	"html"
	"net/http"
	"net/url"
	"os"
)

func main() {
	savedValue := ""
	cookieHeader := os.Getenv("HTTP_COOKIE")
	if cookieHeader != "" {
		request := &http.Request{
			Header: http.Header{
				"Cookie": []string{cookieHeader},
			},
		}
		cookie, err := request.Cookie("go_state")
		if err == nil {
			value, err := url.QueryUnescape(cookie.Value)
			if err == nil {
				savedValue = value
			}
		}
	}
	fmt.Print("Content-Type: text/html\r\n\r\n")
	fmt.Print(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aaron's Go State</title>
</head>
<body>
    <h1>Aaron's Saved Go State</h1>
    <hr>
    <p>This page was generated with the Go programming language.</p>
`)
	if savedValue != "" {
		fmt.Printf(`
    <p>Your saved data is: %s</p>
`, html.EscapeString(savedValue))
	} else {
		fmt.Print(`
    <p>No saved data was found.</p>
`)
	}
	fmt.Print(`
    <p>
        <a href="/cgi-bin/go/state-go-aaron">
            Save New Data
        </a>
    </p>
    <p>
        <a href="/cgi-bin/go/state-clear-go-aaron">
            Clear Saved Data
        </a>
    </p>
</body>
</html>
`)
}
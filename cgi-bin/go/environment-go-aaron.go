package main

import (
	"fmt"
	"html"
	"os"
	"sort"
)

func main() {
	fmt.Print("Content-Type: text/html\r\n\r\n")

	fmt.Println(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Go Environment Variables</title>
</head>
<body>
    <h1>Go Environment Variables</h1>
    <ul>`)

	env := os.Environ()
	sort.Strings(env)

	for _, variable := range env {
		fmt.Printf("<li>%s</li>\n", html.EscapeString(variable))
	}

	fmt.Println(`    </ul>
</body>
</html>`)
}
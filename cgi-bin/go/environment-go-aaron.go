package main

import (
	"fmt"
	"html"
	"os"
	"sort"
)

func main() {
	fmt.Print("Content-Type: text/html\r\n\r\n")

	fmt.Print(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aaron's Go Environment</title>
</head>
<body>

    <h1>Aaron's Go Environment Variables</h1>

    <hr>

    <p>This page was generated with the Go programming language.</p>

    <h2>Environment Variables</h2>

    <ul>
`)

	env := os.Environ()
	sort.Strings(env)

	for _, variable := range env {
		fmt.Printf("        <li>%s</li>\n", html.EscapeString(variable))
	}

	fmt.Print(`    </ul>

</body>
</html>
`)
}
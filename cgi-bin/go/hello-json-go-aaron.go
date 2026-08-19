package main

import (
	"encoding/json"
	"fmt"
	"os"
	"time"
)

type Response struct {
	Message   string `json:"message"`
	Language  string `json:"language"`
	DateTime  string `json:"dateTime"`
	IPAddress string `json:"ipAddress"`
}

func main() {
	ip := os.Getenv("REMOTE_ADDR")
	if ip == "" {
		ip = "Unknown"
	}

	response := Response{
		Message:   "Hello from Aaron!",
		Language:  "Go",
		DateTime:  time.Now().Format(time.RFC3339),
		IPAddress: ip,
	}

	fmt.Print("Content-Type: application/json\r\n\r\n")

	data, err := json.MarshalIndent(response, "", "    ")
	if err != nil {
		fmt.Println(`{"error":"Could not generate JSON"}`)
		return
	}

	fmt.Println(string(data))
}
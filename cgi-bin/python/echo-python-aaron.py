#!/usr/bin/python3

import os
import sys
import json
import socket
from datetime import datetime
from urllib.parse import parse_qs

print("Cache-Control: no-cache")
print("Content-Type: application/json")
print()

method = os.environ.get("REQUEST_METHOD", "GET")
content_type = os.environ.get("CONTENT_TYPE", "")

data = {}

# GET
if method == "GET":
    query_string = os.environ.get("QUERY_STRING", "")
    parsed = parse_qs(query_string)
    data = {
        key: values[0] if len(values) == 1 else values
        for key, values in parsed.items()
    }

# POST / PUT / DELETE
else:
    content_length = int(os.environ.get("CONTENT_LENGTH", 0) or 0)
    raw_data = sys.stdin.read(content_length)
    # JSON
    if "application/json" in content_type:
        try:
            data = json.loads(raw_data)
        except json.JSONDecodeError:
            data = {
                "error": "Invalid JSON",
                "raw": raw_data
            }
    # URL encoded
    elif "application/x-www-form-urlencoded" in content_type:
        parsed = parse_qs(raw_data)
        data = {
            key: values[0] if len(values) == 1 else values
            for key, values in parsed.items()
        }
    else:
        data = {
            "raw": raw_data
        }


response = {
    "message": "Echo response from Python",
    "method": method,
    "data": data,
    "hostname": socket.gethostname(),
    "date": datetime.now().strftime("%a %b %d %H:%M:%S %Y"),
    "user_agent": os.environ.get("HTTP_USER_AGENT", "Unknown"),
    "ip": os.environ.get("REMOTE_ADDR", "Unknown")
}

print(json.dumps(response, indent=4))
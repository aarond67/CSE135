#!/usr/bin/python3

import os
import json
from datetime import datetime

print("Cache-Control: no-cache")
print("Content-Type: application/json")
print()

data = {
    "message": "Hello World",
    "name": "Aaron",
    "language": "Python",
    "date": datetime.now().strftime("%a %b %d %H:%M:%S %Y"),
    "ip": os.environ.get("REMOTE_ADDR", "Unknown")
}

print(json.dumps(data, indent=4))
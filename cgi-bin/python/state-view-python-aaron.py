#!/usr/bin/env python3

import os
import html
import urllib.parse
from http.cookies import SimpleCookie

cookie = SimpleCookie()

if "HTTP_COOKIE" in os.environ:
    cookie.load(os.environ["HTTP_COOKIE"])

saved_value = ""

if "python_state" in cookie:
    saved_value = urllib.parse.unquote(cookie["python_state"].value)

safe_value = html.escape(saved_value)

print("Content-Type: text/html")
print()

print("""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Python State</title>
</head>
<body>
    <h1>Saved Python State</h1>
""")

if saved_value:
    print(f"<p>Saved value: {safe_value}</p>")
else:
    print("<p>No saved data was found.</p>")

print("""
    <p>
        <a href="/cgi-bin/python/state-python-aaron.py">
            Save New Data
        </a>
    </p>

    <p>
        <a href="/cgi-bin/python/state-clear-python-aaron.py">
            Clear Saved Data
        </a>
    </p>
</body>
</html>
""")
#!/usr/bin/env python3

import cgi
import html
import urllib.parse

form = cgi.FieldStorage()
value = form.getfirst("message", "")

print("Content-Type: text/html")

if value:
    encoded_value = urllib.parse.quote(value)
    print(f"Set-Cookie: python_state={encoded_value}; Path=/")

print()

safe_value = html.escape(value)

print(f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Python State Demo</title>
</head>
<body>
    <h1>Python State Demo</h1>

    <form method="POST" action="/cgi-bin/python/state-python-aaron.py">
        <label for="message">Enter something to save:</label>
        <input type="text" id="message" name="message" required>
        <button type="submit">Save Data</button>
    </form>
""")

if value:
    print(f"""
    <p>Saved value: {safe_value}</p>
    """)

print("""
    <p>
        <a href="/cgi-bin/python/state-view-python-aaron.py">
            View Saved Data
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
#!/usr/bin/python3

import os
from datetime import datetime

print("Cache-Control: no-cache")
print("Content-Type: text/html")
print()

date = datetime.now().strftime("%a %b %d %H:%M:%S %Y")
address = os.environ.get("REMOTE_ADDR", "Unknown")

print("""<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hello to Aaron's Python World</title>
</head>

<body>

<h1>Hello to Aaron's Python World</h1>
<hr>

<p>Hello World</p>

<p>This page was generated with the Python programming language.</p>

<p>This program was generated at: {}</p>

<p>Your current IP Address is: {}</p>

</body>
</html>
""".format(date, address))
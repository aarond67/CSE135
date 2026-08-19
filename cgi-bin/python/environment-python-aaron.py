#!/usr/bin/python3

import os
import html

print("Cache-Control: no-cache")
print("Content-Type: text/html")
print()
print("""<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Python Environment Variables</title>
</head>

<body>

<h1>Python Environment Variables</h1>

<table border="1">

<thead>
<tr>
    <th>Variable</th>
    <th>Value</th>
</tr>
</thead>

<tbody>
""")
for key, value in sorted(os.environ.items()):
    print("<tr>")
    print("<td>{}</td>".format(html.escape(key)))
    print("<td>{}</td>".format(html.escape(value)))
    print("</tr>")

print("""
</tbody>
</table>

</body>
</html>
""")
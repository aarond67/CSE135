#!/usr/bin/env python3

print("Content-Type: text/html")
print("Set-Cookie: python_state=; Max-Age=0; Path=/")
print()

print("""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clear Python State</title>
</head>
<body>
    <h1>Python State Cleared</h1>

    <p>Your saved Python state has been cleared.</p>

    <p>
        <a href="/cgi-bin/python/state-python-aaron.py">
            Return to State Demo
        </a>
    </p>

    <p>
        <a href="/cgi-bin/python/state-view-python-aaron.py">
            View Saved Data
        </a>
    </p>
</body>
</html>
""")
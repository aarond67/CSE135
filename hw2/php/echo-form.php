<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Homework 2 Echo Form</title>
</head>

<body>

<h1>Echo Form</h1>

<form
    id="echo-form"
    action="/hw2/php/echo-php-aaron.php"
    method="GET"
>

    <p>
        <label for="language">Language:</label>

        <select id="language" name="language">
            <option value="php">PHP</option>

            <!-- Add Python and Go later -->
        </select>
    </p>


    <p>
        <label for="method">HTTP Method:</label>

        <select id="method">
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="DELETE">DELETE</option>
        </select>
    </p>


    <p>
        <label for="encoding">Encoding:</label>

        <select id="encoding">
            <option value="application/x-www-form-urlencoded">
                application/x-www-form-urlencoded
            </option>

            <option value="application/json">
                application/json
            </option>
        </select>
    </p>


    <p>
        <label for="name">Name:</label>
        <input
            type="text"
            id="name"
            name="name"
            value="Aaron"
        >
    </p>


    <p>
        <label for="message">Message:</label>
        <input
            type="text"
            id="message"
            name="message"
            value="Hello World"
        >
    </p>


    <button type="submit">
        Send Request
    </button>

</form>


<noscript>

    <p>
        JavaScript is disabled. This form will submit a basic
        GET request to the PHP echo endpoint.
    </p>

</noscript>


<h2>Response</h2>

<pre id="response">
Submit the form to see the response.
</pre>


<script>

const form = document.getElementById("echo-form");

const language = document.getElementById("language");
const method = document.getElementById("method");
const encoding = document.getElementById("encoding");

const responseArea = document.getElementById("response");


form.addEventListener("submit", async function(event) {

    event.preventDefault();


    let endpoint;


    switch (language.value) {

        case "php":

            endpoint = "/hw2/php/echo-php-aaron.php";

            break;

        /*
        We'll add these later:

        case "python":
            endpoint = "/cgi-bin/echo-python-aaron.py";
            break;

        case "go":
            endpoint = "...";
            break;
        */

    }


    const requestMethod = method.value;

    const requestEncoding = encoding.value;


    const formData = {

        name: document.getElementById("name").value,

        message: document.getElementById("message").value

    };


    let url = endpoint;


    const options = {

        method: requestMethod,

        headers: {}

    };


    /*
    GET requests send data through the URL
    */

    if (requestMethod === "GET") {

        const params = new URLSearchParams(formData);

        url += "?" + params.toString();

    }


    /*
    Other methods send data in the request body
    */

    else {

        if (requestEncoding === "application/json") {

            options.headers["Content-Type"] =
                "application/json";

            options.body =
                JSON.stringify(formData);

        }


        else {

            options.headers["Content-Type"] =
                "application/x-www-form-urlencoded";

            options.body =
                new URLSearchParams(formData);

        }

    }


    try {

        const response = await fetch(url, options);

        const text = await response.text();

        responseArea.textContent = text;

    }

    catch (error) {

        responseArea.textContent =
            "Request failed: " + error;

    }

});

</script>

</body>

</html>
## Team Members
- Aaron Delgado

## Website Links
Test website:
https://test.baddecisions.site

Collector script:
https://collector.baddecisions.site/collector.js

Collector endpoint:
https://collector.baddecisions.site/log/

Reporting API:
https://reporting.baddecisions.site/api/static



## Grader Notes

- The test website is hosted at test.baddecisions.site.
- Static, performance, error, and activity data are sent to the /log endpoint.
- The endpoint stores the collected information in MySQL.
- The reporting API retrieves information from the static_data table.
- All collected records from the same browser session share the same session ID.
- The REST API supports GET, POST, PUT, and DELETE operations.
- The example GET route returns data collected from the test website.

## Collector.js Changes

Beyond the basic ideas shown in the CSE135 collector tutorial, I added:

- A UUID-based session ID stored in sessionStorage.
- A session cookie using Secure and SameSite attributes.
- The same session ID is included with static, performance, error, and activity data.
- Testing for whether cookies, images, and CSS are enabled.
- Network connection information, including effective connection type, downlink, RTT, and data-saver status.
- Full Navigation Timing data.
- Manually calculated page-load start, page-load end, and total load time.
- JavaScript error and unhandled-promise-rejection collection.
- Mouse movement, click, and scrolling collection.
- Keyboard event collection without recording sensitive form values.
- Activity batching to avoid sending a request for every individual event.
- Two-second idle-period detection.
- Page-entry and page-exit events.
- sendBeacon delivery with a fetch fallback.
- Page-exit delivery using sendBeacon so queued events have a better chance of reaching the server.

# Bad Decisions Analytics — HW4

## Team member

Aaron Delgado

## Links

- [GitHub repo](https://github.com/aarond67/CSE135)
- [Main website](https://baddecisions.site/)
- [Test store](https://test.baddecisions.site/)
- [Reporting dashboard](https://reporting.baddecisions.site/)
- [Performance report](https://reporting.baddecisions.site/reports/performance.php)
- [Collector script](https://collector.baddecisions.site/collector.js)
- [Collector endpoint](https://collector.baddecisions.site/log/)

The dashboard and report require a login. The collector endpoint accepts POST requests; opening it in a browser is not a submission test.

## What I built

For HW4, I built on the collector and database from HW3. The test site sends data to a PHP endpoint, the endpoint stores it in MySQL, and the reporting site turns that data into charts and tables.

I kept the main dashboard focused on a few questions: which pages are getting visits, whether sessions are getting through checkout, and which pages might need performance work. The more detailed loading information has its own report so the first page does not have to show everything at once.

The app uses PHP, MySQL, plain JavaScript, and CSS. The charts use HTML elements and CSS, so there is no extra chart library to load. GitHub Actions deploys the sites to my DigitalOcean server when I push to main.

## Why I picked these views

The dashboard has three summary cards: page loads, unique sessions, and average load time. Sessions are browsing sessions, not a count of individual people.

Below the cards, I use two charts and one grid:

- **Most visited pages:** a horizontal bar chart makes it easy to compare page-load counts. It shows up to eight URLs and includes repeat visits.
- **Shopping progress:** a bar chart shows how many sessions visited the site, viewed a product, reached checkout afterward, and then saw the demo success message. Every bar uses the same scale.
- **Pages to investigate:** a table lists up to ten URLs with the highest average load time, along with the number of measurements and slowest load. This gives me somewhere to start looking instead of just showing one average for the entire site.

Dates use UTC, including the full end date. Different URLs stay separate, so `/`, `/index.html`, and product URLs with different query strings may have separate rows.

## Detailed performance report

My guiding question is: **Which pages are loading slowly, and where should performance work be focused?**

The dashboard links to `hw3/reporting/reports/performance.php`. The report has two chart views that can be switched without another data request:

- **Individual loads:** each dot is one page load. I chose this because an average can hide a page that is usually quick but occasionally takes several seconds.
- **Loading stages:** stacked bars show where the average loading time was spent: before the request, waiting for a response, downloading the HTML, and the remaining time until the load event ends.

The table underneath uses the same complete measurements as the stage chart. Missing or inconsistent stage timings are excluded instead of being treated as zero. The charts and stage table use the latest 100 records at most; the summary cards use the full selected date range.

In the test results I looked at, `/product-detail.html` ranged from about 68 ms to 6.60 seconds. That makes me want to investigate the slow loads rather than assume the page is always slow. The loading-stage view showed most of its average time after the HTML response finished. I would start by checking the remaining resources and page work, then collect more measurements. That is a starting point, not proof of the cause.

I can save a written discussion in the analyst comments section. Those comments are shared for this report, not separate notes for every date range, so I need to state which dates I am discussing.

## What the checkout chart actually counts

This is a test store, not a working payment system. **Use made-up information, never real card or personal details.** The checkout intentionally has random outcomes and JavaScript errors.

When the page shows “Order Placed!”, it fires `cse135:demo-order-success`. The collector sends a `demo-order-success` event with `{ demo: true }`, without including checkout field values.

The chart only counts a success if the same session has these records in order, within the selected dates:

1. A product-detail page load.
2. A later checkout page load.
3. A later demo-success event.

Each session counts once per step. Going directly to checkout can create a success event in the database without completing this sequence. That happened during testing and explained why the chart still showed zero until I followed the full path.

“Demo success shown” is not a confirmed purchase or revenue. Older visits had no success tracking, and missing data does not prove that someone abandoned checkout.

## Login and access

Super admins can view all sections and manage accounts. Analysts can only access their assigned technology, performance, and behavior sections. The server checks permissions for pages and API requests, not just the navigation links.

Passwords are hashed. Login regenerates the session ID, forms use CSRF tokens, and displayed user text is escaped. Signing out requires a POST request. The app also checks the account on each request so disabled or deleted users lose access.

The viewer role is in the schema, but the saved-report viewing and publishing flow is still future work. Viewers currently cannot open the dashboard or performance report.

Database credentials stay outside the repository and web roots in `/etc/cse135/`. Grader passwords and SSH keys should be provided through the private assignment submission, not this public README.

## Collector and API notes

The collector keeps a session ID in sessionStorage and a Secure, SameSite cookie. It collects browser information, page timing, errors, and activity. Mouse movement and scrolling are sampled; activity is batched every five seconds and flushed on page exit. Delivery uses sendBeacon with a fetch fallback.

Single-character keys in standard input, textarea, and select fields are redacted. That is not a complete privacy guarantee for every kind of editable content or error message, which is another reason to use only fake test data.

The reporting routes are `/api/overview`, `/api/performance`, and `/api/static`. The static API still supports GET, POST, PUT, and DELETE from HW3. Reads require technology access; writes require a super admin and a valid `X-CSRF-Token` header.

The no-JavaScript pixel writes a log entry; it does not currently insert that entry into MySQL.

## AI use

I used ChatGPT/Codex to help write parts of the PHP and JavaScript, plan the database and charts, debug problems, and clean up the code and README. It helped me connect the different parts of the project and work through errors. I still had to test the results, check the database, and decide whether the charts actually answered a useful question. The checkout tracking was a good example of something that needed checking at each step instead of assuming the first result was right.

## What I would work on next

For HW5, I want to finish saved reports for viewers, add PDF export, and build out technology and behavior reports alongside performance. An error report would also be useful for finding pages with recurring technical problems.

The dataset is still small and mostly from testing. Before treating this as a production system, I would collect more representative data, add login rate limiting and stronger collector validation, and improve the error pages and no-JavaScript experience. The current report comments are shared, editable notes, not a saved snapshot of the chart data.

# Bad Decisions Analytics — HW4

## Team member

Aaron Delgado

## Links

- [GitHub repo](https://github.com/aarond67/CSE135)
- [Main website](https://baddecisions.site/)
- [Test store](https://test.baddecisions.site/)
- [Reporting dashboard](https://reporting.baddecisions.site/)
- [Performance report](https://reporting.baddecisions.site/reports/performance.php)
- [User management](https://reporting.baddecisions.site/admin/users.php)
- [Collector script](https://collector.baddecisions.site/collector.js)
- [Collector endpoint](https://collector.baddecisions.site/log/)

## What I built

For HW4, I built on the collector and database from HW3. The test site sends data to a PHP endpoint, the endpoint stores it in MySQL, and the reporting site uses that data for its dashboard and detailed report.

I kept the dashboard focused on a few questions: which pages are getting visits, whether sessions are getting through checkout, and which pages might need performance work. The more detailed loading information has its own report so the first page does not have to show everything at once.

The app uses PHP, MySQL, plain JavaScript, and CSS. GitHub Actions deploys the sites to my DigitalOcean server when I push to main.

## Authentication

I used PHP sessions for login because the reporting site already uses PHP and MySQL. That let me keep the login checks, account management, and database access in the same application without adding another service.

Users can sign in with either their username or email in the same field. The app checks the password against its stored hash using `password_verify()`, then regenerates the session ID after a successful login. It does not store plain-text passwords.

In this app, the admin account is called `super_admin` and the basic reporting account is called `analyst`. Admins can view every section and manage accounts. Analysts can view their assigned sections but cannot manage users. For the basic grader account, assign technology, performance, and behavior so the grader can see the whole HW4 dashboard and report.

I check access on the server for both pages and API requests. Hiding a link would not stop someone from entering its URL directly. The app also checks the account on each request so disabled or deleted users lose access.

Session cookies use Secure, HttpOnly, and SameSite settings. Forms use CSRF tokens, database queries use prepared statements, and displayed user text is escaped. Signing out requires a POST request and returns to the login page with a confirmation message.

Database credentials stay outside the repository and web roots in `/etc/cse135/`.

### User management

The dashboard shows a Manage users link only to admins. The page at `/admin/users.php` has a table for viewing accounts and forms for creating, editing, and deleting them. Deleting an account asks for confirmation. Admins cannot delete their own account or remove their own admin access.

### Grader login information

Fill in both accounts below in the README copy submitted privately for grading. Do not commit the passwords to the public GitHub repository.


#### Basic — analyst with all three sections assigned
- [Add basic grader login]
- [Add basic grader password]
#### Admin — super_admin
- [Add admin grader login]
- [Add admin grader password]

## Dashboard

The dashboard has three summary cards: page loads, unique sessions, and average load time. I chose these to give a quick picture of traffic and page speed before someone opens the detailed report. Sessions are browsing sessions, not a count of individual people.

Below the cards, I use two charts and one grid:

- **Most visited pages:** a horizontal bar chart compares recorded page loads across up to eight URLs, including repeat visits. Bars make the ranking easy to read, and horizontal labels leave room for page names.
- **Shopping progress:** a funnel chart shows how many sessions visited the site, viewed a product, reached checkout afterward, and then saw the demo success message. I chose a funnel because these are steps in the shopping process. Each step's width uses the same session scale, with the exact count shown beside it. This makes it easier to see where fewer sessions reached the next recorded step, without assuming that every missing step means someone abandoned checkout.
- **Pages to investigate:** a table lists up to ten URLs with the highest average load time, their number of measurements, and their slowest load. I kept these exact values in a grid so someone can compare the average with the sample size before deciding what needs attention.

I used HTML elements and CSS for the charts instead of adding a chart library. That keeps the pages lightweight while still providing labels, values, and keyboard controls where needed. These views build on the HW3 data by adding summaries, date filters, shopping-step ordering, and a link to a more detailed investigation.

The date filter uses UTC and includes the full end date. Different URLs stay separate, so `/`, `/index.html`, and product URLs with different query strings can have separate rows. Empty ranges and failed data requests show a message rather than leaving old numbers on screen.

### What shopping progress counts

This is a test store, not a working payment system. **Use made-up information, never real card or personal details.** The checkout intentionally has random outcomes and JavaScript errors.

When the page shows “Order Placed!”, it fires `cse135:demo-order-success`. The collector sends a `demo-order-success` event with `{ demo: true }`, without including checkout field values.

The chart only counts a success when the same session has these records in order, within the selected dates:

1. A product-detail page load.
2. A later checkout page load.
3. A later demo-success event.

Each session counts once per step. Going directly to checkout can create a success event in the database without completing this sequence. “Demo success shown” is not a confirmed purchase or revenue, and a missing success event does not prove that someone abandoned checkout. Older visits also had no success tracking.

## Report

The report I am submitting is **Website performance**, at `/reports/performance.php`. The dashboard links to it.

My guiding question is: **Which pages are loading slowly, and where should performance work be focused?**

I picked page-load time because it gives me a technical problem to investigate, not just a count of activity. The report has two chart views that can be switched without another data request:

- **Individual loads:** each dot is one page load. I chose this because an average can hide a page that is usually quick but occasionally takes several seconds. Selecting a dot shows its measurement details.
- **Loading stages:** stacked bars show where the average loading time was spent: before the request, waiting for a response, downloading the HTML, and the remaining time until the load event ends.

The table underneath uses the same complete measurements as the stage chart. It gives exact values for comparing pages and stages. Missing or inconsistent stage timings are excluded instead of being treated as zero. The charts and stage table use the latest 100 records at most; the summary cards use the full selected date range.

In the test results I looked at, `/product-detail.html` ranged from about 68 ms to 6.60 seconds. That makes me want to investigate the slow loads rather than assume the page is always slow. The loading-stage view showed most of its average time after the HTML response finished. I would start by checking the remaining resources and page work, then collect more measurements. That is a starting point, not proof of the cause.

The report includes an analyst comments section where I can save the written discussion. Those comments are shared for this report, not separate notes for every date range, so I need to state which dates I am discussing.

## Grading notes and current limitations

- Start with the dashboard, change the date range, and open the performance report. Try both chart views and compare them with the table.
- Use the admin account to check user management and the basic account to confirm that it cannot access that page.
- Dashboard charts and the performance report require JavaScript. There is a notice when it is disabled.
- The data is mostly from testing and the sample is small, so it does not support broad conclusions about real customers.
- The collector's no-JavaScript pixel writes a log entry rather than inserting it into MySQL.
- Grader credentials and the report's saved discussion are not supplied by the source code. Fill in the private login information and check the discussion on the deployed report before submitting.

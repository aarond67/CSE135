# Bad Decisions Analytics — Final Project

## Team member

Aaron Delgado

## Links

- [GitHub repository](https://github.com/aarond67/CSE135)
- [Main website](https://baddecisions.site/)
- [Test store](https://test.baddecisions.site/)
- [Reporting application](https://reporting.baddecisions.site/)
- [Collector](https://collector.baddecisions.site/)

## Project overview

This project closes the loop between the analytics collector from HW3 and people who can act on the data. The test store sends page, performance, activity, and error information to the collector. The reporting application reads that data from MySQL and turns it into an overview plus four detailed reports in three categories: Technical Errors, Performance Budget, Page Engagement, and Checkout Drop-off.

The backend uses PHP and MySQL. The interface uses plain JavaScript and CSS so the reporting pages stay small and do not download a large front-end framework before loading the data. GitHub Actions deploys the main site, test store, collector, and reporting application to my DigitalOcean server when I push to `main`.

## Authentication and authorization

Users sign in with either a username or email and a password. Passwords are stored as hashes and checked with `password_verify()`. A successful login regenerates the PHP session ID. Session cookies use Secure, HttpOnly, and SameSite settings, forms use CSRF tokens, database queries use prepared statements, and displayed user content is escaped.

The application has the required three roles:

- **Super admin:** can see every analytics category, publish reports, export PDFs, and create, edit, disable, or delete users.
- **Analyst:** can use the dashboard and create, edit, publish, and export reports in the sections assigned to that account. For example, one analyst can be limited to the Performance Budget while another can also have Page Engagement.
- **Viewer:** cannot open the live dashboard, raw reporting APIs, user management, or draft reports. A viewer is sent to the report library and can only open published reports and download their PDFs.

Authorization is checked on the server for pages, APIs, report updates, and exports. Hiding a link is not treated as access control. Disabled or deleted accounts also lose access on their next request because the database account is checked again.

## Dashboard decisions

The dashboard is an overview for super admins and analysts. It starts with page loads, unique sessions, and average load time, followed by three different presentations:

- A horizontal bar chart ranks the most visited pages.
- A funnel shows how many sessions reached each ordered shopping step.
- A grid lists the pages with the highest average load time, including sample count and slowest measurement.

The dashboard stays high-level and sends users to the report library for deeper investigation. Date filters use UTC and include the entire end date. Empty results and failed requests display a message instead of leaving old values on screen.

## Reports

### Technical Errors

Guiding question: **Which JavaScript errors affect the most sessions and should be fixed first?**

This report keeps technical problems separate from user behavior. Its chart ranks pages by the number of distinct sessions that recorded a JavaScript error. The table groups the page, message, file, and line so I can see both how many times a problem happened and how many sessions it affected. This matters because one broken loop can record the same error many times for one visitor. I would start with an error that affects more sessions, then use its message and source location to reproduce it.

### Performance Budget

Guiding question: **Which pages exceed the 3-second load-time budget, and where should performance work be focused?**

The main comparison uses the 75th-percentile load time for each page and checks it against a 3,000 ms budget. I used p75 because a plain average can hide slow experiences, while a single worst load can overstate a one-time problem. The report still includes the individual load-time dots and average loading-stage bars for investigating a budget miss. The stage chart separates time before the request, waiting for a response, downloading HTML, and the time after the response until the load event finishes. Invalid or incomplete timing records are excluded rather than treated as zero.

### Page Engagement

Guiding question: **Which pages have the strongest meaningful interaction rate, and which pages need a closer look?**

A page session counts as engaged when it contains at least one click, scroll, or key press. Mouse movement is not included because it creates a lot of records without showing a clear action. The chart compares engaged-session rates instead of raw activity totals, and the table keeps the page-session sample size beside the click, scroll, and key-press counts. A page with no scrolling is not automatically treated as bad; a short page may not need scrolling, and clicks or keyboard use can still make the session engaged. The shopping funnel remains on the dashboard as a high-level overview.

### Checkout Drop-off

Guiding question: **Where in the checkout flow are the most sessions dropping off before the demo success message?**

This report gives the shopping funnel its own detailed behavior view. It follows sessions in order from a product view to checkout, payment, review, and the randomized demo-success message. The funnel makes the narrowing path easy to see, while the table gives the exact loss and continuation rate between each pair of stages. Checkout step events contain only the step number; the collector does not receive names, addresses, card numbers, or other checkout field values. Payment and review tracking begins with this version, so older sessions may appear to stop at checkout even if they continued. The final message is part of the test site and is not a verified purchase.

## Saving, publishing, and exporting

Each report has an analyst-comments field for explaining what the data means and what should be checked next. Super admins and permitted analysts can save a report as a draft or publish it. Viewers only see published reports.

Every accessible report has a server-generated PDF export. The export repeats the report title, guiding question, selected UTC dates, important chart values, table values, and saved analyst comments. The PDF uses a separate HTML template and print stylesheet so it keeps the same cream-and-green visual style without mixing the document layout into the export controller. Dompdf converts that template into a multi-page PDF on the server. Export authorization uses the same rules as the HTML report, so a direct export URL does not bypass report permissions.

## Error handling and performance

The reporting application includes custom 403, 404, and 500 pages, protects internal include files, rejects invalid methods and date ranges, and shows a notice when JavaScript is disabled on the interactive dashboard or performance report. The Technical Errors and Page Engagement reports are rendered by PHP and remain readable without JavaScript.

The application deliberately avoids a front-end framework and browser charting package. Most visualizations use regular HTML and CSS, and the API limits detailed performance samples to the latest 100 records. Dompdf is used only on the server when somebody requests an export, so it does not increase the JavaScript or page weight downloaded by dashboard users. This keeps the interactive pages small while preserving readable labels and exact table values.

## AI use

I used ChatGPT/Codex while building the project to help review PHP and SQL, work through authorization edge cases, reorganize some repeated code, and create test and deployment checklists. It was most useful for finding cases I had not considered, such as protecting a PDF export separately from the page and making sure a viewer could not reach draft data through an API. I still tested the queries and account flows against my deployed server and changed suggestions that did not match the assignment or the data I actually collected. The main limitation was that generated code could become more complicated than the project needed, so I kept the final application in plain PHP, JavaScript, and CSS and removed features that did not answer a useful question.

## Roadmap

With more time, I would save each published report's exact date range so a viewer always sees a frozen snapshot, add pagination as the dataset grows, compare current results with a previous period, and add automated browser tests for the complete login, publish, viewer, and export scenario.

## Setup notes

Database credentials remain outside the repository and web root in `/etc/cse135/`. Run the existing HW4 schema first, then run:

```bash
sudo mysql < hw3/database/hw5_schema.sql
```

The HW5 schema creates the four saved-report records if needed while preserving existing comments and publication status.

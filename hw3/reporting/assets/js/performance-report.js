(function () {
    "use strict";

    const form = document.getElementById("performance-filter");
    const startInput = document.getElementById("performance-start");
    const endInput = document.getElementById("performance-end");
    const applyButton = document.getElementById("performance-apply");
    const resetButton = document.getElementById("performance-reset");
    const status = document.getElementById("performance-status");
    const chart = document.getElementById("performance-page-chart");
    const tableBody = document.getElementById("performance-records");

    const numberFormat = new Intl.NumberFormat("en-US");

    const metricIds = [
        "performance-measurements",
        "performance-average",
        "performance-fastest",
        "performance-slowest"
    ];

    let requestNumber = 0;
    let activeRequest = null;

    function setText(id, value) {
        document.getElementById(id).textContent = value;
    }

    function duration(value) {
        if (value === null || value === undefined) {
            return "—";
        }

        const milliseconds = Number(value);

        if (!Number.isFinite(milliseconds) || milliseconds < 0) {
            return "—";
        }

        return milliseconds >= 1000
            ? (milliseconds / 1000).toFixed(2) + " s"
            : milliseconds.toFixed(1) + " ms";
    }

    function pageLabel(value) {
        try {
            const url = new URL(value);

            return url.pathname + url.search;
        } catch {
            return String(value || "Unknown page");
        }
    }

    function showPlaceholder(message) {
        metricIds.forEach(function (id) {
            setText(id, "—");
        });

        const paragraph = document.createElement("p");
        paragraph.className = "empty-state";
        paragraph.textContent = message;

        chart.replaceChildren(paragraph);

        const row = document.createElement("tr");
        const cell = document.createElement("td");

        cell.colSpan = 4;
        cell.textContent = message;

        row.appendChild(cell);
        tableBody.replaceChildren(row);
    }

    function renderChart(rows) {
        chart.replaceChildren();

        if (rows.length === 0) {
            const paragraph = document.createElement("p");

            paragraph.className = "empty-state";
            paragraph.textContent =
                "No page measurements in this date range.";

            chart.appendChild(paragraph);
            return;
        }

        const maximum = Math.max(
            0,
            ...rows.map(function (row) {
                return Number(row.averageLoadTimeMs) || 0;
            })
        );

        rows.forEach(function (row) {
            const item = document.createElement("div");
            item.className = "chart-row";

            const label = document.createElement("span");

            label.className = "chart-label";
            label.textContent = pageLabel(row.pageUrl);
            label.title = row.pageUrl;

            const samples = document.createElement("small");

            samples.className = "chart-samples";
            samples.textContent =
                numberFormat.format(row.measurements) +
                " measurements";

            label.appendChild(samples);

            const track = document.createElement("div");

            track.className = "chart-track";
            track.setAttribute("aria-hidden", "true");

            const bar = document.createElement("div");

            bar.className = "chart-bar chart-bar-performance";

            const value = Number(row.averageLoadTimeMs) || 0;

            const percentage = maximum > 0
                ? Math.max(
                    0,
                    Math.min(100, value / maximum * 100)
                )
                : 0;

            bar.style.width = percentage + "%";
            track.appendChild(bar);

            const valueLabel = document.createElement("strong");

            valueLabel.className = "chart-value";
            valueLabel.textContent = duration(row.averageLoadTimeMs);

            item.append(label, track, valueLabel);
            chart.appendChild(item);
        });
    }

    function renderRecords(records) {
        tableBody.replaceChildren();

        if (records.length === 0) {
            const row = document.createElement("tr");
            const cell = document.createElement("td");

            cell.colSpan = 4;
            cell.textContent =
                "No performance records in this date range.";

            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }

        records.forEach(function (record) {
            const row = document.createElement("tr");

            const collected = document.createElement("td");

            collected.textContent = record.collectedAt
                ? record.collectedAt + " UTC"
                : "—";

            const page = document.createElement("td");
            const name = document.createElement("strong");

            name.textContent = pageLabel(record.pageUrl);

            const url = document.createElement("small");

            url.className = "url-detail";
            url.textContent = record.pageUrl;

            page.append(name, url);

            const loadTime = document.createElement("td");
            loadTime.textContent = duration(record.totalLoadTimeMs);

            const session = document.createElement("td");

            session.className = "performance-session";
            session.textContent = String(record.sessionId || "—");

            row.append(collected, page, loadTime, session);
            tableBody.appendChild(row);
        });
    }

    function renderReport(payload) {
        const summary = payload.summary;
        const count = Number(summary.measurements);

        setText(
            "performance-measurements",
            numberFormat.format(count)
        );

        setText(
            "performance-average",
            count > 0 ? duration(summary.averageLoadTimeMs) : "—"
        );

        setText(
            "performance-fastest",
            count > 0 ? duration(summary.fastestLoadTimeMs) : "—"
        );

        setText(
            "performance-slowest",
            count > 0 ? duration(summary.slowestLoadTimeMs) : "—"
        );

        renderChart(payload.byPage);
        renderRecords(payload.records);

        status.className = "status-message status-success";

        status.textContent =
            "Data from " + payload.dateRange.start +
            " through " + payload.dateRange.end +
            " (UTC). Showing the latest " +
            numberFormat.format(payload.records.length) +
            " of " + numberFormat.format(count) +
            " measurements in the table.";
    }

    async function loadReport() {
        const thisRequest = ++requestNumber;

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();

        applyButton.disabled = true;
        resetButton.disabled = true;

        status.className = "status-message";
        status.textContent = "Loading performance data...";

        showPlaceholder("Loading performance data...");

        const query = new URLSearchParams({
            start: startInput.value,
            end: endInput.value
        });

        try {
            const response = await fetch(
                "/api/performance?" + query,
                {
                    headers: {
                        Accept: "application/json"
                    },
                    credentials: "same-origin",
                    cache: "no-store",
                    signal: activeRequest.signal
                }
            );

            if (thisRequest !== requestNumber) {
                return;
            }

            if (response.status === 401) {
                window.location.replace("/login.php");
                return;
            }

            const payload = await response.json().catch(function () {
                return {};
            });

            if (thisRequest !== requestNumber) {
                return;
            }

            if (!response.ok) {
                throw new Error(
                    payload.error ||
                    "The server returned HTTP " + response.status + "."
                );
            }

            if (
                payload.success !== true ||
                !payload.summary ||
                !payload.dateRange ||
                !Array.isArray(payload.byPage) ||
                !Array.isArray(payload.records)
            ) {
                throw new Error(
                    "The server returned an unexpected response."
                );
            }

            renderReport(payload);
        } catch (error) {
            if (
                thisRequest !== requestNumber ||
                error.name === "AbortError"
            ) {
                return;
            }

            showPlaceholder("Report data is unavailable.");

            status.className = "status-message status-error";

            status.textContent =
                error.message || "Unable to load the report.";
        } finally {
            if (thisRequest === requestNumber) {
                applyButton.disabled = false;
                resetButton.disabled = false;
            }
        }
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();

        if (form.reportValidity()) {
            loadReport();
        }
    });

    resetButton.addEventListener("click", function () {
        startInput.value = form.dataset.defaultStart;
        endInput.value = form.dataset.defaultEnd;

        loadReport();
    });

    loadReport();
})();

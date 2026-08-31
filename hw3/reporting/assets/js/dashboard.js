(function () {
    "use strict";

    const filterForm =
        document.getElementById("dashboard-filter");

    const startInput =
        document.getElementById("start-date");

    const endInput =
        document.getElementById("end-date");

    const applyButton =
        document.getElementById("apply-filter");

    const resetButton =
        document.getElementById("reset-filter");

    const statusMessage =
        document.getElementById("dashboard-status");

    const numberFormatter =
        new Intl.NumberFormat("en-US");

    function formatNumber(value) {
        return numberFormatter.format(Number(value) || 0);
    }

    function formatDuration(value) {
        const milliseconds = Number(value);

        if (!Number.isFinite(milliseconds)) {
            return "—";
        }

        if (milliseconds >= 1000) {
            return (milliseconds / 1000).toFixed(2) + " s";
        }

        return milliseconds.toFixed(1) + " ms";
    }

    function pageLabel(pageUrl) {
        try {
            const parsedUrl = new URL(pageUrl);

            return parsedUrl.pathname || "/";
        } catch (error) {
            return pageUrl;
        }
    }

    function setSectionVisibility(sectionId, visible) {
        const section = document.getElementById(sectionId);

        if (section) {
            section.hidden = !visible;
        }
    }

    function updateMetric(elementId, value, formatter) {
        const element = document.getElementById(elementId);

        if (!element) {
            return;
        }

        const card = element.closest(".metric-card");

        if (typeof value === "undefined") {
            card.hidden = true;
            return;
        }

        card.hidden = false;
        element.textContent = formatter(value);
    }

    function renderHorizontalChart(
        containerId,
        rows,
        labelProperty,
        valueProperty,
        valueFormatter,
        colorClass,
        usePageLabel
    ) {
        const container =
            document.getElementById(containerId);

        container.replaceChildren();

        if (!Array.isArray(rows) || rows.length === 0) {
            const emptyMessage =
                document.createElement("p");

            emptyMessage.className = "empty-state";
            emptyMessage.textContent =
                "No data was collected during this period.";

            container.appendChild(emptyMessage);
            return;
        }

        const values = rows.map(function (row) {
            return Number(row[valueProperty]) || 0;
        });

        const maximumValue = Math.max(...values, 1);

        rows.forEach(function (row) {
            const value =
                Number(row[valueProperty]) || 0;

            const originalLabel =
                String(row[labelProperty] ?? "Unknown");

            const displayedLabel = usePageLabel
                ? pageLabel(originalLabel)
                : originalLabel;

            const chartRow =
                document.createElement("div");

            chartRow.className = "chart-row";
            chartRow.setAttribute(
                "aria-label",
                displayedLabel +
                    ": " +
                    valueFormatter(value)
            );

            const label =
                document.createElement("span");

            label.className = "chart-label";
            label.textContent = displayedLabel;
            label.title = originalLabel;

            const track =
                document.createElement("div");

            track.className = "chart-track";

            const bar =
                document.createElement("div");

            bar.className =
                "chart-bar " + colorClass;

            const width =
                Math.max((value / maximumValue) * 100, 2);

            bar.style.width = width + "%";

            const valueLabel =
                document.createElement("strong");

            valueLabel.className = "chart-value";
            valueLabel.textContent =
                valueFormatter(value);

            track.appendChild(bar);

            chartRow.append(
                label,
                track,
                valueLabel
            );

            container.appendChild(chartRow);
        });
    }

    function renderTopPages(rows) {
        const tableBody =
            document.getElementById("top-pages-table");

        tableBody.replaceChildren();

        if (!Array.isArray(rows) || rows.length === 0) {
            const tableRow =
                document.createElement("tr");

            const tableCell =
                document.createElement("td");

            tableCell.colSpan = 3;
            tableCell.textContent =
                "No page data was collected during this period.";

            tableRow.appendChild(tableCell);
            tableBody.appendChild(tableRow);
            return;
        }

        rows.forEach(function (row) {
            const tableRow =
                document.createElement("tr");

            const pageCell =
                document.createElement("td");

            const pageName =
                document.createElement("strong");

            pageName.textContent =
                pageLabel(row.pageUrl);

            const fullUrl =
                document.createElement("small");

            fullUrl.className = "url-detail";
            fullUrl.textContent = row.pageUrl;

            pageCell.append(pageName, fullUrl);

            const pageLoadsCell =
                document.createElement("td");

            pageLoadsCell.textContent =
                formatNumber(row.pageLoads);

            const sessionsCell =
                document.createElement("td");

            sessionsCell.textContent =
                formatNumber(row.uniqueSessions);

            tableRow.append(
                pageCell,
                pageLoadsCell,
                sessionsCell
            );

            tableBody.appendChild(tableRow);
        });
    }

    function renderDashboard(payload) {
        const permissions =
            payload.permissions || {};

        const summary =
            payload.summary || {};

        const charts =
            payload.charts || {};

        const tables =
            payload.tables || {};

        setSectionVisibility(
            "technology-report",
            Boolean(permissions.technology)
        );

        setSectionVisibility(
            "top-pages-report",
            Boolean(permissions.technology)
        );

        setSectionVisibility(
            "performance-report",
            Boolean(permissions.performance)
        );

        setSectionVisibility(
            "behavior-report",
            Boolean(permissions.behavior)
        );

        updateMetric(
            "metric-page-loads",
            summary.pageLoads,
            formatNumber
        );

        updateMetric(
            "metric-unique-sessions",
            summary.uniqueSessions,
            formatNumber
        );

        updateMetric(
            "metric-average-load",
            summary.averageLoadTimeMs,
            formatDuration
        );

        updateMetric(
            "metric-fastest-load",
            summary.fastestLoadTimeMs,
            formatDuration
        );

        updateMetric(
            "metric-slowest-load",
            summary.slowestLoadTimeMs,
            formatDuration
        );

        updateMetric(
            "metric-activity-events",
            summary.activityEvents,
            formatNumber
        );

        if (permissions.technology) {
            renderHorizontalChart(
                "page-load-chart",
                charts.pageLoadsByPage,
                "pageUrl",
                "pageLoads",
                formatNumber,
                "chart-bar-technology",
                true
            );

            renderTopPages(
                tables.topPages
            );
        }

        if (permissions.performance) {
            renderHorizontalChart(
                "performance-chart",
                charts.loadTimeByPage,
                "pageUrl",
                "averageLoadTimeMs",
                formatDuration,
                "chart-bar-performance",
                true
            );
        }

        if (permissions.behavior) {
            renderHorizontalChart(
                "activity-chart",
                charts.activityByType,
                "eventType",
                "total",
                formatNumber,
                "chart-bar-behavior",
                false
            );
        }

        startInput.value =
            payload.dateRange.start;

        endInput.value =
            payload.dateRange.end;

        statusMessage.className =
            "status-message status-success";

        statusMessage.textContent =
            "Showing data from " +
            payload.dateRange.start +
            " through " +
            payload.dateRange.end +
            ".";
    }

    async function loadDashboard() {
        applyButton.disabled = true;

        statusMessage.className =
            "status-message";

        statusMessage.textContent =
            "Loading analytics data...";

        const query = new URLSearchParams({
            start: startInput.value,
            end: endInput.value
        });

        try {
            const response = await fetch(
                "/api/overview?" + query.toString(),
                {
                    headers: {
                        Accept: "application/json"
                    },
                    credentials: "same-origin"
                }
            );

            const payload = await response
                .json()
                .catch(function () {
                    return {};
                });

            if (response.status === 401) {
                window.location.href = "/login.php";
                return;
            }

            if (!response.ok) {
                throw new Error(
                    payload.error ||
                    "Unable to load analytics data."
                );
            }

            renderDashboard(payload);
        } catch (error) {
            statusMessage.className =
                "status-message status-error";

            statusMessage.textContent =
                error.message ||
                "Unable to load analytics data.";
        } finally {
            applyButton.disabled = false;
        }
    }

    filterForm.addEventListener(
        "submit",
        function (event) {
            event.preventDefault();
            loadDashboard();
        }
    );

    resetButton.addEventListener(
        "click",
        function () {
            startInput.value =
                filterForm.dataset.defaultStart;

            endInput.value =
                filterForm.dataset.defaultEnd;

            loadDashboard();
        }
    );

    loadDashboard();
})();
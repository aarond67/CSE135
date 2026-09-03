(function () {
    "use strict";

    const filterForm = document.getElementById("dashboard-filter");
    const startInput = document.getElementById("start-date");
    const endInput = document.getElementById("end-date");
    const applyButton = document.getElementById("apply-filter");
    const resetButton = document.getElementById("reset-filter");
    const statusMessage = document.getElementById("dashboard-status");
    const pageChart = document.getElementById("page-load-chart");
    const activityChart = document.getElementById("activity-chart");
    const performanceTable = document.getElementById("page-performance-table");
    const numberFormatter = new Intl.NumberFormat("en-US");
    const metricIds = ["metric-page-loads", "metric-unique-sessions", "metric-average-load"];
    const sectionIds = ["technology-report", "behavior-report", "performance-report"];
    let requestNumber = 0;
    let activeRequest = null;

    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function validNumber(value) {
        return typeof value === "number" && Number.isFinite(value) && value >= 0;
    }

    function formatNumber(value) {
        return validNumber(value) ? numberFormatter.format(value) : "—";
    }

    function formatDuration(value) {
        if (!validNumber(value)) { return "—"; }
        return value >= 1000 ? (value / 1000).toFixed(2) + " s" : value.toFixed(1) + " ms";
    }

    function pageLabel(pageUrl) {
        try {
            const parsed = new URL(pageUrl);
            return parsed.pathname + parsed.search;
        } catch {
            return String(pageUrl || "Unknown page");
        }
    }

    function setVisible(id, visible) {
        document.getElementById(id).hidden = !visible;
    }

    function updateMetric(id, value, formatter, permitted) {
        const node = document.getElementById(id);
        node.closest(".metric-card").hidden = !permitted;
        node.textContent = permitted ? formatter(value) : "—";
    }

    function tableMessage(message) {
        const row = element("tr", "");
        const cell = element("td", "", message);
        cell.colSpan = 4;
        row.appendChild(cell);
        performanceTable.replaceChildren(row);
    }

    function clearDashboard(message) {
        metricIds.forEach(function (id) {
            const node = document.getElementById(id);
            node.textContent = "—";
            node.closest(".metric-card").hidden = true;
        });
        sectionIds.forEach(function (id) { setVisible(id, false); });
        pageChart.replaceChildren(element("p", "empty-state", message));
        activityChart.replaceChildren(element("p", "empty-state", message));
        tableMessage(message);
    }

    function barRow(label, value, maximum, colorClass) {
        const row = element("div", "chart-row");
        const name = element("span", "chart-label", label);
        const track = element("div", "chart-track");
        const bar = element("div", "chart-bar " + colorClass);
        // Zero stays zero: no minimum width that inflates small counts.
        bar.style.width = ((validNumber(value) ? value : 0) / maximum * 100) + "%";
        track.setAttribute("aria-hidden", "true");
        track.appendChild(bar);
        row.append(name, track, element("strong", "chart-value", formatNumber(value)));
        return row;
    }

    function renderPageChart(rows) {
        pageChart.replaceChildren();
        if (rows.length === 0) {
            pageChart.appendChild(element("p", "empty-state", "No page loads recorded in this period."));
            return;
        }
        const pages = rows.slice(0, 8);
        const maximum = Math.max(...pages.map(function (row) { return row.pageLoads; }), 1);
        pageChart.appendChild(element("p", "overview-scale", "Page loads · scale 0–" + formatNumber(maximum)));
        pages.forEach(function (page) {
            const row = barRow(pageLabel(page.pageUrl), page.pageLoads, maximum, "chart-bar-technology");
            row.querySelector(".chart-label").title = page.pageUrl;
            pageChart.appendChild(row);
        });
    }

    function renderInteractions(rows) {
        activityChart.replaceChildren();
        if (rows.length === 0) {
            activityChart.appendChild(element("p", "empty-state", "No click or scroll events recorded in this period."));
            return;
        }
        const pages = rows.slice(0, 8);
        const maximum = Math.max(...pages.flatMap(function (row) { return [row.clicks, row.scrolls]; }), 1);
        activityChart.appendChild(element("p", "overview-scale", "Events · shared scale 0–" + formatNumber(maximum)));
        pages.forEach(function (page) {
            const group = element("div", "overview-interaction-group");
            const heading = element("h3", "overview-page-label", pageLabel(page.pageUrl));
            heading.title = page.pageUrl;
            group.append(heading,
                barRow("Clicks", page.clicks, maximum, "overview-bar-clicks"),
                barRow("Scrolls", page.scrolls, maximum, "chart-bar-behavior"));
            activityChart.appendChild(group);
        });
    }

    function renderPerformance(rows) {
        performanceTable.replaceChildren();
        if (rows.length === 0) {
            tableMessage("No valid performance measurements recorded in this period.");
            return;
        }
        rows.slice(0, 10).forEach(function (page) {
            const row = element("tr", "");
            const name = element("th", "overview-page-cell", pageLabel(page.pageUrl));
            name.setAttribute("scope", "row");
            name.title = page.pageUrl;
            row.append(name,
                element("td", "overview-numeric", formatNumber(page.measurements)),
                element("td", "overview-numeric", formatDuration(page.averageLoadTimeMs)),
                element("td", "overview-numeric", formatDuration(page.slowestLoadTimeMs)));
            performanceTable.appendChild(row);
        });
    }

    function validPayload(payload) {
        if (!payload || payload.success !== true || !payload.permissions || !payload.summary ||
            !payload.charts || !payload.tables || !payload.dateRange ||
            typeof payload.dateRange.start !== "string" || typeof payload.dateRange.end !== "string") {
            return false;
        }
        const p = payload.permissions;
        if (![p.technology, p.performance, p.behavior].every(function (value) { return typeof value === "boolean"; }) ||
            ![p.technology, p.performance, p.behavior].some(Boolean)) {
            return false;
        }
        if (p.technology && (!validNumber(payload.summary.pageLoads) || !validNumber(payload.summary.uniqueSessions) ||
            !Array.isArray(payload.charts.pageLoadsByPage) || !payload.charts.pageLoadsByPage.every(function (row) {
                return row && typeof row.pageUrl === "string" && validNumber(row.pageLoads);
            }))) {
            return false;
        }
        if (p.performance && ((payload.summary.averageLoadTimeMs !== null && !validNumber(payload.summary.averageLoadTimeMs)) ||
            !Array.isArray(payload.tables.pagePerformance) || !payload.tables.pagePerformance.every(function (row) {
                return row && typeof row.pageUrl === "string" && validNumber(row.measurements) &&
                    validNumber(row.averageLoadTimeMs) && validNumber(row.slowestLoadTimeMs);
            }))) {
            return false;
        }
        return !p.behavior || (Array.isArray(payload.charts.interactionsByPage) &&
            payload.charts.interactionsByPage.every(function (row) {
                return row && typeof row.pageUrl === "string" && validNumber(row.clicks) && validNumber(row.scrolls);
            }));
    }

    function renderDashboard(payload) {
        const p = payload.permissions;
        setVisible("technology-report", p.technology);
        setVisible("behavior-report", p.behavior);
        setVisible("performance-report", p.performance);
        updateMetric("metric-page-loads", payload.summary.pageLoads, formatNumber, p.technology);
        updateMetric("metric-unique-sessions", payload.summary.uniqueSessions, formatNumber, p.technology);
        updateMetric("metric-average-load", payload.summary.averageLoadTimeMs, formatDuration, p.performance);
        if (p.technology) { renderPageChart(payload.charts.pageLoadsByPage); }
        if (p.behavior) { renderInteractions(payload.charts.interactionsByPage); }
        if (p.performance) { renderPerformance(payload.tables.pagePerformance); }
        startInput.value = payload.dateRange.start;
        endInput.value = payload.dateRange.end;
        statusMessage.className = "status-message status-success";
        statusMessage.textContent = "Showing data from " + payload.dateRange.start + " through " +
            payload.dateRange.end + " (UTC). Only your permitted sections are shown.";
    }

    async function loadDashboard() {
        const thisRequest = ++requestNumber;
        if (activeRequest) { activeRequest.abort(); }
        activeRequest = new AbortController();
        applyButton.disabled = true;
        resetButton.disabled = true;
        statusMessage.className = "status-message";
        statusMessage.textContent = "Loading analytics data...";
        clearDashboard("Loading analytics data...");
        pageChart.setAttribute("aria-busy", "true");
        activityChart.setAttribute("aria-busy", "true");
        performanceTable.setAttribute("aria-busy", "true");
        const query = new URLSearchParams({ start: startInput.value, end: endInput.value });
        try {
            const response = await fetch("/api/overview?" + query, {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
                cache: "no-store",
                signal: activeRequest.signal
            });
            if (thisRequest !== requestNumber) { return; }
            if (response.status === 401) {
                window.location.replace("/login.php");
                return;
            }
            const payload = await response.json().catch(function () { return {}; });
            if (thisRequest !== requestNumber) { return; }
            if (!response.ok) {
                throw new Error(payload.error || "Unable to load analytics data.");
            }
            if (!validPayload(payload)) {
                throw new Error("The server returned an unexpected overview response. Please refresh after deployment finishes.");
            }
            renderDashboard(payload);
        } catch (error) {
            if (thisRequest !== requestNumber || error.name === "AbortError") { return; }
            clearDashboard("Dashboard data is unavailable.");
            statusMessage.className = "status-message status-error";
            statusMessage.textContent = error.message || "Unable to load analytics data.";
        } finally {
            if (thisRequest === requestNumber) {
                applyButton.disabled = false;
                resetButton.disabled = false;
                pageChart.setAttribute("aria-busy", "false");
                activityChart.setAttribute("aria-busy", "false");
                performanceTable.setAttribute("aria-busy", "false");
            }
        }
    }

    filterForm.addEventListener("submit", function (event) {
        event.preventDefault();
        if (filterForm.reportValidity()) { loadDashboard(); }
    });
    resetButton.addEventListener("click", function () {
        startInput.value = filterForm.dataset.defaultStart;
        endInput.value = filterForm.dataset.defaultEnd;
        loadDashboard();
    });
    loadDashboard();
})();

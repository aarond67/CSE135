(function () {
    "use strict";

    const filterForm = document.getElementById("dashboard-filter");
    const startInput = document.getElementById("start-date");
    const endInput = document.getElementById("end-date");
    const applyButton = document.getElementById("apply-filter");
    const resetButton = document.getElementById("reset-filter");
    const statusMessage = document.getElementById("dashboard-status");
    const pageChart = document.getElementById("page-load-chart");
    const shoppingChart = document.getElementById("shopping-chart");
    const performanceTable = document.getElementById("page-performance-table");
    const numberFormatter = new Intl.NumberFormat("en-US");
    const metricIds = ["metric-page-loads", "metric-unique-sessions", "metric-average-load"];
    const sectionIds = ["technology-report", "behavior-report", "performance-report"];
    let latestRequestId = 0;
    let requestController = null;

    function createElement(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function isValidNumber(value) {
        return typeof value === "number" && Number.isFinite(value) && value >= 0;
    }

    function formatNumber(value) {
        return isValidNumber(value) ? numberFormatter.format(value) : "—";
    }

    function formatDuration(value) {
        if (!isValidNumber(value)) { return "—"; }
        return value >= 1000 ? (value / 1000).toFixed(2) + " s" : value.toFixed(1) + " ms";
    }

    function getPageLabel(pageUrl) {
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

    function showTableMessage(message) {
        const row = createElement("tr", "");
        const cell = createElement("td", "", message);
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
        pageChart.replaceChildren(createElement("p", "empty-state", message));
        shoppingChart.replaceChildren(createElement("p", "empty-state", message));
        showTableMessage(message);
    }

    function createBarRow(label, value, maximum, colorClass) {
        const row = createElement("div", "chart-row");
        const name = createElement("span", "chart-label", label);
        const track = createElement("div", "chart-track");
        const bar = createElement("div", "chart-bar " + colorClass);
        // A zero count should not draw a visible bar.
        bar.style.width = ((isValidNumber(value) ? value : 0) / maximum * 100) + "%";
        track.setAttribute("aria-hidden", "true");
        track.appendChild(bar);
        row.append(name, track, createElement("strong", "chart-value", formatNumber(value)));
        return row;
    }

    function renderPageChart(rows) {
        pageChart.replaceChildren();
        if (rows.length === 0) {
            pageChart.appendChild(createElement("p", "empty-state", "No page loads recorded in this period."));
            return;
        }
        const pages = rows.slice(0, 8);
        const maximum = Math.max(...pages.map(function (row) { return row.pageLoads; }), 1);
        pageChart.appendChild(createElement("p", "overview-scale", "Page loads · scale 0–" + formatNumber(maximum)));
        pages.forEach(function (page) {
            const row = createBarRow(getPageLabel(page.pageUrl), page.pageLoads, maximum, "chart-bar-technology");
            row.querySelector(".chart-label").title = page.pageUrl;
            pageChart.appendChild(row);
        });
    }

    function renderShoppingProgress(progress) {
        shoppingChart.replaceChildren();
        const visited = progress.visitedSessions;
        const products = progress.productSessions;
        const checkout = progress.checkoutSessions;
        const demoSuccess = progress.demoSuccessSessions;
        if (visited === 0) {
            shoppingChart.appendChild(createElement("p", "empty-state", "No shop sessions recorded in this period."));
            return;
        }
        shoppingChart.appendChild(createElement("p", "overview-scale",
            "Funnel width = sessions at each step. Widest step: " + formatNumber(visited) + "."));
        const steps = [
            ["Visited site", visited],
            ["Viewed a product", products],
            ["Then reached checkout", checkout],
            ["Demo success shown", demoSuccess]
        ];
        const funnel = createElement("ol", "shopping-funnel");
        funnel.setAttribute("role", "list");
        funnel.setAttribute("aria-label", "Shopping funnel, sessions at each step");

        steps.forEach(function ([label, count], index) {
            const step = createElement("li", "funnel-step");
            const track = createElement("div", "funnel-track");
            const band = createElement("div", "funnel-band");
            const width = count / visited * 100;

            // Keep counts proportional, including zero. Labels sit outside the shape.
            band.style.width = width + "%";
            track.setAttribute("aria-hidden", "true");
            track.appendChild(band);
            step.append(
                createElement("span", "funnel-label", label),
                track,
                createElement("strong", "funnel-count", formatNumber(count))
            );

            if (index < steps.length - 1) {
                const nextWidth = steps[index + 1][1] / visited * 100;
                const left = (100 - width) / 2;
                const nextLeft = (100 - nextWidth) / 2;
                const connector = createElement("div", "funnel-connector");

                // The pale connector joins two stage widths; it is not another count.
                connector.style.clipPath = "polygon(" + left + "% 0, " +
                    (100 - left) + "% 0, " + (100 - nextLeft) + "% 100%, " +
                    nextLeft + "% 100%)";
                connector.setAttribute("aria-hidden", "true");
                step.appendChild(connector);
            }
            funnel.appendChild(step);
        });
        shoppingChart.appendChild(funnel);

        const result = products > 0
            ? formatNumber(checkout) + " of " + formatNumber(products) +
                " product-viewing sessions reached checkout afterward (" +
                (checkout / products * 100).toFixed(1) + "%)."
            : "No product-page views were recorded, so checkout reach cannot be calculated.";
        shoppingChart.appendChild(createElement("p", "overview-shopping-result", result));

        if (checkout > 0) {
            shoppingChart.appendChild(createElement("p", "overview-shopping-followup",
                "Demo success was recorded afterward in " + formatNumber(demoSuccess) +
                " qualifying " + (demoSuccess === 1 ? "session" : "sessions") +
                ". No success record does not prove failure or abandonment."));
        }
    }

    function renderPerformanceTable(rows) {
        performanceTable.replaceChildren();
        if (rows.length === 0) {
            showTableMessage("No valid performance measurements recorded in this period.");
            return;
        }
        rows.slice(0, 10).forEach(function (page) {
            const row = createElement("tr", "");
            const name = createElement("th", "overview-page-cell", getPageLabel(page.pageUrl));
            name.setAttribute("scope", "row");
            name.title = page.pageUrl;
            row.append(name,
                createElement("td", "overview-numeric", formatNumber(page.measurements)),
                createElement("td", "overview-numeric", formatDuration(page.averageLoadTimeMs)),
                createElement("td", "overview-numeric", formatDuration(page.slowestLoadTimeMs)));
            performanceTable.appendChild(row);
        });
    }

    function isValidOverview(payload) {
        if (!payload || payload.success !== true || !payload.permissions || !payload.summary ||
            !payload.charts || !payload.tables || !payload.dateRange ||
            typeof payload.dateRange.start !== "string" || typeof payload.dateRange.end !== "string") {
            return false;
        }
        const permissions = payload.permissions;
        if (![permissions.technology, permissions.performance, permissions.behavior].every(function (value) { return typeof value === "boolean"; }) ||
            ![permissions.technology, permissions.performance, permissions.behavior].some(Boolean)) {
            return false;
        }
        if (permissions.technology && (!isValidNumber(payload.summary.pageLoads) || !isValidNumber(payload.summary.uniqueSessions) ||
            !Array.isArray(payload.charts.pageLoadsByPage) || !payload.charts.pageLoadsByPage.every(function (row) {
                return row && typeof row.pageUrl === "string" && isValidNumber(row.pageLoads);
            }))) {
            return false;
        }
        if (permissions.performance && ((payload.summary.averageLoadTimeMs !== null && !isValidNumber(payload.summary.averageLoadTimeMs)) ||
            !Array.isArray(payload.tables.pagePerformance) || !payload.tables.pagePerformance.every(function (row) {
                return row && typeof row.pageUrl === "string" && isValidNumber(row.measurements) &&
                    isValidNumber(row.averageLoadTimeMs) && isValidNumber(row.slowestLoadTimeMs);
            }))) {
            return false;
        }
        const progress = payload.charts.shoppingProgress;
        return !permissions.behavior || (progress &&
            [progress.visitedSessions, progress.productSessions, progress.checkoutSessions,
                progress.demoSuccessSessions].every(function (value) {
                return Number.isSafeInteger(value) && value >= 0;
            }) && progress.demoSuccessSessions <= progress.checkoutSessions &&
            progress.checkoutSessions <= progress.productSessions &&
            progress.productSessions <= progress.visitedSessions);
    }

    function renderDashboard(payload) {
        const permissions = payload.permissions;
        setVisible("technology-report", permissions.technology);
        setVisible("behavior-report", permissions.behavior);
        setVisible("performance-report", permissions.performance);
        updateMetric("metric-page-loads", payload.summary.pageLoads, formatNumber, permissions.technology);
        updateMetric("metric-unique-sessions", payload.summary.uniqueSessions, formatNumber, permissions.technology);
        updateMetric("metric-average-load", payload.summary.averageLoadTimeMs, formatDuration, permissions.performance);
        if (permissions.technology) { renderPageChart(payload.charts.pageLoadsByPage); }
        if (permissions.behavior) { renderShoppingProgress(payload.charts.shoppingProgress); }
        if (permissions.performance) { renderPerformanceTable(payload.tables.pagePerformance); }
        startInput.value = payload.dateRange.start;
        endInput.value = payload.dateRange.end;
        statusMessage.className = "status-message status-success";
        statusMessage.textContent = "Showing data from " + payload.dateRange.start + " through " +
            payload.dateRange.end + " (UTC). Only your permitted sections are shown.";
    }

    async function loadDashboard() {
        // A slow response from an older filter must not replace newer results.
        const requestId = ++latestRequestId;
        if (requestController) { requestController.abort(); }
        requestController = new AbortController();
        applyButton.disabled = true;
        resetButton.disabled = true;
        statusMessage.className = "status-message";
        statusMessage.textContent = "Loading analytics data...";
        clearDashboard("Loading analytics data...");
        pageChart.setAttribute("aria-busy", "true");
        shoppingChart.setAttribute("aria-busy", "true");
        performanceTable.setAttribute("aria-busy", "true");
        const query = new URLSearchParams({ start: startInput.value, end: endInput.value });
        try {
            const response = await fetch("/api/overview?" + query, {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
                cache: "no-store",
                signal: requestController.signal
            });
            if (requestId !== latestRequestId) { return; }
            if (response.status === 401) {
                window.location.replace("/login.php");
                return;
            }
            const payload = await response.json().catch(function () { return {}; });
            if (requestId !== latestRequestId) { return; }
            if (!response.ok) {
                throw new Error(payload.error || "Unable to load analytics data.");
            }
            if (!isValidOverview(payload)) {
                throw new Error("The server returned an unexpected overview response. Please refresh after deployment finishes.");
            }
            renderDashboard(payload);
        } catch (error) {
            if (requestId !== latestRequestId || error.name === "AbortError") { return; }
            clearDashboard("Dashboard data is unavailable.");
            statusMessage.className = "status-message status-error";
            statusMessage.textContent = error.message || "Unable to load analytics data.";
        } finally {
            if (requestId === latestRequestId) {
                applyButton.disabled = false;
                resetButton.disabled = false;
                pageChart.setAttribute("aria-busy", "false");
                shoppingChart.setAttribute("aria-busy", "false");
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

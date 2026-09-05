(function () {
    "use strict";

    const filterForm = document.getElementById("performance-filter");
    const startInput = document.getElementById("performance-start");
    const endInput = document.getElementById("performance-end");
    const applyButton = document.getElementById("performance-apply");
    const resetButton = document.getElementById("performance-reset");
    const statusMessage = document.getElementById("performance-status");
    const budgetChart = document.getElementById("performance-budget-chart");
    const budgetTable = document.getElementById("performance-budget-table");
    const loadTimeChart = document.getElementById("performance-page-chart");
    const stageTable = document.getElementById("performance-stage-values");
    const dotButton = document.getElementById("performance-view-dots");
    const stageButton = document.getElementById("performance-view-stages");
    const chartTitle = document.getElementById("performance-chart-title");
    const chartDescription = document.getElementById("performance-chart-description");
    const chartNote = document.getElementById("performance-chart-note");
    const chartDetail = document.getElementById("performance-chart-detail");
    const exportLink = document.getElementById("performance-export");

    const stages = [
        { key: "beforeRequestMs", label: "Before request", className: "before" },
        { key: "waitingMs", label: "Waiting for response", className: "waiting" },
        { key: "downloadMs", label: "HTML download", className: "download" },
        { key: "afterResponseMs", label: "After HTML to load end", className: "after" }
    ];

    const numberFormatter = new Intl.NumberFormat("en-US");

    const metricIds = [
        "performance-measurements",
        "performance-average",
        "performance-within-budget",
        "performance-over-budget"
    ];

    let latestRequestId = 0;
    let requestController = null;
    let selectedChart = "dots";
    let sampleRecords = null;
    let totalMeasurements = 0;

    function setText(id, value) {
        document.getElementById(id).textContent = value;
    }

    function formatDuration(value) {
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

    function getPageLabel(value) {
        try {
            const url = new URL(value);

            return url.pathname + url.search;
        } catch {
            return String(value || "Unknown page");
        }
    }

    function showPlaceholder(message) {
        sampleRecords = null;
        dotButton.disabled = true;
        stageButton.disabled = true;
        chartNote.textContent = "";
        chartDetail.hidden = true;
        chartDetail.textContent = "";

        metricIds.forEach(function (id) {
            setText(id, "—");
        });

        const paragraph = document.createElement("p");
        paragraph.className = "empty-state";
        paragraph.textContent = message;

        loadTimeChart.replaceChildren(paragraph);

        budgetChart.replaceChildren(createElement("p", "empty-state", message));
        budgetTable.replaceChildren(createElement("p", "empty-state", message));
        stageTable.replaceChildren(createElement("p", "empty-state", message));
    }

    function isValidDuration(value) {
        return typeof value === "number" && Number.isFinite(value) && value >= 0;
    }

    function formatMilliseconds(value) {
        return new Intl.NumberFormat("en-US", {
            maximumFractionDigits: 1
        }).format(value);
    }

    // Round up to a readable scale and use it for every page in this view.
    function getAxisMaximum(value) {
        if (value <= 0) {
            return 1;
        }

        const power = Math.pow(10, Math.floor(Math.log10(value)));
        const step = [1, 2, 5, 10].find(function (candidate) {
            return candidate * power >= value;
        });

        return step * power;
    }

    function createElement(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    function addAxis(maximum) {
        const row = createElement("div", "performance-viz-row performance-viz-axis-row");
        const axis = createElement("div", "performance-viz-axis");

        for (let index = 0; index <= 4; index++) {
            const tick = createElement("span", "performance-viz-tick",
                formatMilliseconds(maximum * index / 4));
            tick.style.left = (index * 25) + "%";
            axis.appendChild(tick);
        }

        row.append(createElement("span", "performance-axis-label", "Load time (ms)"),
            axis, createElement("span", ""));
        loadTimeChart.appendChild(row);
    }

    function createPageLabel(url, count) {
        const label = createElement("div", "performance-viz-label", getPageLabel(url));
        label.title = url;
        label.appendChild(createElement("small", "chart-samples",
            numberFormatter.format(count) + (count === 1 ? " measurement" : " measurements")));
        return label;
    }

    function createTimingLabel(value, label) {
        const node = createElement("div", "performance-viz-value");
        node.append(createElement("strong", "", formatDuration(value)),
            createElement("small", "", label));
        return node;
    }

    function emptyChart(message) {
        loadTimeChart.appendChild(createElement("p", "empty-state", message));
    }

    function renderBudgetChart(pages, budgetMs) {
        budgetChart.replaceChildren();

        if (!pages.length) {
            budgetChart.appendChild(createElement(
                "p",
                "empty-state",
                "No valid page-load measurements in this date range."
            ));
            return;
        }

        const largestP75 = Math.max(...pages.map(function (page) {
            return page.p75LoadTimeMs;
        }), budgetMs);
        const axisMaximum = getAxisMaximum(largestP75);
        const budgetPosition = Math.min(budgetMs / axisMaximum * 100, 100);

        const note = createElement(
            "p",
            "performance-budget-scale",
            "Shared scale 0–" + formatMilliseconds(axisMaximum) +
                " ms. Dashed line = " + formatMilliseconds(budgetMs) + " ms budget."
        );
        budgetChart.appendChild(note);

        pages.slice(0, 10).forEach(function (page) {
            const row = createElement("div", "performance-budget-row");
            const label = createElement("span", "chart-label", getPageLabel(page.pageUrl));
            const track = createElement("div", "performance-budget-track");
            const bar = createElement(
                "span",
                "performance-budget-bar " + (page.withinBudget ? "is-within" : "is-over")
            );
            const marker = createElement("span", "performance-budget-marker");
            const value = createElement("strong", "performance-budget-value");

            label.title = page.pageUrl;
            bar.style.width = Math.min(page.p75LoadTimeMs / axisMaximum * 100, 100) + "%";
            marker.style.left = budgetPosition + "%";
            marker.setAttribute("aria-hidden", "true");
            value.textContent = formatDuration(page.p75LoadTimeMs);
            value.appendChild(createElement(
                "small",
                page.withinBudget ? "budget-pass" : "budget-fail",
                page.withinBudget ? "Within budget" : "Over budget"
            ));
            track.setAttribute("aria-hidden", "true");
            track.append(bar, marker);
            row.append(label, track, value);
            budgetChart.appendChild(row);
        });
    }

    function renderBudgetTable(pages, budgetMs) {
        if (!pages.length) {
            budgetTable.replaceChildren(createElement(
                "p",
                "empty-state",
                "No valid page-load measurements in this date range."
            ));
            return;
        }

        const wrapper = createElement("div", "table-wrapper");
        wrapper.tabIndex = 0;
        wrapper.setAttribute("role", "region");
        wrapper.setAttribute("aria-label", "Performance budget results; scroll horizontally if needed");
        const table = createElement("table", "data-table");
        const head = document.createElement("thead");
        const headingRow = document.createElement("tr");

        ["Page", "Measurements", "p75 load", "Average", "Slowest", "Budget result"].forEach(function (heading) {
            const cell = document.createElement("th");
            cell.scope = "col";
            cell.textContent = heading;
            headingRow.appendChild(cell);
        });
        head.appendChild(headingRow);

        const body = document.createElement("tbody");
        pages.forEach(function (page) {
            const row = document.createElement("tr");
            const pageCell = document.createElement("th");
            pageCell.scope = "row";
            pageCell.className = "url-cell";
            pageCell.title = page.pageUrl;
            pageCell.textContent = getPageLabel(page.pageUrl);
            row.appendChild(pageCell);

            [
                numberFormatter.format(page.measurements),
                formatDuration(page.p75LoadTimeMs),
                formatDuration(page.averageLoadTimeMs),
                formatDuration(page.slowestLoadTimeMs)
            ].forEach(function (value) {
                row.appendChild(createElement("td", "", value));
            });

            row.appendChild(createElement(
                "td",
                page.withinBudget ? "budget-pass" : "budget-fail",
                page.withinBudget
                    ? "Within " + formatMilliseconds(budgetMs) + " ms"
                    : "Over by " + formatDuration(page.p75LoadTimeMs - budgetMs)
            ));
            body.appendChild(row);
        });

        table.append(head, body);
        wrapper.appendChild(table);
        budgetTable.replaceChildren(wrapper);
    }

    function renderDots(records) {
        const validRecords = records.filter(function (record) {
            return isValidDuration(record.totalLoadTimeMs);
        });
        const recordsByPage = new Map();

        validRecords.forEach(function (record) {
            if (!recordsByPage.has(record.pageUrl)) {
                recordsByPage.set(record.pageUrl, []);
            }
            recordsByPage.get(record.pageUrl).push(record);
        });

        chartNote.textContent += " " + validRecords.length + " dots; " +
            (records.length - validRecords.length) + " records without a valid total omitted. " +
            "Vertical spacing only separates nearby dots; it does not represent another metric.";

        if (!validRecords.length) {
            emptyChart("No valid individual load times in this date range.");
            return;
        }

        const maximum = getAxisMaximum(Math.max(...validRecords.map(function (record) {
            return record.totalLoadTimeMs;
        })));
        addAxis(maximum);

        const groups = Array.from(recordsByPage.entries()).sort(function (a, b) {
            return Math.max(...b[1].map(function (r) { return r.totalLoadTimeMs; })) -
                Math.max(...a[1].map(function (r) { return r.totalLoadTimeMs; }));
        });

        groups.forEach(function (group) {
            const url = group[0];
            const measurements = group[1].slice().sort(function (a, b) {
                return a.totalLoadTimeMs - b.totalLoadTimeMs;
            });
            const row = createElement("div", "performance-viz-row");
            const plot = createElement("div", "performance-viz-plot");
            const lastDotByLane = [];

            measurements.forEach(function (record) {
                // Stack nearby dots vertically so each one can be selected.
                const position = record.totalLoadTimeMs / maximum * 100;
                let lane = lastDotByLane.findIndex(function (lastPosition) {
                    return position - lastPosition >= 12;
                });
                if (lane === -1) {
                    lane = lastDotByLane.length;
                }
                lastDotByLane[lane] = position;

                const dot = createElement("button", "performance-dot");
                const description = "Record " + record.id + ": " + getPageLabel(url) +
                    ", " + formatMilliseconds(record.totalLoadTimeMs) + " ms, " +
                    record.collectedAt + " UTC";
                dot.type = "button";
                dot.title = description;
                dot.style.left = position + "%";
                dot.style.top = (16 + lane * 28) + "px";
                dot.setAttribute("aria-label", description);
                dot.setAttribute("aria-pressed", "false");
                dot.setAttribute("aria-controls", "performance-chart-detail");
                dot.addEventListener("click", function () {
                    loadTimeChart.querySelectorAll(".performance-dot").forEach(function (other) {
                        other.setAttribute("aria-pressed", "false");
                    });
                    dot.setAttribute("aria-pressed", "true");
                    chartDetail.hidden = false;
                    chartDetail.textContent = description + ". Session: " + record.sessionId + ".";
                });
                plot.appendChild(dot);
            });

            plot.style.height = Math.max(48, lastDotByLane.length * 28 + 8) + "px";
            const slowest = measurements[measurements.length - 1].totalLoadTimeMs;
            row.append(createPageLabel(url, measurements.length), plot, createTimingLabel(slowest, "slowest"));
            loadTimeChart.appendChild(row);
        });
    }

    function groupLoadingStages(records) {
        const groups = new Map();

        records.forEach(function (record) {
            const timing = record.loadingStages;
            if (!timing || !isValidDuration(record.totalLoadTimeMs) ||
                !stages.every(function (stage) { return isValidDuration(timing[stage.key]); })) {
                return;
            }
            const total = stages.reduce(function (sum, stage) {
                return sum + timing[stage.key];
            }, 0);
            if (total <= 0 || Math.abs(total - record.totalLoadTimeMs) > 1) {
                return;
            }

            if (!groups.has(record.pageUrl)) {
                groups.set(record.pageUrl, { pageUrl: record.pageUrl, count: 0,
                    beforeRequestMs: 0, waitingMs: 0, downloadMs: 0, afterResponseMs: 0 });
            }
            const group = groups.get(record.pageUrl);
            group.count++;
            stages.forEach(function (stage) {
                group[stage.key] += timing[stage.key];
            });
        });

        return Array.from(groups.values()).map(function (group) {
            group.total = 0;
            stages.forEach(function (stage) {
                group[stage.key] /= group.count;
                group.total += group[stage.key];
            });
            return group;
        }).sort(function (a, b) { return b.total - a.total; });
    }

    function renderStages(records) {
        const groups = groupLoadingStages(records);
        const validCount = groups.reduce(function (sum, group) { return sum + group.count; }, 0);
        chartNote.textContent += " Stage averages use " + validCount + " complete measurements; " +
            (records.length - validCount) + " with missing or inconsistent timings are excluded.";

        if (!groups.length) {
            emptyChart("No complete loading-stage measurements in this sample. " +
                "Individual load times may still be available.");
            return;
        }

        const legend = createElement("ul", "performance-stage-legend");
        stages.forEach(function (stage) {
            const item = createElement("li", "");
            const swatch = createElement("span", "performance-stage-swatch performance-stage-" + stage.className);
            swatch.setAttribute("aria-hidden", "true");
            item.append(swatch, createElement("span", "", stage.label));
            legend.appendChild(item);
        });
        loadTimeChart.appendChild(legend);

        const maximum = getAxisMaximum(Math.max(...groups.map(function (group) { return group.total; })));
        addAxis(maximum);

        groups.forEach(function (group) {
            const row = createElement("div", "performance-viz-row");
            const track = createElement("div", "performance-viz-stack");
            track.setAttribute("aria-hidden", "true");
            stages.forEach(function (stage) {
                const segment = createElement("span", "performance-stage-segment performance-stage-" + stage.className);
                segment.style.width = (group[stage.key] / maximum * 100) + "%";
                segment.title = stage.label + ": " + formatMilliseconds(group[stage.key]) + " ms";
                track.appendChild(segment);
            });
            row.append(createPageLabel(group.pageUrl, group.count), track, createTimingLabel(group.total, "average total"));
            loadTimeChart.appendChild(row);
        });
    }

    // Keep the same table below both chart views.
    function renderStageTable(records) {
        const groups = groupLoadingStages(records);
        const validCount = groups.reduce(function (sum, group) { return sum + group.count; }, 0);
        const wrapper = createElement("div", "table-wrapper performance-stage-table");
        wrapper.tabIndex = 0;
        wrapper.setAttribute("role", "region");
        wrapper.setAttribute("aria-label", "Loading-stage values; scroll horizontally if needed");
        const table = createElement("table", "data-table");
        table.appendChild(createElement("caption", "performance-table-caption",
            "Average durations in milliseconds, using " + validCount + " complete measurements from the latest " +
            numberFormatter.format(records.length) + " of " + numberFormatter.format(totalMeasurements) +
            " records in the selected dates (100 maximum). " + (records.length - validCount) +
            " with missing or inconsistent timings are excluded. Each row uses the same measurements for all stages. " +
            "Rows are ordered by average total, slowest first."));
        const head = createElement("thead", "");
        const headings = createElement("tr", "");
        ["Page", "Measurements", ...stages.map(function (stage) { return stage.label + " (ms)"; }),
            "Total (ms)"].forEach(function (label) {
            const cell = createElement("th", "", label);
            cell.setAttribute("scope", "col");
            headings.appendChild(cell);
        });
        head.appendChild(headings);
        const body = createElement("tbody", "");
        groups.forEach(function (group) {
            const row = createElement("tr", "");
            const page = createElement("td", "url-detail", getPageLabel(group.pageUrl));
            page.title = group.pageUrl;
            row.append(page, createElement("td", "", numberFormatter.format(group.count)));
            stages.forEach(function (stage) {
                row.appendChild(createElement("td", "", formatMilliseconds(group[stage.key])));
            });
            row.appendChild(createElement("td", "", formatMilliseconds(group.total)));
            body.appendChild(row);
        });
        if (!groups.length) {
            const row = createElement("tr", "");
            const cell = createElement("td", "empty-state", "No complete loading-stage measurements in this sample.");
            cell.colSpan = 7;
            row.appendChild(cell);
            body.appendChild(row);
        }
        table.append(head, body);
        wrapper.appendChild(table);
        stageTable.replaceChildren(wrapper);
    }

    function renderChart() {
        if (sampleRecords === null) {
            return;
        }

        loadTimeChart.replaceChildren();
        chartDetail.hidden = true;
        chartDetail.textContent = "";
        const dots = selectedChart === "dots";
        dotButton.setAttribute("aria-pressed", String(dots));
        stageButton.setAttribute("aria-pressed", String(!dots));
        chartTitle.textContent = dots ? "Individual load times" : "Average loading stages by page";
        chartDescription.textContent = dots
            ? "Each dot is one measured page load. Select a dot to inspect it. " +
                "Widely separated dots indicate inconsistent loading. The table below summarizes loading stages by page."
            : "Each bar adds four consecutive stages of the same page loads. " +
                "Waiting includes network and server effects; after HTML includes remaining resources and page work. " +
                "These are investigation clues, not proof of a server or network fault.";

        chartNote.textContent = "Chart sample: latest " + numberFormatter.format(sampleRecords.length) +
            " of " + numberFormatter.format(totalMeasurements) + " records in the selected dates (100 maximum). " +
            "Summary cards cover the full date range.";

        if (dots) {
            renderDots(sampleRecords);
        } else {
            renderStages(sampleRecords);
        }
    }

    function renderReport(payload) {
        const summary = payload.summary;
        const count = Number(summary.measurements);

        setText(
            "performance-measurements",
            numberFormatter.format(count)
        );

        setText(
            "performance-average",
            count > 0 ? formatDuration(summary.averageLoadTimeMs) : "—"
        );

        setText(
            "performance-within-budget",
            numberFormatter.format(summary.pagesWithinBudget)
        );

        setText(
            "performance-over-budget",
            numberFormatter.format(summary.pagesOverBudget)
        );

        renderBudgetChart(payload.byPage, summary.budgetMs);
        renderBudgetTable(payload.byPage, summary.budgetMs);

        sampleRecords = payload.records;
        totalMeasurements = count;
        dotButton.disabled = false;
        stageButton.disabled = false;
        renderChart();
        renderStageTable(payload.records);

        if (exportLink) {
            const exportQuery = new URLSearchParams({
                key: "performance-overview",
                start: payload.dateRange.start,
                end: payload.dateRange.end
            });
            exportLink.href = "/exports/report.php?" + exportQuery;
        }

        statusMessage.className = "status-message status-success";

        statusMessage.textContent =
            "Data from " + payload.dateRange.start +
            " through " + payload.dateRange.end +
            " (UTC). Using the latest " +
            numberFormatter.format(payload.records.length) +
            " of " + numberFormatter.format(count) +
            " measurements for charts and the stage summary; see sample notes for exclusions.";
    }

    async function loadReport() {
        // Ignore old filter responses, even if aborting the request was too late.
        const requestId = ++latestRequestId;

        if (requestController) {
            requestController.abort();
        }

        requestController = new AbortController();

        applyButton.disabled = true;
        resetButton.disabled = true;

        statusMessage.className = "status-message";
        statusMessage.textContent = "Loading performance data...";
        loadTimeChart.setAttribute("aria-busy", "true");
        budgetChart.setAttribute("aria-busy", "true");
        budgetTable.setAttribute("aria-busy", "true");
        stageTable.setAttribute("aria-busy", "true");

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
                    signal: requestController.signal
                }
            );

            if (requestId !== latestRequestId) {
                return;
            }

            if (response.status === 401) {
                window.location.replace("/login.php");
                return;
            }

            const payload = await response.json().catch(function () {
                return {};
            });

            if (requestId !== latestRequestId) {
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
                !Array.isArray(payload.records) ||
                !isValidDuration(payload.summary.budgetMs) ||
                !isValidDuration(payload.summary.pagesWithinBudget) ||
                !isValidDuration(payload.summary.pagesOverBudget) ||
                !payload.byPage.every(function (page) {
                    return typeof page.pageUrl === "string" &&
                        isValidDuration(page.measurements) &&
                        isValidDuration(page.p75LoadTimeMs) &&
                        isValidDuration(page.averageLoadTimeMs) &&
                        isValidDuration(page.slowestLoadTimeMs) &&
                        typeof page.withinBudget === "boolean";
                })
            ) {
                throw new Error(
                    "The server returned an unexpected response."
                );
            }

            renderReport(payload);
        } catch (error) {
            if (
                requestId !== latestRequestId ||
                error.name === "AbortError"
            ) {
                return;
            }

            showPlaceholder("Report data is unavailable.");

            statusMessage.className = "status-message status-error";

            statusMessage.textContent =
                error.message || "Unable to load the report.";
        } finally {
            if (requestId === latestRequestId) {
                applyButton.disabled = false;
                resetButton.disabled = false;
                loadTimeChart.setAttribute("aria-busy", "false");
                budgetChart.setAttribute("aria-busy", "false");
                budgetTable.setAttribute("aria-busy", "false");
                stageTable.setAttribute("aria-busy", "false");
            }
        }
    }

    filterForm.addEventListener("submit", function (event) {
        event.preventDefault();

        if (filterForm.reportValidity()) {
            loadReport();
        }
    });

    resetButton.addEventListener("click", function () {
        startInput.value = filterForm.dataset.defaultStart;
        endInput.value = filterForm.dataset.defaultEnd;

        loadReport();
    });

    dotButton.addEventListener("click", function () {
        selectedChart = "dots";
        renderChart();
    });

    stageButton.addEventListener("click", function () {
        selectedChart = "stages";
        renderChart();
    });

    loadReport();
})();

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
    const dotButton = document.getElementById("performance-view-dots");
    const stageButton = document.getElementById("performance-view-stages");
    const chartTitle = document.getElementById("performance-chart-title");
    const chartDescription = document.getElementById("performance-chart-description");
    const chartNote = document.getElementById("performance-chart-note");
    const chartDetail = document.getElementById("performance-chart-detail");

    const stages = [
        { key: "beforeRequestMs", label: "Before request", className: "before" },
        { key: "waitingMs", label: "Waiting for response", className: "waiting" },
        { key: "downloadMs", label: "HTML download", className: "download" },
        { key: "afterResponseMs", label: "After HTML to load end", className: "after" }
    ];

    const numberFormat = new Intl.NumberFormat("en-US");

    const metricIds = [
        "performance-measurements",
        "performance-average",
        "performance-fastest",
        "performance-slowest"
    ];

    let requestNumber = 0;
    let activeRequest = null;
    let chartView = "dots";
    let chartRecords = null;
    let totalMeasurements = 0;

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
        chartRecords = null;
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

        chart.replaceChildren(paragraph);

        const row = document.createElement("tr");
        const cell = document.createElement("td");

        cell.colSpan = 4;
        cell.textContent = message;

        row.appendChild(cell);
        tableBody.replaceChildren(row);
    }

    function validDuration(value) {
        return typeof value === "number" && Number.isFinite(value) && value >= 0;
    }

    function milliseconds(value) {
        return new Intl.NumberFormat("en-US", {
            maximumFractionDigits: 1
        }).format(value);
    }

    // A shared zero-based scale for every page in the selected view.
    function axisMaximum(value) {
        if (value <= 0) {
            return 1;
        }

        const power = Math.pow(10, Math.floor(Math.log10(value)));
        const step = [1, 2, 5, 10].find(function (candidate) {
            return candidate * power >= value;
        });

        return step * power;
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    function addAxis(maximum) {
        const row = element("div", "performance-viz-row performance-viz-axis-row");
        const axis = element("div", "performance-viz-axis");

        for (let index = 0; index <= 4; index++) {
            const tick = element("span", "performance-viz-tick",
                milliseconds(maximum * index / 4));
            tick.style.left = (index * 25) + "%";
            axis.appendChild(tick);
        }

        row.append(element("span", "performance-axis-label", "Load time (ms)"),
            axis, element("span", ""));
        chart.appendChild(row);
    }

    function rowLabel(url, count) {
        const label = element("div", "performance-viz-label", pageLabel(url));
        label.title = url;
        label.appendChild(element("small", "chart-samples",
            numberFormat.format(count) + (count === 1 ? " measurement" : " measurements")));
        return label;
    }

    function rowValue(value, label) {
        const node = element("div", "performance-viz-value");
        node.append(element("strong", "", duration(value)),
            element("small", "", label));
        return node;
    }

    function emptyChart(message) {
        chart.appendChild(element("p", "empty-state", message));
    }

    function renderDots(records) {
        const usable = records.filter(function (record) {
            return validDuration(record.totalLoadTimeMs);
        });
        const grouped = new Map();

        usable.forEach(function (record) {
            if (!grouped.has(record.pageUrl)) {
                grouped.set(record.pageUrl, []);
            }
            grouped.get(record.pageUrl).push(record);
        });

        chartNote.textContent += " " + usable.length + " dots; " +
            (records.length - usable.length) + " records without a valid total omitted. " +
            "Vertical spacing only separates nearby dots; it does not represent another metric.";

        if (!usable.length) {
            emptyChart("No valid individual load times in this date range.");
            return;
        }

        const maximum = axisMaximum(Math.max(...usable.map(function (record) {
            return record.totalLoadTimeMs;
        })));
        addAxis(maximum);

        const groups = Array.from(grouped.entries()).sort(function (a, b) {
            return Math.max(...b[1].map(function (r) { return r.totalLoadTimeMs; })) -
                Math.max(...a[1].map(function (r) { return r.totalLoadTimeMs; }));
        });

        groups.forEach(function (group) {
            const url = group[0];
            const measurements = group[1].slice().sort(function (a, b) {
                return a.totalLoadTimeMs - b.totalLoadTimeMs;
            });
            const row = element("div", "performance-viz-row");
            const plot = element("div", "performance-viz-plot");
            const lanes = [];

            measurements.forEach(function (record) {
                const position = record.totalLoadTimeMs / maximum * 100;
                let lane = lanes.findIndex(function (lastPosition) {
                    return position - lastPosition >= 12;
                });
                if (lane === -1) {
                    lane = lanes.length;
                }
                lanes[lane] = position;

                const dot = element("button", "performance-dot");
                const description = "Record " + record.id + ": " + pageLabel(url) +
                    ", " + milliseconds(record.totalLoadTimeMs) + " ms, " +
                    record.collectedAt + " UTC";
                dot.type = "button";
                dot.title = description;
                dot.style.left = position + "%";
                dot.style.top = (16 + lane * 28) + "px";
                dot.setAttribute("aria-label", description);
                dot.setAttribute("aria-pressed", "false");
                dot.setAttribute("aria-controls", "performance-chart-detail");
                dot.addEventListener("click", function () {
                    chart.querySelectorAll(".performance-dot").forEach(function (other) {
                        other.setAttribute("aria-pressed", "false");
                    });
                    dot.setAttribute("aria-pressed", "true");
                    chartDetail.hidden = false;
                    chartDetail.textContent = description + ". Session: " + record.sessionId + ".";
                });
                plot.appendChild(dot);
            });

            plot.style.height = Math.max(48, lanes.length * 28 + 8) + "px";
            const slowest = measurements[measurements.length - 1].totalLoadTimeMs;
            row.append(rowLabel(url, measurements.length), plot, rowValue(slowest, "slowest"));
            chart.appendChild(row);
        });
    }

    function stageGroups(records) {
        const groups = new Map();

        records.forEach(function (record) {
            const timing = record.loadingStages;
            if (!timing || !validDuration(record.totalLoadTimeMs) ||
                !stages.every(function (stage) { return validDuration(timing[stage.key]); })) {
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
        const groups = stageGroups(records);
        const validCount = groups.reduce(function (sum, group) { return sum + group.count; }, 0);
        chartNote.textContent += " Stage averages use " + validCount + " complete measurements; " +
            (records.length - validCount) + " with missing or inconsistent timings are excluded.";

        if (!groups.length) {
            emptyChart("No complete loading-stage measurements in this sample. " +
                "Individual load times may still be available.");
            return;
        }

        const legend = element("ul", "performance-stage-legend");
        stages.forEach(function (stage) {
            const item = element("li", "");
            const swatch = element("span", "performance-stage-swatch performance-stage-" + stage.className);
            swatch.setAttribute("aria-hidden", "true");
            item.append(swatch, element("span", "", stage.label));
            legend.appendChild(item);
        });
        chart.appendChild(legend);

        const maximum = axisMaximum(Math.max(...groups.map(function (group) { return group.total; })));
        addAxis(maximum);

        groups.forEach(function (group) {
            const row = element("div", "performance-viz-row");
            const track = element("div", "performance-viz-stack");
            track.setAttribute("aria-hidden", "true");
            stages.forEach(function (stage) {
                const segment = element("span", "performance-stage-segment performance-stage-" + stage.className);
                segment.style.width = (group[stage.key] / maximum * 100) + "%";
                segment.title = stage.label + ": " + milliseconds(group[stage.key]) + " ms";
                track.appendChild(segment);
            });
            row.append(rowLabel(group.pageUrl, group.count), track, rowValue(group.total, "average total"));
            chart.appendChild(row);
        });

        const wrapper = element("div", "table-wrapper performance-stage-table");
        wrapper.tabIndex = 0;
        wrapper.setAttribute("role", "region");
        wrapper.setAttribute("aria-label", "Loading-stage values; scroll horizontally if needed");
        const table = element("table", "data-table");
        table.appendChild(element("caption", "performance-table-caption",
            "Average stage durations in milliseconds, using only complete measurements in this sample."));
        const head = element("thead", "");
        const headings = element("tr", "");
        ["Page", "Measurements", ...stages.map(function (stage) { return stage.label + " (ms)"; }),
            "Total (ms)"].forEach(function (label) {
            const cell = element("th", "", label);
            cell.setAttribute("scope", "col");
            headings.appendChild(cell);
        });
        head.appendChild(headings);
        const body = element("tbody", "");
        groups.forEach(function (group) {
            const row = element("tr", "");
            const page = element("td", "url-detail", pageLabel(group.pageUrl));
            page.title = group.pageUrl;
            row.append(page, element("td", "", numberFormat.format(group.count)));
            stages.forEach(function (stage) {
                row.appendChild(element("td", "", milliseconds(group[stage.key])));
            });
            row.appendChild(element("td", "", milliseconds(group.total)));
            body.appendChild(row);
        });
        table.append(head, body);
        wrapper.appendChild(table);
        chart.appendChild(wrapper);
    }

    function renderChart() {
        if (chartRecords === null) {
            return;
        }

        chart.replaceChildren();
        chartDetail.hidden = true;
        chartDetail.textContent = "";
        const dots = chartView === "dots";
        dotButton.setAttribute("aria-pressed", String(dots));
        stageButton.setAttribute("aria-pressed", String(!dots));
        chartTitle.textContent = dots ? "Individual load times" : "Average loading stages by page";
        chartDescription.textContent = dots
            ? "Each dot is one measured page load. Select a dot to inspect it. " +
                "Widely separated dots indicate inconsistent loading; the table below has the exact records."
            : "Each bar adds four consecutive stages of the same page loads. " +
                "Waiting includes network and server effects; after HTML includes remaining resources and page work. " +
                "These are investigation clues, not proof of a server or network fault.";

        chartNote.textContent = "Chart sample: latest " + numberFormat.format(chartRecords.length) +
            " of " + numberFormat.format(totalMeasurements) + " records in the selected dates (100 maximum). " +
            "Summary cards cover the full date range.";

        if (dots) {
            renderDots(chartRecords);
        } else {
            renderStages(chartRecords);
        }
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

        chartRecords = payload.records;
        totalMeasurements = count;
        dotButton.disabled = false;
        stageButton.disabled = false;
        renderChart();
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
        chart.setAttribute("aria-busy", "true");

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
                chart.setAttribute("aria-busy", "false");
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

    dotButton.addEventListener("click", function () {
        chartView = "dots";
        renderChart();
    });

    stageButton.addEventListener("click", function () {
        chartView = "stages";
        renderChart();
    });

    loadReport();
})();

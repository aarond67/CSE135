// Collect test-site activity and send it to the PHP endpoint.
(function () {
    "use strict";

    const COLLECTOR_URL = "https://collector.baddecisions.site/log/";
    const SESSION_STORAGE_KEY = "_collector_sid";
    const SESSION_COOKIE_NAME = "_collector_sid";

    function createSessionId() {
        if (
            window.crypto &&
            typeof window.crypto.randomUUID === "function"
        ) {
            return window.crypto.randomUUID();
        }

        return (
            Date.now().toString(36) +
            "-" +
            Math.random().toString(36).substring(2)
        );
    }

    function setSessionCookie(sessionId) {
        document.cookie =
            SESSION_COOKIE_NAME +
            "=" +
            encodeURIComponent(sessionId) +
            "; Path=/; SameSite=Lax; Secure";
    }

    function getSessionId() {
        let sessionId = sessionStorage.getItem(SESSION_STORAGE_KEY);

        if (!sessionId) {
            sessionId = createSessionId();
            sessionStorage.setItem(SESSION_STORAGE_KEY, sessionId);
        }

        setSessionCookie(sessionId);
        return sessionId;
    }

    const sessionId = getSessionId();

    // Try a beacon first so queued data can still leave during navigation.
    function sendPayload(payload) {
        const json = JSON.stringify(payload);
        const blob = new Blob([json], {
            type: "application/json"
        });

        if (typeof navigator.sendBeacon === "function") {
            const accepted = navigator.sendBeacon(COLLECTOR_URL, blob);

            if (accepted) {
                return;
            }
        }

        fetch(COLLECTOR_URL, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: json,
            keepalive: true
        }).catch(function (error) {
            console.error("[Collector] Delivery failed:", error);
        });
    }

    const activityQueue = [];
    const ACTIVITY_SEND_INTERVAL = 5000;
    const MOUSE_SAMPLE_INTERVAL = 100;
    const SCROLL_SAMPLE_INTERVAL = 200;
    const IDLE_THRESHOLD = 2000;

    let lastMouseTime = 0;
    let lastScrollTime = 0;
    let idleTimer = null;
    let idleStartTime = null;
    let lastActivityTime = Date.now();
    const pageEnteredAt = Date.now();

    function queueActivity(eventType, data) {
        activityQueue.push({
            eventType: eventType,
            timestamp: new Date().toISOString(),
            data: data
        });
    }

    queueActivity("page-enter", {
        enteredAt: new Date(pageEnteredAt).toISOString()
    });

    function startIdleTimer() {
        window.clearTimeout(idleTimer);

        idleTimer = window.setTimeout(function () {
            idleStartTime = lastActivityTime;
        }, IDLE_THRESHOLD);
    }

    function noteUserActivity() {
        const currentTime = Date.now();

        if (idleStartTime !== null) {
            queueActivity("idle", {
                endedAt: new Date(currentTime).toISOString(),
                durationMilliseconds: currentTime - idleStartTime
            });

            idleStartTime = null;
        }

        lastActivityTime = currentTime;
        startIdleTimer();
    }

    function sendActivityBatch() {
        if (activityQueue.length === 0) {
            return;
        }

        const events = activityQueue.splice(0, activityQueue.length);

        const payload = {
            type: "activity",
            sessionId: sessionId,
            url: window.location.href,
            timestamp: new Date().toISOString(),
            data: {
                events: events
            }
        };

        console.log("[Collector] Sending activity batch:", payload);
        sendPayload(payload);
    }

    // Checkout sends this after showing "Order Placed!", not after a real payment.
    // Ignore event details so this event never includes checkout form values.
    let demoSuccessSent = false;

    window.addEventListener("cse135:demo-order-success", function () {
        if (
            demoSuccessSent ||
            window.location.origin !== "https://test.baddecisions.site" ||
            window.location.pathname !== "/checkout.html"
        ) {
            return;
        }

        // The demo calls submit twice. Count the message once per page load;
        // the dashboard separately counts each qualifying session once.
        demoSuccessSent = true;
        queueActivity("demo-order-success", { demo: true });
        sendActivityBatch();
    });

    // The test checkout only sends the step number. It never sends the
    // information typed into any checkout field.
    const checkoutStepsSent = new Set();

    window.addEventListener("cse135:checkout-step", function (event) {
        const step = Number(event.detail && event.detail.step);

        if (
            ![1, 2, 3].includes(step) ||
            checkoutStepsSent.has(step) ||
            window.location.origin !== "https://test.baddecisions.site" ||
            window.location.pathname !== "/checkout.html"
        ) {
            return;
        }

        checkoutStepsSent.add(step);
        queueActivity("checkout-step", { step: step });
        sendActivityBatch();
    });

    window.addEventListener("mousemove", function (event) {
        const currentTime = Date.now();

        if (currentTime - lastMouseTime < MOUSE_SAMPLE_INTERVAL) {
            return;
        }

        lastMouseTime = currentTime;

        queueActivity("mousemove", {
            x: event.clientX,
            y: event.clientY
        });
    });

    window.addEventListener("click", function (event) {
        queueActivity("click", {
            x: event.clientX,
            y: event.clientY,
            button: event.button
        });
    });

    window.addEventListener(
        "scroll",
        function () {
            const currentTime = Date.now();

            if (currentTime - lastScrollTime < SCROLL_SAMPLE_INTERVAL) {
                return;
            }

            lastScrollTime = currentTime;

            queueActivity("scroll", {
                x: window.scrollX,
                y: window.scrollY
            });
        },
        {
            passive: true
        }
    );

    function getRecordedKey(event) {
        const element = event.target;
        const tagName = element && element.tagName;

        // Keep single-character typing out of the standard form-field events.
        if (
            tagName === "INPUT" ||
            tagName === "TEXTAREA" ||
            tagName === "SELECT"
        ) {
            if (event.key.length === 1) {
                return "[redacted]";
            }
        }

        return event.key;
    }

    window.addEventListener("keydown", function (event) {
        queueActivity("keydown", {
            key: getRecordedKey(event)
        });
    });

    window.addEventListener("keyup", function (event) {
        queueActivity("keyup", {
            key: getRecordedKey(event)
        });
    });

    [
        "mousemove",
        "click",
        "scroll",
        "keydown",
        "keyup"
    ].forEach(function (eventName) {
        window.addEventListener(eventName, noteUserActivity, {
            passive: true
        });
    });

    startIdleTimer();

    window.setInterval(sendActivityBatch, ACTIVITY_SEND_INTERVAL);

    function cookiesAreEnabled() {
        const testCookie = "_collector_cookie_test";

        try {
            document.cookie =
                testCookie + "=1; Path=/; SameSite=Lax; Secure";

            const enabled = document.cookie
                .split(";")
                .some(function (cookie) {
                    return cookie.trim().startsWith(testCookie + "=");
                });

            document.cookie =
                testCookie +
                "=; Max-Age=0; Path=/; SameSite=Lax; Secure";

            return enabled;
        } catch (error) {
            return false;
        }
    }
    window.addEventListener(
        "pagehide",
        function () {
            const currentTime = Date.now();

            if (idleStartTime !== null) {
                queueActivity("idle", {
                    endedAt: new Date(currentTime).toISOString(),
                    durationMilliseconds: currentTime - idleStartTime
                });

                idleStartTime = null;
            }

            queueActivity("page-exit", {
                leftAt: new Date(currentTime).toISOString()
            });

            sendActivityBatch();
        },
        {
            once: true
        }
    );

    function cssIsEnabled() {
        const style = document.createElement("style");
        const testElement = document.createElement("div");

        style.textContent =
            ".collector-css-test { width: 37px !important; }";

        testElement.className = "collector-css-test";
        testElement.hidden = true;

        document.head.appendChild(style);
        document.body.appendChild(testElement);

        const enabled =
            window.getComputedStyle(testElement).width === "37px";

        testElement.remove();
        style.remove();

        return enabled;
    }

    function imagesAreEnabled() {
        return new Promise(function (resolve) {
            const image = new Image();
            let finished = false;

            const timeout = window.setTimeout(function () {
                finish(false);
            }, 4000);

            function finish(enabled) {
                if (finished) {
                    return;
                }

                finished = true;
                window.clearTimeout(timeout);
                resolve(enabled);
            }

            image.onload = function () {
                finish(true);
            };

            image.onerror = function () {
                finish(false);
            };

            image.src =
                window.location.origin +
                "/assets/usb-pet-rock.svg?collector-test=" +
                Date.now();
        });
    }

    function getNetworkInfo() {
        const connection =
            navigator.connection ||
            navigator.mozConnection ||
            navigator.webkitConnection;

        if (!connection) {
            return {
                supported: false,
                type: null,
                effectiveType: null,
                downlink: null,
                rtt: null,
                saveData: null
            };
        }

        return {
            supported: true,
            type: connection.type || null,
            effectiveType: connection.effectiveType || null,
            downlink: connection.downlink ?? null,
            rtt: connection.rtt ?? null,
            saveData: connection.saveData ?? null
        };
    }

    async function getStaticData() {
        const imagesEnabled = await imagesAreEnabled();

        return {
            userAgent: navigator.userAgent,
            language: navigator.language,
            cookiesEnabled: cookiesAreEnabled(),
            javascriptEnabled: true,
            imagesEnabled: imagesEnabled,
            cssEnabled: cssIsEnabled(),

            screenDimensions: {
                width: window.screen.width,
                height: window.screen.height
            },

            windowDimensions: {
                width: window.innerWidth,
                height: window.innerHeight
            },

            network: getNetworkInfo()
        };
    }
    function getPerformanceData() {
        const navigationEntries =
            window.performance.getEntriesByType("navigation");
        if (navigationEntries.length === 0) {
            return null;
        }
        const navigationTiming = navigationEntries[0];
        const pageLoadStart =
            window.performance.timeOrigin +
            navigationTiming.startTime;
        const pageLoadEnd =
            window.performance.timeOrigin +
            navigationTiming.loadEventEnd;
        const totalLoadTime =
            navigationTiming.loadEventEnd -
            navigationTiming.startTime;
        return {
            navigationTiming: navigationTiming.toJSON(),
            pageLoadStart: new Date(pageLoadStart).toISOString(),
            pageLoadEnd: new Date(pageLoadEnd).toISOString(),
            totalLoadTimeMilliseconds:
                Math.round(totalLoadTime * 100) / 100
        };
    }
    function sendPerformanceData() {
        const performanceData = getPerformanceData();
        if (
            performanceData === null ||
            performanceData.totalLoadTimeMilliseconds <= 0
        ) {
            console.warn(
                "[Collector] Complete performance timing was unavailable."
            );
            return;
        }
        const payload = {
            type: "performance",
            sessionId: sessionId,
            url: window.location.href,
            timestamp: new Date().toISOString(),
            data: performanceData
        };
        console.log("[Collector] Sending performance data:", payload);
        sendPayload(payload);
    }

    function sendPageView() {
        const payload = {
            type: "pageview",
            sessionId: sessionId,
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString()
        };

        console.log("[Collector] Sending pageview:", payload);
        sendPayload(payload);
    }

    async function sendStaticData() {
        const payload = {
            type: "static",
            sessionId: sessionId,
            url: window.location.href,
            timestamp: new Date().toISOString(),
            data: await getStaticData()
        };

        console.log("[Collector] Sending static data:", payload);
        sendPayload(payload);
    }
    function sendError(errorData) {
        const payload = {
            type: "error",
            sessionId: sessionId,
            url: window.location.href,
            timestamp: new Date().toISOString(),
            data: errorData
        };

        console.log("[Collector] Sending error:", payload);
        sendPayload(payload);
    }

    window.addEventListener("error", function (event) {
        sendError({
            eventType: "javascript-error",
            message: event.message || "Unknown JavaScript error",
            filename: event.filename || null,
            lineNumber: event.lineno || null,
            columnNumber: event.colno || null,
            stack:
                event.error && typeof event.error.stack === "string"
                    ? event.error.stack
                    : null
        });
    });

    window.addEventListener("unhandledrejection", function (event) {
        const reason = event.reason;

        sendError({
            eventType: "unhandled-promise-rejection",
            message:
                reason instanceof Error
                    ? reason.message
                    : String(reason),
            filename: null,
            lineNumber: null,
            columnNumber: null,
            stack:
                reason && typeof reason.stack === "string"
                    ? reason.stack
                    : null
        });
    });

    function sendInitialData() {
        sendPageView();
        sendStaticData();
        window.setTimeout(sendPerformanceData, 0);
    }

    if (document.readyState === "complete") {
        sendInitialData();
    } else {
        window.addEventListener("load", sendInitialData, {
            once: true
        });
    }
})();

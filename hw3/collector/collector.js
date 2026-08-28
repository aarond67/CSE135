// collector.js — CSE 135 HW3 analytics collector
(function () {
    "use strict";

    const ENDPOINT = "https://collector.baddecisions.site/log/";
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

    function send(payload) {
        const json = JSON.stringify(payload);
        const blob = new Blob([json], {
            type: "application/json"
        });

        if (typeof navigator.sendBeacon === "function") {
            const accepted = navigator.sendBeacon(ENDPOINT, blob);

            if (accepted) {
                return;
            }
        }

        fetch(ENDPOINT, {
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

    function getNetworkInformation() {
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

            network: getNetworkInformation()
        };
        }
        function getPerformanceData() {
            const navigationEntries =
                window.performance.getEntriesByType("navigation");

            if (navigationEntries.length === 0) {
                return null;
            }

            const navigationTiming = navigationEntries[0];

            /*
            * Navigation Timing values are relative to performance.timeOrigin.
            * Adding timeOrigin converts them into absolute timestamps.
            */
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

        function reportPerformanceData() {
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
            send(payload);
        }

        function reportPageView() {
            const payload = {
                type: "pageview",
                sessionId: sessionId,
                url: window.location.href,
                title: document.title,
                referrer: document.referrer,
                timestamp: new Date().toISOString()
            };

            console.log("[Collector] Sending pageview:", payload);
            send(payload);
    }

    async function reportStaticData() {
        const payload = {
            type: "static",
            sessionId: sessionId,
            url: window.location.href,
            timestamp: new Date().toISOString(),
            data: await getStaticData()
        };

        console.log("[Collector] Sending static data:", payload);
        send(payload);
    }

    function reportInitialData() {
        reportPageView();
        reportStaticData();
        window.setTimeout(reportPerformanceData, 0);
    }

    if (document.readyState === "complete") {
        reportInitialData();
    } else {
        window.addEventListener("load", reportInitialData, {
            once: true
        });
    }
})();
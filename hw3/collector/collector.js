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

    if (document.readyState === "complete") {
        reportPageView();
    } else {
        window.addEventListener("load", reportPageView, { once: true });
    }
})();
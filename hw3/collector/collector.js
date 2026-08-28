// collector.js — CSE 135 HW3 analytics collector
(function () {
    "use strict";

    const ENDPOINT = "https://collector.baddecisions.site/log/";

    function send(payload) {
        const json = JSON.stringify(payload);
        const blob = new Blob([json], {
            type: "application/json"
        });

        if (navigator.sendBeacon) {
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
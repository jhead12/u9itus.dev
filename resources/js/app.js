import "./bootstrap";

import Alpine from "alpinejs";
import flatpickr from "flatpickr";
import "flatpickr/dist/themes/dark.css";

globalThis.Alpine = Alpine;

const calendarSelector = "input[data-calendar-picker]";

function initializeCalendarPicker(input) {
    if (input.dataset.calendarReady === "true") {
        return;
    }

    const pickerMode = input.dataset.calendarPicker || "datetime";
    const enableTime = pickerMode === "datetime";
    const initialValue = input.value;

    input.type = "text";
    input.dataset.calendarReady = "true";

    flatpickr(input, {
        altInput: true,
        altFormat: enableTime ? "F j, Y h:i K" : "F j, Y",
        allowInput: false,
        dateFormat: enableTime ? String.raw`Y-m-d\TH:i` : "Y-m-d",
        defaultDate: initialValue || null,
        disableMobile: true,
        enableTime,
        minuteIncrement: 5,
        time_24hr: false,
    });
}

function initializeCalendarPickers(root = document) {
    root.querySelectorAll(calendarSelector).forEach(initializeCalendarPicker);
}

document.addEventListener("DOMContentLoaded", () => {
    initializeCalendarPickers();
});

Alpine.start();

queueMicrotask(() => {
    initializeCalendarPickers();
});

// WebMCP civic-agent tools — lazily loaded so a page with no AI agent
// attached pays nothing. Every failure path inside is a no-op.
import("./webmcp/index.js")
    .then((m) => m.registerCivicTools())
    .catch(() => {});

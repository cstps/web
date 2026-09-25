(function () {
    "use strict";

    function initializeEventForm() {
        var modeSelect =
            document.getElementById(
                "application_mode"
            );

        var capacityInput =
            document.getElementById(
                "application_capacity"
            );

        if (!modeSelect || !capacityInput) {
            return;
        }

        var capacityField =
            capacityInput.closest(
                ".admin-field"
            );

        if (!capacityField) {
            return;
        }

        function synchronizeCapacityField() {
            var isDirectApplication =
                modeSelect.value === "event";

            capacityField.hidden =
                !isDirectApplication;

            capacityInput.disabled =
                !isDirectApplication;

            capacityField.setAttribute(
                "aria-hidden",
                isDirectApplication
                    ? "false"
                    : "true"
            );
        }

        modeSelect.addEventListener(
            "change",
            synchronizeCapacityField
        );

        synchronizeCapacityField();
    }

    if (
        document.readyState ===
        "loading"
    ) {
        document.addEventListener(
            "DOMContentLoaded",
            initializeEventForm
        );
    } else {
        initializeEventForm();
    }
})();

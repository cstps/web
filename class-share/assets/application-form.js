(function () {
    "use strict";
    function updateGroup(group) {
        var boxes = group.querySelectorAll('input[type="checkbox"]');
        if (boxes.length === 0) {
            return;
        }
        var checked = Array.prototype.some.call(boxes, function (box) {
            return box.checked;
        });
        boxes[0].setCustomValidity(checked ? "" : "한 개 이상 선택해 주세요.");
    }
    function initialize() {
        document.querySelectorAll('fieldset[data-cs-required="1"]').forEach(function (group) {
            updateGroup(group);
            group.addEventListener("change", function () { updateGroup(group); });
        });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize);
    } else {
        initialize();
    }
}());

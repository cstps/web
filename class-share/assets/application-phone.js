(function () {
    "use strict";

    var phone = document.getElementById("phone");
    var help = document.getElementById("phone-format-help");

    if (!phone || !help) {
        return;
    }

    var pattern = /^010-[0-9]{4}-[0-9]{4}$/;
    var message =
        "010으로 시작하는 휴대전화 번호 11자리를 입력해 주세요. 하이픈은 자동으로 추가됩니다.";

    function validatePhone() {
        var original = phone.value;
        var cursor = phone.selectionStart;
        var digits = original.replace(/[^0-9]/g, "");
        var formatted = digits;

        if (digits.length > 7) {
            formatted =
                digits.slice(0, 3) + "-" +
                digits.slice(3, 7) + "-" +
                digits.slice(7);
        } else if (digits.length > 3) {
            formatted =
                digits.slice(0, 3) + "-" +
                digits.slice(3);
        }

        if (original !== formatted) {
            phone.value = formatted;

            // 번호 중간을 수정할 때도 커서 위치를 유지한다.
            if (cursor !== null && document.activeElement === phone) {
                var digitsBeforeCursor =
                    original.slice(0, cursor).replace(/[^0-9]/g, "").length;
                var nextCursor = 0;
                var digitCount = 0;

                while (
                    nextCursor < formatted.length &&
                    digitCount < digitsBeforeCursor
                ) {
                    if (/[0-9]/.test(formatted.charAt(nextCursor))) {
                        digitCount++;
                    }
                    nextCursor++;
                }

                phone.setSelectionRange(nextCursor, nextCursor);
            }
        }

        var invalid =
            phone.value !== "" && !pattern.test(phone.value);

        phone.setCustomValidity(invalid ? message : "");
        phone.setAttribute("aria-invalid", invalid ? "true" : "false");

        help.textContent = invalid
            ? "입력 형식이 올바르지 않습니다. " + message
            : message;
    }

    phone.addEventListener("input", validatePhone);
    phone.addEventListener("change", validatePhone);
    phone.addEventListener("blur", validatePhone);
    validatePhone();
}());

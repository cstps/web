(function () {
    "use strict";

    var phone = document.getElementById("phone");
    var help = document.getElementById("phone-format-help");

    if (!phone || !help) {
        return;
    }

    var pattern = /^010-[0-9]{4}-[0-9]{4}$/;
    var message =
        "하이픈을 포함하여 010-0000-0000 형식으로 입력해 주세요.";

    function validatePhone() {
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

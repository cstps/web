(function () {
    "use strict";

    var body =
        document.body;

    var toggleButton =
        document.getElementById(
            "admin-menu-toggle"
        );

    var sidebar =
        document.getElementById(
            "admin-sidebar"
        );

    var backdrop =
        document.getElementById(
            "admin-sidebar-backdrop"
        );

    if (
        !body ||
        !toggleButton ||
        !sidebar ||
        !backdrop
    ) {
        return;
    }

    var storageKey =
        "oj-admin-sidebar-collapsed";

    var mobileQuery =
        window.matchMedia(
            "(max-width: 768px)"
        );

    function readDesktopCollapsed() {
        try {
            return window.localStorage.getItem(
                storageKey
            ) === "1";
        } catch (error) {
            return false;
        }
    }

    function writeDesktopCollapsed(
        collapsed
    ) {
        try {
            window.localStorage.setItem(
                storageKey,
                collapsed ? "1" : "0"
            );
        } catch (error) {
            // 저장에 실패해도 메뉴 동작은 유지합니다.
        }
    }

    function updateAccessibility(
        visible
    ) {
        toggleButton.setAttribute(
            "aria-expanded",
            visible ? "true" : "false"
        );

        toggleButton.setAttribute(
            "aria-label",
            visible
            ? "관리자 메뉴 접기"
            : "관리자 메뉴 펼치기"
        );

        sidebar.setAttribute(
            "aria-hidden",
            visible ? "false" : "true"
        );

        backdrop.setAttribute(
            "aria-hidden",
            mobileQuery.matches &&
                visible
            ? "false"
            : "true"
        );
    }

    function closeMobileSidebar() {
        body.classList.remove(
            "admin-sidebar-open"
        );

        updateAccessibility(false);
    }

    function applyViewportState() {
        body.classList.remove(
            "admin-sidebar-open"
        );

        if (mobileQuery.matches) {
            body.classList.remove(
                "admin-sidebar-collapsed"
            );

            updateAccessibility(false);
            return;
        }

        var collapsed =
            readDesktopCollapsed();

        body.classList.toggle(
            "admin-sidebar-collapsed",
            collapsed
        );

        updateAccessibility(!collapsed);
    }

    toggleButton.addEventListener(
        "click",
        function () {
            if (mobileQuery.matches) {
                var willOpen =
                    !body.classList.contains(
                        "admin-sidebar-open"
                    );

                body.classList.toggle(
                    "admin-sidebar-open",
                    willOpen
                );

                updateAccessibility(willOpen);
                return;
            }

            var collapsed =
                body.classList.toggle(
                    "admin-sidebar-collapsed"
                );

            writeDesktopCollapsed(
                collapsed
            );

            updateAccessibility(!collapsed);
        }
    );

    backdrop.addEventListener(
        "click",
        closeMobileSidebar
    );

    sidebar.addEventListener(
        "click",
        function (event) {
            if (
                mobileQuery.matches &&
                event.target.closest("a")
            ) {
                closeMobileSidebar();
            }
        }
    );

    document.addEventListener(
        "keydown",
        function (event) {
            if (
                event.key === "Escape" &&
                mobileQuery.matches
            ) {
                closeMobileSidebar();
                toggleButton.focus();
            }
        }
    );

    if (
        typeof mobileQuery.addEventListener ===
        "function"
    ) {
        mobileQuery.addEventListener(
            "change",
            applyViewportState
        );
    } else {
        mobileQuery.addListener(
            applyViewportState
        );
    }

    applyViewportState();
})();

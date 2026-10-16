(function () {
    'use strict';

    function makeCanvas(url) {
        if (typeof window.qrcode !== 'function') {
            throw new Error('QR 라이브러리를 불러오지 못했습니다.');
        }

        var parsed = new URL(url, window.location.href);
        if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') {
            throw new Error('올바른 웹 주소가 아닙니다.');
        }

        var qr = window.qrcode(0, 'M');
        qr.addData(parsed.href);
        qr.make();

        var count = qr.getModuleCount();
        var margin = 4;
        var cell = Math.max(8, Math.ceil(480 / (count + margin * 2)));
        var size = (count + margin * 2) * cell;
        var canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', '단축 URL QR코드');

        var context = canvas.getContext('2d');
        if (!context) {
            throw new Error('이 브라우저에서 QR 이미지를 만들 수 없습니다.');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size, size);
        context.fillStyle = '#000000';

        for (var row = 0; row < count; row++) {
            for (var col = 0; col < count; col++) {
                if (qr.isDark(row, col)) {
                    context.fillRect(
                        (col + margin) * cell,
                        (row + margin) * cell,
                        cell,
                        cell
                    );
                }
            }
        }
        return canvas;
    }

    function download(canvas, url) {
        var parsed = new URL(url, window.location.href);
        var code = (parsed.searchParams.get('code') || 'shorturl')
            .replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 80);

        canvas.toBlob(function (blob) {
            if (!blob) {
                window.alert('PNG 이미지를 만들지 못했습니다.');
                return;
            }

            var objectUrl = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = objectUrl;
            link.download = '1024kr-qr-' + code + '.png';
            document.body.appendChild(link);
            link.click();
            link.remove();

            window.setTimeout(function () {
                URL.revokeObjectURL(objectUrl);
            }, 10000);
        }, 'image/png');
    }

    function button(text) {
        var element = document.createElement('button');
        element.type = 'button';
        element.className = 'shorturl-qr-button';
        element.textContent = text;
        return element;
    }

    function openDialog(url, opener) {
        var dialog = document.createElement('dialog');
        dialog.className = 'shorturl-qr-dialog';
        dialog.setAttribute('aria-labelledby', 'shorturl-qr-heading');

        var title = document.createElement('h2');
        title.id = 'shorturl-qr-heading';
        title.textContent = '단축 URL QR코드';

        var canvas = makeCanvas(url);
        var address = document.createElement('p');
        address.className = 'shorturl-qr-address';
        address.textContent = url;

        var actions = document.createElement('div');
        actions.className = 'shorturl-qr-actions';

        var save = button('PNG 다운로드');
        save.addEventListener('click', function () {
            download(canvas, url);
        });

        var close = button('닫기');
        close.addEventListener('click', function () {
            dialog.close();
        });

        actions.append(save, close);
        dialog.append(title, canvas, address, actions);
        dialog.addEventListener('close', function () {
            dialog.remove();
            opener.focus();
        });
        document.body.appendChild(dialog);

        if (typeof dialog.showModal !== 'function') {
            dialog.remove();
            throw new Error('최신 브라우저에서 QR코드 보기를 이용해 주세요.');
        }
        dialog.showModal();
        close.focus();
    }

    function init() {
        document.querySelectorAll('[data-qr-preview]').forEach(function (area) {
            try {
                var url = area.getAttribute('data-qr-preview');
                var canvas = makeCanvas(url);
                area.insertBefore(canvas, area.firstChild);
                area.querySelector('[data-qr-download]')
                    .addEventListener('click', function () {
                        download(canvas, url);
                    });
            } catch (error) {
                area.textContent = 'QR코드 생성 실패: ' + error.message;
            }
        });

        document.querySelectorAll('[data-qr-open]').forEach(function (opener) {
            opener.addEventListener('click', function () {
                try {
                    openDialog(opener.getAttribute('data-qr-open'), opener);
                } catch (error) {
                    window.alert('QR코드 생성 실패: ' + error.message);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());

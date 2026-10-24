const status = document.getElementById("python-poc-status");
const output = document.getElementById("python-poc-output");
const stopButton = document.getElementById("python-poc-stop");
const canvas = document.getElementById("python-poc-canvas");
const context = canvas.getContext("2d");

let activeWorker = null;
let timer = null;
let versionText = "";

function finish(message) {
    clearTimeout(timer);
    timer = null;
    if (activeWorker) {
        activeWorker.terminate();
        activeWorker = null;
    }
    stopButton.disabled = true;
    status.textContent = message;
}

function deadline(worker, milliseconds, message) {
    clearTimeout(timer);
    timer = setTimeout(function () {
        if (activeWorker === worker) finish(message);
    }, milliseconds);
}

function start(mode) {
    finish("실행을 준비합니다.");
    output.textContent = "";
    versionText = "";
    context.fillStyle = "white";
    context.fillRect(0, 0, 400, 400);

    status.textContent = "Python 실행 환경을 불러오는 중입니다.";
    stopButton.disabled = false;

    let worker;
    try {
        worker = new Worker(
            "/template/syzoj/js/course-python-poc-worker.mjs?v=3",
            { type: "module" }
        );
    } catch (error) {
        output.textContent = String(error);
        finish("Worker를 시작하지 못했습니다.");
        return;
    }

    activeWorker = worker;
    deadline(worker, 60000, "실행 환경 로딩 시간이 초과됐습니다.");

    worker.addEventListener("message", function (event) {
        if (activeWorker !== worker) return;
        const data = event.data;
        if (!data || typeof data !== "object") return;

        if (data.type === "loading") {
            status.textContent = "로컬 Pyodide 파일을 불러오는 중입니다.";
        } else if (data.type === "started") {
            versionText = "Python 버전: " + data.version + "\n";
            output.textContent = versionText;

            if (mode === "loop") {
                status.textContent = "무한 반복 실행 중. 약 2초 후 종료합니다.";
                deadline(worker, 2000, "무한 반복 Worker 종료 완료.");
            } else {
                status.textContent = "Python 예제를 실행하는 중입니다.";
                deadline(worker, 10000, "예제 실행 시간이 초과되어 종료했습니다.");
            }
        } else if (data.type === "frame") {
            try {
                if (!Number.isInteger(data.frame) ||
                    data.frame < 0 || data.frame > 120 ||
                    !(data.pixels instanceof ArrayBuffer) ||
                    data.pixels.byteLength !== 400 * 400 * 4) {
                    throw new Error("그림 데이터 형식이 올바르지 않습니다.");
                }

                context.putImageData(
                    new ImageData(
                        new Uint8ClampedArray(data.pixels),
                        400, 400
                    ),
                    0, 0
                );
                output.textContent = versionText +
                    "현재 프레임: " + data.frame +
                    "\n서버 기준 픽셀 일치: " + data.verifiedFrames + "건";
                status.textContent = mode === "animation"
                    ? "움직이는 그림 실행 중입니다."
                    : "정적 그림을 표시했습니다.";

                deadline(worker, 5000, "프레임 응답이 없어 실행을 종료했습니다.");
            } catch (error) {
                output.textContent += "\n" + String(error);
                finish("그림 표시 오류");
            }
        } else if (data.type === "complete") {
            output.textContent += "\n결과: " + String(data.result);
            finish("실행 완료. Worker 정리 완료.");
        } else if (data.type === "error") {
            output.textContent += "\n" + String(data.message || "알 수 없는 오류");
            finish("Python 실행 오류");
        }
    });

    worker.addEventListener("error", function (event) {
        if (activeWorker !== worker) return;
        output.textContent = event.message || "브라우저 콘솔을 확인해 주세요.";
        finish("Worker 로딩 또는 실행 실패");
    });

    worker.postMessage({ mode: mode });
}

[
    ["python-poc-check", "check"],
    ["python-poc-loop", "loop"],
    ["python-poc-static", "static"],
    ["python-poc-animation", "animation"]
].forEach(function (entry) {
    document.getElementById(entry[0]).addEventListener("click", function () {
        start(entry[1]);
    });
});

stopButton.addEventListener("click", function () {
    finish("실행을 중지했습니다.");
});
window.addEventListener("pagehide", function () {
    finish("화면을 떠나 실행을 종료했습니다.");
});

<?php
$show_title = "자주묻는질문 - " . $OJ_NAME;
include("template/$OJ_TEMPLATE/header.php");
?>
<link
    rel="stylesheet"
    href="<?php echo $path_fix; ?>template/syzoj/css/faqs.css?v=<?php
        echo rawurlencode((string)filemtime(__DIR__ . "/css/faqs.css"));
    ?>">

<section class="oj-faq-page" aria-labelledby="faq-title">
    <header class="oj-faq-header">
        <h1 id="faq-title">자주묻는질문</h1>
        <p>코드 제출과 채점에 관한 기본 안내입니다. 질문을 눌러 내용을 확인해 주세요.</p>
    </header>

    <div class="oj-faq-list">
        <details class="oj-faq-item">
            <summary>어떤 환경에서 코드가 채점되나요?</summary>
            <div class="oj-faq-content">
                <p>제출한 코드는 서버의 Linux 채점 환경에서 실행됩니다.
                개인 컴퓨터와 컴파일러 또는 실행 환경이 다를 수 있습니다.</p>
                <ul>
                    <li>제출할 때 코드에 맞는 언어를 선택해 주세요.</li>
                    <li>문제 화면의 시간 제한과 메모리 제한을 확인해 주세요.</li>
                    <li>특정 운영체제에서만 사용할 수 있는 기능은 피해주세요.</li>
                    <li>Python에서는 사용하려는 외부 라이브러리를 채점 환경에서 지원하는지 확인해야 합니다.</li>
                </ul>
            </div>
        </details>

        <details class="oj-faq-item" open>
            <summary>입력과 출력은 어떻게 작성하나요?</summary>
            <div class="oj-faq-content">
                <p>입력은 표준 입력으로 받고, 답은 표준 출력으로 출력합니다.
                문제에 별도 지시가 없다면 파일에서 입력을 읽지 않습니다.</p>
                <p>“숫자를 입력하세요” 같은 안내 문구는 출력하지 마세요.
                문제에서 요구한 답과 출력 형식을 따라야 합니다.</p>
                <h2>언어별 예제</h2>
                <p>다음은 각 줄에 정수 두 개가 주어지고, 입력이 끝날 때까지
                두 수의 합을 한 줄씩 출력하는 예제입니다.
                실제 문제의 입력 형식에 맞게 수정해 주세요.</p>
                <details class="faq-example"><summary>Python 3</summary><pre><code>import sys

for line in sys.stdin:
    a, b = map(int, line.split())
    print(a + b)</code></pre></details>
<details class="faq-example"><summary>C</summary><pre><code>#include &lt;stdio.h&gt;

int main(void) {
    int a, b;

    while (scanf(&quot;%d %d&quot;, &amp;a, &amp;b) == 2) {
        printf(&quot;%d\n&quot;, a + b);
    }

    return 0;
}</code></pre></details>
<details class="faq-example"><summary>C++</summary><pre><code>#include &lt;iostream&gt;
using namespace std;

int main() {
    int a, b;

    while (cin &gt;&gt; a &gt;&gt; b) {
        cout &lt;&lt; a + b &lt;&lt; &#x27;\n&#x27;;
    }

    return 0;
}</code></pre></details>
<details class="faq-example"><summary>Java</summary><pre><code>import java.util.Scanner;

public class Main {
    public static void main(String[] args) {
        Scanner input = new Scanner(System.in);

        while (input.hasNextInt()) {
            int a = input.nextInt();
            int b = input.nextInt();
            System.out.println(a + b);
        }
    }
}</code></pre></details>
<details class="faq-example"><summary>Pascal</summary><pre><code>program Sum(Input, Output);
var
    a, b: Integer;
begin
    while not eof(Input) do
    begin
        Readln(a, b);
        Writeln(a + b);
    end;
end.</code></pre></details>
            </div>
        </details>

        <details class="oj-faq-item">
            <summary>내 컴퓨터에서는 실행되는데 컴파일 오류가 발생해요.</summary>
            <div class="oj-faq-content">
                <p>먼저 제출 언어가 코드와 일치하는지 확인하고,
                채점기록에서 컴파일 오류 메시지를 확인해 주세요.</p>
                <ul>
                    <li>C와 C++의 시작 함수는 <code>int main()</code> 형태로 작성합니다.</li>
                    <li>선언한 변수의 사용 범위와 필요한 헤더를 확인합니다.</li>
                    <li>특정 컴파일러 전용 함수와 자료형은 서버에서 지원되지 않을 수 있습니다.</li>
                    <li>Java 예제처럼 제출하는 경우 클래스 이름은 <code>Main</code>을 사용합니다.</li>
                </ul>
            </div>
        </details>

        <details class="oj-faq-item">
            <summary>채점 결과는 어떤 의미인가요?</summary>
            <div class="oj-faq-content">
                <div class="oj-faq-table-wrap"
                     tabindex="0"
                     role="region"
                     aria-label="채점 결과 안내 표">
                    <table class="oj-faq-table">
                        <caption>채점 상태와 결과 안내</caption>
                        <thead>
                            <tr>
                                <th scope="col">채점 결과</th>
                                <th scope="col">설명</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Pending, ENT_QUOTES, "UTF-8"); ?></th><td>제출이 접수되어 채점을 기다리고 있습니다.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Pending_Rejudging, ENT_QUOTES, "UTF-8"); ?></th><td>재채점을 기다리고 있습니다.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Compiling, ENT_QUOTES, "UTF-8"); ?></th><td>제출한 코드를 컴파일하고 있습니다.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Running_Judging, ENT_QUOTES, "UTF-8"); ?></th><td>테스트 데이터로 프로그램을 실행하고 있습니다.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Accepted, ENT_QUOTES, "UTF-8"); ?></th><td>채점 기준을 통과한 정답입니다.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Presentation_Error, ENT_QUOTES, "UTF-8"); ?></th><td>출력 형식을 확인해 주세요. 공백과 줄바꿈 등 문제에서 요구한 형식을 살펴보세요.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Wrong_Answer, ENT_QUOTES, "UTF-8"); ?></th><td>일부 테스트에서 기대한 답과 다른 결과를 출력했습니다. 경계값과 예외 상황을 확인해 주세요.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Time_Limit_Exceed, ENT_QUOTES, "UTF-8"); ?></th><td>실행 시간이 제한을 초과했습니다. 알고리즘의 효율과 반복문을 확인해 주세요.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Memory_Limit_Exceed, ENT_QUOTES, "UTF-8"); ?></th><td>사용한 메모리가 제한을 초과했습니다. 배열 크기와 자료 저장 방식을 확인해 주세요.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Output_Limit_Exceed, ENT_QUOTES, "UTF-8"); ?></th><td>출력량이 제한을 초과했습니다. 불필요한 출력과 무한 반복을 확인해 주세요.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Runtime_Error, ENT_QUOTES, "UTF-8"); ?></th><td>실행 중 오류가 발생했습니다. 배열 범위, 0으로 나누기, 잘못된 메모리 접근 등을 확인해 주세요.</td></tr>
<tr><th scope="row"><?php echo htmlspecialchars((string)$MSG_Compile_Error, ENT_QUOTES, "UTF-8"); ?></th><td>컴파일에 실패했습니다. 오류 메시지를 확인하여 문법과 선택한 제출 언어를 점검해 주세요.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <details class="oj-faq-item">
            <summary>수업이나 대회에는 어떻게 참여하나요?</summary>
            <div class="oj-faq-content">
                <p>로그인한 뒤 상단의 <strong>수업·대회</strong> 메뉴를 이용합니다.</p>
                <ul>
                    <li>수업은 담당 교사가 등록한 수강 권한이 필요합니다.
                    수강 등록 후 <strong>내 수업</strong>에서 확인할 수 있습니다.</li>
                    <li>대회는 <strong>대회 목록</strong>에서 확인합니다.
                    대회마다 참가 권한과 운영 시간이 다를 수 있습니다.</li>
                </ul>
                <p>
                    <a href="<?php echo $path_fix; ?>loginpage.php">로그인</a>
                    <span aria-hidden="true"> · </span>
                    <a href="<?php echo $path_fix; ?>contest.php">대회 목록</a>
                </p>
            </div>
        </details>
    </div>

    <?php if (isset($OJ_BBS) && $OJ_BBS): ?>
        <aside class="oj-faq-contact">
            <h2>찾는 답변이 없나요?</h2>
            <p>질문이나 건의 사항은 묻고 답하기에 남겨 주세요.</p>
            <a href="<?php echo $path_fix; ?>discuss.php">묻고 답하기</a>
        </aside>
    <?php endif; ?>
</section>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>

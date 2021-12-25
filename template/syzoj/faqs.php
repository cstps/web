<?php $show_title="FAQ - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<div class="padding">
    <h1 class="ui center aligned header">도움</h1>
    <h4>FAQS</h4>
    <div class="faqs-card">
    <p>Q:이 채점시스템에서 사용하는 코드 컴파일 옵션은?<br>
  A:채점 시스템은 <a href="http://www.ubuntu.com">Ubuntu Linux</a>기반으로. <a href="http://gcc.gnu.org/">GNU GCC/G++</a>for C/C++ compile,
				<a href="http://www.freepascal.org">Free Pascal</a> 파스칼 그리고
				<a href="http://openjdk.java.net">openjdk-7-jdk</a> 자바. 컴파일옵션:<br>
</p>
<table class="table table-hover">
  <tr>
    <td>C:</td>
    <td>gcc Main.c -o Main  -fno-asm -Wall -lm --static -std=c99 -DONLINE_JUDGE
	  </td>
  </tr>
  <tr>
    <td>C++:</td>
    <td>g++ -fno-asm -Wall -lm --static -std=c++11 -DONLINE_JUDGE -o Main Main.cc</td>
  </tr>
  <tr>
    <td>Pascal:</td>
    <td>fpc Main.pas -oMain -O1 -Co -Cr -Ct -Ci </td>
  </tr>
  <tr>
    <td>Java:</td>
    <td><font color="blue">javac -J-Xms32m -J-Xmx256m Main.java
    <br>
    <font size="-1" color="red">*Java 코드를 실행하고 채점하는 경우 +2초, +512MB 가 추가됩니다.
    </td>
  </tr>
</table>
<p>컴파일 버전:<br>
<table class="table table-hover">
  <tr>
    <td>gcc</td>
    <td>gcc version 9.3.0 (Ubuntu 9.3.0-17ubuntu1~20.04)
	  </td>
  </tr>
  <tr>
    <td>glibc</td>
    <td>Ubuntu GLIBC 2.31-0ubuntu9.2</td>
  </tr>
  <tr>
    <td>FPC</td>
    <td>Free Pascal Compiler version 3.0.4+dfsg-23 [2019/11/25] for x86_64</td>
  </tr>
  <tr>
    <td>openjdk</td>
    <td>openjdk 1.7.0_151
    </td>
  </tr>
  <tr>
    <td>python</td>
    <td>Python 3.8.10
    </td>
  </tr>
</table>
</p>
</div>
<div class="faqs-card">
<p>Q:코드 작성시 데이터 입출력은 어떻게 하나요?<br>
  A: stdin('표준입력')에서 입력받고 stdout('표준출력')으로 출력한다.<br>
  예를 들어, C언어에서는 'scanf', C++ 언어에서는 'cin' 을 이용해서 stdin(입력)을 읽어들입니다. 또한, C언어에서는 'printf', C++언어에서는 'cout'을 이용해 stdout(출력)으로 출력할 수 있습니다.<br>
  파일 입출력을 사용한 코드를 제출하는 경우에는 "Runtime Error(실행오류)"를 받게된다.<br>
  <br>
 1037에 대한 예시코드</p>
<p> C++:<br>
</p>
<pre>
<code class="cpp">
#include &lt;iostream&gt;
using namespace std;
int main(){
    int a,b;
    while(cin >> a >> b)
        cout << a+b << endl;
    return 0;
}
</code>
</pre>
C:<br>
<pre>
<code class="c">
#include &lt;stdio.h&gt;
int main(){
    int a,b;
    while(scanf("%d %d",&amp;a, &amp;b) != EOF)
        printf("%d\n",a+b);
    return 0;
}
</code>
</pre>
 PASCAL:<br>
<pre><code class="delphi">
program p1037(Input,Output); 
var 
  a,b:Integer; 
begin 
   while not eof(Input) do 
     begin 
       Readln(a,b); 
       Writeln(a+b); 
     end; 
end.
</code>
</pre>
<br>
Java:<br>
<pre><code class="java">
import java.util.*;
public class Main{
	public static void main(String args[]){
		Scanner cin = new Scanner(System.in);
		int a, b;
		while (cin.hasNext()){
			a = cin.nextInt(); b = cin.nextInt();
			System.out.println(a + b);
		}
	}
}</code></pre>
<br>
</div>
<p><strong>python3 (.py)</strong></p>
        <div class="ui existing segment">
            <pre style="margin-top: 0; margin-bottom: 0; ">
<code class="lang-c">import io
import sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer,encoding='utf8')
for line in sys.stdin:
    a = line.split()
    print(int(a[0]) + int(a[1]))</code></pre>
        </div>
<div class="faqs-card">
Q: 문제가 없는데 컴파일 에러가 발생한다?<br>
A: GNU 와 MS-VC++ 는 다음과 같이 다릅니다. 내용:<br>
					<pre><code class="cpp">main()는 int main()으로 해야 합니다.
"for(int i=0...){...}" 와 같이 선언되어있는 상태인데 for 코드블록 밖에서 i를 참조되는 경우
itoa는 ANSI 함수가 아니다.
__int64 는 VC에서만, long long 을 사용해야 합니다.
VC코드를 그대로 사용하고 싶다면 #define __int64 long long </code></pre>
</div><div class="faqs-card">
Q:채점 코드 제출 후 받게 되는 메시지들은 어떤 의미인가요?<br>
A:채점 코드 제출 후 받게 되는 메시지들의 의미는 다음과 같습니다.<br>
<table class="table table-hover">
<tr>
<td><?php echo $MSG_Pending;?></td>
<td>코드가 제출되고 채점을 기다리고 있는 상태입니다. 대부분의 경우 조금만 기다리면 채점이 진행됩니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Pending_Rejudging;?></td>
<td>채점 데이터가 갱신되어 재채점을 기다리고 있는 상태입니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Compiling;?></td>
<td>제출된 코드를 컴파일 중이라는 의미입니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Running_Judging;?></td>
<td>채점이 진행되고 있는 상태라는 의미입니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Accepted;?></td>
<td>정답입니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Presentation_Error;?></td>
<td>출력된 결과가 문제에서 출력해야하는 출력형식과 다르게 출력되었다는 의미입니다. 문제의 출력형식에서 요구하는 형식과 똑같아야 합니다. 답 출력 후 출력형식에는 없는 공백문자나 줄 바꿈이 더 출력되지는 않았는지 확인해 보아야 합니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Wrong_Answer;?></td>
<td>틀린 답을 출력한 것을 의미합니다. 채점 시스템에 등록하는 채점 데이터들은 외부로 공개하지 않는 것이 일반적입니다. 제출한 코드가 틀린 답을 출력하는 경우가 어떤 경우일지 더 생각해 보아야 합니다. ;-).</td>
</tr>
<tr>
<td><?php echo $MSG_Time_Limit_Exceed;?></td>
<td>제한시간 이내에 답을 출력하지 못했다는 것을 의미합니다. 좀 더 빠르면서도 정확한 결과를 출력하도록 소스 코드를 수정해야합니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Memory_Limit_Exceed;?></td>
<td>제출한 프로그램이 제한된 메모리용량보다 더 많은 기억공간을 사용했다는 것을 의미합니다. 일반적으로는 메모리를 더 적게 사용하는 코드로 수정해야합니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Output_Limit_Exceed;?></td>
<td>제출한 프로그램이 제한된 출력량 이상으로 결과를 출력했다는 것을 의미합니다. 대부분의 경우 무한 반복 실행 구조에 의해 발생합니다. 채점 시스템의 출력 제한 바이트 수는 1M bytes 입니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Runtime_Error;?></td>
<td>제출한 프로그램이 실행되는 도중에 오류가 발생했다는 것을 의미합니다. 예를 들어, 'segmentation fault(허용되지 않는 메모리 영역에 접근하는 경우: 배열 인덱스 초과 등)','floating point exception(실수 계산 예외: 0 으로 나누는 등)','used forbidden functions(제한된 함수를 사용한 경우: 파일 처리 함수 등이 사용된 경우 등)', 'tried to access forbidden memories(허용되지 않는 시스템 메모리 영역 등에 접근하는 경우 등)' 등에 의해 발생합니다.</td>
</tr>
<tr>
<td><?php echo $MSG_Compile_Error;?></td>
<td>제출한 소스코드를 ANSI 표준(gcc/g++/gpc) 컴파일러로 컴파일하지 못했다는 것을 의미합니다. 컴파일 오류 메시지가 아닌 오류 경고(warning)는 이 메시지를 출력하지 않습니다. 메시지 부분을 누르면 컴파일 오류 메시지를 확인할 수도 있습니다.
</td>
</tr>
</table>
</div><div class="faqs-card">
Q:온라인 대회(Online Contests)는 어떻게 참가하나요?<br>
A:회원가입부터 하세요 <a href=registerpage.php>회원가입</a><br>
</div>

</div>

<?php include("template/$OJ_TEMPLATE/footer.php");?>

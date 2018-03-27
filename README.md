# fork
PHP多进程编程初步
最近由于工作需要使用php做个爬虫程序,说道爬虫必然会想到python，其实php加上多进程特性，MQ队列服务,一样可以杠杠的满足常规需求。下面我讲分几篇文章来介绍实现实现原理。

在这篇文章将会介绍一下PHP多进程的基础概念，如何创建多进程以及基本的信号控制，暂时不会告诉你如何进行进程间通信和信息共享。主要是些基础的东西让你对php多进程编程有个初步的认识。

准备
在动手之前，请确定你用的不是M$ Windows平台（因为我没有Windows）。Linux / BSD / Unix应该都是没问题的。确认好了工作环境以后一起来看看我们需要的PHP模块是否都有。打开终端输入下面的命令：

$ php -m
这个命令检查并打印当前PHP所有开启的扩展，看一下pcntl和posix是否在输出的列表中。

pcntl
如果找不到pcntl，八成是编译的时候没把这个扩展编译进去。如果你和我一样是编译安装的PHP，那么需要重新编译安装PHP。在配置的时候记得加上–enable-pcntl参数即可。

$ cd /path/to/php_source_code_dir 
$ ./configure [some other options] --enable-pcntl
$ make && make install    
posix
这货一般默认就会装上，只要你编译的时候没有加上–disable-posix。

预备知识
这里对多进程的概率一定要有个基础的了解，所以当初连操作系统的课翘了的小伙伴还是要还的咯，然后我们一起打开手册吧，我建议有条件的还是看英文版的，中文翻译我就发现了个坑，待会儿一起来看。

分身有术
所以呢，没有点基础知识怎么能理解文档里的内容呢？打开文档首先看到了一个单词：fork。

fork
叉子？叉子是分岔的，一个变多个嘛！差不多就是这个意思。创建子进程就用这个命令。这里需要用到pcntl_fork()函数。（可以先简单看一下PHP手册关于这个函数的介绍。）创建一个PHP脚本：

$pid = pcntl_fork(); // 一旦调用成功，事情就变得有些不同了
if ($pid == -1) {
   die('fork failed');
    } else if ($pid == 0) {
    } else {
    }
pcntl_fork()函数创建一个子进程，子进程和父进程唯一的区别就是PID（进程ID）和PPID（父进程ID）不同。在终端下查看进程用ps命令（问问man看ps怎么用：man ps）。当函数返回值为-1的时候，说明fork失败了。试试在if前面加一句：echo $pid . PHP_EOL;。运行你的脚本，输出可能像下面这样（结果说明子进程和父进程的代码是相同的）：

67789 # 这个是父进程打印的
0     # 这个是子进程打印的
pcntl_fork()函数调用成功后，在父进程中会返回子进程的PID，而在子进程中返回的是0。所以，下面直接用if分支来控制父进程和子进程做不同的事。

分配任务
然后我们给父进程和子进程分配两个简单的输出任务：

$parentPid = getmypid(); // 这就是之前的
$pid = pcntl_fork(); // 一旦调用成功，事情就变得有些不同了
if ($pid == -1) {
    die('fork failed');
} else if ($pid == 0) {
    $mypid = getmypid(); // 用getmypid()函数获取当前进程的PID
    echo 'I am child process. My PID is ' . $mypid . ' and my father's PID is ' . $parentPid . PHP_EOL;
} else {
    echo 'Oh my god! I am a father now! My child's PID is ' . $pid . ' and mine is ' . $parentPid . PHP_EOL;
}
输出的结果可能是这样：

Oh my god! I am a father now! My child's PID is 68066 and mine is 68065
I am child process. My PID is 68066 and my father's PID is 68065
再强调一下，pcntl_fork()调用成功以后，一个程序变成了两个程序：一个程序得到的$pid变量值是0，它是子进程；另一个程序得到的$pid的值大于0，这个值是子进程的PID，它是父进程。在下面的分支语句中，由于$pid值的不同，运行了不同的代码。再次强调一下：子进程的代码和父进程的是一样的。所以就要通过分支语句给他们分配不同的任务。

子进程回收
刚刚有man ps么？一般我习惯用ps aux加上grep命令来查找运行着的后台进程。其中有一列STAT，标识了每个进程的运行状态。这里，我们关注状态Z：僵尸（Zombie）。当子进程比父进程先退出，而父进程没对其做任何处理的时候，子进程将会变成僵尸进程。子进程结束后还留着一个空壳在，直到父进程回收它。僵尸进程虽然不占什么内存，但是很碍眼，院子里一堆躺着的僵尸怎么都觉得怪怪的。（别忘了它们还占用着PID）

一般来说，在父进程结束之前回收挂掉的子进程就可以了。在pcntl扩展里面有一个pcntl_wait()函数，它会将父进程挂起，直到有一个子进程退出为止。如果有一个子进程变成了僵尸的话，它会立即返回。所有的子进程都要回收，所以多等等也没关系啦！

父进程先挂了
如果父进程先挂了怎么办？会发生什么？什么也不会发生，子进程依旧还在运行。但是这个时候，子进程会被交给1号进程，1号进程成为了这些子进程的继父。1号进程会很好地处理这些进程的资源，当它们结束时1号进程会自动回收资源。所以，另一种处理僵尸进程的临时办法是关闭它们的父进程。

信号
一般多进程的事儿讲到上面就完了，可是信号在系统中确实是一个非常重要的东西。信号就是信号灯，点亮一个信号灯，程序就会做出反应。这个你一定用过，比如说在终端下运行某个程序，等了半天也没什么反应，可能你会按 Ctrl+C 来关闭这个程序。实际上，这里就是通过键盘向程序发送了一个中断的信号：SIGINT。有时候进程失去响应了还会执行kill [PID]命令，未加任何其他参数的话，程序会接收到一个SIGTERM信号。程序收到上面两个信号的时候，默认都会结束执行，那么是否有可能改变这种默认行为呢？必须能啊！

注册信号
人是活的程序也是活的，只不过程序需要遵循人制定的规则来运行。现在开始给信号重新设定规则，这里用到的函数是pcntl_signal()（继续之前为啥不先查查PHP手册呢？）。下面这段程序将给SIGINT重新定义行为，注意看好：

// 定义一个处理器，接收到SIGINT信号后只输出一行信息
function signalHandler($signal) {
    if ($signal == SIGINT) {
        echo 'signal received' . PHP_EOL;
    }
}
// 信号注册：当接收到SIGINT信号时，调用signalHandler()函数
pcntl_signal(SIGINT, 'signalHandler');
while (true) {
    sleep(1);
    // do something
    pcntl_signal_dispatch(); // 接收到信号时，调用注册的 signalHandler()
}
执行一下，随时按下 Ctrl+C 看看会发生什么事。

信号分发
说明一下：pcntl_signal()函数仅仅是注册信号和它的处理方法，真正接收到信号并调用其处理方法的是pcntl_signal_dispatch()函数。试试把// do something替换成下面这段代码：

for ($i = 0; $i < 1000000; $i++) {
    echo $i . PHP_EOL;
    usleep(100000);
}
在终端下执行这个脚本，当它不停输出数字的时候尝试按下 Ctrl+C 。看看程序有什么响应？嗯……什么都没有，除了屏幕可能多了个^C以外，程序一直在不停地输出数字。因为程序一直没有执行到pcntl_signal_dispatch()，所以就并没有调用signalHandler()，所以就没有输出signal received。

版本问题
如果认真看了PHP文档，会发现pcntl_signal_dispatch()这个函数是PHP 5.3以上才支持的。如果5.3以下，额，看看手册不。现在是2017年咯，放弃古董吧。

感受僵尸进程
现在我们回到子进程回收的问题上（差点忘了= =”）。当你的一个子进程挂了（或者说是结束了），但是父进程还在运行中并且可能很长一段时间不会退出。一个僵尸进程从此站起来了！这时，保护伞公司（内核）发现它的地盘里出现了一个僵尸，这个僵尸是谁儿子呢？看一下PPID就知道了。然后，内核给PPID这个进程（也就是僵尸进程的父进程）发送一个信号：SIGCHLD。然后，你知道怎么在父进程中回收这个子进程了么？提示一下，用pcntl_wait()函数。

发送信号
希望刚刚有认真man过kill命令。它其实就是向进程发送信号，在PHP中也可以调用posix_kill()函数来达到相同的效果。有了它就可以在父进程中控制其他子进程的运行了。比如在父进程结束之前关闭所有子进程，那么fork的时候在父进程记录所有子进程的PID，父进程结束之前依次给子进程发送结束信号即可。

实践
PHP的多进程跟C还是挺像的，搞明白了以后用其他语言写的话也大同小异差不多都是这么个情况。如果有空的话，尝试写一个小程序，切身体会一下个中滋味。下面这个程序可以读读，如果读明白了，我也就没白啰嗦这么大篇咯。最后，有问题欢迎留言交流咯。

代码
<?php
$arChildId = array();
for ($i = 0; $i < 10; $i++) {
    $iPid = pcntl_fork();
    if ($iPid == -1) {
        die('can\'t be forked.');
    }

    if ($iPid) {
        //主进程逻辑
        $arChildId[] = $iPid;
    } else {
        //子进程逻辑
        $iPid = posix_getpid();  //获取子进程的ID
        $iSeconds = rand(5, 30);
        echo '* Process ' . $iPid . ' was created, and Executed, and Sleep ' . $iSeconds . PHP_EOL;
        excuteProcess($iPid, $iSeconds);
        exit();
    }
}

while (count($arChildId) > 0) {
    foreach ($arChildId as $iKey => $iPid) {
        $res = pcntl_waitpid($iPid, $status, WNOHANG);

        if ($res == -1 || $res > 0) {
            unset($arChildId[$iKey]);
            echo '* Sub process: ' . $iPid . ' exited with ' . $status . PHP_EOL;
        }
    }
}

//子进程执行的逻辑
function excuteProcess($iPid, $iSeconds)
{
    file_put_contents('./log/' . $iPid . '.log', $iPid . PHP_EOL, FILE_APPEND);
    sleep($iSeconds);
}

?>
运行结果
* Process 16163 was created, and Executed, and Sleep 11
* Process 16164 was created, and Executed, and Sleep 21
* Process 16165 was created, and Executed, and Sleep 24
* Process 16166 was created, and Executed, and Sleep 27
* Process 16167 was created, and Executed, and Sleep 8
* Process 16168 was created, and Executed, and Sleep 14
* Process 16169 was created, and Executed, and Sleep 14
* Process 16170 was created, and Executed, and Sleep 26
* Process 16171 was created, and Executed, and Sleep 20
* Process 16172 was created, and Executed, and Sleep 21
* Sub process: 16167 exited with 0
* Sub process: 16163 exited with 0
* Sub process: 16169 exited with 0
* Sub process: 16168 exited with 0
* Sub process: 16171 exited with 0
* Sub process: 16164 exited with 0
* Sub process: 16172 exited with 0
* Sub process: 16165 exited with 0
* Sub process: 16170 exited with 0
* Sub process: 16166 exited with 0

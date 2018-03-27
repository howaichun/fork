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
    storelogs('/Users/heweijun/demo/process/log/' . $iPid . '.log', $iPid . PHP_EOL);
    //file_put_contents('/Users/heweijun/demo/process/log/' . $iPid . '.log', $iPid . PHP_EOL, FILE_APPEND);
    sleep($iSeconds);
}

/**
 * 建立文件夹
 * @param string $aimUrl
 * @return viod
 */
function createDir($aimUrl) {
    $aimUrl = str_replace('', '/', $aimUrl);
    $aimDir = '';
    $arr = explode('/', $aimUrl);
    $result = true;
    foreach ($arr as $str) {
        $aimDir .= $str . '/';
        if (!file_exists_case($aimDir)) {
            @$result = mkdir($aimDir,0777);
        }
    }
    return $result;
}

/**
 * 区分大小写的文件存在判断
 * @param string $filename 文件地址
 * @return boolean
 */
function file_exists_case($filename) {
    if (is_file($filename)) {
        if (IS_WIN) {
            if (basename(realpath($filename)) != basename($filename))
                return false;
        }
        return true;
    }
    return false;
}

function storelogs($filepath,$word){
    if(!file_exists_case($filepath)){
        $tmp =	createFile($filepath);
    }
    $fp = fopen($filepath,"a");
    flock($fp, LOCK_EX) ;
    fwrite($fp,$word."\r\n");
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * @decription 建立文件
 * @param  string $aimUrl
 * @param  boolean $overWrite 该参数控制是否覆盖原文件
 * @return boolean
 */
function createFile($aimUrl, $overWrite = false) {
    if (file_exists_case($aimUrl) && $overWrite == false) {
        return false;
    } elseif (file_exists_case($aimUrl) && $overWrite == true) {
        unlinkFile($aimUrl);
    }
    $aimDir = dirname($aimUrl);
    createDir($aimDir);
    touch($aimUrl);
    return true;
}

?>

<?php
echo PHP_EOL;
$config = require('config.php');
$fileRoot = $config['fileroot'];
$currentPath = getcwd();
$needUpload = false;

function alert($msg) {
    file_put_contents('/tmp/notify_box.sh',sprintf("DISPLAY=:0.0 notify-send '%s' '%s'".PHP_EOL,'KeePass Upload', $msg), FILE_APPEND);
}

$localFiles = scandir($fileRoot);
foreach ($localFiles as $k => $v) {
    if (in_array($v, ['.','..'])) {
        unset($localFiles[$k]);
    }
}

//judge if needUpload
$cache = @file_get_contents($currentPath.'/cache.php');
if ($cache === false) {
    $needUpload = true;
}
$cacheArr = json_decode($cache, TRUE);
$allCacheArr = $cacheArr;
if (empty($cacheArr)) {
    $needUpload = true;
}
foreach ($localFiles as $k => $file) {
    if (isset($cacheArr[$file]) && filesize($fileRoot.'/'.$file) == $cacheArr[$file]) {
        unset($localFiles[$k]);
    }
}
//本地文件对比cache中还有剩余，说明有新增或更新
if (count($localFiles) > 0) {
    $needUpload = true;
}
if ($needUpload) {

    //check server
    $serverInfoShell = sprintf('proxychains bash %s/dropbox_uploader.sh list', $currentPath);
    exec($serverInfoShell, $outputs);
    $serverFileInfo = [];
    foreach ($outputs as $row) {
        //不支持文件夹验证
        if (stripos($row, '[f]') !== false) {
            $row = trim(preg_replace('/ +/', ' ', $row));
            $tmp = explode(' ',trim($row));
            $serverFileInfo[$tmp[2]] = $tmp[1];
        }
    }
}

//检查本地cache与服务器端是否对应
foreach ($localFiles as $file) {
    if (isset($serverFileInfo[$file]) && isset($allCacheArr[$file]) && $allCacheArr[$file] != $serverFileInfo[$file]) {
        echo date('Y-m-d H:i:s');
        $errorInfo = $file.'更新有问题，服务器端文件不是本地上次的文件,请手动更新后再上传';
        echo $errorInfo.PHP_EOL;
        alert($errorInfo);
        exit;
    }
}


//really upload
if ($needUpload) {
    $shell = sprintf('proxychains bash %s/dropbox_uploader.sh upload %s/* /', $currentPath, $config['fileroot']);
    echo date('Y-m-d H:i:s');
    $result = system($shell);
#.$result.PHP_EOL;
    if (stripos($result, 'failed') !== false) {
        alert('Maybe something is wrong');
    }
} else {
    echo date('Y-m-d H:i:s').'No need to upload'.PHP_EOL;
}


//update cache
if ($needUpload) {
    $tmp = [];
    foreach ($localFiles as $v) {
        $tmp[$v] = filesize($fileRoot.'/'.$v);
    }
    $writeResult = file_put_contents($currentPath.'/cache.php', json_encode($tmp));
    if ($writeResult === false) {
        alert('error while writing cache file');
    }
}


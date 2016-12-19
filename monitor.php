<?php
echo PHP_EOL;
$config = require('config.php');
$fileRoot = $config['fileroot'];
$currentPath = getcwd();
$needUpload = false;

function alert($msg) {
    file_put_contents('/tmp/notify_box.sh',sprintf("DISPLAY=:0.0 notify-send '%s' '%s'".PHP_EOL,'KeePass Upload', $msg), FILE_APPEND);
}

$files = scandir($fileRoot);
foreach ($files as $k => $v) {
    if (in_array($v, ['.','..'])) {
        unset($files[$k]);
    }
}

//judge if needUpload
$cache = @file_get_contents($currentPath.'/cache.php');
if ($cache === false) {
    $needUpload = true;
}
$cacheArr = json_decode($cache, TRUE);
if (empty($cacheArr)) {
    $needUpload = true;
}
foreach ($files as $k => $file) {
    if (isset($cacheArr[$file]) && md5_file($fileRoot.'/'.$file) == $cacheArr[$file]) {
        unset($files[$k]);
    }
}
if (count($files) > 0) {
    $needUpload = true;
}

//update cache
if ($needUpload) {
    $tmp = [];
    foreach ($files as $v) {
        $tmp[$v] = md5_file($fileRoot.'/'.$v);
    }
    $writeResult = file_put_contents($currentPath.'/cache.php', json_encode($tmp));
    if ($writeResult === false) {
        alert('error while writing cache file');
    }
}
$needUpload = true;

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


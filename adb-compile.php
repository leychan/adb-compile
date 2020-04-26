<?php
/**
 * Created by chenlei
 * Date 2020/4/3
 * Time 上午8:38
 * Desc
 */

//检查是否连接设备
checkDeviceAttached();

$user = getUser();

$path = "/home/{$user}/MEGAsync/文件/安卓应用编译/compiled.txt";

if (!file_exists($path)) {
    echo 'file not exists, new file will be created when compiling is done', PHP_EOL;
}

echo '读取编译记录...', PHP_EOL;
$compiled_pack = json_decode(file_get_contents($path), true);

echo '读取安装的应用程序记录...', PHP_EOL;
$packages = allPackages();

$total = count($packages);
echo '总共安装',$total,'个程序', PHP_EOL;

$i = 0; //编译数量
$j = 0; //进度标识

foreach ($packages as $p => $ver) {
    $j++;
    echo '当前进度: ', $j, '/', $total, PHP_EOL;
    if (checkExcept($p)) {
        continue;
    }

    if (!isset($compiled_pack[$p]) || $compiled_pack[$p] != $ver) {
        $compiled_pack[$p] = $ver;
        compile($p);
        $i++;
    }
}
echo 'compiled ', $i, ' packages', PHP_EOL;

if ($i) {
    file_put_contents($path, json_encode($compiled_pack));
}

if ($i >= 10) {
    shell_exec('adb reboot');
}

function checkDeviceAttached()
{
    exec("adb devices", $arr);
    if (!isset($arr[1]) || strpos($arr[1], 'device') === false) {
        echo 'no device found, exit.';
        exit;
    }
}


function checkExcept(string $line): bool
{
    $except_arr = [
        'com.android.systemui'
    ];
    if (in_array($line, $except_arr)) {
        return true;
    }
    return false;
}

function getAppVersionInfo($line)
{
    $shell = 'adb shell dumpsys package ' . $line . ' | grep versionName';
    exec($shell, $version_arr);
    $version_arr = filterVersion($version_arr);
    $version_real = formatVersion($version_arr[0]);
    return $version_real;
}

function compareVersion($version1, $version2)
{
    if ($version1 == $version2) {
        return $version1;
    }
    $v1 = explode('.', $version1);
    $v2 = explode('.', $version2);
    $len = count($v1) > count($v2) ? count($v1) : count($v2);
    for ($i = 0; $i < $len; $i++) {
        if (isset($v1[$i]) && isset($v2[$i]) && $v1[$i] != $v2[$i]) {
            return $v1[$i] > $v2[$i] ? $version1 : $version2;
        } else {
            if (!isset($v1[$i])) {
                return $version2;
            }
            return $version1;
        }
    }
}

function filterVersion($version_arr)
{
    return array_filter($version_arr, "notEmpty");
}

function notEmpty($var)
{
    return !empty($var);
}

function formatVersion($version)
{
    $version_tmp = explode('=', $version);
    return $version_tmp[1] ?? '';
}

function formatPackageName($line)
{
    return substr($line, 8);
    //return trim(str_replace('package:', '', $line));
}

function compile($p)
{
    echo $p, PHP_EOL;
    $exec = 'adb shell cmd package compile -m everything -f ' . $p;
    echo shell_exec($exec), PHP_EOL;
}

function allPackages()
{
    $time = time();
    exec('adb shell pm list package', $raw);
    $packages = array_map("formatPackageName", $raw);
    $return = [];
    foreach ($packages as $p) {
        $version = getAppVersionInfo($p);
        $return[$p] = $version;
    }
    echo '读取安装的应用程序记录花费', time() - $time, 's', PHP_EOL;
    return $return;
}

function getUser()
{
    return getenv('USER');
}
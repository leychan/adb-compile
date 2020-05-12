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
echo '总共安装', $total, '个程序', PHP_EOL;

$should_compile = [];
// 对比版本号, 找出需要编译的应用程序
foreach ($packages as $p => $ver) {
    if (checkExcept($p)) {
        continue;
    }
    if (!isset($compiled_pack[$p]) || $compiled_pack[$p] != $ver) {
        //新版本号写入记录
        $compiled_pack[$p] = $ver;
        array_push($should_compile, $p);
    }
}

//需要编译的应用程序数量
$should_compile_num = count($should_compile);

echo '需要编译' . $should_compile_num . '应用程序', PHP_EOL;

if ($should_compile_num > 0) {
    for ($i = 0; $i < $should_compile_num; $i++) {
        compile($should_compile[$i]);
        echo '当前进度: ' . ($i + 1) . '/' . $should_compile_num, PHP_EOL, PHP_EOL;
    }

    echo 'compiled ', $i, ' packages', PHP_EOL;

    file_put_contents($path, json_encode($compiled_pack));

    echo PHP_EOL, '正在停止新编译应用程序...', PHP_EOL;
    for ($i = 0; $i < $should_compile_num; $i++) {
        stopApp($should_compile[$i]);
        echo '当前进度: ' . ($i + 1) . '/' . $should_compile_num, PHP_EOL, PHP_EOL;
    }

} else {
    echo '无需要编译的应用程序', PHP_EOL;
}

echo 'all done.';
//if ($i >= 10) {
//    shell_exec('adb reboot');
//}

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
    return strlen($line) > 8 ? substr($line, 8) : '';
    //return trim(str_replace('package:', '', $line));
}

function compile($p)
{
    echo $p, PHP_EOL;
    $exec = 'adb shell cmd package compile -m everything -f ' . $p;
    echo shell_exec($exec);
}

function stopApp($p) {
    echo '正在停止' . $p . PHP_EOL;
    $exec = 'adb shell am kill ' . $p;
    echo shell_exec($exec);
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
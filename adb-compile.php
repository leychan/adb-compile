<?php
/**
 * Created by chenlei
 * Date 2020/4/3
 * Time 上午8:38
 * Desc
 */

//检查是否连接设备
checkDeviceAttached();

$path = '/home/chenlei/MEGAsync/文件/安卓应用编译/compiled.txt';

$compiled_pack = json_decode(file_get_contents($path), true);

$packages = allPackages();

$i = 0;

foreach ($packages as $p => $ver) {
    if (checkExcept($p)) {
        continue;
    }
    if (!isset($compiled_pack[$p]) || $compiled_pack[$p] != $ver) {
        $i++;
        $compiled_pack[$p] = $ver;
        compile($p);
    }
}
echo 'compiled ' . $i . 'packages', PHP_EOL;
if ($i) {
    file_put_contents($path, json_encode($compiled_pack));
}

//shell_exec('adb reboot');

function checkDeviceAttached()
{
    $str = shell_exec("adb devices");
    $arr = explode(PHP_EOL, $str);
    if (!isset($arr[1]) && strpos($arr[1], 'device') === false) {
        echo 'no device found, exit.';
        exit;
    }
}


function checkExcept(string $line): bool
{
    if (
        strpos($line, 'ui') !== false
        || strpos($line, 'samsung') !== false
        || strpos($line, 'desktop') !== false
        || strpos($line, 'bixby') !== false
        || strpos($line, 'knox') !== false
    ) {
        return true;
    }
    return false;
}

function getAppVersionInfo($line)
{
    $shell = 'adb shell dumpsys package ' . $line . ' | grep versionName';
    $version_str = shell_exec($shell);
    $version_arr = explode(PHP_EOL, $version_str);
    $version_arr = filterVersion($version_arr);
    $version_real = '';
    foreach ($version_arr as $version) {
        $version = formatVersion($version);
        if (empty($version)) {
            $version_real = $version;
            continue;
        }
        $version_real = compareVersion($version_real, $version);
    }
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
    return trim(str_replace('package:', '', $line));
}

function compile($p)
{
    echo $p, PHP_EOL;
    $exec = 'adb shell cmd package compile -m everything -f ' . $p;
    echo shell_exec($exec), PHP_EOL;
}

function allPackages() {
    $raw = shell_exec('adb shell pm list package');
    $raw_arr = explode(PHP_EOL, $raw);
    $packages = array_map("formatPackageName", $raw_arr);
    $return = [];
    foreach ($packages as $p) {
        $version = getAppVersionInfo($p);
        $return[$p] = $version;
    }
    return $return;
}
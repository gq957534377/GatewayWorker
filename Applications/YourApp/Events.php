<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */

//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前client_id发送数据 
        Gateway::sendToClient($client_id, "Hello $client_id\r\n");
        // 向所有人发送
        Gateway::sendToAll("$client_id login\r\n");
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */


    public static function onMessage($client_id, $message)
    {
//

//       ad0302 01 14000000 39 1437111c040c14 3456 2802bfc8b4be 1130 0002 00 0d0a
//       ad0302 02 14000000 38 1437111c040c14 3456 2802bfc8b4be 1130 0001 00 0d0a
//       ad0302 01 14000000 39 1437111c040c14 3456 2802bfc8b4be 1130 0005 00 0d0a

//       ad0301 03 12000000 3a 3456 2802bfc8b4be 02 00 5805 e304 0c05 00000d0a
//       ad0301 04 12000000 39 3456 2802bfc8b4be 02 00 5805 e304 0c05 00000d0a
//       ad0301 06 12000000 37 3456 2802bfc8b4be 01 00 5805 e304 0c05 00000d0a
        $ip = $_SERVER['REMOTE_ADDR'];
        // 向所有人发送
        //Gateway::sendToAll("$client_id said $message\r\n");
//		echo(" $client_id said   $IP : ".bin2hex($message)."  \r\n  ");

//        echo $message;

        $hex_data = bin2hex($message);
        var_dump($hex_data);
//        $hex_data="ad03020114000000391437111c040c1434562802bfc8b4be11300002000d0a";
        $type = substr($hex_data, 0, 6);
        $serial_number = substr($hex_data, 6, 2);// 序列号
        $check_sum = substr($hex_data, 16, 2);// 校验和

        $sendString = '';
        switch ($type) {
            case 'ad0301': // 心跳数据
                $sendString = "AD9909" . $serial_number . "07000000" . $check_sum . "1437111C040C140D0A";
                break;
            case 'ad0302': // 报警数据
                $sendString = "AD9911" . $serial_number . "06000000" . $check_sum . "0000000200000D0A";
                $event_num = substr($hex_data, -14, 4);// 报警事件
                $sector = substr($hex_data, -10, 4);// 防区
                $alarm_res = self::_curl("http://www.huaxue.com/alarm_message", [
                    'client_id' => $client_id,
                    'ip' => $ip,
                    'type' => $type,
                    'event_num' => $event_num,
                    'hex_data' => $hex_data,
                    'serial_number' => $serial_number,
                    'sector' => $sector,
                    'check_sum' => $check_sum,
                ]);
                var_dump($alarm_res);
                break;
            default:
                break;
        }
        $sendString = "AD9909" . $serial_number . "07000000" . $check_sum . "1437111C040C140D0A";
        //Gateway::sendToAll("$client_id said  $IP : 16form: $hex_data   cmd \r\n");
        //echo(" $client_id said   $IP : 二进制:  $message   \r\n  ");


        $send_datttt = hex2bin($sendString);

//        Gateway::sendToAll("$client_id said  $IP : " . $string . "  wengjiang \r\n");


//       AD99091E070000008C1437111C040C140D0A
//       AD99110206000000380000000200000D0A
        Gateway::sendToClient($client_id, $send_datttt);


        // 如果报警，通知web端报警
        // 发送数据给机器（1.询问web是否有处理完成消息，2.发送对应的回应消息）
        //curl 访问其他系统
//        $curl_res = self::_curl("http://www.huaxue.com/alarm_message?id=2222");
//        var_dump($curl_res);
//        Gateway::sendToAll("$client_id said http $output \r\n");
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // 向所有人发送
        GateWay::sendToAll("$client_id logout\r\n");
    }

    public static function _curl($url, $param = [])
    {
        $httpUrl = $url.'?'.http_build_query($param);
        var_dump($httpUrl);
        // 1. 初始化
        $ch = curl_init();
        // 2. 设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $httpUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-type: application/json","X-Requested-With:XMLHttpRequest"));

        // 3. 执行并获取HTML文档内容
        $output = curl_exec($ch);
        // 4. 释放curl句柄
        curl_close($ch);

        if ($output === FALSE) {
            echo "CURL Error:" . curl_error($ch);
            return false;
        }

        $result = json_decode($output);
        if (empty($result)) {
            echo "web 服务器异常 请联系管理员！错误信息：" . $output;
            return false;
        }

        if (empty($result->StatusCode)){
            echo "web 服务器异常 请联系管理员！错误信息：" . $output;
            return false;
        }

        if ($result->StatusCode != 200) {
            echo "web 服务器提示错误 请联系管理员！错误信息：" . $result->ResultData;
            return false;
        }

        return $result;
    }

}

<?php
session_start();
class Robot
{
    private $cookieUrl;
    private $dataUrl;
    
    function __construct(){
        $this->cookieUrl = "http://osp.voicecloud.cn/index.php/default/quicktest/index";
        $this->dataUrl = "http://osp.voicecloud.cn/index.php/ajax/quicktest/index";
        if ( !isset($_SESSION['cookie']) ) {
            $_SESSION['cookie'] = $this->getCookie();
        }
    }
    /**
     * 获取cookie
     * @return $cookie
     */
    private function getCookie() {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->cookieUrl);
        curl_setopt($ch,CURLOPT_HEADER,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $options = array(
            'User-Agent:Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
        );
        curl_setopt($ch,CURLOPT_HTTPHEADER,$options);
        $result = curl_exec($ch);
        curl_close($ch);
        $pattern='/Set-Cookie: .*;/';
        preg_match_all($pattern,$result,$cookie);
        $cookie = substr($cookie[0][0],12,36);
        return $cookie;
    }
    /**
     * 获取数据
     * @param $content
     * @return $data
     */
    private function getData($content) {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->dataUrl);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $options = array(
            'User-Agent:Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
        );
        curl_setopt($ch,CURLOPT_HTTPHEADER,$options);
        curl_setopt($ch,CURLOPT_COOKIE,$_SESSION['cookie']);
        // post
        $postData = 'appid=52a6ee99&space%5B%5D=app&space%5B%5D=flower&space%5B%5D=websearch&space%5B%5D=radio&space%5B%5D=shortRent&space%5B%5D=telephone&space%5B%5D=pm25&space%5B%5D=weibo&space%5B%5D=message&space%5B%5D=tvControl&space%5B%5D=cookbook&space%5B%5D=tv&space%5B%5D=airControl&space%5B%5D=gift&space%5B%5D=hotel&space%5B%5D=music&space%5B%5D=weather&space%5B%5D=stock&space%5B%5D=map&space%5B%5D=video&space%5B%5D=website&space%5B%5D=translation&space%5B%5D=schedule&space%5B%5D=train&space%5B%5D=flight&space%5B%5D=restaurant&generalqa%5B%5D=chat&generalqa%5B%5D=1&generalqa%5B%5D=2&generalqa%5B%5D=3&generalqa%5B%5D=datetime&generalqa%5B%5D=calc&generalqa%5B%5D=baike&generalqa%5B%5D=faq&text='.$content;
        //$postData = 'appid=52a6ee99&space%5B%5D=app&space%5B%5D=schedule&space%5B%5D=website&space%5B%5D=map&space%5B%5D=stock&space%5B%5D=weather&space%5B%5D=hotel&space%5B%5D=music&space%5B%5D=video&space%5B%5D=translation&space%5B%5D=train&space%5B%5D=flight&space%5B%5D=restaurant&space%5B%5D=websearch&space%5B%5D=radio&space%5B%5D=pm25&space%5B%5D=weibo&space%5B%5D=cookbook&space%5B%5D=tv&space%5B%5D=gift&space%5B%5D=airControl&space%5B%5D=tvControl&space%5B%5D=message&space%5B%5D=telephone&space%5B%5D=shortRent&space%5B%5D=flower&subSpace%5B%5D=airControl_smartHome&subSpace%5B%5D=fan_smartHome&subSpace%5B%5D=light_smartHome&subSpace%5B%5D=airCleaner_smartHome&subSpace%5B%5D=cleaningRobot_smartHome&subSpace%5B%5D=slot_smartHome&subSpace%5B%5D=switch_smartHome&subSpace%5B%5D=freezer_smartHome&subSpace%5B%5D=washer_smartHome&subSpace%5B%5D=curtain_smartHome&subSpace%5B%5D=riceCooker_smartHome&subSpace%5B%5D=humidifier_smartHome&subSpace%5B%5D=oven_smartHome&subSpace%5B%5D=electricKettle_smartHome&subSpace%5B%5D=towelRack_smartHome&subSpace%5B%5D=webcam_smartHome&subSpace%5B%5D=tv_smartHome&subSpace%5B%5D=inductionStove_smartHome&subSpace%5B%5D=underFloorHeating_smartHome&subSpace%5B%5D=window_smartHome&subSpace%5B%5D=heater_smartHome&subSpace%5B%5D=bathroomMaster_smartHome&subSpace%5B%5D=rangeHood_smartHome&subSpace%5B%5D=musicPlayer_smartHome&subSpace%5B%5D=microwaveOven_smartHome&subSpace%5B%5D=waterHeater_smartHome&subSpace%5B%5D=airVent_smartHome&subSpace%5B%5D=toiletLid_smartHome&subSpace%5B%5D=racks_smartHome&subSpace%5B%5D=airCooler_smartHome&subSpace%5B%5D=homeMonitor_smartHome&subSpace%5B%5D=footBath_smartHome&subSpace%5B%5D=dishWasher_smartHome&subSpace%5B%5D=deHumidifier_smartHome&subSpace%5B%5D=coffeeMaker_smartHome&subSpace%5B%5D=cookStove_smartHome&subSpace%5B%5D=soymilk_smartHome&subSpace%5B%5D=toaster_smartHome&subSpace%5B%5D=sterilizer_smartHome&generalqa%5B%5D=1&generalqa%5B%5D=2&generalqa%5B%5D=3&generalqa%5B%5D=datetime&generalqa%5B%5D=calc&generalqa%5B%5D=baike&generalqa%5B%5D=faq&generalqa%5B%5D=chat&text='.$content;
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$postData);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    /**
     * 对外提供函数
     * @param  $content 发送消息内容
     * @return string   回复消息内容
     */
    public function index($content){
        $data = $this->getData($content);
        $data = json_decode($data,true);
        if( !empty($data['answer']['text']) ){
            return $data['answer']['text'];
        } else if (!empty($data['webPage']['header'])) {
            return $data['webPage']['header'].':'.$data['webPage']['url'];
        } else {
            return '本宝宝不知道该怎么回答了！\(^o^)/~';
        }
    }
}


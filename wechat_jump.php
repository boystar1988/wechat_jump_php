<?php

/**
 * @name 微信跳一跳脚本
 * @author zhaozhuobin
 * @date 2018-01-08
 */
class wechatJumpApp
{
    /**
     * 应用名称
     * @var string
     */
    public $appName = 'wechat_jump';

    /**
     * 手机截图路径
     * @var string
     */
    public $screenPath = '/sdcard/';

    /**
     * 截图文件名
     * @var string
     */
    public $screenName = 'autojump.png';

    /**
     * 棋子底座高度的1/2
     * @var int
     */
    public $pieceBaseHeightHalf = 16;

    /**
     * 棋子的宽度
     * @var int
     */
    public $pieceBodyWidth = 64;

    /**
     * 按压时间，请自己根据实际情况调节
     * @var float
     */
    public $pressCoefficient = 1.45;

	/**
     * 精度（太小影响性能）
     * @var int
     */
    public $pieceAccuracy = 5;

    /**
     * 精度（太小影响性能）
     * @var int
     */
    public $nextJumpAccuracy = 1;

    private $data=[];

    public function run()
    {
        try{
            $this->init();
            $this->deviceInfo();
            while(1){
                $this->screenShot($this->screenPath.$this->screenName);
                $res = $this->findPieceAndBoard();
                $this->jump($res);
                sleep(1);
            }
        }catch (Exception $e){
            echo $this->colorize($e->getMessage(),"FAILURE").PHP_EOL;
            exit;
        }
    }

    /**
     * 初始化配置
     */
    public function init()
    {
        if(file_exists('config.php')){
            $config = include_once 'config.php';
            $config = $config[$this->appName];
            $reflectionClass = new ReflectionClass($this);
            foreach($config as $k=>$v){
                if($reflectionClass->hasProperty($k) && $reflectionClass->getProperty($k)->isPublic()){
                    $this->$k = $v;
                }
            }
        }
    }

    /**
     * 获取设备信息
     * @throws Exception
     */
    public function deviceInfo()
    {
        echo "===========================".PHP_EOL;
        echo "[微信跳一跳精灵PHP版]".PHP_EOL;
        echo "作者:zhaozhuobin".PHP_EOL;
        echo "日期:2018-01-08".PHP_EOL;
        $model = trim(shell_exec('adb shell getprop ro.product.model'));
        if($model == 'error: no devices/emulators found'){
            throw new Exception("未检测到手机");
        }
        preg_match('/Physical size: (\d+)x(\d+)/', shell_exec('adb shell wm size'),$size);
        echo "手机型号：{$model}".PHP_EOL;
        echo "手机屏幕：{$size[1]} x {$size[2]}".PHP_EOL;
        $this->data['width'] = $size[1];
        $this->data['height'] = $size[2];
        echo "===========================".PHP_EOL;
    }

    /**
     * 保存手机截图
     * @param $path
     * @throws Exception
     */
    public function screenShot($path)
    {
        if(empty($path)){
            throw new Exception("必须配置截屏路径");
        }
        if(file_exists($this->screenName)){
            unlink($this->screenName);
        }
        echo "[".date("H:i:s")."] 截屏中...".PHP_EOL;
        shell_exec("adb shell screencap -p ".$path);
        shell_exec("adb pull ".$this->screenPath.$this->screenName." .");
        if(!file_exists($this->screenName)){
            throw new Exception("截图失败");
        }
        //Todo: 截取分数部分
        $screen = imagecreatefrompng($this->screenName);
        $after = imagecreatetruecolor($this->data['width']/3,120);
        imagecopy($after,$screen,0,0,0,180,$this->data['width']/3,300);
        imagedestroy($screen);
        imagepng($after,'autojump_score.png');
        echo "[".date("H:i:s")."] 截屏完成...".PHP_EOL;
        exit;
    }

    /**
     * 查找棋子和棋盘位置
     * @return array
     */
    public function findPieceAndBoard()
    {
        echo "[".date("H:i:s")."] 开始计算...".PHP_EOL;
        $pngInfo = getimagesize($this->screenName);
        list($width,$height) = $pngInfo;
        $img = imagecreatefrompng($this->screenName);
        //Todo: 计算扫描起点
        $scanStartY = 0;
        for($h=intval($height/3);$h<intval(2*$height/3);$h+=50){
            $lastColor = $this->getColor($img,0,$h);
            for($w=1;$w<$width;$w++){
                $color = $this->getColor($img,$w,$h);
                if($color[0]!=$lastColor[0] || $color[1]!=$lastColor[1] || $color[2]!=$lastColor[2]){
                    $scanStartY = $h - 50;
                    break;
                }
            }
            if($scanStartY){
                break;
            }
        }

        //Todo: 计算棋子坐标
        $pieceXSum = 0;
        $pieceXC = 0;
        $pieceYMax = 0;
        $scanXBorder = intval($width/8);
        for($h=$scanStartY;$h<intval(2*$height/3);$h+=$this->pieceAccuracy){
            for($w=$scanXBorder;$w<($width-$scanXBorder);$w+=$this->pieceAccuracy){
                $color = $this->getColor($img,$w,$h);
                if(
                    (50 < $color[0]) && ($color[0]< 60) &&
                    (53 < $color[1]) && ($color[1]< 63) &&
                    (95 < $color[2]) && ($color[2]< 110)
                ){
                    $pieceXSum += $w;
                    $pieceXC += 1;
                    $pieceYMax = max($h,$pieceYMax);
                }
            }
        }
        if($pieceXSum == 0 || $pieceXC == 0){
            return [0,0,0,0];
        }
        $pieceX = intval($pieceXSum / $pieceXC);
        $pieceY = $pieceYMax - $this->pieceBaseHeightHalf;
        echo "[".date("H:i:s")."] 棋子坐标: ".$pieceX.','.$pieceY.PHP_EOL;

        //Todo: 计算棋盘坐标
        $boardX = 0;
        $boardY = 0;
        //修复音符BUG
        if($pieceX < $width/2){
            $boardXStart = $pieceX;
            $boardXEnd = $width;
        }else{
            $boardXStart = 0;
            $boardXEnd = $pieceX;
        }
        for($h=intval($height/3);$h<intval(2*$height/3);$h+=$this->nextJumpAccuracy){
            $lastColor = $this->getColor($img,0,$h);
            if($boardX || $boardY){
                break;
            }
            $boardXSum = 0;
            $boardXC = 0;
            for($w=intval($boardXStart);$w<intval($boardXEnd);$w+=$this->nextJumpAccuracy){
                $color = $this->getColor($img,$w,$h);
                //棋子比下一跳高bug
                if(abs($w - $pieceX) < $this->pieceBodyWidth){
                    continue;
                }
                //下一跳圆形块出现一条线的bug
                if (
                    abs($color[0] - $lastColor[0]) +
                    abs($color[1] - $lastColor[1]) +
                    abs($color[2] - $lastColor[2]) > 10
                ){
                    $boardXSum += $w;
                    $boardXC += 1;
                }
            }
            if($boardXSum){
                $boardX = intval($boardXSum / $boardXC);
            }
        }
        $lastColor = $this->getColor($img,$boardX,$h);
        echo "[".date("H:i:s")."] 计算棋盘坐标阶段1".PHP_EOL;

        //从上顶点往下+274的位置开始向上找颜色与上顶点一样的点，为下顶点(对纯色平面有效)
        //取开局时最大的方块的上下顶点距离
        $beginBigBoardTop = 274;
        for($k=$h+$beginBigBoardTop;$k>$h;$k--){
            $color = $this->getColor($img,$boardX,$k);
            if (
                abs($color[0] - $lastColor[0]) +
                abs($color[1] - $lastColor[1]) +
                abs($color[2] - $lastColor[2]) < 10
            ){
                break;
            }
        }
        $boardY = intval(($h+$k)/2);
        echo "[".date("H:i:s")."] 计算棋盘坐标阶段2".PHP_EOL;

        //上一跳命中中间，则下一跳中心会出现r245 g245 b245的bug
        for($l=$h;$l<$h+200;$l++){
            $color = $this->getColor($img,$boardX,$l);
            if(abs($color[0] - 245) + abs($color[1] - 245) + abs($color[2] - 245) == 0){
                $boardY = $l + 10;
                break;
            }
        }
        echo "[".date("H:i:s")."] 下一跳坐标: ".$boardX.','.$boardY.PHP_EOL;
        if($boardX == 0 || $boardY == 0){
            return [0,0,0,0];
        }
        return [$pieceX,$pieceY,$boardX,$boardY];
    }

    /**
     * 跳!
     * @param $res
     */
    public function jump($res)
    {
        $distance = sqrt( pow($res[2]-$res[0],2) + pow($res[3]-$res[1],2) );
        echo "[".date("H:i:s")."] 距离:".$distance.PHP_EOL;
        $pressTime = $distance * $this->pressCoefficient;
        $pressTime = max(200,intval($pressTime));
        echo "[".date("H:i:s")."] 转换成按压时间:".$pressTime.PHP_EOL;
        $x1 = $x2 = $this->data['width']/2;
        //再玩一局按钮位置
        $y1 = $y2 = intval(1584 * ($this->data['height'] / 1920.0));
        $cmd = "adb shell input swipe $x1 $y1 $x2 $y2 $pressTime";
        echo "[".date("H:i:s")."] 命令行:".$cmd.PHP_EOL;
        shell_exec($cmd);
        echo "===========================".PHP_EOL;
    }

    /**
     * 获取单点颜色
     * @param $img
     * @param $x
     * @param $y
     * @return array
     */
    public function getColor($img,$x,$y)
    {
        $rgb = imagecolorat($img,$x,$y);
        $r=($rgb >>16) & 0xFF;
        $g=($rgb >>8) & 0xFF;
        $b=$rgb & 0xFF;
        return [$r,$g,$b];
    }

    /**
     * 命令行样式
     * @param $text
     * @param string $status
     * @return string
     * @throws Exception
     */
    public function colorize($text, $status="FAILURE")
    {
        switch($status) {
            case "SUCCESS":
                $out = "[42m"; //Green background
                break;
            case "FAILURE":
                $out = "[41m"; //Red background
                break;
            case "WARNING":
                $out = "[43m"; //Yellow background
                break;
            case "NOTE":
                $out = "[44m"; //Blue background
                break;
            default:
                throw new Exception("Invalid status: " . $status);
        }
        echo chr(27) . "$out" . "$text" . chr(27) . "[0m" .PHP_EOL;
    }

}

$app = new wechatJumpApp();
$app->run();

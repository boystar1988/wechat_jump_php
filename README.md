# 微信跳一跳脚本精灵PHP版本

### 一、安装adb驱动
#### [Windows]
##### (1)手机驱动
`http://dl.adbdriver.com/upload/adbdriver.zip`
##### (2)adb环境
`http://adbshell.com/upload/adb.zip`

#### [MacOs]
`brew cask install android-platform-tools`

### 二、执行
##### 命令行模式
`php wechat_jump.php`

### 三、原理
>利用手机端截图,拉取到本地进行图像分析,通过计算棋子和下一跳中心的距离,从而算出需要按压的时间,
通过adb shell进行模拟按压

![Alt text](https://github.com/boystar1988/wechat_jump_php/blob/master/autojump.png "微信跳一跳精灵PHP")
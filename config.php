<?php

return [
	//微信跳一跳
	'wechat_jump'=>[
		'screenPath' 			=> '/sdcard/',		//手机截图路径
		'screenName' 			=> 'autojump.png',	//截图文件名
		'pieceBaseHeightHalf' 	=> 16,				//二分之一的棋子底座高度，可能要调节
		'pieceBodyWidth' 		=> 64,				//棋子的宽度，比截图中量到的稍微大一点比较安全，可能要调节
		'pressCoefficient' 		=> 1.45,			//按压时间，请自己根据实际情况调节
		'pieceAccuracy' 		=> 5,				//当前棋子位置计算精度,1-10(1精度最高,但是耗性能)
		'nextJumpAccuracy' 		=> 1,				//下一跳棋盘位置计算精度(这里不能设太大,建议值1)
	],
];
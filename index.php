<?php
/**png转jpg，因为PHP操作jpg格式的函数更强大一些，摘抄网上的
 * 	srcPathName 图片地址
 *	delOri 是否删除源文件
 **/
function png2jpg($srcPathName, $delOri=true)  
{  
    $srcFile=$srcPathName;  
    $srcFileExt=strtolower(trim(substr(strrchr($srcFile,'.'),1)));  
    if($srcFileExt=='png')  
    {  
        $dstFile = str_replace('.png', '.jpg', $srcPathName);  
        $photoSize = GetImageSize($srcFile);  
        $pw = $photoSize[0];  
        $ph = $photoSize[1];  
        $dstImage = ImageCreateTrueColor($pw, $ph);  
        imagecolorallocate($dstImage, 255, 255, 255);  
        //读取图片  
        $srcImage = ImageCreateFromPNG($srcFile);  
        //合拼图片  
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $pw, $ph, $pw, $ph);  
        imagejpeg($dstImage, $dstFile, 90);  
        if ($delOri)  
        {  
            unlink($srcFile);  
        }  
        imagedestroy($srcImage);  
    }  
}

/**
 * 发送命令控制手机截图复制到F盘
 */
function pull_screenshot() {
    $screenShell = "cd C:\Users\Administrator&adb shell /system/bin/screencap -p /sdcard/screenshot.png&adb pull /sdcard/screenshot.png F:tiaoyitiao/screenshot.png";
    exec($screenShell);
}

/**
 * 控制跳，传入距离
 */
function jump($distance){
    $param = 1.393;//可以在游戏的第一步自己进行反复调试，不同分辨率测出来不一样
    $time = $distance*$param;
    $time = round($time,0);
    //计算完毕之后执行弹跳
    $touchShell = "cd C:\Users\Administrator&adb shell input swipe 50 250 250 250 ".$time;
    exec($touchShell);
    //延迟2.5秒后截图出来
}

/**
 * 分析棋子和下一个棋盘的距离
 */
function mmain(){
	/*获取截图*/
	pull_screenshot();
	sleep(1);
	png2jpg("F:tiaoyitiao/screenshot.png",true);
	// sleep(1);
	$url = 'F:tiaoyitiao/screenshot.jpg';
	$img = imagecreatefromjpeg($url);  
	$img_info = getimagesize($url);
	$p_w = $img_info[0];//图片宽度
	$p_h = $img_info[1];//图片高度
	$piece_x_sum = 0;
	$piece_x_c = 0;
	$piece_y_max = 0;
	/*下一个棋盘的坐标*/
	$board_x = 0;
	$board_y = 0;
	$piece_h_half = 23;//二分之一的棋子底座高度，可能要调节
	$scan_start_y = 0;  //扫描的起始y坐标
	$under_game_score_y = 350; //截图中刚好低于分数显示区域的 Y 坐标，直接从低于分数显示开始扫描，不同分辨率不同
	$piece_body_width = 78; //棋子的宽度
	//下面的 (353, 859) 和 (772, 1100) 是游戏截图里刚开始两个台子的中点坐标，主要用来算角度，不同分辨率可能会不一样
	$sample_board_x1 = 353;
	$sample_board_y1 = 859;	
	$sample_board_x2 = 772;
	$sample_board_y2 = 1100;

	//棋子的位置piece_x_c  piece_y_max
	for ($y=$under_game_score_y; $y<$p_h ;$y+=10) {
		$last_rgb = imagecolorat($img,1,$y);
		$lastr = ($last_rgb >> 16) & 0xFF;  
	    $lastg = ($last_rgb >> 8) & 0xFF;  
	    $lastb = $last_rgb & 0xFF;
	  	for ($x=1; $x<$p_w; $x++) {
			$rgb = imagecolorat($img, $x, $y);
			$r = ($rgb >> 16) & 0xFF;  
		    $g = ($rgb >> 8) & 0xFF;  
		    $b = $rgb & 0xFF;
		    if ($lastr != $r or $lastg != $g or $lastb != $b){
		    	echo "$x $y<br/>";
	            $scan_start_y = $y - 50;
	            break;
	        }
	        if ($scan_start_y)
	            break;
		}
	}
	echo "$scan_start_y <br/>";

	//棋子的位置piece_x_c  piece_y_max
	for ($y=$scan_start_y; $y<$p_h*2/3 ;$y++) {
	  	for ($x=1; $x<$p_w; $x++) { 
		    $rgb = imagecolorat($img,$x,$y);  
		    $r = ($rgb >> 16) & 0xFF;  
		    $g = ($rgb >> 8) & 0xFF;  
		    $b = $rgb & 0xFF;
		    if ((50 < $r && $r < 60) && (53 < $g && $g < 63) && (95 < $b && $b < 110)){
			    $piece_x_sum += $x;
			    $piece_x_c += 1;
			    $piece_y_max = max($y, $piece_y_max);//找到棋子最低端的点
		}
	  }
	  // echo "<br/>";
	} 
	$piece_x = $piece_x_sum / $piece_x_c;//取所有符合条件颜色点的平均值，这样会获取到相对合适的重心
	$piece_y = $piece_y_max - $piece_h_half;//棋子的Y坐标取棋子的中间部分
	echo "$piece_x_sum $piece_x_c $piece_y_max <br/>";
	echo "$piece_x $piece_y <br/>";

	//下一个圆盘的位置board_x_c  board_y_max
	for ($y=$scan_start_y; $y<$p_h*2/3 ;$y++) {
		$last_rgb = imagecolorat($img,1,$y);
		$lastr = ($last_rgb >> 16) & 0xFF;  
	    $lastg = ($last_rgb >> 8) & 0xFF;  
	    $lastb = $last_rgb & 0xFF;

	    if ($board_x or $board_y)
	        break;
	    $board_x_sum = 0;
	    $board_x_c = 0;

	  	for ($x=1; $x<$p_w; $x++) { 
	        //修掉脑袋比下一个小格子还高的情况的 bug
	  		if (abs($x - $piece_x) < $piece_body_width)
	            continue;
		    $rgb = imagecolorat($img,$x,$y);//获取RGB
		    $r = ($rgb >> 16) & 0xFF;  
		    $g = ($rgb >> 8) & 0xFF;  
		    $b = $rgb & 0xFF;
		    //修掉出现圆顶棋盘的时候一条线识别错误导致的bug
	        if (abs($r - $lastr) + abs($g - $lastg) + abs($g - $lastg) > 10){
	            $board_x_sum += $x;
	            $board_x_c += 1;
	        }
	  	}
	    if ($board_x_sum)
	        $board_x = $board_x_sum / $board_x_c;//取X坐标重心，和上面棋子一个意思
	}
	/*根据角度来获得Y坐标，假设是一个直角坐标系，已经知道下一个棋盘的X坐标和棋子的坐标，然后分析一下一开始的两个点角度，那就能获得棋盘的Y坐标了*/
	$board_y = $piece_y - abs($board_x - $piece_x) * abs($sample_board_y1 - $sample_board_y2) / abs($sample_board_x1 - $sample_board_x2);
	echo "$board_x $board_y <br/>";
	//然后就跳咯，勾股定理
	jump(sqrt(($board_x - $piece_x) *($board_x - $piece_x) + ($board_y - $piece_y) *($board_y - $piece_y)));
}
for ($i=0; $i < 10; $i++) { 
	sleep(1);
	mmain();
}
?>
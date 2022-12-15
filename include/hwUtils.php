<?php

function cpuTemp() {
	$zones = [0,3]; // 0=RPi, 3=Dell 3040
	foreach($zones as $z) {
		$file = "/sys/class/thermal/thermal_zone$z/temp";
		if(file_exists($file)) {
			$ct = (int)file_get_contents($file);
			if($ct)
				break;
		}
	}
	$ct /= 1000.0;
	$ft = round($ct * 1.8 + 32);
	$ct = round($ct);
	if($ft < 120)
		$style = "background-color:darkgreen;";
	elseif($ft < 150)
		$style = "background-color:#660;";
	else
		$style = "font-weight:bold;color:yellow;background-color:red;";
	return "CPU Temp: <span style=\"$style\">&nbsp;$ft&deg;F / $ct&deg;C&nbsp;</span> @ " . date('H:i');
}

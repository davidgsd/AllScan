<?php

// CPU Temps up to 150�F are normal by most standards. Supermon however treats > 120�F as 'yellow', which is not at
// all hot by modern electronics standards, which are rated to at least 70�C (158�F) and often to 85�C or more.
// Therefore, AllScan uses 130�F and 150�F as the cutoffs between green/yellow/red (vs. 120/150 in supermon).
// This should help prevent unnecessary worrying about node temps < 130�F.
// To read temp on Pi2B: /opt/vc/bin/vcgencmd measure_temp - example result: temp=56.9'C
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
	if(!isset($ct) || !$ct) {
		$file = '/opt/vc/bin/vcgencmd';
		if(file_exists($file)) {
			$s = `$file measure_temp 2>&1`;
			if($s && preg_match('/[0-9\.]{2,6}/', $s, $m) == 1)
				$ct = 1000 * $m[0];
		}
	}
	if(!isset($ct) || !$ct)
		return '';
	$ct /= 1000.0;
	$ft = round($ct * 1.8 + 32);
	$ct = round($ct);
	if($ft < 130)
		$style = "background-color:darkgreen;";
	elseif($ft < 150)
		$style = "background-color:#660;";
	else
		$style = "font-weight:bold;color:yellow;background-color:red;";
	return "CPU Temp: <span style=\"$style\">&nbsp;$ft&deg;F / $ct&deg;C&nbsp;</span> @ " . date('H:i');
}

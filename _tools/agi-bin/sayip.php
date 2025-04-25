#!/usr/bin/php
<?php
$node = $argv[1] ?? null;
if(!$node || !is_executable('/usr/bin/asl-tts'))
	exit(1);

// Say LAN IP and mDNS URL where AllScan can be accessed
$hn = exec('hostname');	
$lanip = exec("hostname -I | cut -f1 -d' '");
if(!filter_var($lanip, FILTER_VALIDATE_IP)) {
	$lanip = exec("ifconfig | grep inet | head -1 | awk '{print $2}'");
	if($lanip === '127.0.0.1')
		$lanip = exec("ifconfig | grep inet | tail -1 | awk '{print $2}'");
}
if(!$hn && !$lanip)
	$msg = 'The local IPV4 address and hostname of this node could not be determined. Try again later.';
else {
	if($lanip) {
		// convert lanip to format 1 2 3 dot 3 4 dot 5 4 dot 1 1 6
		$o1 = '';
		foreach(str_split($lanip) as $c) {
			$o1 .= ($c == '.') ? 'dot' : "$c ";
		}
		$s1 = "IP Address $o1";
	}
	if($hn) {
		// convert hostname to eg. node 5 6 7 8 9 0
		$o2 = '';
		foreach(str_split($hn) as $c) {
			$o2 .= (is_numeric($c)) ? " $c " : $c;
		}
		$s2 = "mDNS URL $o2 dot local";
	}
	$msg = "This node can be accessed at ";
	if(isset($s1) && isset($s2))
		$msg .= "$s1, or $s2";
	elseif(isset($s1))
		$msg .= $s1;
	else
		$msg .= $s2;
}
$ret = exec("asl-tts -n $node -t '$msg'");
exit($ret);

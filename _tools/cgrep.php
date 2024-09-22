#!/usr/bin/php
<?php
// 'gc' Recursive grep utility that looks only in specific file types and can shorten path string in results
// Look in each dir and grep for specified text in any html and php files
// if '-r' option present also look in all subdirs recursively.
/* Useful aliases to add to .bashrc:
alias www='cd /var/www/html/'
alias ast='cd /etc/asterisk/'
alias d='ls -lart'
alias gci='php -q ~/cgrep.php'
alias gc='php -q ~/cgrep.php -r'
alias gv='grep -v'
*/
if(isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_HOST']))
{
	echo "This script must be run via CLI.<br>\n";
	exit(0);
}
$exts = ['c', 'h', 'php', 'txt', 'md', 'sh', 'html', 'conf'];
$recursive = false;
// Drop first element off of argv and argc (command text)
$argc--;
array_shift($argv);
// Check for -r option
if(isset($argv[0]) && $argv[0] === '-r') {
	$recursive = true;
	$argc--;
	array_shift($argv);
}
// Get search words
$n = count($argv);
if($n < 1) {
	echo "No search words specified.\n";
	exit(0);
}
$searchwords = $argv;
// Change to pwd (script starts in dir where it is located, not where called from)
$pwd = getcwd();
chdir($pwd);
//echo "pwd: $pwd\n";
$subdirs = [];
// Combine words into search pattern
function escapeVal(&$val, $key) { $val = preg_quote($val, '/'); }
array_walk($searchwords, 'escapeVal');
$pattern = '('.implode('|', $searchwords).')';
$level=0;
searchDir('.', $subdirs, $pattern, $recursive);
exit(0);

function searchDir($dir, $subdirs, $pattern, $recursive) {
	global $exts, $pwd, $level;
	$level++;
	if($handle = opendir($dir)) {
		while(($file = readdir($handle)) !== false) {
			// If is a directory, dir not excluded, and recursion enabled, call this function recursively
			if(is_dir($file)) {
				if($recursive && !in_array($file, ['.', '..']) && filetype($file) !== 'link' &&
					(empty($subdirs) || in_array($file, $subdirs))) {
					chdir($file);
					// Do not recurse more than 3 levels
					searchDir('.', null, $pattern, ($level < 3));
					chdir("..");
				}
			} else {
				// Check file extension
				$path_parts = pathinfo($file);
				if(isset($path_parts['extension']) && in_array($path_parts['extension'], $exts)) {
					// Search file
					$contents = file($file);
					$results = preg_grep("/$pattern/", $contents);
					if(!empty($results)) {
						// Output results
						//var_dump($results);
						$path = getcwd() . "/" . $file;
						if(strpos($path, $pwd) === 0) {
							$path = '.' . substr($path, strlen($pwd));
						} elseif(preg_match("|/var/www/html/(.*)|", $path, $matches) == 1 ||
								 preg_match("|/home/allscan/(.*)|", $path, $matches) == 1) {
							$path = $matches[1];
						}
						$lines = array_keys($results);
						foreach($lines as $line)
							printf("%s:%s: %s\n", $path, $line, trim($results[$line]));
					}
				}
			}
		}
	}
	closedir($handle);
	$level--;
}

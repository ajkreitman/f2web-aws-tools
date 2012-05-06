#!/usr/bin/php
<?php

if (count($argv) > 1) {
    if ($argv[1] == 'all') {
        $all = true;
    } else {
        $all = false;
        $i = (int)$argv[1];
    }
    if (isset($argv[2])) {
        $c = $argv[2];
    } else {
        $c = '';
    }
} else {
    $all = false;
    $i = null;
}

require_once(dirname(__FILE__) . '/lib/f2.class.php');

$ips = f2web::getClusterIPs();

if ($all or !is_null($i)) {
    if (! $all) {
        $host = $ips[$i];
        passthru("/usr/bin/ssh ec2-user@{$host} {$c}");
        print "You connection to $i has been terminated\n\n";
    } else {
        foreach ($ips as $host) {
            passthru("/usr/bin/ssh ec2-user@{$host} {$c}");
        }
    }
} else {
    print "\n\nActive Cluster Servers\n";
    $cnt = 0;
    foreach ($ips as $i) {
        print $cnt . ":" . $i . "\n";
        $cnt++;
    }
    $s = $_SERVER['PHP_SELF'];
    print "\nUse this script with the item # to connect to that server using ssh.\n\n";
}

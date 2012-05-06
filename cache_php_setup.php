#!/usr/bin/php
<?php
    require_once('AWSSDKforPHP/sdk.class.php');

    $ec2 = new AmazonEC2();
    $response = $ec2->describe_instances();

    $values = array();
    foreach ($response->body->reservationSet->item as $item) {
        if (isset($item->instancesSet->item->tagSet) and $item->instancesSet->item->instanceState->name == 'running') {
            $ip = (string)$item->instancesSet->item->privateIpAddress;
            $values[] = "tcp://{$ip}:11211?persistent=1&weight=1&timeout=1&retry_interval=15";
        }
    }

    $cache_line = 'session.save_path = "' . implode(",", $values) . '"' . "\n";

    $ini_file = php_ini_loaded_file();
    $file = fopen($ini_file,'r');
    
    $new_lines = array();
    while (!feof($file)) {
        $line = fgets($file);
        if (preg_match('/^session\.save_path/',$line)) {
            $new_lines[] = $cache_line;
        } else {
            $new_lines[] = $line;
        }
    }

    fclose($file);

    unlink($ini_file);
    $file = fopen($ini_file,'c');
    foreach($new_lines as $line) {
        fwrite($file, $line);
    }
    fclose($file);

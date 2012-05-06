<?php

class f2web {

    public static function getClusterIPs() {

        # Get IP #'s of instances used as web-cluster server items
        require_once("AWSSDKforPHP/sdk.class.php");

        $ec2 = new AmazonEC2();

        $response = $ec2->describe_instances();
        $ip = array();
        foreach ($response->body->reservationSet->item as $item) {
            $tags = array();

           if (isset($item->instancesSet->item->tagSet) and $item->instancesSet->item->instanceState->name == 'running') {
               foreach ($item->instancesSet->item->tagSet->item as $t) {
                   $tags["{$t->key}"]= (string)$t->value;
               }


               if (substr($tags['Name'],0,13) != "f2web.control") {
                   $ip[] = (string)$item->instancesSet->item->privateIpAddress;
               }
           }
       }
       return $ip;
    }
}

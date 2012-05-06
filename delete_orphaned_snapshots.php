#!/usr/bin/php
<?php

    # Set default values for verbose and dryrun
    $verbose = false;
    $dryrun = false;
    
    # Process command-line arguments
    $args = array_map(create_function('$value', 'return strtolower($value);'), $argv);

    foreach (array("--help","--h","-h","help") as $a) {
        if (in_array($a, $args)) {
            print helpText(); 
            exit;
        }
    }

    foreach (array("dryrun","--dryrun","debug","--debug") as $a) {
        if (in_array($a, $args)) {
            $dryrun = true;
            $verbose = true;
        }
    }

    if (!$verbose) {
        foreach (array("--verbose","verbose", "-v","--v") as $a) {
            if (in_array($a, $args)) {
                $verbose = true;
            }
        }
    }

    if ($dryrun) {
        print "DRYRUN Only, no snapshots will actually be deleted.\n";
    }

    # Instantiate the API 
    require_once('AWSSDKforPHP/sdk.class.php');
    $ec2 = new AmazonEC2();

    # Get Volumes
    $response = $ec2->describe_volumes();
    $body = $response->body;

    $volumes = array();
    foreach ($body->volumeSet->item as $i) {
        $volume = (string)$i->volumeId;
        $volumes[] = $volume;        
    }

    if ($verbose) {
        # Print active volumes
        print "Active Volumes: " . implode(",", $volumes) . "\n";
    }

    $cnt = 0;
    $response = $ec2->describe_snapshots(array('Owner' => 'self'));
    $body = $response->body;
    foreach ($body->snapshotSet->item as $i) {
        $volume = (string)$i->volumeId;

        if (!in_array($volume, $volumes)) {
            # Keep snapshots that have a description or a tagset- probably kept for a reason.
            $description = (string)$i->description;
            if (empty($description) and !is_object($i->tagSet->item)) {
                if (!$dryrun) {
                    $r = $ec2->delete_snapshot($i->snapshotId);
                    if ($r->isOK()) {
                        $cnt ++;
                        print $i->snapshotId . " deleted\n";
                    } else {
                        print $i->snapshoId . " could not be deleted\n";
                    }
                } else {
                    print "Snapshot {$i->snapshotId} is for volume {$i->volumeId} and would've been deleted\n";
                } 
            } else {
                if ($verbose) {
                    print "Snapshot {$i->snapshotId} has the Description \"{$description}\" , or a tagset, and was therefore bypassed.\n";
                }
            }
        } else {
            if ($verbose) {
                print "Snapshot {$i->snapshotId} is for volume {$volume}, so it won't be deleted\n";
            }
        }
    }

    if ($cnt == 0) {
        if (!$dryrun) {
            # Supress this message on a dryrun
            print "No orphaned snapshots found\n";
        }
    }


    # Put this in a function because of the way the lack of indentation screws up the nice code above.
    function helpText() {
        $self = $_SERVER['PHP_SELF'];

        return "
Usage: $self [parameters]

  Parameters:
   --help       this text
   --dryrun     dryrun only
   --verbose    verbose mode

";
    }

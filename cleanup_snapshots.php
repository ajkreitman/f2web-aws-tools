#!/usr/bin/php
<?php

    # Set this.
    $aws_path = "/opt/aws/bin/";        

    $args = array_map(create_function('$value', 'return strtolower($value);'), $argv);

    $dryrun = false;
    foreach (array("dryrun","--dryrun","debug","--debug") as $a) {
           if (in_array($a, $args)) {
            $dryrun = true;
           }
    }
        
    if ($dryrun) {
           print "DRYRUN Only, no snapshots will actually be deleted.\n";
    }    
        

    # How many snapshots to keep by volume - default is set, and check to see if passed by command line.
    $keep_qty = 2;
    foreach ($args as $a) {
        if (substr($a,0,8) == "keep_qty") {
            list($foo, $val) = explode("=",$a);
            $keep_qty = $val;
            if ($dryrun) {
                print "keep_qty has been set to $val\n";
            }
        }   
    }

    # Customize how many to keep by volume.  You must specify the volume ID and the # to keep.  If you exclude a volume - it uses the default $keep_qty.
    #$keep_by_volume = array("vol-identifier-1" => 2, "vol-identifier-2" => 10, "vol-identifier-3" => 6, "vol-identifier-4" => 10);
    $keep_by_volume = array();

    # Pass in ignore values as command line argument?
    # Ignore these snapshots (may be snapshots you wish to keep or snaps that can't be deleted anyways)
    $ignore = array();
        
    # This script has a depdency on the EC2 tools and environment variables
    # $out stores the result of the command-line script "ec2-describe-snapshots"
    $out = array();
    exec("{$aws_path}ec2-describe-snapshots", $out);

    /*
    The command line output looks like this:
    SNAPSHOT    snap-0Xa4X6Xe   vol-ident-1 completed   2009-10-29T05:00:34+0000    100%
    SNAPSHOT    snap-7X3XdXX8   vol-ident-1 completed   2009-10-30T05:00:34+0000    100%
   */

    # Store each snapshot in it's own array element
    $snaps = array();
    $line = '';
    foreach ($out as $snap) {
        # Notice the output above is separated by tabs.
        # Only include snapshots without a description and without a custom tag.  These are likely intended to be kept.
        $line_before = $line;
        $line = explode("\t", $snap);
        if ($line[0] != 'TAG' and empty($line[8])) {
            $snaps[] = $line;
        } else {
            if ($dryrun) {
                if (!empty($line[8])) {
                    print "snapshot {$line[1]} was skipped because it contains a description: {$line[8]}\n";
                } else {
                    print "snapshot {$line_before[1]} was skipped because it contains tag: {$line[4]}\n";
                }
            }
        }
    }
    
    # convert to a unix timestamp
    $inx = 0;   # counter
    $tags = array();
    foreach ($snaps as $s) {
        $snaps[$inx][4] = strtotime($s[4]);
        $inx++;
    }


    # You can't really sort a PHP array on an element within the array without doing some tricks
    # So here, we're going to turn the array inside out so we can sort on the volume
    # and the timestamp
    foreach ($snaps as $key => $row) {
        $column1[$key] = $row[0];
        $column2[$key] = $row[1];
        $column3[$key] = $row[2];
        $column4[$key] = $row[3];
        $column5[$key] = $row[4];
        $column6[$key] = $row[5];
    }

    # sort it
    array_multisort($column3, SORT_ASC, $column5, SORT_DESC, $snaps);

    # Now store a consolidated array of each volume with it's snapshots
    # This will look like
    $all_snaps = array();
    foreach ($snaps as $s) {
        # Make sure we're not ignoring this snapshot
        if (!in_array($s[1],$ignore)) {
            if (empty($all_snaps[$s[2]])) {
                $all_snaps[$s[2]] = $s[1];
            } else {
                $all_snaps[$s[2]] .= "," . $s[1];
            }
        }
    }

    /*
     # At this point, we should have an array that contains looks like this
     $all_snaps['volume-id-1'] = 'snapshot-id-1,snapshot-id-2'
     $all_snaps['volume-id-2'] = 'snapshot-id-1,snapshot-id-2'
    */

    if ($dryrun) {
        print "Snapshots Found (by volume):\n";
        print_r($all_snaps);
    }

    # Since these are sorted from newest to oldest, we can go through these rows
    # and delete all of the entries past the $keep_qty count
    foreach ($all_snaps as $volume => $vol_snaps) {
        $snap_arr = split(",",$vol_snaps);

        $count = 1;
        # is this volume in the special keep_by_volume array we declared up top?
        if (array_key_exists($volume, $keep_by_volume)) {
            $keep = $keep_by_volume[$volume];
        } else {
            $keep = $keep_qty;
        }

        # Show how many snapshots we're keeping for this particular volume
        print "Volume $volume; keeping $keep\n";

        # Iterate through snapshots for this volume
        foreach ($snap_arr as $s) {
            if ($count <= $keep) {
                print "Keeping: $volume/ $s \n";
            } else {
                # Delete the snapshot, and print the output from the ec2-delete-snapshot command
                if (! $dryrun) {
                    print "Deleting: $volume/ $s\n";
                    $out = array();
                    $cmd = "{$aws_path}ec2-delete-snapshot {$s}";
                    exec($cmd, $out);
                    print "Output from command $cmd: \n";
                    foreach ($out as $o) {
                            print $o . "\n";
                    }
                } else {
                    print "Would've deleted: $volume/ $s\n";
                }
            }
            $count++;
        }
        print "\n";
    }

   # Close your PHP here
?>

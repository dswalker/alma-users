<?php

chdir(__DIR__);
include("vendor/autoload.php");

use Alma\Users;
use Alma\Users\UserIdentifier;
use Users\Config;

// command line params

$campus = ""; if (array_key_exists(1, $argv)) $campus = $argv[1];
$action = ""; if (array_key_exists(2, $argv)) $action = $argv[2];

// specify campus(es)

$campuses = array();

if ($campus == "") {
    die('No campus specified');
}

if ($campus == 'all') {
    $list = glob('campuses/*');
    foreach ($list as $entry) {
        if (is_dir($entry)) {
            $entry  = str_replace('campuses/', '', $entry);
            $campuses[] = $entry;
        }
    }
} else {
    $campuses = array($campus);
}

// process each campus

foreach ($campuses as $campus) {
    
    $config = new Config("campuses/$campus/config.ini");
    
    $host = $config->get('host', true); 
    $api_key = $config->get('api_key', true);
    $id_type = $config->get('id_type', true);
    
    $users = new Users($host, $api_key);
    
    try {
    
        // get any csv files
        $files = glob("campuses/$campus/incoming/*.*"); 
        
        foreach ($files as $file) {
            
            if (($handle = fopen($file, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    
                    $date = date('Y-m-d', time()); // today's date
                    
                    // extract user id and barcode from line
                    
                    $user_id = preg_replace('/\D/', '', $data[0]);
                    $barcode = preg_replace('/\D/', '', $data[1]);
                    
                    // get user
                    $user = $users->getUser($user_id);
                    
                    // see if user has a barcode
                    
                    $barcode_in_record = "";
                    $ids = $user->getUserIdentifiers();
                    
                    foreach ($ids as $id) {
                        if ($id->getIdType()->value == $id_type) {
                            $barcode_in_record = $id->getValue();
                        }
                    }
                    
                    $log = "\n$date\t$user_id\t$barcode\t$barcode_in_record";
                    
                    // user has no barcode
                    if ($barcode_in_record == "") {
                        
                        // keep original json
                        $json = (string) $user->json();
                        
                        // add barcode from file
                        
                        $user_identifier = new UserIdentifier();
                        $user_identifier->setIdType($id_type);
                        $user_identifier->setValue($barcode);
                        $user_identifier->setNote("Added by CO $date");
                        
                        // add to current identifiers
                        
                        $ids[] = $user_identifier;
                        $user->setUserIdentifiers($ids);
                        
                        // update data in Alma
                        $user->save();
                        
                        // write out original json
                        file_put_contents("campuses/$campus/changed/$user_id-$date.json", $json);
                        $log .= "ADDED";
                    }
                    // write to log
                    file_put_contents("logs/$campus/" . date('Y-m', time()) . ".log", $log, FILE_APPEND);
                }
                
                // close original file
                fclose($handle);
                
                // construct path to archive
                
                $parts = explode('/', $file);
                $csv_file = array_pop($parts);
                $archive = implode('/', $parts) . "/archive/$date-" . $csv_file;
                
                // move file to archive
                rename($file, $archive);
            }
        }
    } catch (Exception $e) {
        $error = "\n[$date]\n$campus\n" . $e->getTraceAsString() . "\n\n";
        file_put_contents("logs/error-" . date('Y-m', time()) . ".log", $error, FILE_APPEND);
    }
}






/*
$start = 0;
$chunk = 100;

do {
    $list = $users->getUsers("", $chunk, $start);
    $start += 100;
    
    foreach ($list->getResults() as $user) {
        $user->getFullRecord();
        
        echo $user->getLastName() . ", " . $user->getFirstName() . "\n";
        
        foreach ($user->getUserIdentifiers() as $id) {
            if ($id->getIdType()->value == 'BARCODE') {
                echo "\t" . $id->getValue() . "\n";
            }
        }
    }
    exit;
} while ($list->end < $list->total);
*/
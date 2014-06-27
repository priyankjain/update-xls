<?php

$filename = $_GET['file']; // of course find the exact filename....        
header('Expires: 0');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Cache-Control: private', false); // required for certain browsers 
header('Content-Type: application/csv');

header('Content-Disposition: attachment; filename="'. basename($filename) . '";');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize(getcwd().'/output/'.$filename));

readfile(getcwd().'/output/'.$filename);

exit;
?>
<?php

$host = 'localhost';
$user = 'root';
$pass = '';
$database = 'xls';

$db = mysql_connect($host, $user, $pass);
mysql_query("use $database", $db);

/********************************************************************************/
// Parameters: filename.csv table_name

// $argv = $_SERVER[argv];

// if($argv[1]) { $file = $argv[1]; }
// else {
// 	echo "Please provide a file name\n"; exit; 
// }
// if($argv[2]) { $table = $argv[2]; }
// else {
// 	$table = pathinfo($file);
// 	$table = $table['filename'];
// }
$file = getcwd()."/categories_description.csv";
$table = "categories";

/********************************************************************************/
// Get the first row to create the column headings

$fp = fopen($file, 'r');
$frow = fgetcsv($fp);
$columns="";
$ccount = 0;
foreach($frow as $column) {
	$ccount++;
	if($columns) $columns .= ', ';
	$columns .= "`$column` varchar(250)";
	if($ccount == 1) $columns .= " primary key ";
}
mysql_query("drop table if exists $table ");
$create = "create table if not exists $table ($columns);";
echo $create;
mysql_query($create, $db);

/********************************************************************************/
// Import the data into the newly created table.

$q = "load data infile '$file' into table $table fields terminated by ',' ignore 1 lines";
mysql_query($q, $db) or mysql_error();
?>
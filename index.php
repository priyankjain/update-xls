<?php
ini_set('auto_detect_line_endings', true);
if ( isset($_POST["submit"]) ) {
//Delete existing files in upload folder
$path=getcwd().'/upload/';
if ($handle = opendir($path)) {
    while (false !== ($entry = readdir($handle))) {
    	if(strpos($entry, ".csv") !==false || strpos($entry,".txt")!==false)
	unlink($path.$entry);
    }
    closedir($handle);
}
if(isset($_POST['exchange']) && is_numeric($_POST['exchange']))
$exchange = $_POST['exchange'];
else {
	echo 'Please enter exchange rate of USD to CAD';
	exit;
}
$shipping = (isset($_POST['shipping']) && is_numeric($_POST['shipping']))?$_POST['shipping']:2;
$multiplier = (isset($_POST['multiplier']) && is_numeric($_POST['multiplier']))?$_POST['multiplier']:1.2;
//Upload the files to upload folder
$files = array("dealer.txt"=>"dealer_file","final.csv"=>"final_file","categories_description.csv"=>"categories_file","manufacturers.csv"=>"manu_file","categories.csv"=>"cat_file","manufacturers_info.csv"=>"manu_info_file");
foreach($files as $key=>$value){
   if ( isset($_FILES[$value])) {
            //if there was an error uploading the file
        if ($_FILES[$value]["error"] > 0) {
            echo "Return Code: " . $_FILES[$value]["error"] . "<br />";
        }
        else {
                 //if file already exists
             if (file_exists("upload/" . $_FILES[$value]["name"])) {
            echo $_FILES[$value]["name"] . " already exists. ";
             }
             else {
                    //Store file in directory "upload" with the name of "uploaded_file.txt"
            move_uploaded_file($_FILES[$value]["tmp_name"], "upload/" . $key);
            // echo "Stored in: " . "upload/" . $_FILES[$value]["name"] . "<br />";
            }
        }
     } else {
             echo "No file selected for ".$key." <br />";
     	}
 	}

//Import the csv files into the database
 	require_once("config.php");
	$mysqli = new mysqli($config['host'],$config['user'],$config['pwd'],$config['db']);
	if($mysqli->connect_errno > 0){
		echo 'Error connecting to database';
		exit;
	}
	$files = array("final.csv"=>"final","categories_description.csv"=>'categories_description',"manufacturers.csv"=>'manufacturers',"manufacturers_info.csv"=>"manufacturers_info","categories.csv"=>"categories");
	foreach($files as $key=>$value){
		$file = $path.$key;
		$fp = fopen($file, 'r');
		$frow = fgetcsv($fp);
		$frow = explode(";",$frow[0]);
		$columns="";
		$ccount = 0;
		foreach($frow as $column) {
			$ccount++;
			$column = str_replace("`", "", $column);
			if($ccount == 1) { $columns .= " `$column` int(11) primary key auto_increment"; continue; }
			if($columns) $columns .= ', ';

			$columns .= "`$column` varchar(250)";
			
		}
		$mysqli->query("drop table if exists $value") or die("Error dropping table $value");
		$create = "create table if not exists $value ($columns);";
		$mysqli->query($create) or die("Error creating table $value, query: $create");
		$q = "load data infile '$file' into table $value fields terminated by ';' enclosed by '`' ignore 1 lines";
		$mysqli->query($q) or die("Error inserting rows into table $value");
	}

//DO the processing
		//Delete existing files in output folder
	$path=getcwd().'/output/';
	if ($handle = opendir($path)) {
	    while (false !== ($entry = readdir($handle))) {
	    	if(strpos($entry, ".csv") !==false || strpos($entry,".txt")!==false)
		unlink($path.$entry);
	    }
	    closedir($handle);
	}
	$txt_file=fopen(getcwd()."/upload/dealer.txt","r");
	$line = fgets($txt_file);
	$new_products=fopen(getcwd()."/output/new_products.csv","w");
	$tokens= explode("\t",$line);
	fwrite($new_products,'`'.implode("`;`",$tokens).'`'.PHP_EOL);
	$new_categories=fopen(getcwd()."/output/new_categories.txt","w");
	$new_manufacturers=fopen(getcwd()."/output/new_manufacturers.txt","w");
	$line_no = 1;
	while(!feof($txt_file))
	{

		$line=fgets($txt_file);
		$tokens= explode("\t",$line);
		$col=36;
		$sku = $tokens[0];
		if($tokens[1]=="") break;
		$productname = $tokens[1];

		$description = $tokens[3];
		$url = $tokens[6];
		$url = explode("/",$url);
		$mainimageurl = "pyr/".end($url);
		$shippingweight = $tokens[7];
		$caliber = $tokens[8];
		$velocity = $tokens[9];
		$instockquantity = $tokens[15];
		$wnet = $tokens[17];
		$url = $tokens[18];
		$url = explode("/", $url);
		$smallimageurl = "pyr/".end($url);
		$category_name = $tokens[19];
		$manu_name = $tokens[2];
		$description=str_replace("\"", "", $description);
		$mainimageurl=str_replace("\"", "", $mainimageurl);
		$smallimageurl=str_replace("\"", "", $smallimageurl);
		$productname=str_replace("\"", "", $productname);
		$category_name=str_replace("\"", "", $category_name);
		$manu_name=str_replace("\"", "", $manu_name);
		$description=addslashes($description);
		$mainimageurl = addslashes($mainimageurl);
		$smallimageurl = addslashes($smallimageurl);
		$productname = addslashes($productname);
		$category_name = addslashes($category_name);
		$manu_name=addslashes($manu_name);
		$category_id="";
		$manu_id = "";
		$result = $mysqli->query("select categories_id from `categories_description` where `categories_name` = '".$category_name."'") or die("Error getting category id");
		//Create a new row in categories_description if needed
		if($result->num_rows == 0){
			echo $category_name;
			$mysqli->query("insert into `categories_description`(`language_id`,`categories_name`,`categories_heading_title`,`categories_description`,`categories_head_title_tag`,`categories_head_desc_tag`,`categories_head_keywords_tag`,`categories_htc_title_tag`,`categories_htc_desc_tag`,`categories_htc_keywords_tag`,`categories_htc_description`) values('1','".$category_name."','".$category_name."','','','','','','','','');") or die("Error inserting new category"); 
			$category_id = $mysqli->insert_id;
			$result = $mysqli->query("select * from `categories` where `categories_id`=".$category_id);
			if($result->num_rows == 0){
				$mysqli->query("insert into `categories` values(".$category_id.",'','9','0','".date('Y-m-d H:i:s')."','".date('Y-m-d H:i:s')."')");
			}
			fwrite($new_categories,$category_id.PHP_EOL);
		}
		else {
			$row = $result->fetch_assoc();
			$category_id=$row['categories_id'];
		}
		$result = $mysqli->query("select manufacturers_id from `manufacturers` where `manufacturers_name` = '".$manu_name."'") or die("Error getting manufacturer id");
		//Create a new row in manufacturers if needed
		if($result->num_rows == 0){
			$mysqli->query("insert into `manufacturers`(`manufacturers_name`,`manufacturers_image`,`date_added`,`last_modified`) values('".$manu_name."','','".date('Y-m-d H:i:s')."','".date('Y-m-d H:i:s')."')") or die("Error inserting new manufacturer"); 
			$manu_id = $mysqli->insert_id;
			$result = $mysqli->query("select * from `manufacturers_info` where `manufacturers_id`=".$manu_id);
			if($result->num_rows == 0){
				$mysqli->query("insert into `manufacturers_info` values(".$manu_id.",'1','','0','".date('Y-m-d H:i:s')."','','','')");
			}
			fwrite($new_manufacturers,$manu_id.PHP_EOL);
		}
		else {
			$row = $result->fetch_assoc();
			$manu_id=$row['manufacturers_id'];
		}
		$cad = $wnet*$exchange;
		$gst = $cad*0.005;
		$duty = ($cad+$gst)*0.065;
		$cad_cost = $cad + $gst + $duty + $shipping;
		$cad_selling = $cad_cost * $multiplier;
		$cad = sprintf("%.2f", $cad);
		$gst = sprintf("%.2f", $gst);
		$duty = sprintf("%.2f", $duty);
		$cad_cost = sprintf("%.2f", $cad_cost);
		$cad_selling = sprintf("%.2f", $cad_selling);
		$shipping = sprintf("%.2f", $shipping);
		$select = "SELECT * FROM `final` WHERE `Prod_model`='".$sku."'";
		$result = $mysqli->query($select) or die("Could not search rows");
		if($result->num_rows == 0)
		{//If it is a new entry show it in n.txt
			fwrite($new_products,'`'.implode("`;`",$tokens).'`'.PHP_EOL);
			$query = "insert into `final`(`Prod_model`,`Parent_ID`,`Prod_Status`,`Prod_tax`,`Prod_sort`,
				`Prod_lang`, `Product_Name`, `Manufacturer`, `Manuf_number`, `ProductDescription1`, `Images`, `Images_MED`, `Images_LRG`,
				`Weight`, `Caliber`, `Velocity`, `InStockQuantity`, `WNet`, `CAD`, `GST`, `Duty 6.5%`, `Shipping`, `Cad_COST`, `CAD_Selling`,`SmallImageURL`,
				`Prod_Category`, `PRODUCT_Cat_number`) values(
				'".$sku."','0','1','1','0','1','".$productname."','".$manu_name."','".$manu_id."',
				'".$description."','".$mainimageurl."','".$mainimageurl."','".$mainimageurl."','".$shippingweight."','','','".$instockquantity."','".$wnet."','".$cad."','".$gst."','".$duty."','".$shipping."','".$cad_cost."','".$cad_selling."',
				'".$smallimageurl."','".$category_name."','".$category_id."')";
			$mysqli->query($query)  or die("Could not insert row into final table");	
		}
		else 
		{
			$query = "update `final` set `Product_Name` = '".$productname."', `Manufacturer`='".$manu_name."', `Manuf_number`='".$manu_id."', `ProductDescription1`='".$description."',
			`Images` = '".$mainimageurl."', `Images_MED` = '".$mainimageurl."', `Images_LRG` = '".$mainimageurl."', `Weight`='".$shippingweight."', `Caliber`='".$caliber."', `Velocity`='".$velocity."', 
			`InStockQuantity` = '".$instockquantity."', `WNet` = '".$wnet."', `CAD` = '".$cad."', `GST` = '".$gst."', `Duty 6.5%` = '".$duty."', `Shipping` = '".$shipping."', `CAD_COST` = '".$cad_cost."', `CAD_Selling` = '".$cad_selling."', `SmallImageURL` = '".$smallimageurl."', `Prod_Category`
			= '".$category_name."', `PRODUCT_Cat_number` = '".$category_id."' where `Prod_model` = '".$sku."'";
			// echo $query;
			$mysqli->query($query)  or die("Could not update rows of final table");	
		}
	}
	//Delete items with zero cost;
	// $result=$mysqli->query("select * from `final` where WNet like '0.00';");
	// echo $result->num_rows;
	// var_dump($result);
	$mysqli->query("DELETE FROM `final` WHERE `WNet` like '0.00';") or die("Error deleting ");
	fclose($new_products);
	fclose($new_manufacturers);
	fclose($new_categories);
	fclose($txt_file);

	//Export and allow user to download
	foreach($files as $key=>$value){
		$file = fopen(getcwd().'/output/'.$key,"w");
		$result = $mysqli->query("select * from ".$value) or die("Error fetching rows of table ".$value);
		$count =0;
		while($row = $result->fetch_assoc()){
			if($count == 0){
					$arraykeys = array_keys($row);
					$line = '`'.implode("`;`",$arraykeys).'`';
					fwrite($file,$line.PHP_EOL);
				}
				$arrayvals = array_values($row);
				$line = '`'.implode("`;`",$arrayvals).'`';
				fwrite($file,$line.PHP_EOL);
				$count++;
			}
			fclose($file);
			echo 'Output '.$key." <a target='_blank' href='download.php?file=".$key."'>".$key."</a><br/>";
		}
// 		$mysqli->query("SELECT CONCAT(GROUP_CONCAT(COLUMN_NAME SEPARATOR ';'), '\n')
// FROM INFORMATION_SCHEMA.COLUMNS
// WHERE TABLE_SCHEMA = '".$config['db']."' AND TABLE_NAME = '".$value."' GROUP BY TABLE_NAME UNION select * into outfile '".getcwd()."/output/".$key."' FIELDS TERMINATED BY ';' ENCLOSED BY '\"' FROM ".$value) or die($mysqli->error);	
	// }
	$mysqli->close();
}

else
{
?>

<table width="600">
<form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="post" enctype="multipart/form-data">

<tr>
<td width="40%">1 USD is how much in CAD?</td>
<td width="60%"><input type="text" name="exchange" id="exchange" /></td>
</tr>

<tr>
<td width="40%">Shipping cost is how much in CAD? (default is 2)</td>
<td width="60%"><input type="text" name="shipping" id="shipping" /></td>
</tr>

<tr>
<td width="40%">CAD_SELLING = ? x CAD_COST (default is 1.2)</td>
<td width="60%"><input type="text" name="multiplier" id="multiplier" /></td>
</tr>

<tr>
<td width="40%">Select dealer.txt file</td>
<td width="60%"><input type="file" name="dealer_file" id="dealer_file" /></td>
</tr>

<tr>
<td width="40%">Select final.csv file </td>
<td width="60%"><input type="file" name="final_file" id="final_file" /></td>
</tr>

<tr>
<td width="40%">Select categories_description.csv file</td>
<td width="60%"><input type="file" name="categories_file" id="categories_file" /></td>
</tr>

<tr>
<td width="40%">Select categories.csv file</td>
<td width="60%"><input type="file" name="cat_file" id="categories_file" /></td>
</tr>

<tr>
<td width="40%">Select manufacturers.csv file</td>
<td width="60%"><input type="file" name="manu_file" id="manu_file" /></td>
</tr>

<tr>
<td width="40%">Select manufacturers_info.csv file</td>
<td width="60%"><input type="file" name="manu_info_file" id="categories_file" /></td>
</tr>
<tr>
<td>Submit</td>
<td><input type="submit" name="submit" /></td>
</tr>

</form>
</table>

<?php
}
?>
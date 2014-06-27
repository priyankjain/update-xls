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

//Upload the files to upload folder
$files = array("dealer.txt"=>"dealer_file","final.csv"=>"final_file","categories_description.csv"=>"categories_file","manufacturers.csv"=>"manu_file");
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
            echo "Stored in: " . "upload/" . $_FILES[$value]["name"] . "<br />";
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
	$files = array("final.csv"=>"final","categories_description.csv"=>'categories_description',"manufacturers.csv"=>'manufacturers');
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
	$new_products=fopen(getcwd()."/output/new_products.txt","w");
	$new_categories=fopen(getcwd()."/output/new_categories.txt","w");
	$new_manufacturers=fopen(getcwd()."/output/new_manufacturers.txt","w");
	$line_no = 1;
	while(!feof($txt_file))
	{

		$line=fgets($txt_file);
		$tokens= explode("\t",$line);
		$col=36;
		$sku = $tokens[0];
		$productname = $tokens[1];
		$description = $tokens[3];
		$url = $tokens[6];
		$url = explode("/",$url);
		$mainimageurl = "pyr/".end($url);
		$shippingweight = $tokens[7];
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
			fwrite($new_manufacturers,$manu_id.PHP_EOL);
		}
		else {
			$row = $result->fetch_assoc();
			$manu_id=$row['manufacturers_id'];
		}
		$select = "SELECT * FROM `final` WHERE `products_model`='".$sku."'";
		$result = $mysqli->query($select) or die("Could not search rows");
		if($result->num_rows == 0)
		{//If it is a new entry show it in n.txt
			fwrite($new_products,$line);
			$query = "insert into `final`(`products_model`,`Prod_ID`,`Prod_model`,`Parent_ID`,`Prod_Status`,`Prod_tax`,`Prod_sort`,
				`Prod_lang`, `Product_Name`, `Manufacturer`, `Manuf_number`, `Manu_ID lookup`, `ProductDescription1`, `Images`, `Images_MED`, `Images_LRG`,
				`ShippingWeight`, `Weight`, `Caliber`, `Velocity`, `InStockQuantity`, `QBItem`, `WNet`, `CAD`, `GST`, `Duty 6.5%`, `Shipping`, `Cad_COST`, `CAD_Selling`,`SmallImageURL`,
				`Prod_Category`, `PRODUCT_CAY_NUM`, `CATEGORY`, `CATNUM`, `AI`, `AJ`, `SKU`, `Category1`) values(
				'".$sku."','0','1','1','0','1','','','".$productname."','".$manu_name."','".$manu_id."','',
				'".$description."','".$mainimageurl."','".$mainimageurl."','".$mainimageurl."','".$shippingweight."','','','','".$instockquantity."','','".$wnet."','','','','','','',
				'".$smallimageurl."','".$category_name."','".$category_id."','".$category_name."','".$category_id."','','','','')";
			$mysqli->query($query)  or die("Could not insert row into final table");	
		}
		else 
		{
			$query = "update `final` set `Product_Name` = '".$productname."', `Manufacturer`='".$manu_name."', `Manuf_number`='".$manu_id."', `ProductDescription1`='".$description."',
			`Images` = '".$mainimageurl."', `Images_MED` = '".$mainimageurl."', `Images_LRG` = '".$mainimageurl."', `ShippingWeight`='".$shippingweight."', 
			`InStockQuantity` = '".$instockquantity."', `WNet` = '".$wnet."', `SmallImageURL` = '".$smallimageurl."', `Prod_Category`
			= '".$category_name."', `PRODUCT_CAY_NUM` = '".$category_id."', `CATEGORY` = '".$category_name."', `CATNUM` = '".$category_id."' where `products_model` = '".$sku."'";;
			$mysqli->query($query)  or die("Could not update rows of final table");	
		}
	}
	//Delete items with zero cost;
	$mysqli->query("DELETE FROM `final` WHERE `WNet` = '0';") or die("Error deleting ");
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
			echo 'Lines: '.$count.' Output '.$key." <a target='_blank' href='download.php?file=".$key."'>".$key."</a><br/>";
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
<td width="20%">Select dealer.txt file</td>
<td width="80%"><input type="file" name="dealer_file" id="dealer_file" /></td>
</tr>

<tr>
<td width="20%">Select final.csv file </td>
<td width="80%"><input type="file" name="final_file" id="final_file" /></td>
</tr>

<tr>
<td width="20%">Select categories_description.csv file</td>
<td width="80%"><input type="file" name="categories_file" id="categories_file" /></td>
</tr>

<tr>
<td width="20%">Select manufacturers.csv file</td>
<td width="80%"><input type="file" name="manu_file" id="manu_file" /></td>
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
<?php
/*	LG NSU emulator, by SMX */
define("EXIT_SUCCESS", 0);
define("EXIT_FAILURE", 1);	
	class Logger {
		private $logfile;
		public function initLog(){
			date_default_timezone_set(date_default_timezone_get());
			if(!is_dir("logs")) mkdir("logs");
			chmod("logs", 0777);
			$this->logfile = "logs/log_".date("d-m-y");
			$logprefix="_";
			$logpostfix=".log";
			$logno=0;
			while(file_exists($this->logfile.$logprefix.$logno.$logpostfix)){
				$logno++;
			}
			$this->logfile.=$logprefix.$logno.$logpostfix;
			touch($this->logfile);
			chmod($this->logfile, 0666);
		}
		
		public function __construct(){
			$this->initLog();
		}
	
		public function log($str){
			$str = date("d-m-y H:i:s")."\t".$str;
			if($str[strlen($str)-1] != PHP_EOL)
				$str.=PHP_EOL;
			file_put_contents($this->logfile, $str, FILE_APPEND);
		}
	}
	
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	
	$logger = new Logger();
	$logger->log("Got NSU Request from ".$ip);
	$logger->log("Parsing Request...");
	$indata=file_get_contents('php://input');
	$indata=base64_decode($indata);
	if(strpos($indata, "REQUEST") == false){
		$logger->log("ERROR: Invalid Request");
		return_message("Invalid Request");
	}
	
	$usecustom = 1;
	$sourcefile = null;
	
	$myrequest = simplexml_load_string($indata);
	if($myrequest == null){
		$logger->log("ERROR: XML Data is INVALID or MISSING");
		return_error(701,"XML Data is INVALID or MISSING");
	}
	$product_nm = $myrequest->PRODUCT_NM;
	$model_nm	= $myrequest->MODEL_NM;
	$sw_type	= $myrequest->SW_TYPE;
	$major_ver	= $myrequest->MAJOR_VER;
	$minor_ver	= $myrequest->MINOR_VER;
	$country	= $myrequest->COUNTRY;
	$device_id	= $myrequest->DEVICE_ID;
	$auth_flag	= $myrequest->AUTH_FLAG;
	$ign_disable= $myrequest->IGNORE_DISABLE;
	$in_eco_info= $myrequest->ECO_INFO;
	$config_key = $myrequest->CONFIG_KEY;
	$langcode	= $myrequest->LANGUAGE_CODE;
	
	if ($product_nm == null || $model_nm == null || $sw_type == null || $major_ver == null || $minor_ver == null
		|| $country == null || $device_id == null || $auth_flag == null || $ign_disable == null || $in_eco_info == null
		|| $config_key == null || $langcode == null){
		$logger->log("ERROR: Request doesn't contain the required information");
		return_error(700,"XML Data is NULL");
	}
	
	$logger->log("#### Request Details ####");
	$logger->log("#  Product Name: ".$product_nm);
	$logger->log("#  Model Name ".$model_nm);
	$logger->log("#  Software Type ".$sw_type);
	$logger->log("#  Version Major ".$major_ver);
	$logger->log("#  Version Minor ".$minor_ver);
	$logger->log("#  Country ".$country);
	$logger->log("#  Device ID ".$device_id);
	$logger->log("#  Auth Flag ".$auth_flag);
	$logger->log("#  Ignore Disable ".$ign_disable);
	$logger->log("#  Eco Info ".$in_eco_info);
	$logger->log("#  Config Key ".$config_key);
	$logger->log("#  Language Code ".$langcode);
	$logger->log("#### END ####");
	
	$reqprefix="-";
	$reqpostfix=".req";
	$reqno=0;
	if(!is_dir("requests")) mkdir("requests");
	chmod("requests", 0777);
	$filename = "requests/".$model_nm.$reqprefix.$reqno.$reqpostfix;
	while(file_exists($filename)){
		$data = file_get_contents($filename);
		if(!strcmp($data, $indata))
			goto reqsaved; //we have the same request on disk
		else
			$filename = "requests/".$model_nm.$reqprefix.++$reqno.$reqpostfix;
	}
	$fn = "requests/".$model_nm.$reqprefix.$reqno.$reqpostfix;
	$logger->log("Dumping Request to ".$fn." ...");
	file_put_contents($fn, $indata);
	chmod($fn, 0666);
	
	reqsaved:
	$usesourcefile = false; //assume we have to build the response
	$foundconfig=false; //assume we have no config
	if(file_exists("server.cfg")){		//found config file
		$fwnames=fopen("server.cfg","r");
		while(!feof($fwnames)){
			$fwname=fgets($fwnames);
			$fwname=str_replace(str_split("\t\n\r "),"",$fwname);
			$fwname=explode("=",$fwname);
			if(empty($fwname[0]) || $fwname[0][0] == '#' || count($fwname) != 2) continue;
			if($fwname[0] == "@ALL_OVERRIDE" || $fwname[0] == $model_nm || strtoupper($fwname[0] == $model_nm)){ //if we found a matching config
				$foundconfig = true;
				if($fwname[0] == "@ALL_OVERRIDE"){
					$logger->log("Found @ALL_OVERRIDE, providing ".$fwname[1]." for ".$model_nm);
				} else {
					$logger->log("Found Config ".$fwname[1]." for ".$model_nm);
				}
				$sourcefile = $fwname[1];
				if(!file_exists($sourcefile)){
					if(file_exists("models/".$sourcefile)) $sourcefile = "models/".$sourcefile;
					elseif(file_exists("epks/".$sourcefile)) $sourcefile = "epks/".$sourcefile;
				}
				if(!file_exists($sourcefile)){
					$logger->log("File ".$sourcefile." not Found");
					return_message("File ".$sourcefile." not Found");
				}
				$fileinfo = pathinfo($sourcefile);
				switch(strtolower($fileinfo["extension"])){
					case "epk":
						$logger->log("Providing epk file ".$sourcefile);
						$img_name = $fwname[1];
						break;
					case "xml":
						$logger->log("Using response file ".$sourcefile);
						$usesourcefile = true; //we have premade response
						break;
					default:
						$logger->log("ERROR: Unrecognized file extension: ".$fileinfo["extension"]);
						break;
				}
				break;
			}
		}
		if(!$foundconfig){
			$logger->log("WARNING: No config found for ".$model_nm);
		}
	}
	if(!$foundconfig){
		$logger->log("Trying models/".$model_nm.".xml");
		if(file_exists("models/$model_nm.xml")){ //try config file in "models" dir
			$sourcefile="models/$model_nm.xml";
		} else { //no model config file
			$logger->log("ERROR: No config/file found for ".$model_nm);
			return_message("ERROR: No config/file found");
		}
	}
	if($usesourcefile){
		$req = fopen("$sourcefile","r"); //open response file
		$filedata = fread($req,filesize($sourcefile)); //get data 
		if(strpos($filedata,"RESPONSE") != false){
			$tsourcefile=explode(".",$sourcefile)[0].".xml"; //set decoded filename
			fclose($req);
			$req =fopen($tsourcefile,"w+"); //open outfile for writing
			fwrite($req,$filedata); //write decoded data
			$sourcefile=$tsourcefile;
			rewind($req);
		}
		rewind($req);
		$line=fgets($req);
		rewind($req);
		
		if(strpos(strtoupper($line),"RESPONSE") == false){
			$logger->log("\"$sourcefile\" is not a valid RESPONSE file");
			return_message("\"$sourcefile\" is not a valid RESPONSE file");
		}
		
		$myrequest	 = simplexml_load_file("$sourcefile");
		if($myrequest == null){
			$logger->log("Cannot load ".$sourcefile);
			return_error(702,"RESPONSE NOT VALID");
			exit();
		}
		$resultcode	 = $myrequest->RESULT_CD;
		$message	 = $myrequest->MSG;
		$rid		 = $myrequest->REQ_ID;
		$img_name	 = $myrequest->IMAGE_NAME; //MANDATORY!
		$img_max	 = $myrequest->UPDATE_MAJOR_VER;
		$img_min	 = $myrequest->UPDATE_MINOR_VER;
		$img_url	 = $myrequest->IMAGE_URL;
		$img_size	 = $myrequest->IMAGE_SIZE;
		$forceflag	 = $myrequest->FORCE_FLAG;
		$ke			 = $myrequest->KE;
		$date		 = $myrequest->GMT;
		$out_eco_info= $myrequest->ECO_INFO;
		$cdn_url	 = $myrequest->CDN_URL;
		$contents	 = $myrequest->CONTENTS;
	}
	
	
	if(!isset($img_min) || $img_min == ""){ //set img minor
		if(floatval($minor_ver) < 99.99){
			$img_min = number_format((floatval($minor_ver)+0.01),2,".","");
		} else {
			$img_min = 11.11;
		}
	}
	
	if(!isset($img_max) || $img_max == ""){ //set img major
		if($img_min < $minor_ver){
			$img_max = $major_ver + 1;
			if($img_max < 10) $img_max = "0".$img_max;
		} else {
			$img_max = $major_ver;
		}
	}
	if(!isset($img_url) || $img_url == "" || $usecustom){ //set img url
		if($usecustom){
			$img_url="http://snu.lge.com/epks/";
			$cdn_url=$img_url.$img_name;
		} else {
			$img_url="http://snu.lge.com/SWDownload.laf";
		}
	}
	
	if(!isset($message) || $message == "")
		$message = "Success";
	if(!isset($rid) || $rid == "")
		$rid=rand(882670000,882679999);
	if(!isset($data) || $date == "")	
		$gmt=gmdate("d M Y H:i:s", time())." GMT";
	if(!isset($img_name) || $img_name == "" || !file_exists("epks/".$img_name)){
		$logger->log("ERROR: EPK File ".$img_name." Missing!");
		return_error(703,"EPK missing");
	}
	if(!isset($img_size) || $img_size == "")	
		$img_size = filesize("epks/".$img_name);
	if(!isset($resultcode) || $resultcode == "")
		$resultcode=900;	
	if(!isset($forceflag) || $forceflag == "")
		$forceflag="Y";
	if(!isset($ke))
		$ke="";
	if(!isset($out_exo_int) || $out_eco_info == "")
		$out_eco_info="01";
	if(!isset($contents))
		$contents="";
	
	$outdata="<RESPONSE>";
		$outdata.="<RESULT_CD>$resultcode</RESULT_CD>";
		$outdata.="<MSG>$message</MSG>";
		$outdata.="<REQ_ID>$rid</REQ_ID>";
		$outdata.="<IMAGE_URL>$img_url</IMAGE_URL>";
		$outdata.="<IMAGE_SIZE>$img_size</IMAGE_SIZE>";
		$outdata.="<IMAGE_NAME>$img_name</IMAGE_NAME>";
		$outdata.="<UPDATE_MAJOR_VER>$img_max</UPDATE_MAJOR_VER>";
		$outdata.="<UPDATE_MINOR_VER>$img_min</UPDATE_MINOR_VER>";
		$outdata.="<FORCE_FLAG>$forceflag</FORCE_FLAG>";
		$outdata.="<KE>$ke</KE>";
		$outdata.="<GMT>$gmt</GMT>";
		$outdata.="<ECO_INFO>$out_eco_info</ECO_INFO>";
		$outdata.="<CDN_URL>$cdn_url</CDN_URL>";
		$outdata.="<CONTENTS>$contents</CONTENTS>";
	$outdata.="</RESPONSE>";
	$logger->log("Built Response: ");
	$logger->log($outdata);
	$logger->log("Sending Response...");
	return_data($outdata);
	
	
	//Return an xml encoded error/message
	function return_error($resultcode, $message){
		$outdata="<RESPONSE>";
			$outdata.="<RESULT_CD>$resultcode</RESULT_CD>";
			$outdata.="<MSG>$message</MSG>";
		$outdata.="</RESPONSE>";
		return_data($outdata);
		exit(EXIT_FAILURE);
	}
	
	//Mostly for debugging
	function return_message($message){
		$outdata=$message;
		return_data($outdata);
		exit(EXIT_FAILURE);
	}
	
	function return_data($outdata){
		$outdata=base64_encode($outdata);
		header("Content-Type: application/octet-stream;charset=UTF-8");
		header("Content-Description: File Transfer"); 
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: " . strlen($outdata));
		echo $outdata;
		exit(EXIT_SUCCESS);
	}
?>

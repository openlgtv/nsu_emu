<html>
	<head><title>Nus request sender</title></head>
	<body>
	<center>
		<img src="logo.png" width=5% style="float:center;"></img>
		<h1>NSU Emulator by SMX</h1>
		<h2>Choose a request file</h2>
		<form method="post" action="upload.php" enctype="multipart/form-data">
			<input type="file" name="response">
			<input type="submit" name="request" value="Send"><br><br>
		</form>
		<div id="mesg"></div>
		</center>
	</body>
<?php
function dopost($url,$data){	
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/xml"));
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($curl);
	$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);
	if(strval($code)[0] == "2" || strval($code[0]) == "3"){
		return $result;
	}
	echo "The server can't process your request<br>";
	echo "Requested url: ${url}<br>";
	echo "-----server reply (status code ".$code.")-----<br>";
	echo $result;
	return false;

}

function postRequest($url, $data, $optionalHeaders = null)
{
    $params = array('http' => array(
        'method' => 'POST',
        'content' => $data
    ));
    if ($optionalHeaders !== null) {
        $params['http']['header'] = $optionalHeaders;
    }

    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        throw new Exception("Problem with $url, $errormsg");
    }
    $response = @stream_get_contents($fp);
    if ($response === false) {
        throw new Exception("Problem reading data from $url, $errormsg");
    }

    return $response;
}

function say($err,$append){
	$err = str_replace('"','\"',$err);
	if($append) $append="+"; else $append="";
	echo "<script>document.getElementById('mesg').innerHTML $append= \"<font color='red' size=12>$err</font>\";</script>";
}

/**
*  Takes XML string and returns a boolean result where valid XML returns true
*/
function is_valid_xml ( $xml ) {
    libxml_use_internal_errors( true );
     
    $doc = new DOMDocument('1.0', 'utf-8');
     
    $doc->loadXML( $xml );
     
    $errors = libxml_get_errors();
     
    return empty( $errors );
}

function beautify($xmlString){
 
 $outputString = "";
 $previousBitIsCloseTag = false;
 $indentLevel = 0;
 $bits = explode("<", $xmlString);
 
 foreach($bits as $bit){
 
  $bit = trim($bit);
  if (!empty($bit)){
 
   if ($bit[0]=="/"){ $isCloseTag = true; }
   else{ $isCloseTag = false; }
 
   if(strstr($bit, "/>")){
    $prefix = "\n".str_repeat(" ",$indentLevel);
    $previousBitIsSimplifiedTag = true;
   }
   else{
    if ( !$previousBitIsCloseTag and $isCloseTag){
     if ($previousBitIsSimplifiedTag){
      $indentLevel--;
      $prefix = "\n".str_repeat(" ",$indentLevel);
 
     }
     else{
      $prefix = "";
      $indentLevel--;
     }
    }
    if ( $previousBitIsCloseTag and !$isCloseTag){$prefix = "\n".str_repeat(" ",$indentLevel); $indentLevel++;}
    if ( $previousBitIsCloseTag and $isCloseTag){$indentLevel--;$prefix = "\n".str_repeat(" ",$indentLevel);}
    if ( !$previousBitIsCloseTag and !$isCloseTag){{$prefix = "\n".str_repeat(" ",$indentLevel); $indentLevel++;}}
    $previousBitIsSimplifiedTag = false;
   }
 
   $outputString .= $prefix."<".$bit;
 
   $previousBitIsCloseTag = $isCloseTag;
  }
 }
 return $outputString;
}

if(isset($_POST["request"])){
	//$target_url = 'http://snu.lge.com/CheckSWManualUpdate.php';
	$target_url = dirname("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")."/CheckSWManualUpdate.php";
	if(!file_exists("CheckSWManualUpdate.php")){
		say("Missing NSU_EMU script");
		exit();
	}
	if (isset($_FILES['response']) && $_FILES['response']['error'] == UPLOAD_ERR_OK
	&& is_uploaded_file($_FILES['response']['tmp_name'])) {
	
	$requestdata = file_get_contents($_FILES['response']['tmp_name']); 
	} else {
		say("Upload Failed",0);
		exit();
	}
	
	//$ext = pathinfo($file, PATHINFO_EXTENSION);
        $decoded = base64_decode($requestdata, true);
        if($decoded === false){ //not base64
			$requestdata=base64_encode($requestdata);
		}
	
	if(strpos(base64_decode($requestdata),"REQUEST") == false){
		say("Invalid File",1);
		exit();
	}
	
	echo "<h2>NUS Request</h2>";
	$out=base64_decode($requestdata);
	$out=beautify($out);
	$out=htmlspecialchars($out);
	$out=str_replace("\n","<br>\n",$out);
	$out=str_replace(" ","&nbsp;",$out);
	echo($out);
	//$out=str_replace("&lt;","<table><tr><td>&lt;",$out);
	//$out=str_replace("&gt;","&gt;</td></tr>",$out);	
	echo "<br><br>";
	
	echo "<h2>NUS Reply for <font color='orange'>".$_FILES['response']["name"]."</font></h2>";
	$res = dopost($target_url,$requestdata);
	if($res === false){
		exit();
	}
	if(base64_decode($res, true) === false){
		echo "-----php bug-----<br>";
		echo $res;
		exit();
	} else {
		$res = base64_decode($res);
		if(is_valid_xml($res) == false){
			echo($res);
		} else {
			$xres = simplexml_load_string($res);
			if($xres->CDN_URL != null){
				echo "<input type='button' value='Download' onclick=\"location.href='".$xres->CDN_URL."';\">";
				echo "<br>";
			}
			$res=beautify($res);
			$res=htmlspecialchars($res);
			$res=str_replace("\n","<br>\n",$res);
			$res=str_replace(" ","&nbsp;",$res);
			echo($res);	
		}
	}
	echo "</center>";
}

//var_dump($result);
?>
</html>
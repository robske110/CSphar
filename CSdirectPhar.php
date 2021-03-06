<?php
$brokenBuilds = [149,150,151];
$reqCnt = 0;
$debugBuffer = [];
$directOutput = false;
$buildCache = [];
const BR = '<br/>';
const NEWLINE = '<br/><br/>';
/** UTILS */
function getDataFromUrl($url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	$returnData = curl_exec($ch);
	curl_close($ch);
	return $returnData;
}
function addToDebugBuffer($string, $doBR = true){
	global $debugBuffer, $directOutput, $debug;
	if($directOutput && $debug){
		if($doBR){
			echo(BR.htmlspecialchars($string));
		}else{
			echo(htmlspecialchars($string));
		}
		echo("\n");
		ob_flush();
		flush();
	}else{
		$debugBuffer[] = [$string, $doBR];
	}
}
function showDebugBuffer(){
	global $debugBuffer, $directOutput;
	if($directOutput){
		return;
	}
	echo(BR."--DEBUG BUFFER--");
	foreach($debugBuffer as $debugDATA){
		if($debugDATA[1]){
			echo(BR.htmlspecialchars($debugDATA[0]));
		}else{
			echo(htmlspecialchars($debugDATA[0]));
		}
		echo("\n");
	}
	echo(NEWLINE."--OTHER INFO--".NEWLINE);
}
/** Main Code */
$beginTime = time();
if(isset($_GET['branch'])){
	$branch = urlencode($_GET['branch']);
}else{
	$branch = false;
}
if(isset($_GET['debug'])){
	$debug = true;
	echo("<thisisjustheretomakethebrowserthinkithasloadedenoughofthiswebsitetostartdisplayingiknowthatthisisatotalwasteofnetworkcapacity><asthisisfarfromenoughtomakethebrowserthinkithasloadedenoughherearesomefacts><thisisexactlyhowmuchisneededtostartdisplayingsthevenonedigitwillbedisplayerexactlyafterthistext><asiamslowlyrunningoutofwordshereissomeblah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blah><blahffff>");
	echo("Loading...");
	ob_flush();
	flush();
}else{
	$debug = false;
	echo("Redirecting...");
}
echo(BR);
if(isset($_GET['directOutput'])){
	$directOutput = true;
	echo("[INFO] directOutput enabled! ".BR."----Log----");
	echo(BR);
	ob_flush();
	flush();
}else{
	$directOutput = false;
}
/** Main function */
function getMainData(){
	addToDebugBuffer("getMainData()");
	global $reqCnt;;
	$rawData = getDataFromUrl("https://circleci.com/api/v1/project/ClearSkyTeam/ClearSky");
	$reqCnt++;
	return json_decode($rawData);
}
function getCurlHandle($url){
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	
	return $ch;
}
function doAsyncCurl($curlHandles){
	$mh = curl_multi_init();
	foreach($curlHandles as $curlHandleData){
		curl_multi_add_handle($mh, $curlHandleData[1]);
	}

	$running = null;
	do{
		curl_multi_exec($mh, $running);
	}while($running);
	
	foreach($curlHandles as $curlHandleData){
		curl_multi_remove_handle($mh, $curlHandleData[1]);
	}
	curl_multi_close($mh);
  
	//yay, everything recieved, now just get that stuff into the cache...
	foreach($curlHandles as $curlHandleData){
		writeToBuildCache($curlHandleData[0], curl_multi_getcontent($curlHandleData[1]));
	}
}
function writeToBuildCache($buildnum, $buildData){
	global $buildCache;
	$buildCache[$buildnum] = $buildData;
}
function getBuildData($buildnum){
	addToDebugBuffer("getBuildData(".$buildnum.")");
	global $reqCnt, $buildCache;
	if(isset($buildCache[$buildnum])){
		addToDebugBuffer("Reading from Cache...");
		return json_decode($buildCache[$buildnum]);
	}
	if(false){
		addToDebugBuffer("Loading RAW...");
		$rawData = getDataFromUrl('https://circleci.com/api/v1/project/ClearSkyTeam/ClearSky/'.$buildnum);
		$reqCnt++;
		return json_decode($rawData);
	}else{
		addToDebugBuffer("Requesting next 10 builds in a batch...");
		$clonedBuildNum = $buildnum;
		for($i = 0; $i <= 10; $i++){
			$reqCnt++;
			$curlHandles[] = [$clonedBuildNum, getCurlHandle('https://circleci.com/api/v1/project/ClearSkyTeam/ClearSky/'.$clonedBuildNum)];
			$clonedBuildNum--;
		}
		doAsyncCurl($curlHandles);
	}
	return json_decode($buildCache[$buildnum]);
}
function getBuildPharLink($buildJson){
	addToDebugBuffer("getBuildPharLink(object)");
	if($buildJson === false){
		return false;
	}
	global $reqCnt;
	$rawData = getDataFromUrl('https://circleci.com/api/v1/project/ClearSkyTeam/ClearSky/'.$buildJson->build_num.'/artifacts');
	$reqCnt++;
	$artifactsData = json_decode($rawData);
	if(isset($artifactsData[0])){
		return $artifactsData[0]->url;
	}else{
		return false;
	}
}
function getLatestBuild($branch, $startFromBuildNum){
	addToDebugBuffer("getLatestBuild('".$branch."', ".$startFromBuildNum.")");
	global $brokenBuilds;
	if($startFromBuildNum > 0){
		$buildData = getBuildData($startFromBuildNum);
		if($branch === false || (isset($buildData->branch) && $buildData->branch == $branch)){
			if(!in_array($buildData->build_num, $brokenBuilds)){
				return $buildData;
			}
		}
		return getLatestBuild($branch, $startFromBuildNum - 1);
	}else{
		return -1;
	}
}
function getLatestPharLink($mainData, $branch){
	addToDebugBuffer("getLatestBuild(object, '".$branch."')");
	global $brokenBuilds;
	foreach($mainData as $buildData){
		if($branch === false || (isset($buildData->branch) && $buildData->branch == $branch)){
			if(in_array($buildData->build_num, $brokenBuilds)){
				continue;
			}
			$pharLink = getBuildPharLink($buildData);
			if($pharLink === false){
				continue;
			}
			return $pharLink;
		}
		$finalBuildNum = $buildData->build_num;
	}
	$foundBuildWithPhar = false;
	while(!$foundBuildWithPhar && $finalBuildNum > 0){
		$finalBuildNum--;
		$buildData = getLatestBuild($branch, $finalBuildNum);
		if($buildData === -1){
			echo('<span style="color:FFAB00">'."No phar was found for the branch '".$branch."'!".'</span>'.NEWLINE);
			break;
		}
		addToDebugBuffer("BUILD_IS_FROM_BRANCH");
		#addToDebugBuffer(nl2br(print_r($buildData,true)));
		if($buildData !== false && is_object($buildData)){
			addToDebugBuffer("BUILD_IS_STABLE");
			#addToDebugBuffer(nl2br(print_r($buildData,true)));
			#addToDebugBuffer(NEWLINE);
			$finalBuildNum = $buildData->build_num;
			$pharLink = getBuildPharLink($buildData);
			$foundBuildWithPhar = ($pharLink !== false);
		}
	}
	if($finalBuildNum < 0){
		echo('<span style="color:FFAB00">'."No phar was found for the branch '".$branch."'!".'</span>'.NEWLINE);
	}
	return $pharLink;
}
/** Final code */
$finalPharLink = getLatestPharLink(getMainData(), $branch);
if($finalPharLink !== "" && $finalPharLink !== NULL && $finalPharLink !== -1){
	$pharLinkSeemsStable = true;
}
if(!$debug){
	if($pharLinkSeemsStable){
		header("Location: ".$finalPharLink);
		exit;
	}
}
if($debug){
	echo(NEWLINE."----DEBUG-----".BR);
}elseif(!$pharLinkSeemsStable){
	echo("[WARNING] F A I L E D to get the phar Link!".BR);
}else{
	echo("dfuc? (A very strange error happened....)".BR);
}
showDebugBuffer();
echo("CircleCI API request count: '".$reqCnt."'".BR);
echo('Phar link: "'.$finalPharLink.'"'.BR);
echo(BR."Done! Took ");
echo(- ($beginTime - time()));
echo("s.");
exit;
?>
Something went very, very wrong <br/>
ERR_9999
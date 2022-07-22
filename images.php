<?php

// Process JATS XML and fetch images to store locally, rewriting JATS to
// use local images.

//----------------------------------------------------------------------------------------
function get($url, $format = '')
{
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	if ($format != '')
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: " . $format));	
	}
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	curl_close($ch);
	
	return $response;
}


//----------------------------------------------------------------------------------------


$filename = 'pone.0063616.xml';
$filename = '917847.xml';
$filename = '26292.xml';

$xml = file_get_contents($filename);

$dom= new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

$xpath->registerNamespace("xlink", "http://www.w3.org/1999/xlink");

// get figures
foreach ($xpath->query ('//fig/graphic') as $node)
{
	$href = $node->getAttribute('xlink:href');
	
	$filename = $href;
	
	
	// create URL to fetch image (will be publisher specific)
	
	// plos
	//$href = 'https://journals.plos.org/plosone/article/figure/image?download&size=large&id=' . $href;
	// create a safe file name 
	//$filename = str_replace('info:doi/10.1371/', '', $href);
	
	// Hindawi
	//$href = 'https://static-02.hindawi.com/articles/psyche/volume-2013/917847/figures/' . $href . '.jpg';
	
	// Pensoft
	foreach ($xpath->query ('uri', $node) as $n)
	{
		$href = $n->firstChild->nodeValue;
	}
		
	// tmp file to store image
	$tmp_name = "imagefile";
	
	// fetch image and determine its type
	$image = get($href);
	file_put_contents($tmp_name, $image);
	
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime_type = finfo_file($finfo, $tmp_name);
	
	$extension = "." . explode('/', $mime_type )[1];
	
	$filename .= $extension;
	
	copy($tmp_name, $filename);
	
	unlink($tmp_name);
	
	$node->setAttribute('xlink:href', $filename);
	
}

echo $dom->saveXML();

?>


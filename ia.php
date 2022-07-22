<?php

// IA to JATS

require_once (dirname(__FILE__) . '/nameparse.php');

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
function get_meta_xml($ia, $force = false)
{
	$meta_filename = $ia . '.xml';

	if (!file_exists($meta_filename))
	{
		$url = 'https://archive.org/download/' . $ia . '/' . $ia . '_meta.xml';
		
		$xml = get($url);
		
		file_put_contents($meta_filename, $xml);
	}

	$xml = file_get_contents($meta_filename);
	
	return $xml;
}

//----------------------------------------------------------------------------------------
function get_page_numbers($ia, $force = false)
{
	$pages_filename = $ia . '.json';

	if (!file_exists($pages_filename))
	{
		$url = 'https://archive.org/download/' . $ia . '/' . $ia . '_page_numbers.json';
		
		$json = get($url);
		
		if ($json =='')
		{
			$json = '{}';
		}
		
		file_put_contents($pages_filename, $json);
	}

	$json = file_get_contents($pages_filename);
	
	return $json;
}

//----------------------------------------------------------------------------------------
function csl_to_jats($csl, $page_numbers = null, $id_type = "unknown")
{
	$impl = new DOMImplementation();

	$doc = $impl->createDocument(null, '',
		$impl->createDocumentType("article", 
			"SYSTEM", 
			"jats-archiving-dtd-1.0/JATS-archivearticle1.dtd"));
	
	// http://stackoverflow.com/questions/8615422/php-xml-how-to-output-nice-format
	$doc->preserveWhiteSpace = false;
	$doc->formatOutput = true;	
	
	// root element is <records>
	$article = $doc->appendChild($doc->createElement('article'));
	
	$article->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
	
	$front = $article->appendChild($doc->createElement('front'));
	
	if (isset($csl->{"container-title"}))
	{	
		$journal_meta = $front->appendChild($doc->createElement('journal-meta'));
		$journal_title_group = $journal_meta->appendChild($doc->createElement('journal-title-group'));
		$journal_title = $journal_title_group->appendChild($doc->createElement('journal-title'));
		$journal_title->appendChild($doc->createTextNode($csl->{"container-title"}));
	}
	
	if (isset($csl->ISSN))
	{
		$issn = $journal_meta->appendChild($doc->createElement('issn'));
		$issn->appendChild($doc->createTextNode($csl->ISSN[0]));
	}
	
	$article_meta = $front->appendChild($doc->createElement('article-meta'));
	
	$article_id = $article_meta->appendChild($doc->createElement('article-id'));
	$article_id->setAttribute('pub-id-type', $id_type);
	$article_id->appendChild($doc->createTextNode($csl->id));

	if (isset($csl->DOI))
	{
		$article_id = $article_meta->appendChild($doc->createElement('article-id'));
		$article_id->setAttribute('pub-id-type', 'doi');
		$article_id->appendChild($doc->createTextNode($csl->DOI));
	}
	
	if (isset($csl->ARK))
	{
		$article_id = $article_meta->appendChild($doc->createElement('article-id'));
		$article_id->setAttribute('pub-id-type', 'ark');
		$article_id->appendChild($doc->createTextNode($csl->ARK));
	}	
	
	$title_group = $article_meta->appendChild($doc->createElement('title-group'));
	$article_title = $title_group->appendChild($doc->createElement('article-title'));
	$article_title->appendChild($doc->createTextNode($csl->title));
	
	if (isset($csl->author) && count($csl->author) > 0)
	{
		$contrib_group = $article_meta->appendChild($doc->createElement('contrib-group'));
		
		foreach ($csl->author as $author)
		{
			$contrib = $contrib_group->appendChild($doc->createElement('contrib'));
			$contrib->setAttribute('contrib-type', 'author');
			
			$name = $contrib->appendChild($doc->createElement('name'));
			
			if (isset($author->family))
			{			
				$surname = $name->appendChild($doc->createElement('surname'));
				$surname->appendChild($doc->createTextNode($author->family));
			}
			if (isset($author->given))
			{
				$given_name = $name->appendChild($doc->createElement('given-names'));
				$given_name->appendChild($doc->createTextNode($author->given));
			}
		}
	}
	
	if (isset($csl->issued))
	{
		$pub_date = $article_meta->appendChild($doc->createElement('pub-date'));
		$pub_date->setAttribute('pub-type', 'ppub');
		
		if (count($csl->issued->{'date-parts'}[0]) == 1)
		{
			$year = $pub_date->appendChild($doc->createElement('year'));
			$year->appendChild($doc->createTextNode($csl->issued->{'date-parts'}[0][0]));		
		}

		if (count($csl->issued->{'date-parts'}[0]) == 2)
		{
			$month = $pub_date->appendChild($doc->createElement('month'));
			$month->appendChild($doc->createTextNode($csl->issued->{'date-parts'}[0][1]));		
		}
		
		if (count($csl->issued->{'date-parts'}[0]) == 3)
		{
			$month = $pub_date->appendChild($doc->createElement('day'));
			$month->appendChild($doc->createTextNode($csl->issued->{'date-parts'}[0][2]));		
		}
	}
	
	if (isset($csl->volume))
	{
		$volume = $article_meta->appendChild($doc->createElement('volume'));
		$volume->appendChild($doc->createTextNode($csl->volume));
	}
	if (isset($csl->issue))
	{
		$issue = $article_meta->appendChild($doc->createElement('issue'));
		$issue->appendChild($doc->createTextNode($csl->issue));
	}
	
	if (isset($csl->page))
	{
		if (preg_match('/(.*)-(.*)/', $csl->page, $m))
		{
			$fpage = $article_meta->appendChild($doc->createElement('fpage'));
			$fpage->appendChild($doc->createTextNode($m[1]));		
			
			$lpage = $article_meta->appendChild($doc->createElement('lpage'));
			$lpage->appendChild($doc->createTextNode($m[2]));		
								
		}
		else
		{
			$fpage = $article_meta->appendChild($doc->createElement('fpage'));
			$fpage->appendChild($doc->createTextNode($csl->page));		
		}	
	}
	
	
	if (isset($csl->imagecount))
	{
		$body = $article->appendChild($doc->createElement('body'));
		
		$supplementary_material = $body->appendChild($doc->createElement('supplementary-material'));
		$supplementary_material->setAttribute('content-type', 'scanned-pages');
	
		$num_pages = $csl->imagecount;
		
		$page_labels = array();
		
		// numbering
		if ($page_numbers)
		{
			foreach ($page_numbers->pages as $pages)
			{
				if (isset($pages->pageNumber))
				{
					$page_labels[$pages->leafNum] = $pages->pageNumber;
				}
			}
		}
		
		for ($page_count = 0; $page_count < $num_pages; $page_count++)
		{
			$graphic = $supplementary_material->appendChild($doc->createElement('graphic'));
		
			$graphic->setAttribute('id', 'graphic-' . $page_count);
		
			$graphic->setAttribute('xlink:href', 
				'https://archive.org/download/' . $csl->id . '/page/n' . $page_count  . '.jpg');
		
			$page_name = 'scanned-page';
			

			
			if (isset($page_labels[$page_count]))
			{
				$page_name = $page_labels[$page_count];
			}
		
			$graphic->setAttribute('xlink:title', $page_name );
		}

	}

	return $doc;

}

//----------------------------------------------------------------------------------------


$ia = 'ijo1388789020099748349';
$ia = 'acta-entomologica-sinica-3206';


$xml = get_meta_xml($ia);
echo $xml;

$json = get_page_numbers($ia);

$page_numbers = json_decode($json);

// CSL

$csl = new stdclass;

$dom = new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

foreach ($xpath->query('//identifier') as $node)
{
	$csl->id =  $node->firstChild->nodeValue;
}

foreach ($xpath->query('//external-identifier') as $node)
{
	if (preg_match('/^doi:(.*)/', $node->firstChild->nodeValue, $m))
	{
		$csl->DOI = $m[1];
	}
	
}

foreach ($xpath->query('//identifier-ark') as $node)
{
	if (preg_match('/^ark:\/(.*)/', $node->firstChild->nodeValue, $m))
	{
		$csl->ARK = $m[1];
	}
	
}

foreach ($xpath->query('//title') as $node)
{
	$csl->title =  $node->firstChild->nodeValue;
}

foreach ($xpath->query('//creator') as $node)
{
	if (!isset($csl->author))
	{
		$csl->author = array();
	}
	
	$author = new stdclass;
	
	$parts = parse_name($node->firstChild->nodeValue);
	
	if (isset($parts['last']))
	{
		$author->family = $parts['last'];
	}
	
	if (isset($parts['first']))
	{
		$author->given = $parts['first'];
		
		if (array_key_exists('middle', $parts))
		{
			$author->given .= ' ' . $parts['middle'];
		}
	}
	
	if (!isset($author->given) && !isset($author->family))
	{
		$author->literal = $node->firstChild->nodeValue;
	}
	
	$csl->author[] = $author;
}

foreach ($xpath->query('//journaltitle') as $node)
{
	$csl->{'container-title'} =  $node->firstChild->nodeValue;
}

foreach ($xpath->query('//volume') as $node)
{
	$csl->volume =  $node->firstChild->nodeValue;
}

foreach ($xpath->query('//issue') as $node)
{
	$csl->issue =  $node->firstChild->nodeValue;
}

foreach ($xpath->query('//pages') as $node)
{
	$csl->page =  $node->firstChild->nodeValue;
}

foreach ($xpath->query('//year') as $node)
{
	$csl->issued = new stdclass;
	$csl->issued->{'date-parts'} = array();
	$csl->issued->{'date-parts'}[] = array((Integer)($node->firstChild->nodeValue));
}

foreach ($xpath->query('//imagecount') as $node)
{
	$csl->imagecount = $node->firstChild->nodeValue;
}


print_r($csl);

$doc = csl_to_jats($csl, $page_numbers);




echo $doc->saveXML();


// convert to JATS

// add page numbers

?>



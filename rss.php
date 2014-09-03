<?php
/*==========================*/
/*Gallery address definition*/
/*==========================*/
$gallery_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$gallery_domain = $_SERVER['HTTP_HOST'];
$gallery_path = dirname($_SERVER['REQUEST_URI']);
$gallery_path = (substr($gallery_path, -1) === '/') ? substr($gallery_path, 0, -1) : $gallery_path;
$gallery_link = $gallery_protocol.$gallery_domain.$gallery_path;
$self_link = $gallery_link.'/rss.php';

/*===================*/
/*Functions*/
/*===================*/
	# Hardly inspired from here : codes-sources.commentcamarche.net/source/35937-creation-d-une-arborescenceI
	# Listing all files of a folder and sub folders.
	function ListFiles($gallery_link, &$content, $Folder, $SkipFileExts, $SkipObjects)
	{
		$dir = opendir($Folder);
		while (false !== ($Current = readdir($dir))) // Loop on all contained on the folder
		{
			if ($Current !='.' && $Current != '..' && in_array($Current, $SkipObjects)===false)
			{
				if(is_dir($Folder.'/'.$Current)) // If the current element is a folder
				{
					ListFiles($gallery_link, $content, $Folder.'/'.$Current, $SkipFileExts, $SkipObjects); // Recursivity
				}
				else
				{
					$FileExt = strtolower(substr(strrchr($Current ,'.'),1));
					if (in_array($FileExt, $SkipFileExts)===false) // Should we display this extension ?
						$current_adress = $gallery_link . "/" . $Folder.'/'. $Current;
						$content[] = $current_adress;
				}
			}
		}
		closedir($dir);

		return $content;
	}

	function print_array($array_to_display) {
		echo '<pre>';
		print_r($array_to_display);
		echo '</pre>';
	}

	function log_array($array_to_log) {
		error_log(var_export($array_to_log, true));
	}

	/**
	 * SimpleXMLElement with CDATA support
	 * http://stackoverflow.com/a/20511976
	 */
	Class SimpleXMLElementExtended extends SimpleXMLElement {
		/**
		* Adds a child with $value inside CDATA
		* @param unknown $name
		* @param unknown $value
		*/
		public function addChildWithCDATA($name, $value = NULL) {
			$new_child = $this->addChild($name);

			if ($new_child !== NULL) {
			  $node = dom_import_simplexml($new_child);
			  $no   = $node->ownerDocument;
			  $node->appendChild($no->createCDATASection($value));
			}

			return $new_child;
		}

		public function prependChild($name, $value)
		{
			$dom = dom_import_simplexml($this);

			$new = $dom->insertBefore(
				$dom->ownerDocument->createElement($name, $value),
				$dom->firstChild
			);

			return simplexml_import_dom($new, get_class($this));
		}
	}

/*===================*/
/*Variables*/
/*===================*/
	require("config.php");

	$old_files_list  = "db_old_files"; //list of files in ./photos
	$db_feed_source = "db_feed_source";
	$db_rss_timestamp = "db_rss_timestamp";
	$Folder =  'photos';
	$content = ListFiles($gallery_link, $content, $Folder, $SkipExts, $SkipObjects);
	log_array($content);
	// Init files
	if (!file_exists($old_files_list))
	{
		file_put_contents($old_files_list, serialize(array()));
	}
	if (!file_exists($db_feed_source))
	{
		$empty_feed =
	"<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
		<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">
			<channel>
			<atom:link href=\"'$self_link'\" rel=\"self\" type=\"application/rss+xml\" />
			<title>".$title."</title>
			<link>".$gallery_link."</link>
			<description>".$description."</description>
			</channel>
		</rss>
	";
		file_put_contents($db_feed_source, $empty_feed);
	}
	if (!file_exists($db_rss_timestamp))
	{
		file_put_contents($db_rss_timestamp, '');
	}

/*===================*/
/*Computing*/
/*===================*/
	#Todo : ajouter une condition : dois-je regénérer le flux ou utiliser les anciens fichiers ?
	$cached_rss = simplexml_load_file($db_feed_source, 'SimpleXMLElementExtended', LIBXML_NOCDATA);
	$last_rss_gen = file_get_contents($db_rss_timestamp);
	$current_time = time();
	//If the RSS generation is already launched, don't do a second generation at the same time
	if (($current_time - $last_rss_gen) > $rss_refresh_interval && file_exists("rss.locker") == false)
	{
		file_put_contents("rss.locker", "");
		file_put_contents($db_rss_timestamp, time());
		// Load the list from files.
		$old_files_list_content = unserialize(file_get_contents($old_files_list));
		$new_files_list_content = $content;
		// Generate and stock new elements
		$differences = array_diff($old_files_list_content, $new_files_list_content);

		//Add new elements at the top of the feed's source
		foreach ($differences as $picture) {
			$pieceOfTitle = strrchr($picture, '/');
			$titleLenght = strlen($pieceOfTitle) - strlen(strrchr($pieceOfTitle, '.'));
			$currentItem = $cached_rss->channel->prependChild('item');
			$currentItem->addChild('title', substr($pieceOfTitle, 1, $titleLenght-1));
			$currentItem->addChild('link', $picture);
			$currentItem->addChild('guid', $picture);
			$currentItem->addChildWithCDATA('description', '<img src="'.$picture.'"/>');
		}

		file_put_contents($db_feed_source, $cached_rss->asXml());
		// Store the current file list for the next generation
		file_put_contents($old_files_list, serialize($content));
		unlink("rss.locker");
	}

	echo $cached_rss->asXml();
/*===================*/
/*XML Gen*/
/*===================*/
	// $pieceOfTitle;
	// $titleLenght;
	// foreach ($content as $picture) {
	// 	$pieceOfTitle = strrchr($picture, '/');
	// 	$titleLenght = strlen($pieceOfTitle) - strlen(strrchr($pieceOfTitle, '.'));
	// 	$currentItem = $cached_rss->channel->addChild('item');
	// 	$currentItem->addChild('title', substr($pieceOfTitle, 1, $titleLenght-1));
	// 	$currentItem->addChild('link', $picture);
	// 	$currentItem->addChild('guid', $picture);
	// 	$currentItem->addChildWithCDATA('description', '<img src="'.$picture.'"/>');
	// }

	// $xml = $cached_rss->asXML();
	// echo $xml;
	// file_put_contents($db_feed_source, $xml);

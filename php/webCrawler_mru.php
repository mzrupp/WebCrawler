<?php
function console_log( $data ){
	echo '<script>';
	echo 'console.log('. json_encode( $data ) .')';
	echo '</script>';
  }

function shutDownFunction() { 
    $error = error_get_last();
    // fatal error, E_ERROR === 1
    if (isset($error) && $error['type'] === E_ERROR) { 
		echo "<p>Error: ".$error['message']."</p>";
		echo "<p>You might want to set the maxium execution time on a higher value!</p>";
		echo "<p>If you use xampp the execution time is increased in xampp\php\php.ini, variable max_execution_time.</p>";
    } 
}

class Crawler {
	
	protected $markup = '';
	
	public $base = '';

	public function __construct($uri) {
		$this->base = $uri;
		$this->markup = $this->getMarkup($uri);
	}
	
	public function getMarkup($uri) {
		$content = @file_get_contents($uri);
		if($content === FALSE) {
			return "invalidUri";
		}
		return $content;
	}

	public function get($type) {
		$method = "_get_{$type}";
		if (method_exists($this, $method)){
			return call_user_func(array($this, $method));
		}
	}

	protected function _get_images() {
		if (!empty($this->markup)){
			preg_match_all('/<img([^>]+)\/>/i', $this->markup, $images);
			return !empty($images[1]) ? $images[1] : FALSE;
		}
	}

	protected function _get_links() {
		if (!empty($this->markup)){
			preg_match_all('/href=\"([^;\s]*?)\"/i', $this->markup, $allLinks);
			preg_match_all('/href=\"([^;\s]*?(\.css(\?){0,1}){1}[^;\s]*?)\"/i', $this->markup, $cssLinks);
			if(!empty($allLinks[1]) && !empty($cssLinks[1])){
				$links = array_diff($allLinks[1], $cssLinks[1]);
			}

			return !empty($links) ? $links : FALSE;
		}
	}

	protected function _get_words(){
		if (!empty($this->markup)){
			
			$temp = [];
			$words = explode(" ", preg_replace('(<script.*<\/script>|<[^>]*?>|<!--|-->|\s|[\)\("„“|,:/+?]|&#[0-9]*;|_[-]_)', ' ', $this->markup));
			
			foreach($words as $word){
				if( isset($word) && !empty($word)){
					
					if(preg_match('/.*[.]/', $word)){
						
						$abbreviations_de = array("allg.", "bzw.", "bspw.", "d.h.", "etc.", "evtl.", "geb.", "ggf.", "n. Chr.", "s.o.", "s.u." , "usw.", "v. Chr.", "vgl.", "z.B.");
						if (!in_array("Irix", $abbreviations_de)) {
							$word = rtrim($word, ".");
						}
					}
					
					$word = html_entity_decode($word);
					
					array_push( $temp, $word );
				}
			}
			
			$words = $temp;

			if(!empty($words)){
				$uniqueWords = array_count_values($words);
			}
			
			return !empty($uniqueWords) ? $uniqueWords : FALSE;
		}
	}

	protected function _get_markup(){
		return $this->markup;
	}
	
	private function  sonderzeichen($string)
	{
		 $string = str_replace("ä", "ae", $string);
		 $string = str_replace("ü", "ue", $string);
		 $string = str_replace("ö", "oe", $string);
		 $string = str_replace("Ä", "Ae", $string);
		 $string = str_replace("Ü", "Ue", $string);
		 $string = str_replace("Ö", "Oe", $string);
		 $string = str_replace("ß", "ss", $string);
		 $string = str_replace("´", "", $string);
		 return $string;
	}
}

$crawl = null;

function crawl ( $id, $url, $dbConn )
{
	$crawl = new Crawler($url);
	
	#&& (strcmp( $crawler->get('markup'), "invalidUri" ) !== 0) ?
	if( isset( $crawl ) ){	
	
		$words = $crawl->get('words');
		$links = $crawl->get('links');

		updateCrawledURL( $id, $dbConn );

		if(isset($words)){
			foreach ( $words as $word => $count ){
			
				$word = $dbConn->real_escape_string($word);
			
				$sql = 'select id from word where word = "' .$word. '"';
				$result = $dbConn->query( $sql );
				
				$wordId;
				
				if ( isset( $result ) && !empty( $result ) && ($result->num_rows > 0) ) {
					$row = $result->fetch_array(MYSQLI_ASSOC);
					$wordId = $row["id"];
				}
				else{
					$sql = 'insert into word(word) values("' .$word. '")';
					$dbConn->query($sql);
				
					$wordId = $dbConn->insert_id;
				}
					
				$sql = "insert into link(url_id, word_id, number_of_words_in_url) values (" . $id. "," .$wordId. "," .$count. ")";
				$dbConn->query($sql);
					
			}
			if ( !$dbConn -> commit() ) {
				$msg = "Word Commit failed";
				console_log( $msg );
				exit();
			}		
		}

		#TODO moritz.rupp $links !== FALSE?
		if($links !== FALSE && isset($links)){
			foreach( $links as $link ) {			
				
				if( $link != $url ){
						
					if ( ( substr( $link, 0, 7 ) != 'http://' ) && ( substr( $link , 0, 8 ) != 'https://' ) ){
						#TODO moritz.rupp $crawler->base or $url
						$link = $crawl->base . "/" .$link;
					}
							
					$sql = 'insert into url(url, timestamp) select * from (select "' .$link. '", "0000-00-00") as u where not exists ( select url from url where url = "' .$link. '") limit 1';
					$dbConn->query( $sql );
					
					$id = $dbConn->insert_id;

					#crawl( $id, $link, $dbConn );
				}
			}
			if ( !$dbConn -> commit() ) {
				
				$msg = "Url Commit failed";
				console_log( $msg );
				exit();
			}
		}

	}
	else {
		#delete invalid url
		if(isset($urlId)){
			$sql = "DELETE from url where id = $urlId";
			$dbConn->query($sql);
			if (!$dbConn -> commit()) {
				$msg = "Delete Url Commit failed";
				console_log( $msg );
				exit();
			}
		}
	}
}

/*
function updateWords( $urlId, $words, $dbConn ){
	
	foreach ( $words as $word => $count ){
			
		$sql = 'insert into word(word) select * from (select "' .$word. '") as w where not exists ( select word from word where word = "' .$word. '") limit 1';
		$dbConn->query($sql);
		
		$wordId = $dbConn->insert_id;
			
		$sql = "insert into link(url_id, word_id, number_of_words_in_url) values (" . $urlId. "," .$wordId. "," .$count. ")";
		$dbConn->query($sql);
			
	}
	if (!$dbConn -> commit()) {
		$msg = "Word Commit failed";
		console_log( $msg );
		exit();
	}
}

function insertLinks( $crawl, $links, $dbConn ){
		
	foreach( $links as $link ) {			
				
		#if( $link != $url ){
				
			if ( ( substr( $link, 0, 7 ) != 'http://' ) && ( substr( $link , 0, 8 ) != 'https://' ) ){
				#TODO moritz.rupp $crawler->base or $url
				$link = $crawl->base . "/" .$link;
			}
					
			$sql = 'insert into url(url, timestamp) select * from (select "' .$link. '", "0000-00-00") as u where not exists ( select url from url where url = "' .$link. '") limit 1';
			$dbConn->query( $sql );
			
			$id = $dbConn->insert_id;

			#crawl( $id, $link, $dbConn );
		#}
	}
	if (!$dbConn -> commit()) {
		
		$msg = "Url Commit failed";
		console_log( $msg );
		exit();
	}
}
*/

function updateCrawledURL( $id, $dbConn ){
		
	$sql = 'update url set timestamp = NOW() where id = ' .$id;
	$dbConn->query($sql);
		
	if (!$dbConn -> commit()) {
		$msg = 'Failed updating timestamp of url with url: ' .$url;
		console_log( $msg );
		exit();
	}
}

/* START OF THE PROGRAMM */
$dbConn = new mysqli("127.0.0.1", "root", "", "webcrawler");

mysqli_autocommit( $dbConn, FALSE );

register_shutdown_function('shutDownFunction');

try{
	if( isset( $dbConn ) )
	{
		#while(true){
			
			$sql = 'select id, url from url where timestamp = 0000-00-00 or TIMESTAMPDIFF(SECOND, timestamp, NOW()) >= 10';
			$result = $dbConn->query( $sql );
				
			if ( isset( $result ) && ($result->num_rows > 0) ) {
				while( $row = $result->fetch_assoc() ) {
					$url = $row['url'];
					$id = $row['id'];
					
					crawl( $id, $url, $dbConn );
				}
			}
		#}
	}
}
catch (Error $e){
	console_log("Error abgefangen:".$e->getMessage());
}
finally{
	$dbConn->close();
}

?>

<html>
	<head>
		<title>WebCrawler</title>
	</head>
	<body>
		<h2>Webcrawler</h2>
		<p>Alle Urls wurden bearbeitet!</p>
	</body>
</html>
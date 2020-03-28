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
			//preg_match_all('/<a([^>]+)\>(.*?)\<\/a\>/i', $this->markup, $links);
			//href=\"([^;\s]*?)\"
			//preg_match_all('/href=\"(.*?)\"/i', $this->markup, $links);
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
			//preg_match_all('/<a([^>]+)\>(.*?)\<\/a\>/i', $this->markup, $links);
			preg_match_all('/([A-Z]{0,1}[a-zäöüÄÖÜß]{1,})/', $this->markup, $words);
			if(!empty($words)){
				$uniqueWords = array_count_values($words[1]);
			}
			return !empty($uniqueWords) ? $uniqueWords : FALSE;
		}
	}

	protected function _get_markup(){
		return $this->markup;
	}
}

function insertIfNot ($ifNotSql, $insertSql, $conn){
	$searchRes = $conn -> query($ifNotSql);
	if (!$searchRes->num_rows > 0) {
		$success = $conn -> query($insertSql);
		if (!$success) {
			console_log($conn->error);
			return FALSE;
		}
		return TRUE;
	}
	return FALSE;
}

function crawl ($parentUrl, $url, $dbConn)
{
	#get urlId and crawler object
	$sql = "SELECT id from url where url = '".$url."'";
	$result = $dbConn->query($sql);
	$urlId = null;
	$crawl = null;
	if(isset($result) && $result->num_rows > 0){
		$firstRow = $result->fetch_row();
		$urlId = $firstRow[0];
		$crawl = new Crawler($parentUrl.$url);
	}
	
	if(isset($urlId) && isset($crawl) && strcmp($crawl->get('markup'), "invalidUri") !== 0 ){	
		$words = $crawl->get('words');
		$links = $crawl->get('links');
		$newLinks = array();

		if($links !== FALSE && isset($links)){

			#fill the url table with new urls
			foreach($links as $l) {
				if($l != $url){
					$parentUrl = "";
					if (substr($l,0,7)!='http://' && substr($l,0,8)!='https://'){
						$parentUrl = $crawl->base . "/";
					}
					$completeUrl = $parentUrl.$l;
					$insertSql = "INSERT INTO url(url, timestamp) VALUES ('" .$completeUrl. "','0000-00-00')";
					$ifNotSql = "SELECT url from url where url='" .$completeUrl. "'";
					$success = insertIfNot($ifNotSql, $insertSql, $dbConn);
					if ($success) {
						array_push($newLinks, $l);	
					}
				}
			}
			if (!$dbConn -> commit()) {
				$msg = "Url Commit failed";
				console_log( $msg );
				exit();
			}
		}
		
		if(isset($words)){
			#fill the word table with new words
			foreach($words as $w => $w_count) {
				$insertSql = "INSERT INTO word(word) VALUES ('" .$w. "')";
				$ifNotSql = "SELECT word from word where word='" .$w. "'";
				insertIfNot($ifNotSql, $insertSql, $dbConn);
			}
			if (!$dbConn -> commit()) {
				$msg = "Word Commit failed";
				console_log( $msg );
				exit();
			}

			#fill the link table
			foreach($words as $w => $w_count) {
				$sql = "SELECT id from word where word='" .$w. "'";
				$result = $dbConn->query($sql);
				if(isset($result) && $result->num_rows > 0){
					$firstRow = $result->fetch_row();
					$wordId = $firstRow[0];				
					$sql = "INSERT INTO link(url_id, word_id, number_of_words_in_url) VALUES (". $urlId. "," .$wordId. "," .$w_count.")";
					$dbConn->query($sql);
				}
			}
			if (!$dbConn -> commit()) {
				$msg = "Link Commit failed";
				console_log( $msg );
				exit();
			}			
		}

		#set link on crawled
		$sql = "UPDATE url SET timestamp = CURRENT_TIME() where id = " .$urlId;
		$dbConn->query($sql);
		if (!$dbConn -> commit()) {
			$msg = "Update Timestamp Commit failed";
			console_log( $msg );
			exit();
		}

		#recursive crawling for new links 
		if(isset($newLinks)){
			foreach($newLinks as $l) {
				$parentUrl = "";
				if (substr($l,0,7)!='http://' && substr($l,0,8)!='https://'){
					$parentUrl = $crawl->base . "/";
				}
				crawl($parentUrl, $l, $dbConn);
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

/* START OF THE PROGRAMM */


$dbConn = new mysqli("127.0.0.1", "root", "", "webcrawler");
mysqli_autocommit($dbConn,FALSE);
register_shutdown_function('shutDownFunction');

try{
	if(isset($dbConn))
	{
		$sql = "SELECT id, url from url where timestamp = 0000-00-00";
		$result = $dbConn->query($sql);
				
		if (isset($result) && $result->num_rows > 0) {
			while($lRow = $result->fetch_assoc()) {
				$url = $lRow["url"];
				#make url valid if necessary
				$parentUrl="";
				if (substr($url,0,7)!='http://' && substr($url,0,8)!='https://'){
					$parentUrl = "http://";
				}
				crawl($parentUrl, $url, $dbConn);
			}
		}
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
<body>
<h2>Webcrawler</h2>
<p>Alle Urls wurden bearbeitet!</p>
</body>
</html>
<?php
function console_log( $data ){
	echo '<script>';
	echo 'console.log('. json_encode( $data ) .')';
	echo '</script>';
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
			preg_match_all('/href=\"([^;\s]*?)\"/i', $this->markup, $links);
			return !empty($links[1]) ? $links[1] : FALSE;
		}
	}

	protected function _get_words(){
		if (!empty($this->markup)){
			//preg_match_all('/<a([^>]+)\>(.*?)\<\/a\>/i', $this->markup, $links);
			preg_match_all('/([A-Z]{0,1}[a-zäöüÄÖÜß]{1,})/', $this->markup, $words);
			if(!empty($words))
			{
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
			print_r($conn->error);
		}
		return TRUE;
	}
	return FALSE;
}

function crawl ($url, $dbConn)
{
	#get urlId and crawler object
	$sql = "SELECT id from url where url = '".$url."'";
	$result = $dbConn->query($sql);
	$urlId = null;
	$crawl = null;
	if(isset($result) && $result->num_rows > 0){
		$firstRow = $result->fetch_row();
		$urlId = $firstRow[0];
		$crawl = new Crawler($url);
	}
	console_log($url);
	console_log($urlId);
	console_log("----------------------");

	if(isset($urlId) && isset($crawl) && strcmp($crawl->get('markup'), "invalidUri") !== 0 )
	{
		$words = $crawl->get('words');
		$links = $crawl->get('links');
		
		if($links !== FALSE && isset($links)){
			#fill the url table with new urls
			$newLinks = array();
			foreach($links as $l) {
				if (substr($l,0,7)!='http://' && substr($l,0,8)!='https://'){
					$l = $crawl->base . "/" . $l;
				}
				$insertSql = "INSERT INTO url(url, timestamp) VALUES ('" .$l. "','0000-00-00')";
				$ifNotSql = "SELECT url from url where url='" .$l. "'";
				$success = insertIfNot($ifNotSql, $insertSql, $dbConn);
				if ($success) {
					array_push($newLinks, $l);	
				}
			}
			if (!$dbConn -> commit()) {
				$msg = "Url Commit failed";
				console_log( $msg );
				exit();
			}
		}

		#recursive crawling for new links 
		if(isset($newLinks)){
			foreach($newLinks as $l) {
				crawl($l, $dbConn);
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
					$sql = "INSERT INTO link(url_id, word_id, anzahl) VALUES (". $urlId. "," .$wordId. "," .$w_count.")";
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
	}
	else {
		#delete invali
		if(isset($urlId)){
			$sql = "DELETE from url where id = $urlId";
			$dbConn->query($sql);
			if (!$dbConn -> commit()) {
				$msg = "Delete Timestamp Commit failed";
				console_log( $msg );
				exit();
			}
		}
	}
}

/* START OF THE PROGRAMM */
$dbConn = new mysqli("127.0.0.1", "root", "", "webcrawler");
mysqli_autocommit($dbConn,FALSE);
try{
	if(isset($dbConn))
	{
		$sql = "SELECT id, url from url where timestamp = 0000-00-00";
		$result = $dbConn->query($sql);
				
		if (isset($result) && $result->num_rows > 0) {
			while($lRow = $result->fetch_assoc()) {
				$url = $lRow["url"];
				crawl($url, $dbConn);
			}
		}
	}
}
catch (Exception $e){
	console_log("Exception abgefangen:".$e->getMessage());
}
finally{
	console_log("Finally block executed");
	$dbConn->close();
}

?>
<html>
<body>
<h2>Webcrawler</h2>
<p>Alle Urls wurden bearbeitet!</p>
</body>
</html>
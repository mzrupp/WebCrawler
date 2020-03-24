<?php
class Crawler {
protected $markup = '';
 public $base = '';

 public function __construct($uri) {
	 $this->base = $uri;
	 $this->markup = $this->getMarkup($uri);
 }
 public function getMarkup($uri) {
	return file_get_contents($uri);
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
			 preg_match_all('/href=\"(.*?)\"/i', $this->markup, $links);
			 return !empty($links[1]) ? $links[1] : FALSE;
	}
 }
 protected function _get_words(){
	 if (!empty($this->markup)){
			 //preg_match_all('/<a([^>]+)\>(.*?)\<\/a\>/i', $this->markup, $links);
			 preg_match_all('/([A-Z]{0,1}[a-zäöüÄÖÜß]{1,})/', $this->markup, $words);
			 return !empty($words[1]) ? $words[1] : FALSE;
	}
 }
 
}

function console_log( $data ){
  echo '<script>';
  echo 'console.log('. json_encode( $data ) .')';
  echo '</script>';
}

function insertIfNot ($ifNotSql, $insertSql, $conn){
	$searchRes = $conn -> query($ifNotSql);
	if (!$searchRes->num_rows > 0) {
		#print_r($insertSql);
		$success = $conn -> query($insertSql);
		if (!$success) {
			#print_r($insertSql);
			print_r($conn->error);
		  #$msg = "insertIfNot Query failed for insert:" .$insertSql;
		  #console_log( $msg );
		}
		/*if (!$conn -> commit()) {
			$msg = "Commit failed";
			console_log( $msg );
		  exit();
		}*/
	}
}


#$heute = getdate();
#$year = 'year';
#$heute[$year];


$mysqli = new mysqli("127.0.0.1", "root", "", "webcrawler");
#mysqli_autocommit($mysqli,FALSE);
$mysqli->query('SET AUTOCOMMIT = 1');

#insert test
#$mysqli->query("INSERT INTO words (word) VALUES ('rhgdfy')");
#$insertSql = "INSERT INTO words (word) VALUES ('hallo?')";
#$ifNotSql = "SELECT word from words where word='hallo?'";
#insertIfNot($ifNotSql, $insertSql, $mysqli);

if(isset($mysqli))
{
	$sql = "SELECT id, url from url";
	$linkSearchRes = $mysqli->query($sql);
			
	if ($linkSearchRes->num_rows > 0) {
		while($lRow = $linkSearchRes->fetch_assoc()) {
			console_log($lRow["url"]);
			$crawl = new Crawler($lRow["url"]);
			$words = $crawl->get('words');
			$links = $crawl->get('links');
				
			#write links in db with time set 0000-00-00
			foreach($links as $l) {
				if (substr($l,0,7)!='http://' && substr($l,0,8)!='https://')
				{
					$l = $crawl->base . "/" . $l;
				}
				#insert link
				if(strcmp($l, "http://www.dhbw-heidenheim.de/javascript:linkTo_UnCryptMailto('nbjmup+tuvejfocfsbuvohAeicx.ifjefoifjn\/ef');"))
				{
					$insertSql = "INSERT INTO url(url) VALUES ('" .$l. "')";
					$ifNotSql = "SELECT url from url where url='" .$l. "'";
					insertIfNot($ifNotSql, $insertSql, $mysqli);
				}
				
			}
			
			foreach($words as $w) {
				#write words in db
				$insertSql = "INSERT INTO word(word) VALUES ('" .$w. "')";
				$ifNotSql = "SELECT word from word where word='" .$w. "'";
				insertIfNot($ifNotSql, $insertSql, $mysqli);
				
				#fill link table
				$sql = "SELECT id from word where word='" .$w. "'";
				$wordSearchRes = $mysqli->query($sql);
				if ($wordSearchRes->num_rows > 0) {
					while($wRow = $wordSearchRes->fetch_assoc()) {
						$sql = "INSERT INTO link(url_id, word_id) VALUES ('". $lRow["id"]. "','" .$wRow["id"]. "')";
						$mysqli->query($sql);
						/*if (!$mysqli -> commit()) {
						  echo "Commit transaction failed";
						  exit();
						}*/
					}
				}
			}
			
			#set link on crawled
			/*$sql = "UPDATE url SET time_stamp = '1111-11-11' where id = '" .$lRow["id"]. "'";
			$mysqli->query($sql);
			if (!$mysqli -> commit()) {
			  echo "Commit transaction failed";
			  exit();
			}*/
		}
	}
}
/*
$crawl = new Crawler('http://www.dhbw-heidenheim.de');
$words = $crawl->get('words');
$links = $crawl->get('links');
*/
?>
<html>
<body>
<h2>Webcrawler</h2>
<?php
/*foreach($links as $l) {
 if (substr($l,0,7)!='http://' && substr($l,0,8)!='https://')
echo "<br>Link: $crawl->base/$l";
	else
		echo "<br>Link: $l";
}
foreach($words as $w) {
echo "<br>Word: $w";
}*/
?>
</body>
</html>
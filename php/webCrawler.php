<?php
/*#########################*/
/*####### Functions #######*/
/*#########################*/

function console_log( $data ){
	
	echo '<script>';
	echo 'console.log('. json_encode( $data ) .')';
	echo '</script>';
}

function shutdownFunction() { 

    $error = error_get_last();
	
    if ( isset( $error ) && $error[ 'type' ] === E_ERROR ) { 
	
		echo "<p>Error: ".$error['message']."</p>";
		echo "<p>You might want to set the maxium execution time on a higher value!</p>";
		echo "<p>If you use xampp the execution time is increased in xampp\php\php.ini, variable max_execution_time.</p>";
    } 
}
/*###########################*/
/*#### WEB-CRAWLER CLASS ####*/
/*###########################*/

class Crawler {
	
	protected $markup = '';
	
	protected $id;
	
	protected $baseUrl;

	public function __construct( $id, $url ) {
		
		$this->id = $id;
		$this->baseUrl = $url;
		$this->markup = $this->getMarkup($url);
	}
	
	public function getMarkup() {
		
		$content = @file_get_contents( $this->baseUrl );
		
		if($content === FALSE) {
			return "invalid url";
		}
		
		return $content;
	}

	public function get( $type) {
		
		$method = "_get_{$type}";
		
		if( method_exists( $this, $method ) ){
			
			return call_user_func( array( $this, $method ) );
		}
	}

	protected function _get_images() {
		
		if ( !empty($this->markup) ){
			
			preg_match_all( '/<img([^>]+)\/>/i', $this->markup, $images );
			
			return !empty($images[1]) ? $images[1] : FALSE;
		}
	}

	protected function _get_links() {
		
		if (!empty($this->markup)){
			
			preg_match_all( '/href=\"([^;\s]*?)\"/i', $this->markup, $allLinks );
			preg_match_all( '/href=\"([^;\s]*?(\.css(\?){0,1}){1}[^;\s]*?)\"/i', $this->markup, $cssLinks );
			
			if( !empty( $allLinks[1] ) && !empty( $cssLinks[1] ) ){
				
				$links = array_diff( $allLinks[1], $cssLinks[1] );
			}

			return !empty( $links ) ? $links : FALSE;
		}
	}

	protected function _get_words(){
		
		if (!empty($this->markup)){
			
			$temp = [];
			$words = explode(" ", preg_replace( '(<script.*<\/script>|<[^>]*?>|<!--|-->|\s|[\)\("„“|,:/+?]|&#[0-9]*;|_[-]_)', ' ', $this->markup ) );
			
			foreach( $words as $word ){
				if( isset( $word ) && !empty( $word ) ){
					
					if( preg_match( '/.*[.]/', $word ) ){
						
						$abbreviations_de = array( 'allg.', 'bzw.', 'bspw.', 'd.h.','etc.', 'evtl.', 'geb.', 'ggf.', 'n. Chr.', 's.o.', 's.u.', 'usw.', 'v. Chr.', 'vgl.', 'z.B.' );
						if ( !in_array( $word, $abbreviations_de ) ) {
							$word = rtrim( $word, '.' );
						}
					}
					
					$word = html_entity_decode( $word );
					
					array_push( $temp, $word );
				}
			}
			
			$words = $temp;

			if( !empty( $words ) ){
				
				$uniqueWords = array_count_values( $words );
			}
			
			return !empty( $uniqueWords ) ? $uniqueWords : FALSE;
		}
	}

	protected function _get_markup(){
		
		return $this->markup;
	}
}

function updateCrawledURL( $id, $dbConn ){
		
	//update the timestamp of 
	$sql = 'update url set timestamp = NOW() where id = ' .$id;
	$result = $dbConn->query($sql);
			
	if ( !$dbConn -> commit() ) {
		$msg = 'Failed updating timestamp of url with id: ' .$id;
		console_log( $msg );
			
		exit();
	}
}

function crawl ( $id, $url, $dbConn ){

	$crawler = new Crawler( $id, $url );

	if( isset( $crawler ) && strcmp( $crawler->get( 'markup' ), "invalid url" ) !== 0  ){	

		//get all words of url website
		$words = $crawler->get( 'words' );
			
		//get all links of url website
		$links = $crawler->get( 'links' );

		//update url
		updateCrawledURL( $id, $dbConn );

		if( isset( $words ) ){
				
			//loop over words
			foreach ( $words as $word => $count ){
				
				//escape word &uuml; -> ü, &ouml; -> ö, ...
				$word = $dbConn->real_escape_string( $word );
				
				$sql = 'select id from word where word = "' .$word. '"';
				$result = $dbConn->query( $sql );
					
				$wordId;
					
				//check if word is already in database
				if ( isset( $result ) && !empty( $result ) && ($result->num_rows > 0) ) {
						
					//if in database get wordId
					$row = $result->fetch_array( MYSQLI_ASSOC );
					$wordId = $row[ 'id' ];
				}
				else{
						
					//else insert word
					$sql = 'insert into word(word) values("' .$word. '")';
					$dbConn->query( $sql );
					
					//get the id of the current insert word
					$wordId = $dbConn->insert_id;
				}	
						
				//insert url-word link
				$sql = "insert into link(url_id, word_id, number_of_words_in_url) values (" . $id. "," .$wordId. "," .$count. ")";
				$dbConn->query($sql);
						
			}
				
			if ( !$dbConn -> commit() ) {
					
				$msg = "Failed insert words";
				console_log( $msg );
					
				exit();
			}		
		}

		if($links !== FALSE && isset( $links ) ){
				
			//loop over links
			foreach( $links as $link ) {			
					
				//handle links which looks like /folder/folder/page.html or .../folder/folder/page.html
				if ( ( substr( $link, 0, 7 ) != 'http://' ) && ( substr( $link , 0, 8 ) != 'https://' ) ){
					
						$host_url = parse_url($url, PHP_URL_HOST);
						
						if ( ( substr( $host_url, 0, 7 ) != 'http://' ) && ( substr( $host_url , 0, 8 ) != 'https://' ) ){
							$host_url = "http://" .$host_url;
						}
						
						$link = $host_url. "/" .$link;
				}
					
				if( $link != $url ){
								
					//insert link url
					$sql = 'insert into url(url, timestamp) values ("' .$link. '", "0000-00-00")';
					$dbConn->query( $sql );
						
					//get the id of the current insert url
					$id = $dbConn->insert_id;

					//if webCrawler should be recursive comment in and set autocommit = TRUE
					#crawl( $id, $link, $url);
				}
			}
			if ( !$dbConn -> commit() ) {
					
				$msg = "Failed insert link";
				console_log( $msg );
				exit();
			}
		}

	}
	else {
		
		//delete unvalid url
		if( isset( $url ) ){
				
			$sql = 'delete from url where id =' .$id;
			$dbConn->query( $sql );
				
			if ( !$dbConn -> commit() ) {
					
				$msg = "Failed delete url";
				console_log( $msg );
					
				exit();
			}
		}
	}
}

/*###############################*/
/*#### START OF THE PROGRAMM ####*/
/*###############################*/

//open new database connection
$dbConn = new mysqli("127.0.0.1", "root", "", "webcrawler");

//set autocommit to false
mysqli_autocommit( $dbConn, FALSE );

//register shutdown function
register_shutdown_function('shutdownFunction');

try{
	if( isset( $dbConn ) )
	{
		#while(true){ //if crawler should be running all the time comment in while loop
			
			//get all url's were timestamp = 0000-00-00 (initial value when insert via gui) or which aren't crawled since the last ten minutes
			$sql = 'select id, url from url where timestamp = 0000-00-00 or TIMESTAMPDIFF(MINUTE, timestamp, NOW()) >= 10';
			$result = $dbConn->query( $sql );
				
			if ( isset( $result ) && ($result->num_rows > 0) ) {

				while( $row = $result->fetch_assoc() ) {
					
					$id = $row['id'];
					$url = $row['url'];
					
					//call crawl function
					crawl( $id, $url, $dbConn );
				}
			}
		#} //if crawler should be running all the time comment in while loop
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
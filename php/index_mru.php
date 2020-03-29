<!DOCTYPE html>
<html>
	<head>
		<title>Startseite</title>
	</head>
<?php	

	function insertURL()
	{
		#insert new url
		$url = $_POST["url"];
		
		if ( ( substr( $url, 0, 7 ) != 'http://' ) && ( substr( $url , 0, 8 ) != 'https://' ) ){
			$url = 'http://' .$url;
		}
		
		$dbConn = new mysqli("127.0.0.1", "root", "", "webcrawler");
		
		if(isset($dbConn))
		{
			$sql = 'insert into url(url, timestamp) VALUES ("' .$url. '", 0000-00-00 )';
			$dbConn->query($sql);
		}
		else
		{
			echo "<p>SQL-Error, the url couldn't be insert into the database.</p>";
		}
	}
	
	if(isset($_POST['url']))
	{
	   insertURL();
	}
	
	function getSearchResult()
	{
		$dbConn = new mysqli("127.0.0.1", "root", "", "webcrawler");
		if(isset($dbConn))
		{
			#URLs ausgeben mit suchwort sortiert nach anzahl
			$sql  = "SELECT u.url, u.id, l.word_id as idWord ";
			$sql .= "FROM url u ";
			$sql .= "INNER JOIN ";
			$sql .= "(SELECT url_id, word_id, count(*) as count ";
			$sql .= "FROM link " ;
			$sql .= "WHERE word_id = (SELECT id FROM word WHERE word = '".$_POST["searchInput"]."') ";
			$sql .= "GROUP BY url_id, word_id) l ";
			$sql .= "ON u.id = l.url_id ";
			$sql .= "ORDER BY l.count DESC";
			
			$searchRes = $dbConn->query($sql);
			
			return $searchRes;
		}
		else
		{
			echo "<p>SQL-Error, the search result couldn't be fetched.</p>";
		}
	}
?>
		<form action="index.php" method="POST">
			<h3>Insert URL into database</h3>
			<label for="url">Please enter a URL:</label>
			<input type="text" id="url" name="url"><br><br>
			<text> (http://www.xyz.de or only www.xyz.de)<br>
			<br>
			<br>
			<input type="submit" value="Submit" name="submit">
		</form>
		<br>
		<br>
		<hr>
		<br>
		<div align="center">
			<form action="index.php" method="post">
			  <h3>Word search</h3>
			  <input type="text" id="searchInput" name="searchInput"/><br><br>
			  <input type="submit" value="Search"/>
			</form>
		</div>
		<br>
		<hr>
		<br>
		<p style="font-weight: bold; font-size: 30px">Result</p>

<?php

if(isset( $_POST[ 'searchInput' ]) )
{
	$result = getSearchResult();
	
	if ( $result->num_rows > 0 ) {
		
		// search word (id from sql)
		$firstRow = $result->fetch_row();
		$result->data_seek(0);
		
		echo"<li>".$_POST['searchInput']." has id: " .$firstRow[2]."</li>";
		echo"<ol>";
		
		// output data of each row
		while( $row = $result->fetch_assoc() ) {
			
			echo '<li>wordlink found. The id_link is ' .$row["id"]. '<br>';
			echo 'Link: <a href="'.$row['url']. '">' .$row['url']. '</a></li>';
		}
		
		echo"</ol>";
		
	} else {
		
		echo "<p>0 results</p>";
	}
}
?>

	</body>
</html>
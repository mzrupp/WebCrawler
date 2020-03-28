<!DOCTYPE html>
<html>
<head>
<title>DHBW Search - Admin</title>
</head>
<?php	
	function addUrlDb()
	{
		#Einfügen einer neuen URL
		$sql = "INSERT INTO url(url, timestamp) VALUES ('" .$_POST["addUrl"]. "','0000-00-00')";
		$mysqli = new mysqli("127.0.0.1", "root", "", "webcrawler");
		if(isset($mysqli))
		{
			$mysqli->query($sql);
		}
		else
		{
			echo "<p>SQL-FEHLER</p>";
		}
	}
	
	if(isset($_POST['urlSubmit']))
	{
	   addUrlDb();
	}
	
	function getSearchResult()
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
		#echo "<p>" .$sql. "</p>";
		
		$mysqli = new mysqli("127.0.0.1", "root", "", "webcrawler");
		if(isset($mysqli))
		{
			$searchRes = $mysqli->query($sql);
			return $searchRes;
		}
		else
		{
			echo "<p>SQL-FEHLER</p>";
		}
	}
?>
<form action="index.php" method="post">
  <label for="addUrl">DHBW Add URL to Database:</label>
  <input type="text" id="addUrl" name="addUrl"><br><br>
  <text> (http://www.xyz.de or only www.xyz.de)<br>
  <input type="submit" value="Daten absenden" name="urlSubmit">
</form>
<hr>
<div align="center">
	<form action="index.php" method="post">
	  <label for="searchInput">DHBW Search</label><br><br>
	  <input type="text" id="searchInput" name="searchInput"/><br><br>
	  <input type="submit" value="Daten absenden"/>
	</form>
</div>
<hr>
<p style="font-weight: bold; font-size: 30px">Result</p>

<?php
if(isset($_POST['searchInput']))
{
	$result = getSearchResult();
	if ($result->num_rows > 0) {
		// suchwort angeben (ID aus dem sql ziehen)
		$firstRow = $result->fetch_row();
		$result->data_seek(0);
		echo"<li>".$_POST["searchInput"]." has id: " .$firstRow[2]."</li>";
		echo"<ol>";
		// output data of each row
		while($row = $result->fetch_assoc()) {
			echo"<li>wordlink found. The id_link is " .$row["id"]. "<br>";
			echo"Link: <a href=&quot;" .$row["url"]. "&quot;>" .$row["url"]. "</a></li>";
		}
		echo"</ol>";
	} else {
		echo "<p>0 results</p>";
	}
}
?>

</body>
</html>
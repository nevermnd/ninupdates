<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/titlesetdesc";

dbconnection_start();

db_checkmaintenance(1);

$titleid = "";
$desc = "";
if(isset($_REQUEST['titleid']))$titleid = mysqli_real_escape_string($mysqldb, $_REQUEST['titleid']);
if(isset($_REQUEST['desc']))$desc = mysqli_real_escape_string($mysqldb, $_REQUEST['desc']);

$query = "SELECT id, description FROM ninupdates_titleids WHERE titleid='" . $titleid . "'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);
		
if($numrows==0)
{
	dbconnection_end();

	header("Location: reports.php");
	writeNormalLog("ROW FOR TITLEID NOT FOUND. RESULT: 302");

	return;
}

$row = mysqli_fetch_row($result);
$rowid = $row[0];
$curdesc = $row[1];

if($curdesc == NULL)
{
	$curdesc = "";
}
else
{
	$curdesc = "  Current description: $curdesc</br></br>";
}

if(!isset($_REQUEST['desc']))
{
	$con = "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update Set Title Description</title></head><body>
<form method=\"post\" action=\"title_setdesc.php?titleid=$titleid\" enctype=\"multipart/form-data\">
$curdesc
  Description: <input type=\"text\" value=\"\" name=\"desc\"/><input type=\"submit\" value=\"Submit\"/></form></body></html>";

	dbconnection_end();
	if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");
	echo $con;

	return;
}
else
{
	echo "Description changing is disabled due to abuse.\n";
	writeNormalLog("ATTEMPTED TO CHANGE TITLEDESC, DENIED: $desc. RESULT: 302");
	return;

	$desc = strip_tags($desc);

	while(1)
	{
		$pos = strpos($desc, ",");
		if($pos === FALSE)break;
		$desc[$pos] = " ";
	}

	$query = "UPDATE ninupdates_titleids SET description='".$desc."' WHERE id=$rowid";
	$result=mysqli_query($mysqldb, $query);
	dbconnection_end();

	header("Location: reports.php");
	writeNormalLog("CHANGED DESC TO $desc. RESULT: 302");

	return;
}

?>

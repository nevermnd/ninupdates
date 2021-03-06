<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/titlelistphp";

dbconnection_start();

db_checkmaintenance(1);

$reportdate = "";
$system = "";
$region = "";
$filter_tid = "";
$usesoap = "";
$genwiki = "";
$gencsv = "";
$soapreply = "";
$gentext = "";
if(isset($_REQUEST['date']))$reportdate = mysqli_real_escape_string($mysqldb, $_REQUEST['date']);
if(isset($_REQUEST['sys']))$system = mysqli_real_escape_string($mysqldb, $_REQUEST['sys']);
if(isset($_REQUEST['reg']))$region = mysqli_real_escape_string($mysqldb, $_REQUEST['reg']);
if(isset($_REQUEST['tid']))$filter_tid = mysqli_real_escape_string($mysqldb, $_REQUEST['tid']);
if(isset($_REQUEST['soap']))$usesoap = mysqli_real_escape_string($mysqldb, $_REQUEST['soap']);
if(isset($_REQUEST['wiki']))$genwiki = mysqli_real_escape_string($mysqldb, $_REQUEST['wiki']);
if(isset($_REQUEST['csv']))$gencsv = mysqli_real_escape_string($mysqldb, $_REQUEST['csv']);
if(isset($_REQUEST['soapreply']))$soapreply = mysqli_real_escape_string($mysqldb, $_REQUEST['soapreply']);
if(isset($_REQUEST['gentext']))$gentext = mysqli_real_escape_string($mysqldb, $_REQUEST['gentext']);

if($usesoap!="" && $gentext!="")$gentext = "";

if($system=="")
{
	dbconnection_end();
	writeNormalLog("SYSTEM NOT SPECIFIED. RESULT: 200");
	echo "System not specified.\n";
	return;
}

$sys = getsystem_sysname($system);

$con = "";

if($genwiki=="" && $gencsv=="")
{
	$con .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\" dir=\"ltr\">\n";
}

$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
$result=mysqli_query($mysqldb, $query);
$row = mysqli_fetch_row($result);
$systemid = $row[0];

if($region!="")
{
	$query="SELECT id FROM ninupdates_regions WHERE regioncode='".$region."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		dbconnection_end();
		writeNormalLog("REGION ROW NOT FOUND. RESULT: 200");
		echo "Invalid region.\n";
		return;
	}
}

$reportid = 0;
$curdate = "";

$text = "";
$reportname = "";

if($reportdate!="")
{
	$query="SELECT id, updateversion, curdate FROM ninupdates_reports WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_reports.systemid=$systemid && ninupdates_reports.log='report'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		dbconnection_end();
		writeNormalLog("REPORT ROW NOT FOUND. RESULT: 200");
		echo "Invalid reportdate.\n";
		return;
	}
	else
	{
		$row = mysqli_fetch_row($result);
		if($row[1]=="N/A")
		{
			$text = "$sys $reportdate";
		}
		else
		{
			$text = "$sys ".$row[1];
		}
		$reportid = $row[0];
		$curdate = $row[2];
	}
	$reportname = $text;
	if($region!="")$text.= " $region";
}
else
{
	$text = $sys;
}

if($soapreply=="1" && $reportdate!="" && $region!="")
{
	dbconnection_end();

	$path = "$sitecfg_workdir/soap$system/$region/$reportdate.html.soap";
	$con = file_get_contents($path);

	if($con!==FALSE)
	{
		header("Content-Type: application/xml");
		echo $con;
	}
	else
	{
		writeNormalLog("FAILED TO OPEN SOAPREPLY FILE: $path\n");
		echo "The SOAP reply data for this is not available.\n";
	}

	return;
	
}

if($genwiki!="")
{
	header("Content-Type: text/plain");

		$con.= "=== $text ===\n";
		$con.= "{| class=\"wikitable\" border=\"1\"\n";
		$con.= "|-
!  TitleID
!  Region
!  Versions\n";
}
else if($gencsv!="")
{
	header("Content-Type: text/plain");
	$con.= "TitleID,Region,Title versions,Update versions\n";
}
else
{
	$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update Titlelist $text</title></head>\n<body>";

	$con.= "$sitecfg_sitenav_header<a href=\"reports.php\">Homepage</a> -> ";
	if($reportdate!="")$con.= "<a href=\"reports.php?date=$reportdate&sys=$system\">$reportname report</a> -> ";
	if($reportdate=="")$con.= "$text ";
	if($region!="")$con.= "Region $region Titlelist";
	if($region=="")$con.= "Titlelist";
	if($usesoap!="")$con.= " SOAP";
	$con.= "<hr><br/><br/>\n";

	if($gentext=="")
	{
		$con.= "<table border=\"1\">
<tr>
  <th>TitleID</th>
  <th>Region</th>
  <th>Title description</th>
  <th>Title versions</th>
  <th>Update versions</th>\n";

		if($reportdate!="" && $usesoap=="")$con.="  <th>Title status</th>\n";

		$con.="</tr>\n";
	}
}

$titlelist_array = array();
$titlelist_array_numentries = 0;

$numrows = 0;

$reportquery = "";
$soapquery = "";
if($reportdate!="")
{
	if($usesoap=="")$reportquery = " && ninupdates_titles.reportid=$reportid && ninupdates_reports.id=$reportid";
	if($usesoap!="")$soapquery = " && ninupdates_titles.curdate<='".$curdate."' && ninupdates_reports.curdate<='".$curdate."'";
}

$regionquery = "";
if($region!="")$regionquery = " && ninupdates_titles.region='".$region."'";

$versionquery = "GROUP_CONCAT(DISTINCT ninupdates_titles.version ORDER BY ninupdates_titles.version SEPARATOR ','),";

$reportdatequery = "GROUP_CONCAT(DISTINCT ninupdates_reports.reportdate ORDER BY ninupdates_reports.curdate SEPARATOR ','),";
$updateverquery = "GROUP_CONCAT(DISTINCT ninupdates_reports.updateversion ORDER BY ninupdates_reports.curdate SEPARATOR ','),";

$filter_tid_query = "";
if($filter_tid!="")$filter_tid_query = " && ninupdates_titleids.titleid='".$filter_tid."'";

$query = "SELECT ninupdates_titleids.titleid, ninupdates_titleids.description, $versionquery ninupdates_titles.region, ninupdates_regions.regionid, $reportdatequery $updateverquery GROUP_CONCAT(fssize ORDER BY ninupdates_titles.version SEPARATOR ','), GROUP_CONCAT(tmdsize ORDER BY ninupdates_titles.version SEPARATOR ','), GROUP_CONCAT(tiksize ORDER BY ninupdates_titles.version SEPARATOR ','), ninupdates_titles.region FROM ninupdates_titles, ninupdates_titleids, ninupdates_reports, ninupdates_regions WHERE ninupdates_titles.systemid=$systemid && ninupdates_reports.systemid=$systemid && ninupdates_titles.tid=ninupdates_titleids.id && ninupdates_reports.id=ninupdates_titles.reportid && ninupdates_regions.regioncode=ninupdates_titles.region$filter_tid_query";
if($reportquery!="")$query.= $reportquery;
if($soapquery!="")$query.= $soapquery;
if($regionquery!="")$query.= $regionquery;

$query.= " GROUP BY ninupdates_titles.tid, ninupdates_titles.region, ninupdates_regions.regionid";

$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);
$titlelist_array_numentries = $numrows;
$titlelist_array_updatedtitles = 0;
$titlelist_array_newtitles = 0;
$regioncode = "";

$updatesize = 0;
for($i=0; $i<$numrows; $i++)
{
	$row = mysqli_fetch_row($result);
	$titleid = $row[0];
	$desctext = $row[1];
	$versions = $row[2];
	$reg = $row[3];
	$regionid = $row[4];
	$reportdates = $row[5];
	$updateversions = $row[6];
	$updatesize += $row[7] + $row[8] + $row[9];
	$regioncode = $row[10];

	$versiontext = $versions;
	$updatevers = $updateversions;
	if($reportdates!=$reportdate)
	{
		$versiontext = "";
		$updatevers = "";
		$total_entries = 0;
		$version_array = array();
		$reportdate_array = array();
		$updateversion_array = array();

		$ver = strtok($versions, ",");
		while($ver!==FALSE)
		{
			$version_array[] = "v$ver";
			$total_entries++;
			$ver = strtok(",");
		}

		$cur_reportdate = strtok($reportdates, ",");
		while($cur_reportdate!==FALSE)
		{
			$reportdate_array[] = $cur_reportdate;
			$cur_reportdate = strtok(",");
		}

		$updatever = strtok($updateversions, ",");
		while($updatever!==FALSE)
		{
			$updateversion_array[] = $updatever;
			$updatever = strtok(",");
		}

		for($enti=0; $enti<$total_entries; $enti++)
		{
			$url = "titlelist.php?date=".$reportdate_array[$enti]."&amp;sys=$system";
			if($region!="")$url.= "&amp;reg=$reg";

			if($gencsv=="")
			{
				if($enti>0)$versiontext .= ", ";
				if($enti>0)$updatevers .= ", ";
			}
			else
			{
				if($enti>0)$versiontext .= " ";
				if($enti>0)$updatevers .= " ";
			}
			$updatevers.= $updateversion_array[$enti];

			if($genwiki!="")
			{
				$versiontext .= "[[".$updateversion_array[$enti]."|".$version_array[$enti]."]]";
			}
			else if($gencsv!="")
			{
				$versiontext .= $version_array[$enti];
			}
			else
			{
				$versiontext .= "<a href =\"$url\">".$version_array[$enti]."</a>";
			}
		}
	}
	else
	{
		$versiontext = "v$versions";
	}

	$regtext = $regionid;
	if($reg!=$region && $genwiki=="" && $gencsv=="")
	{
		$url = "titlelist.php?";
		if($reportdate!="")$url.= "date=$reportdate&amp;";
		$url.= "sys=$system&amp;reg=$reg";
		if($usesoap!="")$url.= "&amp;soap=1";
		if($filter_tid!="")$url.= "&amp;tid=$titleid";
		$regtext = "<a href=\"$url\">$regionid</a>";
	}

	$titleid_text = $titleid;
	if($filter_tid=="" && $genwiki=="" && $gencsv=="")
	{
		$url = "titlelist.php?";
		if($reportdate!="")$url.= "date=$reportdate&amp;";
		$url.= "sys=$system";
		if($region!="")$url.= "&amp;reg=$reg";
		if($usesoap!="")$url.= "&amp;soap=1";
		$url.= "&amp;tid=$titleid";
		$titleid_text = "<a href=\"$url\">$titleid</a>";
	}

	if($desctext == NULL || $desctext == "")$desctext = "<a href=\"title_setdesc.php?titleid=$titleid\">N/A</a>";

	$titlelist_array[] = array();
	$titlelist_array[$i][0] = $titleid;
	if($genwiki!="" || $gencsv!="")
	{
		$titlelist_array[$i][1] = $regionid;
	}
	else
	{
		$titlelist_array[$i][1] = $regtext;
	}

	$titlelist_array[$i][2] = $desctext;
	$titlelist_array[$i][3] = $versiontext;
	$titlelist_array[$i][4] = $updatevers;
	$titlelist_array[$i][5] = $versions;
	$titlelist_array[$i][6] = $regioncode;
	$titlelist_array[$i][8] = $titleid_text;
}

$count = 0;
for($i=0; $i<$titlelist_array_numentries; $i++)
{
	$titleid = $titlelist_array[$i][0];
	$regtext = $titlelist_array[$i][1];
	$desctext = $titlelist_array[$i][2];
	$versiontext = $titlelist_array[$i][3];
	$updatevers = $titlelist_array[$i][4];
	$versions = $titlelist_array[$i][5];
	$regioncode = $titlelist_array[$i][6];
	$titleid_text = $titlelist_array[$i][8];

	$titlestatus = "";
	if($reportdate!="" && $usesoap=="")
	{
		$titlestatus = "N/A";

		$query = "SELECT MIN(ninupdates_titles.version) FROM ninupdates_titles, ninupdates_titleids WHERE ninupdates_titles.systemid=$systemid && ninupdates_titles.tid=ninupdates_titleids.id && ninupdates_titleids.titleid='$titleid' && ninupdates_titles.region='$regioncode'";
		$result=mysqli_query($mysqldb, $query);
		if(mysqli_num_rows($result)>0)
		{
			$row = mysqli_fetch_row($result);
			$titlestatus = "Changed";
			if($versions==$row[0])
			{
				$titlestatus = "New";
				$titlelist_array_newtitles++;
			}
			else
			{
				$titlelist_array_updatedtitles++;
			}
		}
	}

	$titlelist_array[$i][7] = $titlestatus;

	if($genwiki!="")
	{
		$con.= "|-
| $titleid
| $regtext
| $versiontext\n";
	}
	else if($gencsv!="")
	{
		$con.= "$titleid,$regtext,$versiontext,$updatevers\n";
	}
	else if($gentext=="")
	{
		$con.= "<tr>\n";
		$con.= "<td>$titleid_text</td>\n";
		$con.= "<td>$regtext</td>\n";
		$con.= "<td>$desctext</td>\n";
		$con.= "<td>$versiontext</td>\n";
		$con.= "<td>$updatevers</td>\n";
		if($reportdate!="" && $usesoap=="")$con.= "<td>$titlestatus</td>\n";
		$con.= "</tr>\n";
	}
}

if($gentext!="" && $titlelist_array_updatedtitles>0)
{
	$con.= "<br />The following titles were updated: ";

	$count = 0;
	for($i=0; $i<$titlelist_array_numentries; $i++)
	{
		$titleid = $titlelist_array[$i][0];
		$regtext = $titlelist_array[$i][1];
		$desctext = $titlelist_array[$i][2];
		$versiontext = $titlelist_array[$i][3];
		$updatevers = $titlelist_array[$i][4];
		$versions = $titlelist_array[$i][5];
		$regioncode = $titlelist_array[$i][6];
		$titlestatus = $titlelist_array[$i][7];

		if($titlestatus!="Changed")continue;

		if($count==$titlelist_array_updatedtitles-1 && $titlelist_array_updatedtitles>1)$con.= " and ";
		if(strstr($desctext, "N/A")!==FALSE)
		{
			$con.= "$titleid";
		}
		else
		{
			$con.= "$desctext";
		}
		if($region=="")$con.="($regtext)";
		if($count<$titlelist_array_updatedtitles-1 && $titlelist_array_updatedtitles>1)
		{
			$con.= ", ";
		}
		else
		{
			$con.= ".<br />\n";
		}

		$count++;
	}
}

if($gentext!="" && $titlelist_array_newtitles>0)
{
	$con.= "<br />The following new titles were added: ";

	$count = 0;
	for($i=0; $i<$titlelist_array_numentries; $i++)
	{
		$titleid = $titlelist_array[$i][0];
		$regtext = $titlelist_array[$i][1];
		$desctext = $titlelist_array[$i][2];
		$versiontext = $titlelist_array[$i][3];
		$updatevers = $titlelist_array[$i][4];
		$versions = $titlelist_array[$i][5];
		$regioncode = $titlelist_array[$i][6];
		$titlestatus = $titlelist_array[$i][7];

		if($titlestatus=="Changed")continue;

		if($count==$titlelist_array_newtitles-1 && $titlelist_array_newtitles>1)$con.= " and ";
		if(strstr($desctext, "N/A")!==FALSE)
		{
			$con.= "$titleid";
		}
		else
		{
			$con.= "$desctext";
		}
		if($region=="")$con.="($regtext)";
		if($count<$titlelist_array_newtitles-1 && $titlelist_array_newtitles>1)
		{
			$con.= ", ";
		}
		else
		{
			$con.= ".<br />\n";
		}

		$count++;
	}
}

if($genwiki=="")
{
	if($gencsv=="" && $gentext=="")$con.= "</table>";
}
else
{
	$con.= "|}\n";
}

if($genwiki=="" && $gencsv=="" && $reportdate!="" && $soapquery=="")
{
	$con.= "<br />\n";
	$con.= "Total titles sizes: $updatesize<br />\n";

	if($region!="")
	{
		$hashval = "";

		$query="SELECT ninupdates_systitlehashes.titlehash FROM ninupdates_reports, ninupdates_consoles, ninupdates_systitlehashes WHERE ninupdates_systitlehashes.reportid=ninupdates_reports.id && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."' && ninupdates_systitlehashes.region='".$region."' && ninupdates_reports.log='report' && ninupdates_reports.reportdate='".$reportdate."'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows>0)
		{
			$row = mysqli_fetch_row($result);
			$hashval = $row[0];
		}

		if($hashval===FALSE || $hashval=="")$hashval = "N/A";

		$con.= "<br/>SOAP TitleHash: $hashval<br/>\n";
	}

	if($reportdate!="" && $usesoap=="")
	{
		$con.= "<br/>\nTitle info: <br/>\n<br/>\n";
		$titleinfo_count = 0;

		$titledata_base = "$sitecfg_workdir/sysupdatedl/autodl_sysupdates/$reportdate-$system/";

		if(file_exists($titledata_base)!==FALSE)
		{

			$diriter = new RecursiveDirectoryIterator($titledata_base);
			$iter = new RecursiveIteratorIterator($diriter);
			$regex_iter = new RegexIterator($iter, '/^.+\.*info$/i', RecursiveRegexIterator::GET_MATCH);

			foreach($regex_iter as $path => $pathobj)
			{
				$url = substr($path, strlen($sitecfg_workdir)+1);
				$con.= "<a href=\"$url\">$url</a><br/>\n";

				$titleinfo_count++;
			}
		}

		if($titleinfo_count==0)$con.="N/A<br/>\n";
	}
}

if($genwiki=="" && $gencsv=="")$con .= "</body></html>";

dbconnection_end();

if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");

echo $con;

?>

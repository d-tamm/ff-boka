<?php
// To be called once every 60 minutes via cron or webcron
require("common.php");
global $db, $cfg;

// Update sections from API once a day
$data = json_decode(file_get_contents($cfg['apiUrl']."/api/feed/PAN_ExtBokning_GetSections"));
$stmt = $db->prepare("INSERT INTO sections SET sectionID=:sectionID, name=:name1, timestamp=NULL ON DUPLICATE KEY UPDATE name=:name2, timestamp=NULL");
foreach ($data->results as $section) {
	if ($section->cint_nummer && $section->cint_name) {
		echo "Add section ".$section->cint_nummer . " " . $section->cint_name."<br>";
		$stmt->execute(array(
			":sectionID" => $section->cint_nummer,
			":name1" => $section->cint_name,
			":name2" => $section->cint_name,
		));
	}
}
// Delete all records not affected by the update
$db->exec("DELETE FROM sections WHERE TIMESTAMPDIFF(SECOND, `timestamp`, NOW())>10");

// Update assignments from API once a day
$data = json_decode(file_get_contents($cfg['apiUrl']."/api/feed/Pan_ExtBokning_GetAllAssignmenttypes"));
$stmt = $db->prepare("INSERT INTO assignments SET ass_name=:name, typeID=:typeID, type_name=:type_name, timestamp=NULL ON DUPLICATE KEY UPDATE timestamp=NULL");
foreach ($data->results as $ass) {
	if ($ass->cint_name && $ass->cint_assignment_party_type->value) {
		echo "Add assignment ".$ass->cint_name." ".$ass->cint_assignment_party_type->name."<br>";
		$stmt->execute(array(
			":name"   => $ass->cint_name,
			":typeID" => $ass->cint_assignment_party_type->value,
			":type_name" => $ass->cint_assignment_party_type->name,
		));
	}
}
// Delete all records not affected by the update
$db->exec("DELETE FROM assignments WHERE TIMESTAMPDIFF(SECOND, timestamp, NOW())>10");

// Delete records from cat_admin_noalert which do not any more belong to a user with admin rights 
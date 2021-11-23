<?php

$courseId = $argv[1];

// Base URLs for Canvas API 
// Courses

$apiCourses = 'https://uk.instructure.com:443/api/v1/courses?';
$paramCourses = 'enrollment_type=teacher&enrollment_role=TeacherEnrollment';

$apiModules = 'https://uk.instructure.com:443/api/v1/courses/' . $courseId . '/modules';
$paramModules = '';

$apiPages = 'https://uk.instructure.com:443/api/v1/courses/' . $courseId . '/pages?per_page=500';
$paramPages = '';

$apiDiscussions = 'https://uk.instructure.com:443/api/v1/courses/' . $courseId . '/discussion_topics';
$paramDiscussions = '';

$apiAssignments = 'https://uk.instructure.com:443/api/v1/courses/' . $courseId . '/assignments?per_page=500';
$paramAssignments = '';

// Function to get stuff from Canvas API
function callCanvas($api, $param) {
    $canvasUrl = $api . $param;
    
    // My Integration Token
    $canvasToken = 'REDACTED';

    // create a new cURL resource
    
    $ch = curl_init();
    
    // set URL and other appropriate options
    curl_setopt($ch, CURLOPT_URL, $canvasUrl);
    //curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept: application/json')); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $canvasToken ));
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,TRUE);

    
    // grab URL and pass it to the browser
    $response = curl_exec($ch);
    
    curl_close($ch);
    
    $canvasResponse = json_decode($response, true);

    return $canvasResponse;
}

//Get the course IDs

$courseList = callCanvas($apiCourses, $paramCourses);

$arrayCount = count($courseList); 

$timeStamp = date('YmdHis');
$reportName = "reports/" . $courseId . '_' . $timeStamp . ".txt";
echo 'trying to create ' . $reportName . "\n";
$txtReport = fopen($reportName, "w") or die("Error creating file\n");

echo '*************************************************************************' . "\n";
echo date('YmdHis') . "\n";
echo 'Course ' . $courseIdn."\n";

fwrite($txtReport, 'Course ' . $courseId ."\n");

// Using the Course ID to review various parts of a course

// MODULES //////////////////////////////////////////////////////////////////////
echo '-Modules:';
fwrite($txtReport, "-Modules\n");

// Get the list of modules

$moduleList = callCanvas($apiModules . '?per_page=500', $paramModules);

$arrayCount = count($moduleList); 
echo '--Number of Modules: ' . $arrayCount . "\n";
fwrite($txtReport, "--Number of Modules: " . $arrayCount . "\n");
for ($c=0; $c < $arrayCount; $c++) {
    // CHECK ITEMS PER MODULE
    $moduleListUrl = 'https://uk.instructure.com/api/v1/courses/' . $courseId . '/modules/' . $moduleList[$c]["id"] . '/items?per_page=500';
    $moduleListParam = '';
    
    $moduleItems = callCanvas($moduleListUrl, $moduleParam);
    $countModuleItems = count($moduleItems);
    echo '--Module ID: ' . $moduleList[$c]["id"] . ' -- Module Name: ' . $moduleList[$c]["name"] . ' -- Module Item Count: ' . $countModuleItems . "\n";
    fwrite($txtReport, "--Module ID: " . $moduleList[$c]["id"] . " -- Module Name: " . $moduleList[$c]["name"] . " -- Module Item Count: " . $countModuleItems . "\n");
    // Go through module items
    for ($d=0; $d < $countModuleItems; $d++) {
        if (strpos($moduleItems[$d]["external_url"], 'saa-primo') > 0) {
            $checkPrimo = 'yes';
            echo '---Module Item ID: ' . $moduleItems[$d]["id"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
            fwrite($txtReport, "---Module Item ID: " . $moduleItems[$d]["id"] . " -- Has IKD link: " . $checkPrimo . "\n");
            echo '----OLD: ' . $moduleItems[$d]["external_url"] . "\n";
            echo '----OLD: ' . $moduleItems[$d]["external_url"] . "\n";
            $moduleUpdate = oneLink($moduleItems[$d]["external_url"]);
            echo '----NEW: ' . $moduleUpdate  . "\n";
            echo '----NEW: ' . $moduleUpdate  . "\n";
            $updateUrl = $apiModules . '/' . $moduleList[$c]["id"] . '/items/' . $moduleItems[$d]["id"];

            $wikiArray = array("module_item"=>array("external_url"=> $moduleUpdate));
            $wikiJson = json_encode($wikiArray);

            putPage($updateUrl, $wikiJson);
            
            
        } else {
            $checkPrimo = 'no';
            echo '---Module Item ID: ' . $moduleItems[$d]["id"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
            fwrite($txtReport, "---Module Item ID: " . $moduleItems[$d]["id"] . " -- Has IKD link: " . $checkPrimo . "\n");
            
        }
        
    }

}
echo "\n"; 
fwrite($txtReport, "\n");

// PAGES //////////////////////////////////////////////////////////////////////
echo '-Pages: ' . "\n";
fwrite($txtReport, "-Pages: " . "\n");

$pagesList = callCanvas($apiPages, $paramPages);

$arrayCount = count($pagesList); 
echo '--Number of Pages: ' . $arrayCount . "\n";
fwrite($txtReport, "--Number of Pages: " . $arrayCount . "\n");

for ($c=0; $c < $arrayCount; $c++) {
    // CHECK INDIVIDUAL PAGE using page_id and course_id
    $pageUrl = 'https://uk.instructure.com:443/api/v1/courses/' . $courseId . '/pages/' . $pagesList[$c]["url"];
    $pageUrlParam = '';
    
    $pageContent = getPage($pageUrl);
    
    if(strpos($pageContent, "saa-primo") > 0) {
        $checkPrimo = 'yes';
        echo '--Page ID: ' . $pagesList[$c]["page_id"] . ' -- Page Name: ' . $pagesList[$c]["title"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
        fwrite($txtReport, "--Page ID: " . $pagesList[$c]["page_id"] . " -- Page Name: " . $pagesList[$c]["title"] . " -- Has IKD link: " . $checkPrimo . "\n");

        $bodyUpdate = processContentArea($pageContent);
        $updateUrl = $pagesList[$c]["url"];
        $wikiArray = array("wiki_page"=>array("url"=>$updateUrl,"body"=> $bodyUpdate));
        $wikiJson = json_encode($wikiArray);
        

        putPage($pageUrl, $wikiJson);
    } else {
        $checkPrimo = 'no';
        echo '--Page ID: ' . $pagesList[$c]["page_id"] . ' -- Page Name: ' . $pagesList[$c]["title"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
        fwrite($txtReport, "--Page ID: " . $pagesList[$c]["page_id"] . " -- Page Name: " . $pagesList[$c]["title"] . " -- Has IKD link: " . $checkPrimo . "\n");

    }
}

// Get next hundred pages
if (count($pagesList) == 100) {
    $pagesList = callCanvas($apiPages . '&page=2', $paramPages);
    
    $arrayCount = count($pagesList); 
    echo '--<strong>More pages:</strong> ' . $arrayCount . "\n";
    fwrite($txtReport, "--Page ID: " . $pagesList[$c]["page_id"] . " -- Page Name: " . $pagesList[$c]["title"] . " -- Has IKD link: " . $checkPrimo . "\n");

    for ($c=0; $c < $arrayCount; $c++) {
        // CHECK INDIVIDUAL PAGE using page_id and course_id
        $pageUrl = 'https://uk.instructure.com:443/api/v1/courses/' . $courseId . '/pages/' . $pagesList[$c]["url"];
        $pageUrlParam = '';
        
        $pageContent = getPage($pageUrl);
        
        if(strpos($pageContent, "saa-primo") > 0) {
            $checkPrimo = 'yes';
            echo '--Page ID: ' . $pagesList[$c]["page_id"] . ' -- Page Name: ' . $pagesList[$c]["title"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
            fwrite($txtReport, "--Page ID: " . $pagesList[$c]["page_id"] . " -- Page Name: " . $pagesList[$c]["title"] . " -- Has IKD link: " . $checkPrimo . "\n");

            $bodyUpdate = processContentArea($pageContent);
            $updateUrl = $pagesList[$c]["url"];
            $wikiArray = array("wiki_page"=>array("url"=>$updateUrl,"body"=> $bodyUpdate));
            $wikiJson = json_encode($wikiArray);
            
            putPage($pageUrl, $wikiJson);
        } else {
            $checkPrimo = 'no';
            echo '--Page ID: ' . $pagesList[$c]["page_id"] . ' -- Page Name: ' . $pagesList[$c]["title"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
            fwrite($txtReport, "--Page ID: " . $pagesList[$c]["page_id"] . " -- Page Name: " . $pagesList[$c]["title"] . " -- Has IKD link: " . $checkPrimo . "\n");
        }
    }
}
// end next hundred
echo "\n";
fwrite($txtReport, "\n");


// DISCUSSION TOPICS //////////////////////////////////////////////////////////
echo '-Discussion Topics: ' . "\n";
fwrite($txtReport, "-Discussion Topics:\n");
$discussionsList = callCanvas($apiDiscussions . '?per_page=500', $paramDiscussions);

$arrayCount = count($discussionsList); 
echo '--Number of Discussion Topics: ' . $arrayCount  . "\n";
fwrite($txtReport, "--Number of Discussion Topics: " . $arrayCount  . "\n");


for ($c=0; $c < $arrayCount; $c++) {
    
    if(substr_count($discussionsList[$c]["message"], 'saa-primo') > 0) {
        $checkPrimo = 'yes';
        echo '--Discussion Topic ID: ' . $discussionsList[$c]["id"] . ' -- Discussion Topic Name: ' . $discussionsList[$c]["title"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
        fwrite($txtReport, "--Discussion Topic ID: " . $discussionsList[$c]["id"] . " -- Discussion Topic Name: " . $discussionsList[$c]["title"] . " -- Has IKD link: " . $checkPrimo . "\n");
       
        $messageUpdate = processContentArea($discussionsList[$c]["message"]);
        $discussionId = $discussionsList[$c]["id"];
        $updateUrl = $apiDiscussions . '/' . $discussionId;

        $wikiArray = array("id"=>$discussionId,"message"=> $messageUpdate);
        $wikiJson = json_encode($wikiArray);
        
        putPage($updateUrl, $wikiJson);
    } else {
        $checkPrimo = 'no';
        echo '--Discussion Topic ID: ' . $discussionsList[$c]["id"] . ' -- Discussion Topic Name: ' . $discussionsList[$c]["title"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
        fwrite($txtReport, "--Discussion Topic ID: " . $discussionsList[$c]["id"] . " -- Discussion Topic Name: " . $discussionsList[$c]["title"] . " -- Has IKD link: " . $checkPrimo . "\n");

    }

}

echo "\n";
fwrite($txtReport, "\n");

// ASSIGNMENTS ////////////////////////////////////////////////////////////////
echo '-Assignments: ' . "\n";
fwrite($txtReport, "-Assignments: " . "\n");

$assignmentList = callCanvas($apiAssignments, $paramAssignments);

$arrayCount = count($assignmentList); 
echo '--Number of Assignments: ' . $arrayCount;
fwrite($txtReport, "--Number of Assignments: " . $arrayCount);


for ($c=0; $c < $arrayCount; $c++) {
    
    if(substr_count($assignmentList[$c]["description"], 'saa-primo') > 0) {
        $checkPrimo = 'yes';
        echo '---Assignment ID: ' . $assignmentList[$c]["id"] . ' -- Assignment Name: ' . $assignmentList[$c]["name"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
        fwrite($txtReport, "---Assignment ID: " . $assignmentList[$c]["id"] . " -- Assignment Name: " . $assignmentList[$c]["name"] . " -- Has IKD link: " . $checkPrimo . "\n");

        $descUpdate = processContentArea($assignmentList[$c]["description"]);
        $assignmentId = $assignmentList[$c]["id"];
        $updateUrl = 'https://uk.instructure.com:443/api/v1/courses/' . $courseId . '/assignments/' . $assignmentId;
        $wikiArray = array("assignment"=>array("id"=>$assignmentId,"description"=> $descUpdate));
        $wikiJson = json_encode($wikiArray);
        
        putPage($updateUrl, $wikiJson);
    } else {
        $checkPrimo = 'no';
        echo '---Assignment ID: ' . $assignmentList[$c]["id"] . ' -- Assignment Name: ' . $assignmentList[$c]["name"] . ' -- Has IKD link: ' . $checkPrimo . "\n";
        fwrite($txtReport, "---Assignment ID: " . $assignmentList[$c]["id"] . " -- Assignment Name: " . $assignmentList[$c]["name"] . " -- Has IKD link: " . $checkPrimo . "\n");

    }
    
}

echo "\n";
fwrite($txtReport, "\n");


// Function to replace PID with MMS for Alma stuff
function pidToMms($primoId) {
        $url = "REDACTED" . $primoId . "&library=XXX";
        $xml = simplexml_load_file($url);
        $json = json_encode($xml);
        $array = json_decode($json,TRUE);

        $arrCount = count($array["OAI-PMH"]["ListRecords"]["record"]["metadata"]["record"]["controlfield"]) - 1;
        $MMS = $array["OAI-PMH"]["ListRecords"]["record"]["metadata"]["record"]["controlfield"][$arrCount];

        return $MMS;
}




// PROCESS BODY TEXT ///////////////////////////////////////////////////////////
function processContentArea($contentHtml) {
    
    $bodyStuff = $contentHtml;
    
    preg_match_all('/[\"]https:\/\/saa-primo\S*[\"]/', $contentHtml, $matches);
    
    for ($c=0; $c < count($matches[0]); $c++) {
        $oldLink = $matches[0][$c];
        // trying CDI first
        if (preg_match('/[tT][nN][_][a-zA-Z_0-9.\/\-]+/', $matches[0][$c], $linkId)) {
            $newLink = '"https://saalck-uky.primo.exlibrisgroup.com/discovery/fulldisplay?context=PC&vid=01SAA_UKY:UKY&docid=' . substr($linkId[0], 3) . '"';
        } else if (preg_match('/[uU][kK][yY][_][aA][lL][mM][aA][0-9]+/', $matches[0][$c], $linkId)) {
            $newLink = '"https://saalck-uky.primo.exlibrisgroup.com/discovery/fulldisplay?context=L&vid=01SAA_UKY:UKY&docid=alma' . pidToMms(substr($linkId[0], 8)) . '"';
        } else if (preg_match('/exploreuk.uky.edu\/[a-zA-Z_0-9]+/', $matches[0][$c], $linkId)) {
            // make this one the exploreuk just in case
            $newLink = '"https://saalck-uky.primo.exlibrisgroup.com/discovery/search?query=any,contains,' . $linkId[0] . '&tab=Everything&search_scope=Default&vid=01SAA_UKY:UKY&lang=en&offset=0"';
        } else {
            // do string replace on these bits to catch others: base url, vid, inst, tab, scope
            $oldParts = ['saa-primo.hosted.exlibrisgroup.com/primo-explore', 'vid=UKY', 'tab=default_tab', 'tab=alma_tab', 'tab=cr_tab', 'tab=exploreuk_tab', 'search_scope=default_scope', 'search_scope=alma_scope', 'search_scope=Course%20Reserves', 'search_scope=exploreuk_scope', 'lang=en_US'];
            $newParts = ['saalck-uky.primo.exlibrisgroup.com/discovery','vid=01SAA_UKY:UKY','tab=Everything', 'tab=LibraryCatalog', 'tab=CourseReserves', 'tab=LocalCollections','search_scope=Default', 'search_scope=MyInstitution', 'search_scope=CourseReserves', 'search_scope=ExploreUK', 'lang=en'];
        
            $newLink = str_replace($oldParts, $newParts, $matches[0][$c]);
        }
        echo '---OLD: ' . $oldLink . "\n";
        //fwrite($txtReport, "---OLD: " . $oldLink . "\n");
        echo '---NEW: ' . $newLink . "\n";
        //fwrite($txtReport, "---NEW: " . $newLink . "\n");

        $bodyStuff = str_replace($oldLink, $newLink, $bodyStuff);
        //echo '<br/><br/>New body: ' . $bodyStuff;
        
    }
    //echo '!!! CONVERSION DONE !!! ' . "\n";
    return $bodyStuff;
}


// GET PAGE AND UPDATE PAGE FUNCTIONS //////////////////////////////////////////
function getPage($pageUrl) {
    $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $pageUrl,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array('REDACTED'),
));

$response = curl_exec($curl);

curl_close($curl);
    //echo $response;
    $array = json_decode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    return $array["body"];
}



function putPage($pageUrl, $pageJson) {
    //echo '<br/>STARTING PUT';
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $pageUrl,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_POSTFIELDS => $pageJson,
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Authorization: Bearer REDACTED',
  ),
));

$response = curl_exec($curl);

curl_close($curl);
  //echo '<br/>PUT RESPONSE: ';
  //var_dump($response);
}

function oneLink($oneLinkUrl) {
    $oldLink = $oneLinkUrl;
   if (preg_match('/[tT][nN][_][a-zA-Z_0-9.\/\-]+/', $oneLinkUrl, $linkId)) {
            $newLink = 'https://saalck-uky.primo.exlibrisgroup.com/discovery/fulldisplay?context=PC&vid=01SAA_UKY:UKY&docid=' . substr($linkId[0], 3);
        } else if (preg_match('/[uU][kK][yY][_][aA][lL][mM][aA][0-9]+/', $oneLinkUrl, $linkId)) {
            $newLink = 'https://saalck-uky.primo.exlibrisgroup.com/discovery/fulldisplay?context=L&vid=01SAA_UKY:UKY&docid=alma' . pidToMms(substr($linkId[0], 8)) ;
        } else if (preg_match('/exploreuk.uky.edu\/[a-zA-Z_0-9]+/', $oneLinkUrl, $linkId)) {
            // make this one the exploreuk just in case
            $newLink = 'https://saalck-uky.primo.exlibrisgroup.com/discovery/search?query=any,contains,' . $linkId[0] . '&tab=Everything&search_scope=Default&vid=01SAA_UKY:UKY&lang=en&offset=0';
        } else {
            // do string replace on these bits to catch others: base url, vid, inst, tab, scope
            $oldParts = ['saa-primo.hosted.exlibrisgroup.com/primo-explore', 'vid=UKY', 'tab=default_tab', 'tab=alma_tab', 'tab=cr_tab', 'tab=exploreuk_tab', 'search_scope=default_scope', 'search_scope=alma_scope', 'search_scope=Course%20Reserves', 'search_scope=exploreuk_scope', 'lang=en_US'];
            $newParts = ['saalck-uky.primo.exlibrisgroup.com/discovery','vid=01SAA_UKY:UKY','tab=Everything', 'tab=LibraryCatalog', 'tab=CourseReserves', 'tab=LocalCollections','search_scope=Default', 'search_scope=MyInstitution', 'search_scope=CourseReserves', 'search_scope=ExploreUK', 'lang=en'];
        
            $newLink = str_replace($oldParts, $newParts, $matches[0][$c]);
        }
        echo 'OLD: ' . $oldLink . "\n";
        //fwrite($txtReport, "OLD: " . $oldLink . "\n");
        echo 'NEW: ' . $newLink . "\n"; 
        //fwrite($txtReport, "NEW: " . $newLink . "\n"); 
        
        return $newLink;
}

echo '!!! DONE REVIEWING COURSE ' . $courseId . '!!!' . "\n";
fwrite($txtReport, "!!! DONE REVIEWING COURSE " . $courseId . "!!!" . "\n");

fclose($txtReport);
?>  
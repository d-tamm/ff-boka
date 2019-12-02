<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Question;
$message = "";

/**
 * Get the list of admins for this section
 * @param \FFBoka\Section $section
 * @return string HTML string with <li> elements.
 */
function adminList($section) {
    if (!$admins = $section->getAdmins()) $ret = "<li>Inga administratörer har lagts upp än.</li>";
    foreach ($admins as $admId) {
        $adm = new User($admId);
        $ret .= "<li><a href='#'><h2>" . ($adm->name ? htmlspecialchars($adm->name) : "(ingen persondata tillgänglig)") . "</h2><p>{$adm->id}</p></a><a href=\"javascript:removeAdmin({$adm->id}, '" . htmlspecialchars($adm->name) . "');\">Ta bort</a></li>";
    }
    return $ret;
}


session_start();
require(__DIR__."/../inc/common.php");
global $cfg, $FF;

// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser']) {
    header("Location: /?action=sessionExpired");
    die();
}

// Set current section and user
if ($_GET['sectionId']) $_SESSION['sectionId'] = $_GET['sectionId'];
if (!$_SESSION['sectionId']) {
    header("Location: /?action=sessionExpired");
    die();
}
$section = new Section($_SESSION['sectionId']);
$currentUser = new User($_SESSION['authenticatedUser']);

// Only allow users which have at least some admin role in this section
if (!$section->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
    header("Location: /?action=accessDenied&to=" . urlencode("administrationssidan för {$section->name}"));
    die();
}
$userAccess = $section->getAccess($currentUser);

switch ($_REQUEST['action']) {
    case "ajaxFindUser":
        // This is also called from category.php
        header("Content-Type: application/json");
        die(json_encode($FF->findUser($_REQUEST['q'])));
		
    case "ajaxAddSectionAdmin":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) die("Ajja bajja!");
        header("Content-Type: application/json");
	    if (!is_numeric($_REQUEST['id'])) {
	        die(json_encode(["error"=>"Ogiltigt medlemsnummer {$_REQUEST['id']}."]));
	    } elseif ($section->addAdmin($_REQUEST['id'])) {
	        die(json_encode(["html"=>adminList($section)]));
		} else {
		    die(json_encode(["error"=>"Kunde inte lägga till administratören. Är den kanske redan med i listan?"]));
		}
		
    case "ajaxRemoveSectionAdmin":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) die("Ajja bajja!");
	    header("Content-Type: application/json");
	    if ($section->removeAdmin($_REQUEST['id'])) die(json_encode(["html"=>adminList($section)]));
	    else die(json_encode(["error"=>"Kan inte ta bort LA-administratören."]));
		
	case "ajaxGetQuestion":
        header("Content-Type: text/plain");
		$question = new Question($_REQUEST['id']);
		die(json_encode([
			"id"=>$question->id,
			"caption"=>$question->caption,
			"type"=>$question->type,
			"options"=>$question->options,
		]));
		
    case "ajaxGetQuestions":
        header("Content-Type: text/plain");
        foreach ($section->questions() as $question) {
            echo "<li><a href='#' onClick='showQuestion({$question->id})'><span style='white-space:normal;'>" . htmlspecialchars($question->caption) . "</span><p style='white-space:normal;'>";
            echo $question->optionsReadable();
			echo "</p></a><a href='#' onClick='deleteQuestion({$question->id});'>Ta bort frågan</a></li>";
        }
        die();
        
    case "ajaxSaveQuestion":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) die("Ajja bajja!");
        header("Content-Type: application/json");
        if ($_REQUEST['id']==0) {
            $question = $section->addQuestion();
        } else {
            $question = new Question($_REQUEST['id']);
        }
        $question->caption = $_REQUEST['caption'];
        $question->type = $_REQUEST['type'];
        switch ($question->type) {
            case "radio":
            case "checkbox":
                $question->options = json_encode([ "choices" => explode("\n", $_REQUEST['choices']) ]); break;
            case "text":
                $question->options = json_encode([ "length"=>$_REQUEST['length'] ]); break;
            case "number":
                $question->options = json_encode([ "min"=>$_REQUEST['min'], "max"=>$_REQUEST['max'] ]); break;
        }
        die (json_encode("OK"));
        
    case "ajaxDeleteQuestion":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) die("Ajja bajja!");
        header("Content-Type: application/json");
        $question = new Question($_REQUEST['id']);
        die(json_encode($question->delete()));
}

unset($_SESSION['catId']);

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Admin ".$section->name) ?>
	<script>
	$( document ).on( "mobileinit", function() {
		<?php if ($message) { ?>
		$( document ).on( "pagecontainershow", function( event, ui ) {
			setTimeout(function() {
				$("#popupMessage").popup('open');
			}, 500); // We need some delay here to make this work on Chrome.
		} );
		<?php } ?>
	});
	</script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page-section-admin">
	<?= head("LA " . htmlspecialchars($section->name), $currentUser) ?>
	<div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
    	<p><?= $message ?></p>
    	<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

	<div data-role="collapsibleset" data-inset="false">

		<div data-role="collapsible" data-collapsed="<?= isset($_REQUEST['expand']) ? "true" : "false" ?>">
			<h2>Kategorier</h2>
			<ul data-role="listview">
			<?php
			foreach ($section->getMainCategories() as $cat) {
			    if ($cat->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
    				echo "<li><a href='category.php?catId={$cat->id}' data-ajax='false'>" .
    					htmlspecialchars($cat->caption) .
    					embedImage($cat->thumb);
    				$children = array();
    				foreach ($cat->children(TRUE) as $child) $children[] = htmlspecialchars($child->caption);
    				if ($children) echo "<p>" . implode(", ", $children) . "</p>";
    				echo "<span class='ui-li-count'>{$cat->itemCount}</span></a></li>";
			    }
			}
			if ($section->getAccess($currentUser) & FFBoka::ACCESS_SECTIONADMIN) echo "<li><a href='category.php?action=new' data-ajax='false'>Skapa ny kategori</a></li>"; ?>
			</ul>
			<br>
		</div>

		<?php if ($section->getAccess($currentUser) & FFBoka::ACCESS_SECTIONADMIN) { ?>
		
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']=="admins" ? "false" : "true" ?>">
			<h2>Bokningsfrågor</h2>
			<p>Här kan du skapa frågor som sedan kan visas när folk bokar resurser.</p>
			
			<a href="#" onClick="showQuestion(0);" class="ui-btn">Lägg till bokningsfråga</a>
			
			<div data-role="popup" id="popup-section-question" data-overlay-theme="b" class="ui-content">
				<div class="ui-field-contain">
					<label for="sec-question-caption">Fråga att visa:</label>
					<input id="sec-question-caption">
				</div>
				<div class="ui-field-contain">
					<label for="sec-question-types">Typ av fråga:</label>
    				<div data-role="controlgroup" data-mini="true" id="sec-question-types">
        				<label><input type="radio" id="sec-question-type-radio" name="sec-question-type" value="radio">Flera optioner, ett svar</label>
        				<label><input type="radio" id="sec-question-type-checkbox" name="sec-question-type" value="checkbox">Flera optioner, flera svar</label>
        				<label><input type="radio" id="sec-question-type-text" name="sec-question-type" value="text">Fritext</label>
        				<label><input type="radio" id="sec-question-type-number" name="sec-question-type" value="number">Numerisk</label>
    				</div>
				</div>
				
				<div class="ui-content" id="sec-question-opts-checkboxradio">
					Svarsalternativ (1 svar per rad):
					<textarea id="sec-question-choices"></textarea>
				</div>
				<div class="ui-content" id="sec-question-opts-text">
					<div class="ui-field-contain">
						<label for="sec-question-opts-length">Max längd:</label>
						<input type="number" id="sec-question-length" placeholder="Längd">
					</div>
				</div>
				<div class="ui-content" id="sec-question-opts-number">
					<div class="ui-grid-a">
						<div class="ui-block-a">Min:</div>
						<div class="ui-block-b">Max:</div>
						<div class="ui-block-a"><input type="number" id="sec-question-min" placeholder="min"></div>
						<div class="ui-block-b"><input type="number" id="sec-question-max" placeholder="max"></div>
					</div>
				</div>
				<input type="button" onClick="saveQuestion()" data-theme="b" data-corners="false" value="Spara">
			</div>
			
			<ul data-role="listview" id="sec-questions" data-inset="true" data-split-icon="delete"></ul>

		</div>
		
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']=="admins" ? "false" : "true" ?>">
			<h2>Administratörer</h2>

			<p>Lägg till ny administratör på LA-nivå: (skriv medlemsnummer eller namn)
				<a href="#popup-help-admin-access" data-rel="popup" class="tooltip ui-btn ui-alt-icon ui-nodisc-icon ui-btn-inline ui-icon-info ui-btn-icon-notext">Tipps</a>
			</p>
			<div data-role="popup" id="popup-help-admin-access" class="ui-content" data-overlay-theme="b">
				<p>Här ställer du in vilka medlemmar som ska ha behörighet att administrera resursbokningen i lokalavdelningen <?= $section->name ?>. Du kan alltid lägga till en ny admin genom att ange dess medlemsnummer. Om personen tidigare har loggat in i resursbokningen och lagt in sina kontaktuppgifter kan du även hitta hen genom att leta efter namnet istället.</p>
				<p><?php
                $allAss = $cfg['sectionAdmins'];
                $oneAss = array_pop($allAss);
                echo implode(", ", $allAss) . " och " . $oneAss; ?>
            	har automatiskt administratörsbehörighet.</p>
            	<p>Om du bara vill tilldela behörigheter för att någon ska hantera enskilda kategorier gör du det under respektive kategori.</p>
			</div>

			<form class="ui-filterable">
				<input id="sec-adm-autocomplete-input" data-type="search" placeholder="Lägg till admin...">
			</form>
			<ul id="sec-adm-autocomplete" data-role="listview" data-filter="true" data-input="#sec-adm-autocomplete-input" data-inset="true"></ul>
				
			<p>Användare med admin-behörighet:</p>
			<ul id="ul-sec-admins" data-role="listview" data-split-icon="delete" data-split-theme="c" data-inset="true">
				<?= adminList($section) ?>
			</ul>
		<?php } ?>
		</div>

	</div><!--/collapsibleset-->

	</div><!--/main-->

	<script>
		showQuestionOptions("");
		var questionId = 0;
		var questionType = "";
		
    	$( document ).on( "pagecreate", "#page-section-admin", function() {
        	getQuestions();
        	
    	    $( "#sec-adm-autocomplete" ).on( "filterablebeforefilter", function ( e, data ) {
    	        var $ul = $( this ),
    	            $input = $( data.input ),
    	            value = $input.val(),
    	            html = "";
    	        $ul.html( "" );
    	        if ( value && value.length > 2 ) {
    	            $ul.html( "<li><div class='ui-loader'><span class='ui-icon ui-icon-loading'></span></div></li>" );
    	            $ul.listview( "refresh" );
    				$.getJSON("index.php", {action: "ajaxFindUser", q: value}, function(data, status) {
    	                $.each( data, function ( i, val ) {
        	                html += "<li style='cursor:pointer;' title='Lägg till " + val.name + " som LA-admin' onClick='addAdmin(" + val.userId + ");'>" + val.userId + " " + (val.name ? val.name : "(ingen persondata tillgänglig)") + "</li>";
    	                });
						if (data.length==0) {
							if (Number(value)) html += "<li style='cursor:pointer;' title='Lägg till medlem med medlemsnummer " + Number(value) + " som LA-admin' onClick='addAdmin(" + Number(value) + ");'>" + Number(value) + " (ny användare)</li>";
							else html += "<li>Sökningen på <i>" + value + "</i> gav ingen träff</li>";
						}
    	                $ul.html( html );
    	                $ul.listview( "refresh" );
    	                $ul.trigger( "updatelayout");
    	            });
    	        }
    	    });

    	    $("input[type=radio][name=sec-question-type]").click( function() {
				showQuestionOptions(this.value);
    	    });
    	});
		
		function showQuestionOptions(type) {
			questionType = type;
			$("#sec-question-opts-checkboxradio").hide();
			$("#sec-question-opts-text").hide();
			$("#sec-question-opts-number").hide();
			switch (questionType) {
			case "radio":
			case "checkbox":
				$("#sec-question-opts-checkboxradio").show();
				break;
			case "text":
				$("#sec-question-opts-text").show();
				break;
			case "number":
				$("#sec-question-opts-number").show();
				break;
			}
		}

    	function getQuestions() {
			$.mobile.loading("show", {});
        	$.get("index.php", { action: "ajaxGetQuestions" }, function(data, status) {
            	$("#sec-questions").html(data).listview("refresh");
    			$.mobile.loading("hide", {});
        	});
    	}
    	
	   	function clearQuestionInputs() {
		   	$("#sec-question-caption").val("");
		   	$("#sec-question-choices").val("");
		   	$("#sec-question-length").val("");
		   	$("#sec-question-min").val("");
		   	$("#sec-question-max").val("");
	   	}

	   	function saveQuestion() {
		   	if ($("#sec-question-caption").val()=="") {
			   	alert("Du måste skriva in frågan.");
			   	return;
		   	}
			if (questionType=="") {
				alert("Välj en frågetyp först.");
				return;
			}
			$.mobile.loading("show", {});
		   	$.getJSON("index.php", {
			   	action: "ajaxSaveQuestion",
			   	id: questionId,
			   	caption: $("#sec-question-caption").val(),
			   	type: questionType,
			   	choices: $("#sec-question-choices").val(),
			   	length: $("#sec-question-length").val(),
			   	min: $("#sec-question-min").val(),
			   	max: $("#sec-question-max").val()  
		   	}, function(data, status) {
	        	$("#popup-section-question").popup('close', { transition: "pop" } );
				$.mobile.loading("hide", {});
				getQuestions();
		   	});
	   	}

	   	function deleteQuestion(id) {
			$.mobile.loading("show", {});
		   	$.getJSON("index.php", { action: "ajaxDeleteQuestion", id: id }, function(data, status) {
				$.mobile.loading("hide", {});
				getQuestions();
		   	});
	   	}
	   	
    	function showQuestion(id) {
    		questionId = id;
			clearQuestionInputs();
        	if (id==0) {
            	showQuestionOptions("");
            	$("input[type=radio][name=sec-question-type]").removeAttr("checked").checkboxradio("refresh");
				$("#popup-section-question").popup('open', { transition: "pop" } );
        	} else {
				$.mobile.loading("show", {});
				$.getJSON("index.php", { action: "ajaxGetQuestion", id: id }, function(data, status) {
					questionId = data.id;
					$("#sec-question-caption").val( data.caption );
					showQuestionOptions(data.type);
					$("input[name=sec-question-type]").prop("checked", false);
					$("#sec-question-type-"+data.type).prop("checked", "checked");
					$("input[name=sec-question-type]").checkboxradio("refresh");
					switch (data.type) {
						case "radio":
						case "checkbox":
							$("#sec-question-choices").val(data.options.choices.join("\n")); break;
						case "text":
							$("#sec-question-length").val(data.options.length); break;
						case "number":
							$("#sec-question-min").val(data.options.min); $("#sec-question-max").val(data.options.max); break;
					}
					$.mobile.loading("hide", {});
					$("#popup-section-question").popup('open', { transition: "pop" } );
				});
        	}
    	}

    	function addAdmin(id) {
        	$.getJSON("index.php", {action: "ajaxAddSectionAdmin", id: id}, function(data, status) {
    			if (data['html']) {
					$("#ul-sec-admins").html(data['html']).listview("refresh");
		        	$("#sec-adm-autocomplete-input").val("");
		        	$("#sec-adm-autocomplete").html("");
    			} else {
        			alert(data['error']);
    			}
        	});
    	}

		function removeAdmin(id, name) {
			if (confirm('Du håller på att återkalla admin-behörighet för ' + (id==<?= $currentUser->id ?> ? "dig själv" : (name ? name : "(okänd)")) + '. Vill du fortsätta?')) {
    			$.getJSON("index.php", {action: "ajaxRemoveSectionAdmin", id: id}, function(data, status) {
        			if (data['html']) {
						$("#ul-sec-admins").html(data['html']).listview("refresh");
						if (id==<?= $currentUser->id ?>) location.reload();
        			} else {
            			alert(data['error']);
        			}
    			});
			}
		}
	</script>

</div><!--/page-->
</body>
</html>

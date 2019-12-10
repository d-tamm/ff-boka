// General vars
var toutSetValue;

/*$(document).on('pagecreate', function(e) {
    console.log("pagecreate", e.target.attributes.id);
});
$(document).on('pageshow', function(e) {
    console.log("pageshow", e.target.attributes.id);
});*/

// Prevent caching of pages
$(document).on('pagehide', function (event, ui) { 
    var page = jQuery(event.target);
    page.remove(); 
});

// ========== index.php ==========
$(document).on('pagecreate', "#page-start", function(e) {
    // bind events
});

$(document).on('pageshow', "#page-start", function() {
    // Show message if there is any
    if ($("#msg-page-start").html()) {
        setTimeout(function() {
            $("#popup-msg-page-start").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});



// ========== book-part.php ==========
var checkedItems, fbStart, wday;

$(document).on('pagecreate', "#page-book-part", function() {
    // bind events
});

$("#page-book-part").on('pagebeforeshow', function() {
    // Show message if there is any
    if ($("#msg-page-book-part").html()) {
        setTimeout(function() {
            $("#popup-msg-page-book-part").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
    // Uncheck all items
    checkedItems = {};
    $(".book-item").removeClass("item-checked");
    // Initialise date chooser
    fbStart = new Date();
    fbStart.setHours(0,0,0,0); // Midnight
    wday = fbStart.getDay() ? fbStart.getDay()-1 : 6; // Weekday, where monday=0 ... sunday=6
    fbStart.setDate(fbStart.getDate() - wday); // Should now be last monday
    scrollDate(0);
});

function scrollDate(offset) {
    $.mobile.loading("show", {});
    // Calculate start end end of week
    fbStart.setDate(fbStart.getDate() + offset);
    var fbEnd = new Date(fbStart.valueOf());
    fbEnd.setDate(fbEnd.getDate() + 6);
    var readableRange = "må " + fbStart.getDate() + "/" + (fbStart.getMonth()+1);
    if (fbStart.getFullYear() != fbEnd.getFullYear()) readableRange += " '"+fbStart.getFullYear().toString().substr(-2);
    readableRange += " &ndash; sö " + fbEnd.getDate() + "/" + (fbEnd.getMonth()+1) + " '"+fbEnd.getFullYear().toString().substr(-2);
    // Get freebusy bars
    $.getJSON("book-part.php", { action: "ajaxFreebusy", start: fbStart.valueOf()/1000, ids: checkedItems }, function(data, status) {
        $("#book-current-range-readable").html( readableRange );
        $.each(data.freebusyBars, function(key, value) { // key will be "item-nn"
            $("#freebusy-"+key).html(value);
        });
        $("#book-combined-freebusy-bar").html(data.freebusyCombined);
        $.mobile.loading("hide", {});
    });
}

function toggleItem(itemId){
    if (checkedItems[itemId]) {
        delete checkedItems[itemId];
    } else {
        checkedItems[itemId] = true;
    }
    $("#book-item-"+itemId).toggleClass("item-checked");
    
    if (Object.keys(checkedItems).length>0) {
        // Get access information for all selected items
        $.mobile.loading("show", {});
        $.getJSON("book-part.php", { action: "ajaxCombinedAccess", start: fbStart.valueOf()/1000, ids: checkedItems }, function(data, status) {
            if (data.access <= ACCESS_READASK) {
                 $("#book-access-msg").html("<p>Komplett information om tillgänglighet kan inte visas för ditt urval av resurser. Ange önskad start- och sluttid nedan för att skicka en intresseförfrågan.</p><p>Ansvarig kommer att höra av sig till dig med besked om tillgänglighet och eventuell bekräftelse av din förfrågan.</p>");
            } else {
                $("#book-access-msg").html("");
                if (data.access <= ACCESS_PREBOOK) {
                    $("#book-access-msg").append("<p><b>OBS: Bokninen är preliminär.</b> För ditt urval av resurser kommer bokningen behöva bekräftas av materialansvarig.</p>"); 
                }
            }
            $("#book-combined-freebusy-bar").html(data.freebusyCombined);
            $.mobile.loading("hide", {});
        });
        $("#book-step2").show();
    } else {
        $("#book-step2").hide();
    }
}

function popupItemDetails(id) {
    $.mobile.loading("show", {});
    $.getJSON("book-part.php", { action: "ajaxItemDetails", id: id }, function(data, status) {
        $.mobile.loading("hide", {});
        $("#popup-item-details").html(data).popup('open', { transition: "pop", y: 0 });
    });
}

function checkTimes() {
    // User has chosen start and end time. Check that the chosen range  
    // does not collide with existing bookings visible to the user.
    // First, check that user has entered some times:
    if ($("#book-date-start").val()=="" | $("#book-time-start").val()=="" | $("#book-date-end").val()=="" | $("#book-time-end").val()=="") {
        alert("Du måste välja start- och sluttid först.");
        return false;
    }
    // Ensure that end time is later than start time:
    var startDate = new Date($("#book-date-start").val() + " " + $("#book-time-start").val());
    var endDate = new Date($("#book-date-end").val() + " " + $("#book-time-end").val());
    if (startDate.valueOf() >= endDate.valueOf()) {
        alert("Du har valt en sluttid som ligger före starttiden.");
        return false;
    }
    // Send times to server to check availability:
    $.mobile.loading("show", {});
    $.getJSON("book-part.php", {
        action: "ajaxCheckTimes",
        ids: checkedItems,
        start: startDate.valueOf()/1000,
        end: endDate.valueOf()/1000
    }, function(data, status) {
        $.mobile.loading("hide", {});
        if (data.timesOK) {
            // Reset subbooking section to prepare for next subbooking
            checkedItems = {};
            $(".book-item").removeClass("item-checked");
            $("#book-step2").hide();
            $("#book-date-start").val("");
            $("#book-time-start").val("");
            $("#book-date-end").val("");
            $("#book-time-end").val("");
            // update freebusy
            scrollDate(0);
            location.href="book-sum.php";
        } else {
            $("#ul-items-unavail").html("");
            $.each(data.unavail, function( key, item ) {
                $("#ul-items-unavail").append("<li>"+item+"</li>");
            });
            $("#popup-items-unavail").popup('open', { transition: "pop" });
        }
    });
}




// ========== book-sum.php ==========
var reqCheckRadios;

$(document).on('pagecreate', "#page-book-sum", function() {
    // bind events
    $("#form-booking").submit(function(event) {
	    // Validate required checkboxes and radios
	    $.each(reqCheckRadios, function( id, q ) {
		    if ($("[name^=answer-"+id+"]:checked").length == 0) {
			    alert("Du måste först svara på frågan: "+q);
			    event.preventDefault();
			    return false;
		    }
	    });
	    return true;
    });
});

$("#page-book-sum").on('pagebeforeshow', function() {
    // Show message if there is any
    if ($("#msg-page-book-sum").html()) {
        setTimeout(function() {
            $("#popup-msg-page-book-sum").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

function popupItemDetails(id) {
    $.mobile.loading("show", {});
    $.getJSON("book-part.php", { action: "ajaxItemDetails", id: id }, function(data, status) {
        $.mobile.loading("hide", {});
        $("#popup-item-details").html(data).popup('open', { transition: "pop", y: 0 });
    });
}

function removeItem(subId, bookedItemId) {
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", {
        action: "ajaxRemoveItem",
        subId: subId,
        bookedItemId: bookedItemId
    }, function(data, status) {
        $.mobile.loading("hide", {});
        if (data.error) alert(data.error);
        else if (data.status=="booking empty") location.href="book-part.php";
        else location.reload();
    });
}

function deleteBooking() {
	if (confirm("Är du säker på att du vill ta bort din bokning?")) {
		location.href="?action=deleteBooking";
	}
	return false;
}




// ========== admin/index.php ==========
var questionId, questionType;

$(document).on('pagecreate', "#page-admin-section", function() {
    // bind events
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

$("#page-admin-section").on('pagebeforeshow', function() {
    // Show message if there is any
    if ($("#msg-page-admin-section").html()) {
        setTimeout(function() {
            $("#popup-msg-page-admin-section").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
    showQuestionOptions("");
    questionId = 0;
    questionType = "";
    getQuestions();
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


function removeAdmin(id, currentUserId, name) {
    if (confirm('Du håller på att återkalla admin-behörighet för ' + (currentUserId==id ? "dig själv" : (name ? name : "(okänd)")) + '. Vill du fortsätta?')) {
        $.getJSON("index.php", {action: "ajaxRemoveSectionAdmin", id: id}, function(data, status) {
            if (data['html']) {
                $("#ul-sec-admins").html(data['html']).listview("refresh");
                if (currentUserId==id) location.reload();
            } else {
                alert(data['error']);
            }
        });
    }
}




// ========== admin/category.php ==========
var chosenAccessId;

$(document).on('pagecreate', "#page-admin-category", function() {
    // bind events
    $("#cat-caption").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "caption", this.value);
    });

    $("#cat-parentId").on('change', function() {
        setCatProp("parentId", this.value);
    });

    $("#cat-prebookMsg").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "prebookMsg", this.value);
    });

    $("#cat-postbookMsg").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "postbookMsg", this.value);
    });

    $("#cat-bufferAfterBooking").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "bufferAfterBooking", this.value);
    });

    $("#file-cat-img").change(function() {
        // Save image via ajax: https://makitweb.com/how-to-upload-image-file-using-ajax-and-jquery/
        var fd = new FormData();
        var file = $('#file-cat-img')[0].files[0];
        fd.append('image', file);
        fd.append('action', "ajaxSetImage");
        $.mobile.loading("show", {});

        $.ajax({
            url: 'category.php',
            type: 'post',
            data: fd,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(data) {
                if (data.status=="OK") {
                    var d = new Date();
                    $('#cat-img-preview').attr("src", "/image.php?type=category&id=<?= $cat->id ?>&" + d.getTime()).show().trigger( "updatelayout" );
                } else {
                    alert(data.error);
                }
                $.mobile.loading("hide", {});
            },
        });
    });

    $( "#cat-contact-autocomplete" ).on( "filterablebeforefilter", function ( e, data ) {
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
                    html += "<li style='cursor:pointer;' title='Sätt " + val['name'] + " som kontaktperson' onClick='setContactUser(" + val['userId'] + ");'>" + val['userId'] + " " + (val['name'] ? val['name'] : "(ingen persondata tillgänglig)") + "</li>";
                });
                if (data.length==0) {
                    if (Number(value)) html += "<li style='cursor:pointer;' title='Lägg till medlem med medlemsnummer " + Number(value) + " som kontaktperson' onClick='setContactUser(" + Number(value) + ");'>" + Number(value) + " (ny användare)</li>";
                    else html += "<li>Sökningen på <i>" + value + "</i> gav ingen träff</li>";
                }
                $ul.html( html );
                $ul.listview( "refresh" );
                $ul.trigger( "updatelayout");
            });
        }
    });
    
    $( "#cat-adm-autocomplete" ).on( "filterablebeforefilter", function ( e, data ) {
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
                    html += "<label><input type='radio' class='cat-access-id' name='id' value='" + val['userId'] + "'>" + val['userId'] + " " + (val['name'] ? val['name'] : "(ingen persondata tillgänglig)") + "</label>";
                });
                if (data.length==0) {
                    if (Number(value)) html += "<label><input type='radio' class='cat-access-id' name='id' value='" + Number(value) + "'>" + Number(value) + " (ny användare)</label>";
                    else html += "<li>Sökningen på <i>" + value + "</i> gav ingen träff</li>";
                }
                $ul.html( html );
                $ul.trigger( "create");
            });
        }
    });

    $("#cat-access-ids").on("change", ".cat-access-id", function(e, data) {
        // Triggered when user choses group or specific user for new access rights (step 1)
        $(".cat-access-level").attr("checked", false).checkboxradio("refresh");
        chosenAccessId = this.value;
        $("#cat-access-levels").show();
    });

    $(".cat-access-level").click(function() {
        // Triggered when user choses access level (step 2)
        $.mobile.loading("show", {});
        $("#cat-access-levels").hide();
        $(".cat-access-id").prop("checked", false).checkboxradio("refresh");
        $("#cat-adm-autocomplete-input").val("");
        $("#cat-adm-autocomplete").html("");
        $.get("?action=ajaxSetAccess&id="+encodeURIComponent(chosenAccessId)+"&access="+this.value, function(data, status) {
            if (data!=0) {
                $("#assigned-cat-access").html(data).enhanceWithin();
                $("#assigned-cat-access a.ajax-input").addClass('change-confirmed');
                setTimeout(function(){ $("#assigned-cat-access a.ajax-input").removeClass("change-confirmed"); }, 1500);
            } else {
                alert("Kunde inte spara behörigheten.");
            }
            $.mobile.loading("hide", {});
        });
    });

    $("#delete-cat").click(function() {
        if (confirm("Du håller på att ta bort kategorin och alla poster i den. Fortsätta?")) {
            location.href="?action=deleteCat";
        }
    });
});

$("#page-admin-category").on('pagebeforeshow', function() {
    // Show message if there is any
    if ($("#msg-page-admin-category").html()) {
        setTimeout(function() {
            $("#popup-msg-page-admin-category").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
    chosenAccessId=0;
});

function unsetAccess(id) {
    $.mobile.loading("show", {});
    $.get("?action=ajaxSetAccess&id=" + encodeURIComponent(id) + "&access=" + ACCESS_NONE, function(data, status) {
        if (data!=0) {
            $("#assigned-cat-access").html(data).enhanceWithin();
        } else {
            alert("Kunde inte återkalla behörigheten.");
        }
        $.mobile.loading("hide", {});
    });
}

function setCatProp(name, val) {
    $.getJSON("category.php", {action: "ajaxSetCatProp", name: name, value: val}, function(data, status) {
        if (data.status=="OK") {
            $("#cat-saved-indicator").addClass("saved");
            setTimeout(function(){ $("#cat-saved-indicator").removeClass("saved"); }, 2500);
        } else {
            alert("Kan inte spara ändringen :(");
        }
    });
}

function setContactUser(id) {
    $.getJSON("category.php", { action: "ajaxSetContactUser", id: id }, function(data, status) {
        $("#cat-contact-data").html(data.html);
        $("#cat-contact-autocomplete-input").val("");
        $("#cat-contact-autocomplete").html("");
    });
}

function toggleQuestion(id) {
    $.getJSON("category.php", {
        action: "ajaxToggleQuestion",
        id: id,
    }, function(data, status) {
        $("#cat-questions").html(data.html).listview("refresh");
    });
}




// ========== admin/item.php ==========
$(document).on('pagecreate', "#page-admin-item", function() {
    // Bind events
    $("#item-caption").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setItemProp, 1000, "caption", this.value);
    });
    
    $("#item-description").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setItemProp, 1000, "description", this.value);
    });

    $("#item-active").click(function() {
        setItemProp("active", this.checked ? 1 : 0);
    });
    
    $("#delete-item").click(function() {
        if (confirm("Du håller på att ta bort utrustningen. Fortsätta?")) {
            $.getJSON("item.php", { action: "ajaxDeleteItem" }, function(data, status) {
                if (data.status=='OK') location.href="category.php?expand=items";
                else alert("Något har gått fel. :(");
            });
        }
    });

    $("#file-item-img").change(function() {
        // Save image via ajax: https://makitweb.com/how-to-upload-image-file-using-ajax-and-jquery/
        var fd = new FormData();
        var file = $('#file-item-img')[0].files[0];
        fd.append('image',file);
        fd.append('action', "ajaxAddImage");
        $.mobile.loading("show", {});
        $.ajax({
            url: 'item.php',
            type: 'post',
            data: fd,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(data) {
                $.mobile.loading("hide", {});
                if (data.html) {
                    $('#item-images').html(data.html).enhanceWithin();
                } else {
                    alert(data.error);
                }
            },
        });
    });
    
    $("#item-images").on("input", ".item-img-caption", function(e, data) {
        // Save image caption to DB via ajax
        var _this = this;
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(function() {
            $.getJSON(
                "item.php",
                { action: 'ajaxSaveImgCaption', id: $(_this).data('id'), caption: _this.value },
                function(data, status) {
                    $("#item-saved-indicator").addClass("saved");
                    setTimeout(function(){ $("#item-saved-indicator").removeClass("saved"); },1000);
                }
            );
        }, 1000);
    });
});

$("#page-admin-item").on('pagebeforeshow', function() {
    // Show message if there is any
    if ($("#msg-page-admin-item").html()) {
        setTimeout(function() {
            $("#popup-msg-page-admin-item").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

function setItemProp(name, val) {
	$.getJSON("item.php", {action: "setItemProp", name: name, value: val}, function(data, status) {
		if (data.status=="OK") {
			$("#item-saved-indicator").addClass("saved");
			setTimeout(function(){ $("#item-saved-indicator").removeClass("saved"); }, 2500);
		} else {
			alert("Kan inte spara ändringen :(");
		}
	});
}

function deleteImage(id) {
    if (confirm("Vill du ta bort denna bild?")) {
        $.getJSON("?action=ajaxDeleteImage&id="+id, function(data, status) {
            if (data.error) {
                alert(data.error);
            } else {
                $('#item-images').html(data.html).enhanceWithin();
            }
        });
    }
}




// ========== userdata.php ==========
$(document).on('pagecreate', "#page-userdata", function() {
    // Bind events
});

$("#page-userdata").on('pagebeforeshow', function() {
    // Show message if there is any
    if ($("#msg-page-userdata").html()) {
        setTimeout(function() {
            $("#popup-msg-page-userdata").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

function deleteAccount() {
	if (window.confirm("Bekräfta att du vill radera ditt konto i resursbokningen. Alla dina bokningar och persondata tas bort från systemet och kan inte återställas!")) {
		location.href="userdata.php?action=deleteAccount";
	}
}


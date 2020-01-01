// General vars
var toutSetValue,
	weekdays = [ 'sön', 'mån', 'tis', 'ons', 'tor', 'fre', 'lör' ];

// Prevent caching of pages
$(document).on('pagecontainerhide', function (event, ui) { 
	ui.prevPage.remove(); 
});

function openBookingAdmin(sectionId) {
	if (screen.width < 700) {
		location.href= "/admin/bookings-d.php?sectionId=" + sectionId;		
	} else {
		location.href= "/admin/bookings-d.php?sectionId=" + sectionId;
	}
}

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
var checkedItems,
	fbStart,
	wday,
	startDate,
	startTime,
	endDate,
	endTime,
	nextDateClick;

$(document).on('pagecreate', "#page-book-part", function() {
    // bind events
	
	/**
	 * User chose start or end time for subbooking
	 */
	$("#book-combined-freebusy-bar ~ .freebusy-tic").click(function(event) {
		var hour = Math.floor(event.offsetX / parseInt($(this).css('width')) * 24);
		if (nextDateClick=="start") {
			startDate = new Date(fbStart.valueOf());
			startDate.setDate(startDate.getDate() + Number(this.dataset.day));
			startTime = hour;
		} else {
			endDate = new Date(fbStart.valueOf());
			endDate.setDate(endDate.getDate() + Number(this.dataset.day));
			endTime = hour;
		}
		nextDateClick = nextDateClick=="start" ? "end" : "start"; 
		updateBookedTimeframe();
		document.activeElement.blur();
	});

	/**
	 * User chose a new start date from date picker for subbooking
	 */
	$('#book-date-start').change(function(event) {
		startDate = new Date(this.value);
        if (startDate<fbStart || startDate.valueOf()>fbStart.valueOf()+7*24*60*60) {
            // scroll to chosen week
            fbStart = new Date(this.value);
            wday = fbStart.getDay() ? fbStart.getDay()-1 : 6; // Weekday, where Monday=0 ... Sunday=6
            fbStart.setDate(fbStart.getDate() - wday); // Should now be last Monday
            scrollDate(0);
        }
        nextDateClick = "end";
		updateBookedTimeframe();
	});
	
	/**
	 * User chose a new end date from date picker for subbooking
	 */
	$('#book-date-end').change(function(event) {
        endDate = new Date(this.value);
        nextDateClick = "start";
		updateBookedTimeframe();
	});

    /**
     * User chose a new start time from dropdown for subbooking
     */
	$('#book-time-start').change(function(event) {
		startTime = Number(this.value);
		updateBookedTimeframe();
	});

    /**
     * User chose a new end time from dropdown for subbooking
     */
	$('#book-time-end').change(function(event) {
		endTime = Number(this.value);
		updateBookedTimeframe();
	});
});

$(document).on('pageshow', "#page-book-part", function() {
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
    startTime = fbStart.getHours();
    endTime = fbStart.getHours();
    fbStart.setHours(0,0,0,0); // Midnight
    startDate = new Date(fbStart.valueOf());
    endDate = new Date(fbStart.valueOf());
    nextDateClick = "start";
    wday = fbStart.getDay() ? fbStart.getDay()-1 : 6; // Weekday, where Monday=0 ... Sunday=6
    fbStart.setDate(fbStart.getDate() - wday); // Should now be last Monday
    scrollDate(0);
    updateBookedTimeframe();
});

/**
 * Scrolls the currently shown freebusy bars to another start date
 * @param int offset Number of days to scroll
 */
function scrollDate(offset) {
    $.mobile.loading("show", {});
    // Calculate start and end of week
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
        updateBookedTimeframe();
        $.mobile.loading("hide", {});
    });
}

/**
 * Toggle the item between unselected and selected state, and get updated combined freebusy data
 * @param itemId ID of item to toggle
 */
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

/**
 * Show item details in popup
 * @param itemId ID of item to show
 */
function popupItemDetails(itemId) {
    $.mobile.loading("show", {});
    $.getJSON("book-part.php", { action: "ajaxItemDetails", id: itemId }, function(data, status) {
        $.mobile.loading("hide", {});
        $("#popup-item-details").html(data).popup('open', { transition: "pop", y: 0 });
    });
}

/**
 * Update currently chosen start and end date/time in user interface
 */
function updateBookedTimeframe() {
    // switch start and end time if start time is after end time
    if (endDate.valueOf()+endTime*60*60*1000 < startDate.valueOf()+startTime*60*60*1000) {
        if (nextDateClick == "start") {
            var temp = new Date(endDate.valueOf());
            endDate = new Date(startDate.valueOf());
            startDate = new Date(temp.valueOf());
            temp = endTime;
            endTime = startTime;
            startTime = temp;
        } else {
            endDate = new Date(startDate.valueOf());
            endTime = startTime;
        }
    }
    $("#book-date-start").val( startDate.toLocaleDateString("sv-SE") );
    $("#book-time-start").val( startTime ).selectmenu("refresh");
    $("#book-date-end").val( endDate.toLocaleDateString("sv-SE") );
    $("#book-time-end").val( endTime ).selectmenu("refresh");
    if (nextDateClick=="start") {
        $("#book-date-chooser-next-click").html("Klicka på önskat startdatum för att ändra datum.");
    } else {
        $("#book-date-chooser-next-click").html("Klicka på önskat slutdatum.");
    }
    $('#book-chosen-timeframe').css('left', ((startDate-fbStart)/1000/60/60+startTime)/24/7*100 + "%");
    $('#book-chosen-timeframe').css('width', ((endDate-startDate)/1000/60/60-startTime+endTime)/24/7*100 + "%");
    checkTimes();
}

/**
 * Check that the chosen range does not collide with existing bookings visible to the user
 * @param bool saveSubbooking Whether to also save the subbooking and go to booking summary
 */
function checkTimes(saveSubbooking=false) {
    $.mobile.loading("show", {});
    // Send times to server to check availability:
    $.getJSON("book-part.php", {
        action: "ajaxCheckTimes",
        ids: checkedItems,
        start: startDate.valueOf()/1000 + startTime*60*60,
        end: endDate.valueOf()/1000 + endTime*60*60,
        saveSubbooking: saveSubbooking
    }, function(data, status) {
        $.mobile.loading("hide", {});
		$("#book-btn-save-sub").prop("disabled", !data.timesOK);
        if (data.timesOK) {
        	if (saveSubbooking) {
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
        	}
    		$("#book-warning-conflict").hide();
        } else {
        	if (saveSubbooking) {
	            $("#ul-items-unavail").html("");
	            $.each(data.unavail, function( key, item ) {
	                $("#ul-items-unavail").append("<li>"+item+"</li>");
	            });
	            $("#popup-items-unavail").popup('open', { transition: "pop" });
        	} else {
        		$("#book-warning-conflict").show();
        	}
        }
    });
}




// ========== book-sum.php ==========
var reqCheckRadios;

$(document).on('pagecreate', "#page-book-sum", function() {
    // bind events
	
	/**
	 * Validate required checkboxes and radios before submitting the booking
	 */
    $("#form-booking").submit(function(event) {
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

/**
 * Show message if there is any
 */
$(document).on('pageshow', "#page-book-sum", function() {
    if ($("#msg-page-book-sum").html()) {
        setTimeout(function() {
            $("#popup-msg-page-book-sum").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

/**
 * Get item details and show them in a popup
 * @param int itemId ID of the item to show
 */
function popupItemDetails(itemId) {
    $.mobile.loading("show", {});
    $.getJSON("book-part.php", { action: "ajaxItemDetails", id: itemId }, function(data, status) {
        $.mobile.loading("hide", {});
        $("#popup-item-details").html(data).popup('open', { transition: "pop", y: 0 });
    });
}

/**
 * Remove a single item from booking
 * @param int bookedItemId ID of item to remove
 */
function removeItem(bookedItemId) {
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", {
        action: "ajaxRemoveItem",
        bookedItemId: bookedItemId
    }, function(data, status) {
        $.mobile.loading("hide", {});
        if (data.error) alert(data.error);
        else if (data.status=="booking empty") location.href="book-part.php";
        else location.reload();
    });
}

/**
 * Delete the whole booking
 * @param userId Used to redirect to userdata page for logged in users, or index for guests
 */
function deleteBooking(userId=0) {
	if (confirm("Är du säker på att du vill ta bort din bokning?")) {
	    $.mobile.loading("show", {});
	    $.getJSON("book-sum.php", { action: "ajaxDeleteBooking" }, function(data, status) {
	        $.mobile.loading("hide", {});
	        if (data.error) alert(data.error);
	        else if (userId) location.href="/userdata.php?action=bookingDeleted";
        	else location.href="/index.php?action=bookingDeleted";
	    });
	}
	return false;
}

/**
 * Mark an item as confirmed
 * @param int bookedItemId ID of item to confirm
 */
function confirmBookedItem(bookedItemId) {
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", { action: "ajaxConfirmBookedItem", bookedItemId: bookedItemId }, function(data, status) {
        $.mobile.loading("hide", {});
        if (data.error) alert(data.error);
        else {
        	$("#book-item-status-"+bookedItemId).html("Bekräftat");
            $("#book-item-btn-confirm-"+bookedItemId).hide();
        }
    });
}

/**
 * Set a price for a booked item
 * @param bookedItemId ID of item to set the price for
 */
function setPrice(bookedItemId) {
	var price = prompt("Pris för resursen (hela kronor):", "0");
	if (isNaN(price)) { alert(price + " är inte ett tal."); return; }
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", { action: "ajaxSetPrice", bookedItemId: bookedItemId, price: price }, function(data, status) {
        $.mobile.loading("hide", {});
        if (data.error) alert(data.error);
        else $("#book-item-price-"+bookedItemId).html(price + " kr");
    });
}



// ========== admin/index.php ==========
var questionId, questionType;

$(document).on('pagecreate', "#page-admin-section", function() {
    // bind events
	
	/**
	 * Get suggestions for users when adding section admin
	 */
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

    /**
     * Update question options when user changes question type 
     */
    $("input[type=radio][name=sec-question-type]").click( function() {
        showQuestionOptions(this.value);
    });
});

$(document).on('pageshow', "#page-admin-section", function() {
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

/**
 * Update booking question options
 * @param type Question type to show options for (radio|checkbox|text|number)
 */
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

/**
 * Get a list of all questions defined in section
 */
function getQuestions() {
    $.mobile.loading("show", {});
    $.get("index.php", { action: "ajaxGetQuestions" }, function(data, status) {
        $("#sec-questions").html(data).listview("refresh");
        $.mobile.loading("hide", {});
    });
}

/**
 * Clear all inputs for booking questions
 */
function clearQuestionInputs() {
    $("#sec-question-caption").val("");
    $("#sec-question-choices").val("");
    $("#sec-question-length").val("");
    $("#sec-question-min").val("");
    $("#sec-question-max").val("");
}

/**
 * Save currently edited booking question
 */
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

/**
 * Delete booking question
 * @param id ID of question to delete
 * @returns
 */
function deleteQuestion(id) {
$.mobile.loading("show", {});
	$.getJSON("index.php", { action: "ajaxDeleteQuestion", id: id }, function(data, status) {
	$.mobile.loading("hide", {});
    getQuestions();
   });
}

/**
 * Show a popup for editing a booking question
 * @param id ID of booking question to edit
 */
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

/**
 * Add a new section admin
 * @param userId UserId of new admin
 */
function addAdmin(userId) {
    $.getJSON("index.php", {action: "ajaxAddSectionAdmin", id: userId}, function(data, status) {
        if (data['html']) {
            $("#ul-sec-admins").html(data['html']).listview("refresh");
            $("#sec-adm-autocomplete-input").val("");
            $("#sec-adm-autocomplete").html("");
        } else {
            alert(data['error']);
        }
    });
}

/**
 * Revoke section admin permissions for a user
 * @param int userId ID of the affected user
 * @param int currentUserId ID of user executing the request. Used for special behaviour if user revokes his|her own permissions.
 * @param string name Name of affected user
 */
function removeAdmin(userId, currentUserId, name) {
    if (confirm('Du håller på att återkalla admin-behörighet för ' + (currentUserId==userId ? "dig själv" : (name ? name : "(okänd)")) + '. Vill du fortsätta?')) {
        $.getJSON("index.php", {action: "ajaxRemoveSectionAdmin", id: userId}, function(data, status) {
            if (data['html']) {
                $("#ul-sec-admins").html(data['html']).listview("refresh");
                if (currentUserId==userId) location.reload();
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
	
	/**
	 * Set timeout for saving category caption
	 */
    $(document).off('input', "#cat-caption").on('input', "#cat-caption", function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "caption", this.value);
    });

    /**
     * Set timeout for saving changed parent category
     */
    $(document).off('change', "#cat-parentId").on('change', "#cat-parentId", function() {
        setCatProp("parentId", this.value);
    });

    /**
     * Set timeout for saving prebook message
     */
    $(document).off('input', "#cat-prebookMsg").on('input', "#cat-prebookMsg", function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "prebookMsg", this.value);
    });

    /**
     * Set timeout for saving postbook message
     */
    $(document).off('input', "#cat-postbookMsg").on('input', "#cat-postbookMsg", function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "postbookMsg", this.value);
    });

    /**
     * Set timeout for saving buffer time after/around bookings
     */
    $(document).off('input', "#cat-bufferAfterBooking").on('input', "#cat-bufferAfterBooking", function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "bufferAfterBooking", this.value);
    });

    /**
     * Save new category image
     */
    $(document).off('change', "#file-cat-img").on('change', "#file-cat-img", function() {
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
                    $('#cat-img-preview').attr("src", "/image.php?type=category&id=" + data.id + "&" + d.getTime()).show().trigger( "updatelayout" );
                } else {
                    alert(data.error);
                }
                $.mobile.loading("hide", {});
            },
        });
    });

    /**
     * Get suggestions of users for category contact person
     */
    $(document).off("filterablebeforefilter", "#cat-contact-autocomplete").on("filterablebeforefilter", "#cat-contact-autocomplete", function ( e, data ) {
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

    /**
     * Get suggestions of users for adding category admins
     */
    $(document).off("filterablebeforefilter", "#cat-adm-autocomplete").on("filterablebeforefilter", "#cat-adm-autocomplete", function ( e, data ) {
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

    /**
     * Step 1 of adding category admin
     * Triggered when user choses group or specific user for new access rights (step 1)
     * Remembers choice and shows step 2
     */
    $(document).off("change", ".cat-access-id").on("change", ".cat-access-id", function(e, data) {
        $(".cat-access-level").attr("checked", false).checkboxradio("refresh");
        chosenAccessId = this.value;
        $("#cat-access-levels").show();
    });

    /**
     * Step 2 of adding new category admin
     * Triggered when user choses access level (step 2)
     * Saves new admin and clears input fields.
     */
    $(document).off("change", ".cat-access-level").on("change", ".cat-access-level", function() {
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

    /**
     * Delete category
     */
    $(document).off('click', "#delete-cat").on('click', "#delete-cat", function() {
        if (confirm("Du håller på att ta bort kategorin och alla poster i den. Fortsätta?")) {
            $.mobile.loading("show", {});
            $.getJSON("category.php", {action: "ajaxDeleteCat"}, function(data, status) {
                $.mobile.loading("hide", {});
            	if (data.status=="OK") {
            		location.href="index.php";
            	} else {
            		alert("Kunde inte radera kategorin. Kontakta administratören, tack.");
            	}
            });
        }
    });
});

$(document).on('pageshow', "#page-admin-category", function() {
    // Show message if there is any
    if ($("#msg-page-admin-category").html()) {
        setTimeout(function() {
            $("#popup-msg-page-admin-category").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
    chosenAccessId=0;
});

/**
 * Revoke category admin permissions
 * @param userId ID of affected user
 */
function unsetAccess(id) {
    $.mobile.loading("show", {});
    $.get("?action=ajaxSetAccess&id=" + encodeURIComponent(userId) + "&access=" + ACCESS_NONE, function(data, status) {
        if (data!=0) {
            $("#assigned-cat-access").html(data).enhanceWithin();
        } else {
            alert("Kunde inte återkalla behörigheten.");
        }
        $.mobile.loading("hide", {});
    });
}

/**
 * Saves a category property
 * @param name Name of the property
 * @param val Value of the property
 */
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

/**
 * Saves contact user and clears user search field
 * @param userId ID of contact user
 */
function setContactUser(userId) {
    $.getJSON("category.php", { action: "ajaxSetContactUser", id: userId }, function(data, status) {
        $("#cat-contact-data").html(data.html);
        $("#cat-contact-autocomplete-input").val("");
        $("#cat-contact-autocomplete").html("");
    });
}

/**
 * Toggles the state of a question between on, off, inherited and mandatory
 * @param id Question ID
 */
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
	
	/**
	 * Set timeout for saving item caption
	 */
    $("#item-caption").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setItemProp, 1000, "caption", this.value);
    });
    
    /**
     * Set timeout for saving item description
     */
    $("#item-description").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setItemProp, 1000, "description", this.value);
    });

    /**
     * Save item active state
     */
    $("#item-active").click(function() {
        setItemProp("active", this.checked ? 1 : 0);
    });
    
    /**
     * Delete item
     */
    $("#delete-item").click(function() {
        if (confirm("Du håller på att ta bort utrustningen. Fortsätta?")) {
            $.getJSON("item.php", { action: "ajaxDeleteItem" }, function(data, status) {
                if (data.status=='OK') location.href="category.php?expand=items";
                else alert("Något har gått fel. :(");
            });
        }
    });

    /**
     * Add a new image to item
     */
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
    
    /**
     * Save item image caption
     */
    $("#item-images").on("input", ".item-img-caption", function(e, data) {
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

$(document).on('pageshow', "#page-admin-item", function() {
    // Show message if there is any
    if ($("#msg-page-admin-item").html()) {
        setTimeout(function() {
            $("#popup-msg-page-admin-item").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

/**
 * Save an item property
 * @param name Property name
 * @param val Property value
 */
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

/**
 * Delete an item image
 * @param id Image ID to delete
 */
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

$(document).on('pageshow', "#page-userdata", function() {
    // Show message if there is any
    if ($("#msg-page-userdata").html()) {
        setTimeout(function() {
            $("#popup-msg-page-userdata").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

/**
 * Delete user account
 */
function deleteAccount() {
	if (window.confirm("Bekräfta att du vill radera ditt konto i resursbokningen. Alla dina bokningar och persondata tas bort från systemet och kan inte återställas!")) {
		location.href="userdata.php?action=deleteAccount";
	}
}


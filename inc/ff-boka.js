// General vars
var toutSetValue,
	toutSearch,
	toutUpdateRepeatPreview,
    weekdays = [ 'sön', 'mån', 'tis', 'ons', 'tor', 'fre', 'lör' ];

// Prevent caching of pages
$(document).on('pagecontainerhide', function (event, ui) { 
    ui.prevPage.remove(); 
});

function showHelp() {
    $.get("?action=help", function( data ) {
        $("#help-content").html(data);
		$("#popup-help").popup("open", { transition: "slide" });
    });
}

/** Convert a date into ISO format (YYYY-mm-dd) */
function dateToISO(date) {
	return date.getFullYear().toString() + '-' + (date.getMonth() + 1).toString().padStart(2, 0) +
    '-' + date.getDate().toString().padStart(2, 0);
}

/**
 * Get a GET request variable. Found on https://stackoverflow.com/questions/831030/how-to-get-get-request-parameters-in-javascript
 * @param name The name of the GET variable to return
 * @returns The content of the requested variable, or undefined if the variable has no value or does not exist
 */
function get(name){
	if(name=(new RegExp('[?&]'+encodeURIComponent(name)+'=([^&]*)')).exec(location.search)) {
		return decodeURIComponent(name[1]);
	}
}


function openBookingAdmin(baseUrl, sectionId) {
    if (screen.width < 800) {
        location.href= baseUrl + "admin/bookings-m.php?sectionId=" + sectionId;
    } else {
        location.href= baseUrl + "admin/bookings-d.php?sectionId=" + sectionId;
    }
}


/* Cookie functions, taken from w3schools.com */
/**
 * setCookie: Set a cookie in root
 * @param string cname The name of the cookie
 * @param string cvalue The value to set
 * @param int exdays Expire after x days.
 */
function setCookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    var expires = "expires="+d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/;SameSite=Strict";
}
  
/**
 * getCookie: read an existing cookie
 * @param {*} cname The name of the cookie
 * @return Returns the value of the cookie. If the cookie does not exist, returns an empty string.
 */
function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}
  

// ========== index.php ==========
$(document).on('pagecreate', "#page-start", function(e) {
    // bind events

    /** Global search */
    $( "#search-autocomplete" ).on( "filterablebeforefilter", function ( e, data ) {
        var $ul = $( this ),
            $input = $( data.input ),
            value = $input.val(),
            html = "";
        $ul.html( "" );
        if ( value && value.length > 2 ) {
            $ul.html( "<li><div class='ui-loader'><span class='ui-icon ui-icon-loading'></span></div></li>" );
            $ul.listview( "refresh" );
            $.getJSON("index.php", { action: "ajaxGlobalSearch", q: value }, function(data, status) {
                if (data.status == "OK") {
                    $.each( data.sections, function ( i, sec ) {
                        html += "<li class='wrap' data-filtertext='" + value + "'><a href='book-part.php?sectionId=" + sec.id + "'><h2>" + sec.name + "</h2><p>" + sec.matches + "</p></a></li>";
                    });
                    if (data.sections.length==0) {
                        html += "<li class='wrap'>Sökningen på <b>" + value + "</b> gav ingen träff. Försök formulera om din sökning.</li>";
                    }
                } else html += "<li class='wrap'>Sökningen misslyckades.</li>";
                $ul.html( html );
                $ul.listview( "refresh" );
                $ul.trigger( "updatelayout");
            });
        }
    });
});

$(document).on('pageshow', "#page-start", function() {
    // Show message if there is any
    if ($("#msg-page-start").html()) {
        setTimeout(function() {
            $("#popup-msg-page-start").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

function answerPoll(pollId, choiceId) {
    $.getJSON("index.php", {
        action: "ajaxAnswerPoll",
        pollId: pollId,
        choiceId: choiceId
    }, function(data, status) {
    	$("#poll-page-start").hide();
        $("#popup-poll-page-start").popup('open');
        setTimeout(function() { $("#popup-poll-page-start").popup('close'); }, 2000);
    });
}


// ========== bookings-m.php ==========
var startDate;

$(document).on('pagecreate', "#page-bookings", function() {
    // bind events
    $(document).on('click', ".freebusy-busy, .link-unconfirmed", function() {
        window.open("../book-sum.php?bookingId=" + this.dataset.bookingId, "booking"+this.dataset.bookingId);
    });
});

$(document).on('pageshow', "#page-bookings", function() {
    startDate = new Date();
    startDate.setHours(0,0,0,0); // Midnight
    wday = startDate.getDay() ? startDate.getDay()-1 : 6; // Weekday, where Monday=0 ... Sunday=6
    startDate.setDate(startDate.getDate() - wday); // Should now be last Monday
    scrollDateBookings(0);
    if ($("#bookings-list-unconfirmed").html!="") {
    	$("#bookings-tab-unconfirmed").collapsible("expand");
    }
});

// Show details for an item
function showItemDetails(itemId) {
    window.open("../item-details.php?itemId=" + itemId, "itemDetails" + itemId);
}

// Scroll by x months, and get updated booking information
// @param int offset Number of days to scroll
function scrollDateBookings(offset) {
    $.mobile.loading("show", {});
    // Calculate start and end of week
    startDate.setDate(startDate.getDate() + offset);
    var endDate = new Date(startDate.valueOf());
    endDate.setDate(endDate.getDate() + 6);
    var readableRange = "må " + startDate.getDate() + "/" + (startDate.getMonth()+1);
    if (startDate.getFullYear() != endDate.getFullYear()) readableRange += " '"+startDate.getFullYear().toString().substr(-2);
    readableRange += " &ndash; sö " + endDate.getDate() + "/" + (endDate.getMonth()+1) + " '"+endDate.getFullYear().toString().substr(-2);
    // Get updated freebusy information for new time span
    $.getJSON("bookings-m.php", {
        action: "ajaxGetFreebusy",
        start: startDate.valueOf()/1000
    }, function(data, status) {
        $("#bookings-current-range-readable").html( readableRange );
        $("#bookings-list-unconfirmed").html("");
        $.each(data.unconfirmed, function( index, value ) {
            $("#bookings-list-unconfirmed").append(value);
        });
        $("#bookings-list-unconfirmed").listview("refresh");
        $("#bookings-unconfirmed-count").text("("+data.unconfirmed.length+")");
        $.each(data.freebusy, function(key, value) { // key will be "item-nn"
            $("#freebusy-"+key).html(value);
        });
        $.mobile.loading("hide", {});
    });
}

// Add a new booking on behalf of another user    
function addBooking(userId) {
    $.getJSON("bookings-m.php", {
        action: "ajaxAddBookingOnBehalf",
        userId: userId
    }, function(data) {
        if (data.status=="OK") {
            openSidePanelOrWindow("../book-part.php");
        }
        else alert("Något har gått fel. Kontakta systemadmin.");
        $('#popup-add-booking').dialog('close');
    });
}



// ========== book-part.php ==========
var bookingStep,
    checkedItems,
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
     * User chose start or end time for booking items on freebusy bar
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
    });

    /**
     * User chose a new start date from date picker for booking items
     */
    $('#book-date-start').change(function(event) {
		startDate = new Date(this.value+"T00:00:00");
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
     * User chose a new end date from date picker for booking items
     */
    $('#book-date-end').change(function(event) {
        endDate = new Date(this.value+"T00:00:00");
        nextDateClick = "start";
        updateBookedTimeframe();
    });

    /**
     * User chose a new start time from dropdown for booking items
     */
    $('#book-time-start').change(function(event) {
        startTime = Number(this.value);
        updateBookedTimeframe();
    });

    /**
     * User chose a new end time from dropdown for booking items
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
    bookingStep=1;
    // Uncheck all items
    checkedItems = {};
    $(".book-item").removeClass("item-checked");
    // Initialise date chooser
    if (get('start') && get('end')) {
    	// If start/end time have been passed as GET parameters startTime and endTime, use them
    	// Parameters are expected as unix timestamp in seconds.
        fbStart = new Date(parseInt(get('start'))*1000);
        fbStart.setHours(0,0,0,0); // Midnight
    	startDate = new Date(parseInt(get('start'))*1000);
    	startTime = startDate.getHours();
    	startDate.setHours(0,0,0,0);
    	endDate = new Date(parseInt(get('end'))*1000);
    	endTime = endDate.getHours();
    	endDate.setHours(0,0,0,0);
    } else {
        fbStart = new Date();
    	startTime = fbStart.getHours();
    	endTime = fbStart.getHours();
        fbStart.setHours(0,0,0,0); // Midnight
    	startDate = new Date(fbStart.valueOf());
    	endDate = new Date(fbStart.valueOf());
    }
    nextDateClick = "start";
    wday = fbStart.getDay() ? fbStart.getDay()-1 : 6; // Weekday, where Monday=0 ... Sunday=6
    fbStart.setDate(fbStart.getDate() - wday); // Should now be last Monday
    scrollDate(0);
    updateBookedTimeframe();
    if (get('selectItemId')) {
    	setTimeout(toggleItem, 500, get('selectItemId'));
    }
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
                    $("#book-access-msg").append("<p><b>OBS: Bokningen är preliminär.</b> För ditt urval av resurser kommer bokningen behöva bekräftas av materialansvarig.</p>"); 
                }
            }
            $("#book-combined-freebusy-bar").html(data.freebusyCombined);
            checkTimes();
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
    $.getJSON("book-part.php", {
        action: "ajaxItemDetails",
        id: itemId,
        bookingStep: bookingStep
    }, function(data, status) {
        $("#item-caption").html(data.caption);
        $("#item-details").html(data.html);
        $("#popup-item-details").popup('open', { transition: "pop", y: 0 });
        $.mobile.loading("hide", {});
        if (bookingStep==2 && data.start!==null) {
            // In step 2, show elements to change the item's booking
            $("#book-item-booking-details").show();
            checkedItems = {};
            checkedItems[itemId] = true;
            // Initialise date chooser
            startDate = new Date(Number(data.start)*1000);
            startTime = startDate.getHours();
            startDate.setHours(0,0,0,0); // Midnight
            endDate = new Date(Number(data.end)*1000);
            endTime = endDate.getHours();
            endDate.setHours(0,0,0,0); // Midnight
            fbStart = new Date(startDate.valueOf());
            wday = fbStart.getDay() ? fbStart.getDay()-1 : 6; // Weekday, where Monday=0 ... Sunday=6
            fbStart.setDate(fbStart.getDate() - wday); // Should now be last Monday
            nextDateClick = "start";
            $("#book-item-booked-start").html(startDate.toLocaleDateString("sv-SE") + ' ' + startTime + ':00');
            $("#book-item-booked-end").html(endDate.toLocaleDateString("sv-SE") + ' ' + endTime + ':00');
            scrollItemDate(0);
            $("#book-item-price").val(data.price);
        } else {
            $("#book-item-booking-details").hide();            
        }
    });
}

/**
 * Update currently chosen start and end date/time in user interface
 */
function updateBookedTimeframe() {
    // swap start and end time if start time is after end time
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
    $("#book-date-start").val( dateToISO(startDate) );
    $("#book-time-start").val( startTime ).selectmenu("refresh");
    $("#book-date-end").val( dateToISO(endDate) );
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
 * @param bool save Whether to also save the booking and go to booking summary
 */
function checkTimes(save=false) {
    $.mobile.loading("show", {});
    // Send times to server to check availability:
    $.getJSON("book-part.php", {
        action: (save ? "ajaxSave" : "ajaxCheckTimes"),
        bookingStep: bookingStep,
        ids: checkedItems,
        start: startDate.valueOf()/1000 + startTime*60*60,
        end: endDate.valueOf()/1000 + endTime*60*60,
    }, function(data, status) {
        $.mobile.loading("hide", {});
        $("#book-btn-save-part").prop("disabled", !data.timesOK);
        if (data.timesOK) {
            if (save && bookingStep==1) {
                // Reset times section to prepare for next booking
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
            } else if (save && bookingStep==2) {
                $("#popup-item-details").popup("close");
				setTimeout(function() { location.reload() },200);
            }
            $("#book-warning-conflict").hide();
        } else {
            if (save) {
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
var reqCheckRadios, itemsToConfirm, repeatType="";

$(document).on('pagecreate', "#page-book-sum", function() {
    // bind events
    
    /**
     * User changes start or end time for booking item on freebusy bar
     */
    $("#book-item-select-dates .freebusy-tic").click(function(event) {
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
    });

	/** User changed number of occurences in the booking series dialog */
    $(document).on('input', '#repeat-count', function() {
		if (repeatType!="") {
	        clearTimeout(toutUpdateRepeatPreview);
	        toutUpdateRepeatPreview = setTimeout(repeatPreview, 600, this.value, repeatType);
		}
	});

    /**
     * User chose a new start date from date picker for booking items
     */
    $('#book-date-start').change(function(event) {
        startDate = new Date(this.value+"T00:00:00");
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
     * User chose a new end date from date picker for booking items
     */
    $('#book-date-end').change(function(event) {
        endDate = new Date(this.value+"T00:00:00");
        nextDateClick = "start";
        updateBookedTimeframe();
    });

    /**
     * User chose a new start time from dropdown for booking items
     */
    $('#book-time-start').change(function(event) {
        startTime = Number(this.value);
        updateBookedTimeframe();
    });

    /**
     * User chose a new end time from dropdown for booking items
     */
    $('#book-time-end').change(function(event) {
        endTime = Number(this.value);
        updateBookedTimeframe();
    });
    
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
    bookingStep=2;
});

/**
 * Get a preview for a new booking series
 * @param int count Number of bookings in the series, including the original one
 * @param int type Type of repetition (day|week|month)
 */
function repeatPreview(count, type) {
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", {
        action: "ajaxRepeatPreview",
        count: count,
		type: type
    }, function(data, status) {
        $.mobile.loading("hide", {});
        if (data.error) alert(data.error);
        else $('#repeat-preview').html(data.html);
		$('#repeat-create').prop("disabled", false);
    });
}

/** User chose to create a booking series */
function repeatCreate() {
    var form = document.forms.formBooking;
    form.elements.action.value = "repeatCreate";
    form.submit();
}

/** Remove the booking from its booking series (but keep it) */
function unlinkBooking() {
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", {
        action: "ajaxUnlinkBooking"
    }, function(data, status) {
        $.mobile.loading("hide", {});
        $('#series-panel').html(data.html).enhanceWithin();
		alert("Bokningen har nu tagits bort från bokningsserien.");
    });
}

/** Remove all bookings from series (but keep them) */
function unlinkSeries() {
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", {
        action: "ajaxUnlinkSeries"
    }, function(data, status) {
        $.mobile.loading("hide", {});
        $('#series-panel').html(data.html).enhanceWithin();
		alert("Bokningen har nu tagits bort från bokningsserien.");
    });
}

function deleteSeries() {
	if (confirm("OBS! Om du fortsätter raderas alla kommande tillfällen i den här serien. Tillfällen som har passerat samt det första tillfället i serien raderas dock inte. Vill du fortsätta?")) {
	    $.mobile.loading("show", {});
	    $.getJSON("book-sum.php", {
	        action: "ajaxDeleteSeries"
	    }, function(data, status) {
	        $.mobile.loading("hide", {});
			alert("Bokningsserien har nu raderats, förutom det första tillfället samt de tillfällen som har passerat.");
			if (data.gotoBookingId) location.href="book-sum.php?bookingId="+data.gotoBookingId;
			else $('#series-panel').html(data.html).enhanceWithin();
	    });
	}
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
        else location.reload();
    });
}

/**
 * Set the price for a booked item
 * @param bookedItemId
 */
function setItemPrice(bookedItemId, lastPrice) {
    var price = prompt("Pris för den här resursen (hela kr):", lastPrice);
    if (price==="" || price) { // otherwise user hit cancel
        $.mobile.loading("show", {});
        $.getJSON("book-sum.php", {
            action: "ajaxSetItemPrice",
            bookedItemId: bookedItemId,
            price: price
        }, function(data, status) {
            $.mobile.loading("hide", {});
            if (data.error) alert(data.error);
            else location.reload();
        });
    }
}

/**
 * Function for admins to input how much the client has paid.
 */
function setPaid(lastPaid) {
    var paid = prompt("Hur mycket är betalt? (hela kronor)", lastPaid);
    if (paid==="" || paid) { // otherwise user hit cancel
        $.mobile.loading("show", {});
        $.getJSON("book-sum.php", {
            action: "ajaxSetPaid",
            paid: paid
        }, function(data, status) {
            $.mobile.loading("hide", {});
            if (data.error) alert(data.error);
            else location.reload();
        });
    }
}

/**
 * Delete the whole booking
 * @param userId Used to redirect to userdata page for logged in users, or index for guests
 * @param baseUrl Base URL of the installation (for redirection after successful deletion)
 */
function deleteBooking(userId=0, baseUrl) {
    if (confirm("Är du säker på att du vill ta bort din bokning?")) {
        $.mobile.loading("show", {});
        $.getJSON("book-sum.php", { action: "ajaxDeleteBooking" }, function(data, status) {
            $.mobile.loading("hide", {});
            if (data.error) alert(data.error);
            else if (userId) location.href = baseUrl + "userdata.php?action=bookingDeleted";
            else location.href = baseUrl + "index.php?action=bookingDeleted";
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
			// Remove item from array of items to confirm
			itemsToConfirm.splice(itemsToConfirm.indexOf(bookedItemId), 1);
			// Update user interface
            $("#book-item-status-"+bookedItemId).html("Bekräftat");
            $("#book-item-btn-confirm-"+bookedItemId).hide();
            $("#book-item-btn-reject-"+bookedItemId).hide();
        	if (data.allManaged == true) {
				$("#btn-confirm-all-items").hide();
				alert("Alla obekräftade poster i bokningen har nu hanterats. Om du har justerat några start- eller sluttider bör du skriva något om det i meddelande-rutan längre ner. Skicka också gärna en uppdaterad bokningsbekräftelse genom att klicka på 'Spara ändringar' längst ner på sidan.");
			}
        }
    });
}

/**
 * Mark all items in booking as confirmed
 */
function confirmAllItems() {
	$.each(itemsToConfirm, function(index, bookedItemId) {
		confirmBookedItem(bookedItemId);
	});
}

/**
 * Mark an item as rejected
 * @param int bookedItemId ID of item to reject
 */
function rejectBookedItem(bookedItemId) {
    $.mobile.loading("show", {});
    $.getJSON("book-sum.php", { action: "ajaxRejectBookedItem", bookedItemId: bookedItemId }, function(data, status) {
        $.mobile.loading("hide", {});
        if (data.error) alert(data.error);
        else {
            $("#book-item-status-"+bookedItemId).html("Avböjt");
            $("li#item-"+bookedItemId).addClass("rejected");
            $("li#item-"+bookedItemId+" a").removeClass("ui-btn-c").addClass("ui-btn-a");
            $("#book-item-btn-confirm-"+bookedItemId).parent().hide();
        	if (data.allManaged == true) alert("Alla obekräftade poster i bokningen har nu hanterats. Kom ihåg att skriva en förklaring i meddelande-fältet längre ner på sidan så att den bokande förstår varför du har avböjt den här posten. Klicka sedan på 'Slutför bokningen' längst ner på sidan för att skicka ut en uppdaterad bokningsbekräftelse.");
        	else alert("Du har nekat bokningsförfrågan för den här posten.\n\nKom ihåg att skriva en förklaring i meddelande-fältet längre ner på sidan så att den bokande förstår varför du har avböjt den här posten. Du kan sedan välja att skicka ut en uppdaterad bokningsbekräftelse genom att klicka på 'Slutför bokningen' längst ner, eller först göra fler justeringar på bokningen.");
        }
    });
}


/**
 * Scrolls the freebusy bar of a single booked item to another start date
 * @param int offset Number of days to scroll
 */
function scrollItemDate(offset) {
    $.mobile.loading("show", {});
    // Calculate start and end of week
    fbStart.setDate(fbStart.getDate() + offset);
    var fbEnd = new Date(fbStart.valueOf());
    fbEnd.setDate(fbEnd.getDate() + 6);
    var readableRange = "må " + fbStart.getDate() + "/" + (fbStart.getMonth()+1);
    if (fbStart.getFullYear() != fbEnd.getFullYear()) readableRange += " '"+fbStart.getFullYear().toString().substr(-2);
    readableRange += " &ndash; sö " + fbEnd.getDate() + "/" + (fbEnd.getMonth()+1) + " '"+fbEnd.getFullYear().toString().substr(-2);
    // Get freebusy bar
    $.getJSON("book-sum.php", { action: "ajaxFreebusyItem", start: fbStart.valueOf()/1000 }, function(data, status) {
        $("#book-current-range-readable").html( readableRange );
        $("#book-freebusy-bar-item").html( data.freebusyBar );
        updateBookedTimeframe();
        $.mobile.loading("hide", {});
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
                    html += "<li style='cursor:pointer;' title='Lägg till " + val.name + " som LA-admin' onClick='addAdmin(" + val.userId + ");'>" + val.userId + " " + (val.name ? val.name : "(inget namn tillgängligt)") + "</li>";
                });
                if (data.length==0) {
                    if (Number(value)) html += "<li style='cursor:pointer;' title='Lägg till medlem med medlemsnummer " + Number(value) + " som LA-admin' onClick='addAdmin(" + Number(value) + ");'>" + Number(value) + " (skapa ny användare)</li>";
                    else html += "<li>Sökningen på <i>" + value + "</i> gav ingen träff. Testa ange medlemsnummer istället.</li>";
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
        if (data['currentUserId']==userId) location.reload();
        else if (data['html']) {
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
            if (typeof data.html != 'undefined') {
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
var t, $tag_box;

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
     * Set contact name
     */
    $(document).off('input', "#cat-contactName").on('input', "#cat-contactName", function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "contactName", this.value);
    });

    /**
     * Set contact phone
     */
    $(document).off('input', "#cat-contactPhone").on('input', "#cat-contactPhone", function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "contactPhone", this.value);
    });

    /**
     * Set contact mail
     */
    $(document).off('input', "#cat-contactMail").on('input', "#cat-contactMail", function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setCatProp, 1000, "contactMail", this.value);
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
                    $('#cat-img-preview').attr("src", "../image.php?type=category&id=" + data.id + "&" + d.getTime()).show().trigger( "updatelayout" );
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
    $(document).off("input", "#cat-contact-autocomplete-input").on("input", "#cat-contact-autocomplete-input", function ( e, data ) {
        clearTimeout(toutSearch);
        var value = this.value;
        toutSearch = setTimeout(function() {
	        var $ul = $("#cat-contact-autocomplete"),
	            html = "";
	        $ul.html( "" );
	        if ( value && value.length > 2 ) {
	            $ul.html( "<li><div class='ui-loader'><span class='ui-icon ui-icon-loading'></span></div></li>" );
	            $ul.listview( "refresh" );
	            $.getJSON("index.php", {action: "ajaxFindUser", q: value}, function(data, status) {
	                $.each( data, function ( i, val ) {
	                    html += "<li style='cursor:pointer;' title='Sätt " + val['name'] + " som kontaktperson' onClick=\"setCatProp('contactUserId', " + val['userId'] + ");\">" + val['userId'] + " " + (val['name'] ? val['name'] : "(ingen persondata tillgänglig)") + "</li>";
	                });
	                if (data.length==0) {
	                    if (Number(value)) html += "<li style='cursor:pointer;' title='Sätt medlem med medlemsnummer " + Number(value) + " som kontaktperson' onClick=\"setCatProp('contactUserId', " + Number(value) + ");\">" + Number(value) + " (ny användare)</li>";
	                    else html += "<li>Sökningen på <i>" + value + "</i> gav ingen träff</li>";
	                }
	                $ul.html( html );
	                $ul.listview( "refresh" );
	                $ul.trigger( "updatelayout");
	            });
	        }
        }, 300);
    });

    /**
     * Get suggestions of users for adding category admins
     */
    $(document).off("input", "#cat-adm-autocomplete-input").on("input", "#cat-adm-autocomplete-input", function(e, data) {
        clearTimeout(toutSearch);
        var value = this.value;
        toutSearch = setTimeout(function() {
            var $ul = $("#cat-adm-autocomplete"),
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
        }, 300);
    });

    /**
     * Step 1 of adding category admin
     * Triggered when user choses group or specific user for new access rights (step 1)
     * Remembers choice and shows step 2. Disables admin levels if group is chosen.
     */
    $(document).off("change", ".cat-access-id").on("change", ".cat-access-id", function(e, data) {
        $(".cat-access-level").attr("checked", false).checkboxradio("refresh");
        chosenAccessId = this.value;
		if (this.value=="") $("#cat-access-levels").hide();
        else $("#cat-access-levels").show();
        // Enable admin levels only for specific members and some groups
		if (this.value.search(/^(accessExternal|accessMember|accessLocal|Valfritt uppdrag|Hjälpledare.*|Ledare)$/) == -1) {
			$("input[type='radio'].cat-access-level-adm").checkboxradio('enable');
		} else {
			$("input[type='radio'].cat-access-level-adm").checkboxradio('disable');
		}
    });

    /**
     * Step 2 of adding new category admin
     * Triggered when user choses access level (step 2)
     * Saves new admin and clears input fields.
     */
    $(document).off("change", ".cat-access-level").on("change", ".cat-access-level", function() {
        $.mobile.loading("show", {});
        $("#cat-access-levels").hide();
        $("select.cat-access-id").val("").selectmenu("refresh");
        $("#cat-adm-autocomplete-input").val("");
        $("#cat-adm-autocomplete").html("");
        $.getJSON("?action=ajaxSetAccess&id="+encodeURIComponent(chosenAccessId)+"&access="+this.value, function(data, status) {
            $("#assigned-cat-access").html(data.html).enhanceWithin();
            $("#assigned-cat-access a.ajax-input").addClass('change-confirmed');
            setTimeout(function(){ $("#assigned-cat-access a.ajax-input").removeClass("change-confirmed"); }, 1500);
            $.mobile.loading("hide", {});
            if (data.message!="") alert(data.message);
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
    
    /**
     * Save new attachment file
     */
    $(document).off('change', "#cat-file-file").on('change', "#cat-file-file", function() {
        // Save file via ajax: https://makitweb.com/how-to-upload-image-file-using-ajax-and-jquery/
        var fd = new FormData();
        var file = $('#cat-file-file')[0].files[0];
        fd.append('file', file);
        fd.append('action', "ajaxAddFile");
        $.mobile.loading("show", {});

        $.ajax({
            url: 'category.php',
            type: 'post',
            data: fd,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(data) {
                $.mobile.loading("hide", {});
                if (data.status=="OK") {
                	$("#cat-file-file")[0].value="";
            		$("#cat-attachments").html(data.html).enhanceWithin();
                }
                else alert(data.error);
            },
            error: function(xhr, status, error) {
                $.mobile.loading("hide", {});
            	alert("Kunde inte spara. Kan inte få kontakt med servern.");
            }
        });    	
    });
});

$(document).on('pageshow', "#page-admin-category", function() {
    setCatProp("onlyGetContactData", "");

    t = $("#cat-sendAlertTo").tagging({
        "forbidden-chars": ["<", ">", " ", ","],
        "edit-on-delete": false,
        "tag-char": "✉"
    });
    $sendAlertTo = t[0]; // This is the $tag_box object of the first captured div

    $sendAlertTo.on( "add:after", function ( elem, text, tagging ) {
        $.mobile.loading("show", {});
        $.getJSON("category.php", {
            action: "ajaxAddAlert", 
            sendAlertTo1: text
        }).done(function(data, status) {
            $.mobile.loading("hide", {});
            if (data.status=="error") {
                $("#cat-sendAlertTo").tagging("remove", text);
                alert(data.error);
            } else {
                $("#cat-saved-indicator").addClass("saved");
                setTimeout(function(){ $("#cat-saved-indicator").removeClass("saved"); }, 2500);
            }
        });
    });
    $sendAlertTo.on( "remove:after", function( elem, text, tagging) {
        $.mobile.loading("show", {});
        $.getJSON("category.php", {
            action: "ajaxDeleteAlert", 
            sendAlertTo1: text
        }).done(function(data, status) {
            $.mobile.loading("hide", {});
            if (data.status=="error") {
            } else {
                $("#cat-saved-indicator").addClass("saved");
                setTimeout(function(){ $("#cat-saved-indicator").removeClass("saved"); }, 2500);
            }
        });
    });
      

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
    $.getJSON("?action=ajaxSetAccess&id=" + encodeURIComponent(id) + "&access=NULL", function(data, status) {
        $("#assigned-cat-access").html(data.html).enhanceWithin();
        $.mobile.loading("hide", {});
        if (data.message!="") alert(data.message);
    });
}

/**
 * Saves a category property and updates the contact data display
 * @param name Name of the property
 * @param val Value of the property
 */
function setCatProp(name, val) {
    $.mobile.loading("show", {});
    $.getJSON("category.php", {
    	action: "ajaxSetCatProp", 
    	name: name, 
    	value: val
	}).done(function(data, status) {
        $.mobile.loading("hide", {});
        if (data.status=="OK") {
            if (name=="parentId") { location.href="?"+Math.round(Math.random()*100000); return false; }
            switch (data.contactType) {
                case "inherited": $("#cat-contact-data-caption").html("Kontaktuppgifterna från överordnad kategori används:"); break;
                case "user": $("#cat-contact-data-caption").html("Så här visas kontaktuppgifterna (länkat till medlemsuppgifter):"); break;
                case "manual": $("#cat-contact-data-caption").html("Så här visas kontaktuppgifterna (enligt inmatningen ovan):"); break;
                case "unset": $("#cat-contact-data-caption").html("Inga kontaktuppgifter visas. Om du vill visa kontaktuppgifter, ställ in dem ovan."); break;
            }
            $("#cat-contact-data").html(data.contactData);
            $("#cat-contactName").val(data.contactName);
            $("#cat-contactPhone").val(data.contactPhone);
            $("#cat-contactMail").val(data.contactMail);
            $("#cat-contact-autocomplete-input").val("");
            $("#cat-contact-autocomplete").html("");
            if (name=="contactMail") $("#cat-contactMailInvalid").hide();
            if (name=="caption") {
                $("#page-caption").text(val);
                $("#cat-breadcrumb-last").text(val);
            }
            if (data.contactType=="user") $("#btn-unset-contact-user").show();
            else $("#btn-unset-contact-user").hide();
            if (name != "onlyGetContactData") {
                $("#cat-saved-indicator").addClass("saved");
                setTimeout(function(){ $("#cat-saved-indicator").removeClass("saved"); }, 2500);
            }
        } else if (data.status=="contactMailInvalid") {
            $("#cat-contactMailInvalid").show();
        } else {
            alert("Kan inte spara ändringen :(");
        }
    }).fail(function() {
        $.mobile.loading("hide", {});
    	alert("Servern accepterar inte förfrågan.");
    });
}

/**
 * Saves a category attachment property
 * @param fileId The fileId of the file
 * @param name Name of the property
 * @param value Value of the property
 */
function setCatFileProp(fileId, name, value) {
    $.mobile.loading("show", {});
    $.getJSON("category.php", {
    	action: "ajaxSetCatFileProp",
    	fileId: fileId,
    	name: name,
    	value: value
	}).done(function(data, status) {
        $.mobile.loading("hide", {});
        if (data.status=="OK") {
        	if (name=="caption") $("#cat-file-header-"+fileId).text(value);
            $("#cat-saved-indicator").addClass("saved");
            setTimeout(function(){ $("#cat-saved-indicator").removeClass("saved"); }, 2500);
        } else {
            alert("Kan inte spara ändringen :(");
        }
    }).fail(function() {
        $.mobile.loading("hide", {});
    	alert("Servern accepterar inte förfrågan.");
    });
}

/**
 * Remove an attachment from category
 * @param fileId ID of the attachment to remove
 */
function catFileDelete(fileId) {
	if (confirm("Vill du ta bort bilagan?")) {
	    $.mobile.loading("show", {});
	    $.get("category.php", {
	    	action: "ajaxDeleteFile",
	    	fileId: fileId
		}).done(function(data, status) {
	        $.mobile.loading("hide", {});
	        if (data.status=="OK") $("#cat-attachments").html(data.html).enhanceWithin();
	        else alert(data.error);
	    }).fail(function() {
	        $.mobile.loading("hide", {});
	    	alert("Servern accepterar inte förfrågan.");
	    });
	}
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
     * Set timeout for saving item postbook message
     */
     $("#item-postbookMsg").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setItemProp, 1000, "postbookMsg", this.value);
    });

    /**
     * Save item active state
     */
    $("#item-active").click(function() {
        setItemProp("active", this.checked ? 1 : 0);
    });
    
    /**
     * Set timeout for saving item internal note
     */
    $("#item-note").on('input', function() {
        clearTimeout(toutSetValue);
        toutSetValue = setTimeout(setItemProp, 1000, "note", this.value);
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
            if (name=="caption") $("#page-caption").text(val);
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


// ========== admin/usage.php ==========
$(document).on('pagecreate', "#page-admin-usage", function() {
    // Bind events
});

$(document).on('pageshow', "#page-admin-usage", function() {
    // Show message if there is any
    if ($("#msg-page-admin-usage").html()) {
        setTimeout(function() {
            $("#popup-msg-page-super-admin").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }

    $("#stat-details").DataTable({
        "info":false,
        "searching":false,
        "order": [[0, 'asc'], [1, 'asc']],
        language: { url:'//cdn.datatables.net/plug-ins/1.10.24/i18n/Swedish.json' },
        "columns": [
            null,
            null,
            {"orderSequence": ["desc", "asc"]},
            {"orderSequence": ["desc", "asc"]},
        ]
    });
});


//========== superadmin.php ==========
$(document).on('pagecreate', "#page-super-admin", function() {
    // Bind events
    
	$(".superadmin-login-post").on("click", function() {
		$("#admin-impersonate-userId").val(this.dataset.userid);
		$("#admin-section-misc").collapsible("expand");
	});
	
	$("#sectionadmin-sectionlist").on('change', function() {
		$.getJSON("?action=ajaxMakeMeAdmin&sectionId=" + this.value, function(data, status) {
			if (data.error) alert(data.error);
			else location.href = "index.php?sectionId=" + data.sectionId;
		});
	});
	
	$("#admin-impersonate-start").on("click", function() {
		$.getJSON("?action=ajaxImpersonate&userId=" + $("#admin-impersonate-userId").val(), function(data,status) {
			if (data.error) alert(data.error);
			else location.href = "../index.php?login";
		});
	});
	
    /**
     * Add a new poll
     */
    $("#add-poll").on('click', function() {
        $.getJSON("?action=ajaxAddPoll", function(data, status) {
        	$("#super-admin-poll-id").val(data.id);
        	$("#super-admin-poll-question").val(data.question);
        	$("#super-admin-poll-choices").val(data.choices.join("\n"));
        	$("#super-admin-poll-expires").val(data.expires);
	        $("#popup-super-admin-poll").popup('open');
        });
    });
});

$(document).on('pageshow', "#page-super-admin", function() {
    // Show message if there is any
    if ($("#msg-page-super-admin").html()) {
        setTimeout(function() {
            $("#popup-msg-page-super-admin").popup('open');
        }, 500); // We need some delay here to make this work on Chrome.
    }
});

/**
 * Perform a system upgrade by fetching the latest version from Github
 */
function systemUpgrade(step) {
	switch(step) {
	case 1:
	    if (confirm("Det här kommer att försöka hämta senaste versionen från Github och installera den. De gamla filerna kommer att skrivas över. Har du gjort en backup? Redo att fortsätta?")) {
	    	$("#upgrade-progress").html("");
	    	$("#upgrade-progress").append("<li>Hämtar senaste versionen från master-grenen på Github...</li>");
	        $.getJSON("?action=ajaxUpgrade&step=1", function(data, status) {
	            if (data.error) {
	            	$("#upgrade-progress").append("<li>"+data.error+"</li>");
	            } else {
	            	$("#upgrade-progress").append("<li>Har hämtat senaste versionen.</li>");
	            	systemUpgrade(2);
	            }
	        });
	    }
	    break;
	case 2:
    	$("#upgrade-progress").append("<li>Packar upp arkivet...</li>");
        $.getJSON("?action=ajaxUpgrade&step=2", function(data, status) {
            if (data.error) {
            	$("#upgrade-progress").append("<li>"+data.error+"</li>");
            } else {
            	$("#upgrade-progress").append("<li>Har packat upp arkivet.</li>");
            	systemUpgrade(3);
            }
        });
        break;
	case 3:
		$("#upgrade-progress").append("<li>Ersätter filerna...</li>");
        $.getJSON("?action=ajaxUpgrade&step=3", function(data, status) {
            if (data.error) {
            	$("#upgrade-progress").append("<li>"+data.error+"</li>");
            } else {
            	$("#upgrade-progress").append("<li>"+data.status+"</li>");
            	$("#upgrade-progress").append("<li>Lyckades att ersätta filerna. <a href='javascript:location.reload();'>Ladda om sidan</a> för att slutföra uppgraderingen.</li>");
            }
        });
        break;
	}
}

function editPoll(id) {
    $.getJSON("?action=ajaxGetPoll&id="+id, function(data, status) {
    	$("#super-admin-poll-id").val(data.id);
    	$("#super-admin-poll-question").val(data.question);
    	$("#super-admin-poll-choices").val(data.choices.join("\n"));
    	$("#super-admin-poll-expires").val(data.expires);
        $("#popup-super-admin-poll").popup('open');
    });
}

function showPollResults(id) {
    $.getJSON("?action=ajaxGetPoll&id="+id, function(data, status) {
    	$("#super-admin-pollresults-question").html(data.question);
    	$("#super-admin-pollresults-votes").html("<tr><th>Svar</th><th colspan=2>Antal</th></tr>");
    	var voteCount = 0;
    	$.each(data.choices, function(index, value) {
    		voteCount += data.votes[index];
    		$("#super-admin-pollresults-votes").append("<tr><td>" + value + "</td><td title='" + data.votes[index] + " röster' style='width:40%;'><span style='display:inline-block; align:right; background-color:gray; width:" + (data.votes[index]/data.voteMax*100) + "%'>&nbsp;</span></td><td>" + data.votes[index] + "</td></tr>");
    	});
        $("#popup-super-admin-pollresults").popup('open');
    });
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
    // Make email input writable
    setTimeout(function() {
        $("#userdata-mail").removeAttr('readonly');
    }, 500);
});

/**
 * Delete user account
 */
function deleteAccount() {
    if (window.confirm("Bekräfta att du vill radera ditt konto i resursbokningen. Alla dina bokningar och persondata tas bort från systemet och kan inte återställas!")) {
        location.href="userdata.php?action=deleteAccount";
    }
}

function setNotificationOptout(catId, notify) {
    $.getJSON("?action=ajaxSetNotificationOptout&catId=" + catId + "&notify=" + notify, function(data, status) {
        switch (data.status) {
        case "warning":
            alert(data.warning); break;
        case "error":
            alert(data.error); break;
        }
    });
    
}

function removePersistentLogin(elem, selector) {
    $.getJSON("?action=ajaxRemovePersistentLogin&selector=" + encodeURIComponent(selector), function(data, status) {
        switch (data.status) {
        case "OK":
            elem.remove();
            break;
        case "error":
            alert(data.error);
            break;
        }
    });
}
var NyuRconInterface = {
	
	playerTable: null,
		
	init: function( controller ) {
		this.controller = controller;
		// Don't initialize if not on rcon page
		if( ! document.getElementById('rcon-player-table') ) return null;
		// Init location selector properties
		this.locationSelectorGeoID = 0;
		this.locationSelectorTarget = $("#rcon-teleport-geoid");
		// Init online players table, dialogs, buttons
		return this.initPlayerTable().initDialogs().initButtons();
	},
	
	initPlayerTable: function() {
		// Create Tabulator table
		this.playerTable = new Tabulator( "#rcon-player-table", {
			layout: "fitColumns",
			index: "ID",
			selectable:true,
			placeholder:"no players online",
			columns: [
				{ title:"Online Players", columns: [
					{ title:"Character", field:"FullName" },
					{ title:"Guild", field:"GuildName" },
				] },
			],
		} );
		return this;
	},
	
	initDialogs: function() {
		var self = this;
		// Default dialog config object
		function generateDialogConfig( submitButton ) {
			return {
				height: "auto", width: "auto", resizable: false, autoOpen: false, modal: true, 
				buttons: [
					{ text: submitButton, click: function() { $("form", this).submit(); } },
					{ text: "Close", click: function() { $(this).dialog("close"); } },
				]
			};
		}
		// Message
		this.messageDialog = $("#rcon-message-dialog").dialog( generateDialogConfig("Send") );
		$("#rcon-message-function").on( 'change', function() {
			$("#rcon-message-duration").toggle( this.value.search("print") !== -1 );
		} );
		$("#rcon-message-time").spinner( {
			min: 5, max: 300, step: 1
		} );
		// Kick
		this.kickDialog = $("#rcon-kick-dialog").dialog( generateDialogConfig("Kick") );
		// Ban 
		this.banDialog = $("#rcon-ban-dialog").dialog( generateDialogConfig("Ban") );
		$("#ban-timed, #ban-permanent").checkboxradio( {
			create: function() { 
				this.id === 'ban-timed' && $(this).click();
			}
		} ).on( 'change', function() {
			$("#rcon-ban-time").toggle( this.id === 'ban-timed' );	
		} );
		$("#rcon-ban-slider").on( 'input change', function() {
			var minutes = parseInt(this.value);
			var result = "";
			if( minutes >= 60 ) result += Math.floor(minutes/60) + " Hours, ";
			result += minutes % 60 + " Minutes";
			$("#ban-duration").html( result );
		} );
		// Insert Item
		this.itemDialog = $("#rcon-item-dialog").dialog( generateDialogConfig("Insert Item") );
		$("#rcon-item-quality").spinner( {min: 1, max: 100, step: 1} );
		$("#rcon-item-quantity").spinner( {min: 1, max: 10000, step: 1} );
		$("#rcon-item-durability").spinner( {min: 100, max: 20000, step: 100} );
		$("#rcon-item-id, #rcon-item-select").on( 'input change', function() {
			( this.id === 'rcon-item-id' ) ? $("#radio-item-id").click() : $("#radio-item-name").click();
		} );
		// Teleport
		this.teleportDialog = $("#rcon-teleport-dialog").dialog( generateDialogConfig("Teleport") );
		$("#rcon-player-select").on( 'change', function() {
			$("#teleport-toplayer").click();
		} );
		$("#rcon-teleport-geoid").on( 'input', function() {
			$("#teleport-togeoid").click();
		} );
		// Locater Dialog
		this.locatorDialog = $("#rcon-locator-dialog").dialog( {
			autoOpen: false, resizable: false, modal: true, height: "auto", width: "auto", 
			buttons: { 
				"Accept": function() { 
					self.locationSelectorTarget.val(self.locationSelectorGeoID);
					$(this).dialog("close");
				},
				"Cancel": function() { $(this).dialog("close"); },
			}
		} );
		$("#location-selector").css("background-image", "url('" + this.controller.config.mapfile_default + "')").on( "click", function( event ) {
			var left = window.pageXOffset || document.documentElement.scrollLeft;
			var top  = window.pageYOffset || document.documentElement.scrollTop;
			var x = (event.originalEvent.layerX - left) / $(this).width() * 1533;
			var y = (event.originalEvent.layerY - top) / $(this).height() * 1533;
			console.log({x:x,y:y});
			self.locationSelectorGeoID = self.controller.px2geoid(x, y);
			$("#location-selector img").css({top: event.originalEvent.layerY - 16 - top, left: event.originalEvent.layerX - 8 - left}).show();
		} );
		return this;
	},
	
	initButtons: function() {
		var self = this;
		$(".rcon-selection-control").on( 'click', function() {
			// Get list of selected characters
			var ids = [];
			self.playerTable.getSelectedData().forEach( function(player) { 
				ids.push(player.ID);
			} );
			if( ids.length === 0 ) {
				alert("Select at least one character from the list!");
				return false;
			}
			// Update selection list in forms
			$(".rcon-selection-summary").html( ids.length + " players selected" );
			$(".rcon-char-list").val( ids.join(',') );
			// Open corresponding dialog
			switch(this.id) {
				case 'rcon-message-button':
					self.messageDialog.dialog('open');
					break;
				case 'rcon-teleport-button':
					self.teleportDialog.dialog('open');
					break;
				case 'rcon-kick-button':
					self.kickDialog.dialog('open');
					break;
				case 'rcon-ban-button':
					self.banDialog.dialog('open');
					break;
				case 'rcon-item-button':
					self.itemDialog.dialog('open');
					break;
			}
		} );
		$("#rcon-locate-icon").on( 'click', function() {
			self.locatorDialog.dialog('open');
		} );
		return this;
	},

	updatePlayerTable: function( list ) {
		var table = this.playerTable;
		// Cache currect selection - we don't want to reset it
		var selected = this.playerTable.getSelectedData();
		// Update table
		table.setData(list);
		// Re-select all previously selected players
		selected.forEach( function(player) {
			table.selectRow(player.ID);	// this works because `ID` is the index field for Tabulator
		} );
		// --
		// Update player list selection
		var select = $("#rcon-player-select");
		// Remember selected ID
		var selectedID = select.children("option:selected").val();
		// Clear select element
		select.empty();
		// Add list of players
		select.append( "<option value=\"0\"></option>" );
		list.forEach( function(player) {
			select.append( "<option value=\"" + player.ID + "\">" + player.FullName + "</option>" );
		} );
		// Re-select previously selected option
		select.children('option[value="' + selectedID + '"]').prop('selected', true);
		return this;
	},

};
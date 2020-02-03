var NyuRconInterface = {
	
	playerTable: null,
	schedulerTable: null,
	
	jobKeys: {
		"bottomprint": "Message on screen bottom",
		"bottomprintall":"Message on screen bottom",
		"centerprint": "Message in screen center",
		"centerprintall": "Message in screen center",
		"crops_grow": "Trigger crops growth cycle",
		"exec_command": "Execute script command",
		"forest_grow": "Trigger forest growth cycle",
		"insert_item": "Insert inventory item",
		"insert_item_all": "Insert inventory item",
		"local_msg": "Message to local chat",
		"local_msg_all": "Message to local chat",
		"system_msg": "Message to system chat",
		"system_msg_all": "Message to system chat",
		"patch_maint": "Trigger patch maintenance",
		"spawn_maint": "Respawn animals",
		"teleport": "Teleport players",
		"kick_player": "Kick player",
		"ban_player": "Ban player",
		"unban_player": "Unban player",
		"reload_schedule": "(System) Reload Scheduler",
	},
		
	init: function( controller ) {
		this.controller = controller;
		// Don't initialize if not on rcon page
		if( ! document.getElementById('rcon-player-table') ) return null;
		// Init location selector properties
		this.locationSelectorGeoID = 0;
		this.locationSelectorTarget = $(".rcon-geoid-holder");
		// Init online players table, dialogs, buttons
		return this.initPlayerTable().initSchedulerTable().initDialogs().initButtons();
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
	
	initSchedulerTable: function() {
		var self = this;
		function timeScheduleFormatter( cell ) {
			var data = cell.getData();
			switch( data.type ) {
				case "repeat":
					var scheduleString = data.interval_value == 1 ? data.interval_unit.toLowerCase() : data.interval_value + " " + data.interval_unit.toLowerCase() + "s";
					return "<span class='ui-icon ui-icon-arrowrefresh-1-n'></span> Every " + scheduleString + " since " + data.runtime.slice(0, -3);
				case "event":
					return "Event-based";
				default:
					return "<span class='ui-icon ui-icon-arrowthickstop-1-e'></span> Once at " + data.runtime;
			}
		}
		this.schedulerTable = new Tabulator( "#rcon-scheduler-table", {
			layout: "fitColumns",
			selectable: "highlight",
			placeholder: "no scheduled jobs",
			index: "ID",
			ajaxURL: "?livemap_id=" + this.controller.config.ID + "&ajax=get_rcon_schedule",
			columns: [
				{ title:"ID", field:"ID", width:32, headerSort:false },
				{ title:"Task Name", field:"name" },
				{ title:"Time Schedule", field:"type", widthGrow:1.25, formatter:timeScheduleFormatter },
				{ title:"Task Type", field:"command", formatter:"lookup", formatterParams:this.jobKeys },
				{ title:"Last Run", field:"last_runtime", width:150 },
				{ title:"Next Run", field:"next_runtime", width:150 },
			],
			initialSort:[
				{ column:"next_runtime", dir:"asc" }
			],
			rowClick: function( e, row ) {
				var data = row.getData();
				var taskType = self.jobKeys.hasOwnProperty(data.command) ? self.jobKeys[data.command] : data.command;
				var taskDetail = "none";
				switch( data.command ) {
					case "centerprint":
					case "centerprintall":
					case "bottomprint":
					case "bottomprintall":
					case "local_msg":
					case "local_msg_all":
					case "system_msg":
					case "system_msg_all":
						taskDetail = "<u>Message:</u><br>" + data.detail;
						break;
					case "teleport":
						if( data.param1 === "ALL" ) taskDetail = "To GeoID " + data.param2;
						break;
					case "insert_item":
					case "insert_item_all":
						var options = data.detail.split("|");
						taskDetail = "Item ID: " + data.param2 + "<br>Quantity: " + options[0] + "<br>Quality: " + options[1] + "<br>Durability: " + options[2];
						break;
				}
				$("#rcon-task-id").val(data.ID);
				$("#rcon-task-name").val(data.name);
				$("#rcon-task-type").html(taskType);
				$("#rcon-task-detail").html(taskDetail);
				self.taskDetailDialog.dialog('open');
			},
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
		$(".rcon-item-quality").spinner( {min: 1, max: 100, step: 1} );
		$(".rcon-item-quantity").spinner( {min: 1, max: 10000, step: 1} );
		$(".rcon-item-durability").spinner( {min: 100, max: 20000, step: 100} );
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
		// Schedule Task Dialog
		this.scheduleTaskDialog = $("#rcon-schedule-dialog").dialog( {
			autoOpen: false, modal: true,
			height: "auto", width: "auto", 
			buttons: { 
				"Accept": function() { $("form", this).submit(); },
				"Cancel": function() { $(this).dialog("close"); },
			}
		} );
		$("#rcon-broadcast-function").on( 'change', function() {
			$("#rcon-broadcast-duration").toggle( this.value.search("print") !== -1 );
		} );
		$("#rcon-broadcast-time").spinner( {
			min: 5, max: 300, step: 1
		} );
		$("#rcon-task-item-id, #rcon-task-item-select").on( 'input change', function() {
			( this.id === 'rcon-task-item-id' ) ? $("#radio-task-item-id").click() : $("#radio-task-item-name").click();
		} );
		$("#rcon-schedule-type").on( 'change', function() {
			$(".rcon-schedule-detail").hide();
			switch(this.value) {
				case "now":
					$("#rcon-schedule-now").show();
					break;
				case "delay":
					$("#rcon-schedule-plan").show();
					break;
				case "repeat":
					$("#rcon-schedule-plan").show();
					$("#rcon-schedule-repeat").show();
					break;
			}
		} );
		$("#rcon-schedule-date").datepicker( {
			firstDay: 1,
			dateFormat: "yy-mm-dd",
			showAnim: "fold",
		} );
		$("#rcon-schedule-hour").spinner( {
			min: 0, max: 23
		} );
		$("#rcon-schedule-minute").spinner( {
			min: 0, max: 59
		} );
		$("#rcon-repeat-value").spinner( {
			min: 1, max: 999
		} );
		// Task Detail
		this.taskDetailDialog = $("#rcon-detail-dialog").dialog( {
			autoOpen: false, resizable: false, modal: true, height: "auto", width: "auto", 
			buttons: { 
				"Update Name": function() { $("form", this).submit(); },
				"Delete Task": function() { window.location.href = "?livemap_id=" + self.controller.config.ID + "&action=DeleteRconTask&task_id=" + $("#rcon-task-id", this).val(); },
				"Close": function() { $(this).dialog("close"); },
			}
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
		$(".rcon-locate-icon").on( 'click', function() {
			self.locatorDialog.dialog('open');
		} );
		$("#rcon-refresh-button").button().on( 'click', function() {
			self.schedulerTable.setData();
		} );
		$(".rcon-task-button").on( 'click', function() {
			$(".rcon-task-fieldset").hide();
			switch(this.id) {
				case 'rcon-task-button-broadcast':
					$("#rcon-task-broadcast").show();
					$("#rcon-task-command").val("broadcast");
					break;
				case 'rcon-task-button-item':
					$("#rcon-task-item").show();
					$("#rcon-task-command").val("insert_item_all");
					break;
				case 'rcon-task-button-teleport':
					$("#rcon-task-teleport").show();
					$("#rcon-task-command").val("teleport_all");
					break;
				case 'rcon-task-button-function':
					$("#rcon-task-function").show();
					$("#rcon-task-command").val("exec_function");
					break;
				case 'rcon-task-button-code':
					$("#rcon-task-code").show();
					$("#rcon-task-command").val("exec_command");
					break;
			}
			self.scheduleTaskDialog.dialog('open');
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
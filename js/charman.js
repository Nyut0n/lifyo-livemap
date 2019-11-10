var NyuCharTable = {
	
	ttmod: false,
	charData: [],
	tabulator: null,
	
	init: function( charData, ttmod ) {
		
		this.ttmod = ttmod;
		this.charData = charData;
		
		return this.initDialogs().initTable().initBulkActions();

	},
	
	initDialogs: function() {
		var self = this;
		// Character rename dialog
		this.renameDialog = $("#char-rename-dialog").dialog( {
			autoOpen: false, resizable: false, modal: true,
			height: "auto", width: "auto",
			buttons: { 
				"Rename": function() { $("form", this).submit(); },
				"Close": function() { $(this).dialog("close"); },
			}
		} );
		// Character locater dialog
		this.locationDialog = $("#char-location-dialog").dialog( {
			autoOpen: false, resizable: false, modal: true,
			height: "auto", width: "auto",
			buttons: { 
				"Close": function() { $(this).dialog("close"); },
			}
		} );
		$("#location-marker").tooltip();
		// Character inventory dialog
		this.inventoryDialog = $("#char-inventory-dialog").dialog( {
			autoOpen: false, resizable: true, modal: true,
			height: $(window).height() * 0.7, width: "auto",
			buttons: { 
				"Close": function() { $(this).dialog("close"); }
			}
		} );
		// Skills dialog
		this.skillsDialog = $("#char-skills-dialog").dialog( {
			autoOpen: false, resizable: false, modal: true,
			width: 400, height: $(window).height() * 0.7,
			buttons: { 
				Close: function() { $(this).dialog("close"); }
			}
		} );
		this.skillsTable = new Tabulator( "#char-skills-table", {
			layout: "fitColumns",
			selectable: false, tooltips: false,
			movableColumns: false, resizableRows: false,
			columns: [
				{ title:"Skill", field:"Name", widthGrow: 3 },
				{ title:"Amount", field:"Skill", widthGrow: 2, editor:"input", validator:"numeric", formatter: self.skillFormatter }
			],
			cellEdited: self.skillEditHandler,
		} );
		// Character stats dialog
		this.statsDialog = $("#char-stats-dialog").dialog( {
			autoOpen: false, resizable: false, modal: true,
			height: "auto", width: "auto",
			buttons: { 
				"Close": function() { $(this).dialog("close"); }
			}
		} );
		this.statsChart = new Chart( document.getElementById('chart-charstats').getContext('2d'), {
			type: 'line',
			data: { 
				labels: [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23],
				datasets: [{ label: "Average distribution of playtime over 24 hours", backgroundColor: "#56A3F0", data: [] }]
			},
			options: {
				maintainAspectRatio: false, 
				scales: {
					xAxes: [{ 
						display: true, 
						scaleLabel: {display: true, labelString: "Time (Hour)"},
						labels: ['00:00','01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00'],
						ticks: {}
					}],
					yAxes: [{ display: true, scaleLabel: { display: true, labelString: "Percentage" } }]
				},
				tooltips: {
					callbacks: {
						title: function(items) { return items[0].xLabel + " - " + (parseInt(items[0].xLabel) + 1) + ":00"; },
						label: function(item) { return item.yLabel + " %"; }
					}
				}
			}
		} );
		return this;
	},
	
	initTable: function() {
		
		var self = this;
		
		this.tabulator = new Tabulator( "#charman-table", {
			layout: "fitColumns",
			pagination: "local",
			paginationSize: 20,
			paginationButtonCount: 7,
			paginationSizeSelector: [20, 50, 100, 250],
			movableColumns: true,
			resizableRows: false,
			selectable: true,
			tooltips: false,
			index: "ID",
			placeholder: "NO CHARACTERS IN DATABASE",
			data: self.charData,
			columns: [
				{ title:" ", field:"statusImage", width:30, align:"center", mutator:self.statusMutator, formatter:"image", tooltip:self.statusTooltip },
				{ title:"Acc ID", field:"AccountID", width:80, sorter:"number", headerFilter:true },
				{ title:"Char ID", field:"ID", width:85, sorter:"number", headerFilter:true },
				{ title:"Char Name", field:"Name", sorter:"string", tooltip:true, headerFilter:true, formatter:self.charNameFormatter },
				{ title:"Guild & Rank", field:"GuildName", sorter:"string", tooltip:true, headerFilter:true, formatter:self.guildFormatter },
				{ title:"Alignment", field:"Alignment", width:110, sorter:"number", formatter:self.alignmentFormatter },
				{ title:"<img src=\"images/swords.png\">", field:"Kills", width:50, align:"center", sorter:"number", tooltip:"Counter is updated by TTmod on every server restart.", headerTooltip:"Kills", visible:self.ttmod },
				{ title:"<img src=\"images/death.png\">", field:"Deaths", width:50, align:"center", sorter:"number", tooltip:"Counter is updated by TTmod on every server restart.", headerTooltip:"Deaths", visible:self.ttmod },
				{ title:"Created", field:"CreateTimestamp", sorter:"number", width:110, formatter:self.timeFormatter, tooltip:self.timeTooltip },
				{ title:"Last Activity", field:"LastOnlineTimestamp", sorter:"string", width:120, formatter:self.timeFormatter, tooltip:self.timeTooltip, visible: self.ttmod },
				{ title:"On Record", width:90, field:"Playtime", sorter:"number", formatter:self.recordFormatter, visible: self.ttmod },
				{ title:"Options", width:146, headerSort:false, formatter:self.optionsFormatter.bind(self), cellClick:self.optionsClickHandler },
			],
			footerElement: "<div class='charman-table-footer'><div id='charman-select-page' class='tabulator-page' title='Select all rows on this page'>Select Page</div><div id='charman-deselect-page' class='tabulator-page' title='Deselect all rows on this page'>Deselect Page</div> &nbsp; <span id='rows-selected'></span></div>",
			rowSelectionChanged: function( data ) {
				var table = this;
				$("#rows-selected").html(data.length + " of " + self.charData.length +  " characters selected");
			},
			tableBuilt: function() {
				var table = this;
				// Select / Deselect page buttons
				$("#charman-select-page, #charman-deselect-page").on('click', function() { 
					var row,
					select   = (this.id === "charman-select-page"),
					pageSize = table.getPageSize(),
					pageNo   = table.getPage(),
					maxRow   = table.getDataCount(false);
					for( var i = pageNo * pageSize - pageSize; i < pageNo * pageSize; i++ ) {
						if( i >= maxRow ) break;
						row = table.getRowFromPosition(i, true);
						select ? row.select() : row.deselect();
					}
				} );
			},
		} );
		
		return this;
		
	},
	
	initBulkActions: function() {
		
		var self = this;
		
		$("#char-bulk-form").submit( function(e) {
			var data = self.tabulator.getSelectedData();
			if( data.length > 0 ) {
				for( var i = 0; i < data.length; i++ ) $(this).append('<input type="hidden" name="CharacterID[]" value="' + data[i].ID + '">');
				$("#char-bulk-button").hide();
				// Add spinner
				$("#char-bulk-form").css('position', 'relative');
				new Spinner( {scale:2, color:"#ffffff"} ).spin( document.getElementById('char-bulk-form') );
				return true;
			} else {
				alert("Select at least one character from the table to run a bulk action.");
				return false;
			}
		} );
		
		$("#char-bulk-command").selectmenu( { 
			position: {collision:'flip'},
			change: function() {
				$("#new-alignment-input").toggle( this.options[this.selectedIndex].value === 'align' );
				$("#new-item-input").toggle( this.options[this.selectedIndex].value === 'item' );
			},
		} );

		$("#bulk-alignment").spinner( { min: -1000, max: 1000, step: 5 } );
		$("#bulk-item-quantity").spinner( { min: 1, max: 10000, step: 1 } );
		$("#bulk-item-quality").spinner( { min: 1, max: 100, step: 1 } );
		$("#bulk-item-durability").spinner( { min: 100, max: 20000, step: 100 } );
		$("#bulk-item-id, #bulk-item-select").on( 'input change', function() {
			( this.id === 'bulk-item-id' ) ? $("#radio-item-id").click() : $("#radio-item-name").click();
		} );
		
		$("#char-bulk-button").button();
		
		return this;
		
	},
	
	// ===================
	// Dialog Helpers
	
	buildItemList: function( list, items ) {
		$.each( items, function(index, item) {
			var quality = " <span class='item-quality' title='Quality'>Q" + item.Quality + "</span>";
			if( typeof item.content === 'undefined' ) {
				var durability = parseInt(item.CreatedDurability) === 0 ? '' : " <span class='item-durability' title='Durability'>" + item.Durability + "/" + item.CreatedDurability + "</span>";
				var quantity   = parseInt(item.Quantity) > 1 ? " <span class='item-quantity' title='Quantity'>x" + item.Quantity + "</span>" : '';
				list.append("<li>" + item.Name + quantity + quality + durability + "</li>");
			} else {
				// Add item
				var newitem = $("<li>" + item.Name + quality + "</li>");
				newitem.addClass('item-container');
				list.append(newitem);
				// Add new layer with contents
				var newlayer = $("<ul></ul>");
				appendContainerItems(newlayer, item.content);
				if( item.content.length === 0 ) newlayer.html('<i>empty</i>');
				list.append(newlayer);
				// Hide and attach toggle handler
				newlayer.hide();
				newitem.click( function() { newlayer.toggle(); } );
			}
		} );
	},
	
	// ===================
	// Tabulator Functions
	
	statusMutator: function( value, data, type, mutatorParams ) {
		var icon = 'normal.png';
		if( parseInt(data.CharActive) === 0 ) icon = 'inactive.png';
		if( parseInt(data.AccountActive) === 0 ) icon = 'banned.png';
		if( parseInt(data.is_online) >= 1 ) icon = 'online.png';
		return 'images/charman/' + icon;
	},
	
	statusTooltip: function( cell ) {
		var data = cell.getData();
		var s = "Account and character is active.";
		if( parseInt(data.CharActive) === 0 ) var s = "Character is disabled (inactive), but account is still active.";
		if( parseInt(data.AccountActive) === 0 ) var s = "Account is banned.";
		if( parseInt(data.is_online) >= 1 ) var s = "Character is online.";
		return s;
	},
	
	charNameFormatter: function( cell ) {
		var icon = parseInt(cell.getData().Gender) === 1 ? "images/gender/1.png" : "images/gender/2.png";
		var ttip = parseInt(cell.getData().Gender) === 1 ? "Male Character" : "Female Character";
		var gm_label = parseInt(cell.getData().IsGM) === 1 ? " <span class='char-gmlabel' title='Permanent GM Account'>GM</span>" : "";
		return "<img src=\"" + icon + "\" class=\"pre-icon\" title=\"" + ttip + "\">" + cell.getValue() + gm_label;
	},
	
	guildFormatter: function( cell ) {
		var data = cell.getData();
		if( data.GuildName === null ) return "";
		return data.GuildName + " <img src='images/guild/rank/" + data.GuildRoleID + ".png' title='" + GuildRankText[data.Gender][parseInt(data.GuildRoleID)] + "'>"
	},
	
	alignmentFormatter: function( cell ) {
		var value = this.sanitizeHTML(cell.getValue()) || 0,
		element = cell.getElement(),
		max = 1000, min = -1000,
		percent, percentValue, 
		color;
		percentValue = parseFloat(value) <= max ? parseFloat(value) : max;
		percentValue = parseFloat(percentValue) >= min ? parseFloat(percentValue) : min;
		percent = (max - min) / 100;
		percentValue = 100 - Math.round((percentValue - min) / percent);
		var alignment = parseInt(value);
		if( alignment >= 50 ) color = "Aqua";
		else if( alignment >= 10 ) color = "#93c763";
		else if( alignment >= 0 ) color = "White";
		else if( alignment >= -49 ) color = "#f2973c";
		else color = "#ff4444";
		$(element).css({"min-width":"30px", "position":"relative"});
		$(element).attr("aria-label", percentValue);
		return "<div style='position:absolute; top:6px; bottom:6px; left:4px; right:" + percentValue + "%; margin-right:4px; background-color:" + color + "; display:inline-block;' data-max='" + max + "' data-min='" + min + "'></div><div style='position:absolute; top:4px; left:8px; text-align:left; width:100%; color:Black; font-size:0.875em'>" + value + "</div>";
	},
	
	timeFormatter: function( cell ) {
		var timestamp = cell.getValue();
		if( timestamp !== null ) {
			var s = moment(parseInt(timestamp)*1000).fromNow();
			return s.replace(" ", " <span class=\"hint\">") + "</span>";
		} else return "<span class=\"hint\">never</span>";
	},
	
	timeTooltip: function( cell ) {
		switch( cell.getField() ) {
			case "CreateTimestamp":
				return "Character created " + cell.getData().CreateString;
			case "LastOnlineString":
				return "Last Activity: " + cell.getValue();
			default:
				return "";
		}
	},
	
	recordFormatter: function( cell ) {
		return cell.getValue() + " <span class='hint'>hours</span>";
	},
	
	optionsFormatter: function( cell ) {
		var self = this;
		var span = $("<span></span>");
		var data = cell.getData();
		// Rename Function
		var renameIcon = $("<img src=\"images/edit.png\" class=\"pre-icon\" title=\"Change Name\">").on( 'click', function() {
			$("#rename-char-id").val(data.ID);
			$("#rename-display-char-id").html(data.ID);
			$("#rename-firstname").val(data.FirstName);
			$("#rename-lastname").val(data.LastName);
			self.renameDialog.dialog("open");
		} );
		// Inventory View
		var inventoryIcon = $("<img src=\"images/bag.png\" class=\"pre-icon\" title=\"View Inventory & Equipment\">").on( 'click', function() {
			// Clear and add spinner
			$("#equipment-list, #inventory-list").empty();
			var spinner = new Spinner( {scale:3, color:"#ffffff"} ).spin( document.getElementById('equipment-list') );
			// Open dialog
			self.inventoryDialog.dialog('option', 'title', 'Inventory of ' + data.FullName);
			self.inventoryDialog.dialog('open');
			// Fetch inventory
			Controller.ajax( {'ajax': 'player_inventory', 'char_id': data.ID}, false, function(response) {
				// Clear spinner, build item lists
				spinner.stop();
				self.buildItemList( $("#equipment-list").empty(), response.equipment );
				self.buildItemList( $("#inventory-list").empty(), response.inventory );
			} );
		} );
		// Location
		var locationIcon = $("<img src=\"images/marker.png\" class=\"pre-icon\" title=\"Show Last Location\">").on( 'click', function() {
			var pos = Controller.geoid2px(data.GeoID);
			// Title and position
			$("#location-marker").prop( 'title', data.Name + "'s last GeoID: " + data.GeoID )
			$("#location-marker").css( {"left":Math.round(pos.x/3), "top":Math.round(pos.y/3)} );
			// Open dialog
			self.locationDialog.dialog("open");
		} );
		// Online Statistics
		if( this.ttmod ) { 
			var statsIcon = $("<img src=\"images/calendar.png\" class=\"pre-icon\" title=\"Show Online Statistics\">").on( 'click', function() {
				self.statsDialog.dialog('option', 'title', 'Playtime Distribution: ' + data.Name);
				self.statsDialog.dialog('open');
				document.getElementById('chart-charstats').style.visibility = 'hidden';
				Controller.ajax( {ajax: 'player_statistics', char_id: data.ID}, false, function(response) {
					document.getElementById('chart-charstats').style.visibility = 'visible';
					self.statsChart.data.datasets[0].data = response.map( function(d) { return d.share; } );
					self.statsChart.update();
				} );
			} );
		} else {
			var statsIcon = "";
		}
		// Skills
		var skillsIcon = $("<img src=\"images/skills.png\" class=\"pre-icon\" title=\"Show & Edit Skills\">").on( 'click', function() {
			self.skillsDialog.dialog('option', 'title', 'Skills of ' + data.Name);
			self.skillsDialog.dialog('open');
			self.skillsTable.clearData();
			self.skillsTable.CharacterID = data.ID;
			// Attach loading animation
			var spinner = new Spinner( {scale:3, color:"#ffffff"} ).spin(self.skillsDialog[0]);
			// Fetch skills
			Controller.ajax( {ajax: 'player_skills', char_id: data.ID}, false, function(response) {
				spinner.stop();
				self.skillsTable.setData(response);
				self.skillsDialog.dialog("option", "position", {my: "center", at: "center", of: window});
			} );
		} );
		// Steam Link
		var steamIcon = $("<img src=\"images/steam.png\" class=\"pre-icon is-clickable\" title=\"Steam Profile\">").on( 'click', function() {
			window.open("https://steamcommunity.com/profiles/" + data.SteamID);
		} );
		
		span.append(renameIcon)
			.append(inventoryIcon)
			.append(locationIcon)
			.append(statsIcon)
			.append(skillsIcon)
			.append(steamIcon);
		  
		return span[0];
		
	},
	
	optionsClickHandler: function( e ) {
		e.stopPropagation();
	},
	
	skillFormatter: function( cell ) {
		var value = this.sanitizeHTML(cell.getValue()) || 0;
		var formattedValue = parseFloat(value).toFixed(2);
		var percentValue = 100 - parseFloat(value);
		return "<div style='position:absolute; top:6px; bottom:6px; left:4px; right:" + percentValue + "%; margin-right:4px; background-color:#93c763; display:inline-block;'></div><div style='position:absolute; top:4px; left:8px; text-align:center; width:100%; color:Black; font-size:0.875em'>" + formattedValue + "</div>";
	},
	
	skillEditHandler: function( cell ) {
		var data = cell.getData();
		Controller.ajax( {ajax: 'skill_update', 'char_id': this.CharacterID, 'skill_id': data.ID, 'skill_value': data.Skill }, false, function(response) {
			(response.result === 'OK') ? Controller.showMessageNotification('confirmation', 'Player Skill Updated') : alert("Error trying to update skill. Changes were not saved.");
		} );
	},

};
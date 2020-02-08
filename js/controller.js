function NyuLivemapController() {
	
	var self = this;
	
	this.header  = null;
	this.rcon    = null;
	this.livemap = null;
	
	this.privileges = [];
	this.config  = null;
	this.dayOfYear = null;
	this.serverInfo = null;
	this.players = null;
	
	this.notificationDuration  = 10;
	this.playersUpdateInterval = 10;
	this.onlinePlayersEnabled  = true;
	
	this.init = function( config, privileges ) {
		this.config = config;
		this.privileges = privileges;
		this.header = Object.create(NyuLivemapHeader).init(this) || null;
		this.rcon = Object.create(NyuRconInterface).init(this) || null;
		this.initServerInfo();
		this.initLivemap();
		this.run();
	};
	
	this.minimalInit = function( config, privileges ) {
		this.config = config;
		this.privileges = privileges;
	};
	
	this.run = function() {
		// Main loop
		this.tick();
		setInterval( this.tick.bind(this), 1000 );
		// Refresh online players interval
		this.updateOnlinePlayers();
		setInterval( this.updateOnlinePlayers.bind(this), this.playersUpdateInterval * 1000 );
	};
	
	this.tick = function() {
		this.updateGameTime();
		this.updateRestartTimer();
		this.updateJudgementHour();
	}
	
	// Init server information
	this.initServerInfo = function() {
		this.ajax( "get_server_details", false, function(data) {
			self.serverInfo = data;
			// Process TTmod version
			if( data.ttmod < 1.3 && parseInt(self.config.server_query) === 0 ) {
				self.onlinePlayersEnabled = false;
				self.header.playersItem.remove();
			}
			// If server has JH, show timer and start refresh schedule
			if( data.judgementHour ) {
				self.header.jhItem.show();
				self.header.setJhTooltip(data.judgementHour);
			}
			// Server offline ?
			if( data.sq_rules === null ) {
				$("#serverinfo-fieldset > div").remove();
				$("#serverinfo-fieldset").append(Locale.ui[36]);
			// Fill server info dialog
			} else {
				$(".serverinfo-sq").each( function() {
					this.innerHTML = data.sq_rules[this.dataset.key];
				} );
				data.world_color === false ? $("#serverinfo-color-row").hide() : $(".serverinfo-color").html(data.world_color);
			}			
		} );
	};

	this.initLivemap = function() {

		// Spawn livemap object
		var livemap = this.livemap = new Livemap(this);
		if( ! livemap.initMap(this.config) ) return false;
		
		// Loading base data
		this.ajax('get_base_data', true, function(data) {

			// Init default-visible layers
			livemap.initLayer('areas').setData(data.areas).show();
			livemap.initLayer('guildClaims').setData(data.guildClaims).show();
			livemap.initLayer('tradeposts').setData(data.tradeposts).show();
			livemap.initLayer('outposts').setData(data.outposts).show();
			livemap.initLayer('pois').setData(data.pois).show();
			// Init default-hidden layers
			livemap.initLayer('secondaryMap').setData(self.config.mapfile_alternative);
			livemap.initLayer('grid').setData(true);
			livemap.initLayer('adminClaims').setData(data.adminClaims);
			livemap.initLayer('persoClaims').setData(data.persoClaims);
			livemap.initLayer('planner').setData({guildClaims: data.guildClaims, outposts: data.outposts});
			if( self.hasPrivilege('standings') ) {
				livemap.initLayer('standings').setData({standings: data.standings, active:0});
			}
			if( self.hasPrivilege('animal_spawns') ) {
				livemap.initLayer('animalSpawns').setData(data.animalSpawns);
			}
			// Init on-demand loading layers
			livemap.initLayer('pavedTiles').setLoader( function() {
				var layer = this;
				self.ajax( 'get_paved', true, function(data) {
					layer.setData(data).show();
				} );
			} );
			livemap.initLayer('buildings').setLoader( function() {
				var layer = this;
				self.ajax( 'get_structures', true, function(data) {
					layer.setData(data).show();
				} );
			} );
			livemap.initLayer('trees').setLoader( function() {
				var layer = this;
				self.ajax( 'get_trees', true, function(data) {
					layer.setData(data).show();
				} );
			} );
			livemap.initLayer('regions').setLoader( function() {
				var layer = this;
				self.ajax( 'get_regions', true, function(data) {
					layer.setData(data).show();
				} );
			} );
			livemap.initLayer('players').setLoader( function() {
				var layer = this;
				if( self.players === null ) {
					setTimeout(layer.onLoad.bind(layer), 3000);
				} else {
					layer.setData(self.players.list).show();
				}
			} );
			
			/* Layer Controls */
			
			// Util layers
			parseInt(self.config.alt_map) > 0 && livemap.getLayer('secondaryMap').addControl("images/control/map.png", Locale.ui[100]);
			livemap.getLayer('regions').addControl("images/control/regions.png", Locale.ui[101]);
			livemap.getLayer('grid').addControl("images/control/grid.png", Locale.ui[102]);
			livemap.getLayer('planner').addControl("images/control/planner.png", Locale.ui[103]);
			// Custom Data
			data.pois.length  && livemap.getLayer('pois').addControl("images/control/pois.png", Locale.ui[115]);
			data.areas.length && livemap.getLayer('areas').addControl("images/control/areas.png", Locale.ui[116]);
			// Online Players
			self.hasPrivilege('player_layer') && livemap.getLayer('players').addControl("images/control/players.png", Locale.ui[104]);
			// Claim & outpost layers
			self.hasPrivilege('aclaim_layer') && livemap.getLayer('adminClaims').addControl("images/control/adminclaims.png", Locale.ui[105]);
			self.hasPrivilege('pclaim_layer') && livemap.getLayer('persoClaims').addControl("images/control/persoclaims.png", Locale.ui[106]);
			self.hasPrivilege('claims')		  && livemap.getLayer('guildClaims').addControl("images/control/guildclaims.png", Locale.ui[107]);
			self.hasPrivilege('outposts')	  && livemap.getLayer('outposts').addControl("images/control/outposts.png", Locale.ui[108]);
			// Trading posts
			self.hasPrivilege('trading_posts') && livemap.getLayer('tradeposts').addControl("images/control/tradingposts.png", Locale.ui[117]);
			// Animal spawns
			self.hasPrivilege('animal_spawns') && livemap.getLayer('animalSpawns').addControl("images/control/animalspawns.png", Locale.ui[119]);
			// Canvas Layers
			self.hasPrivilege('road_layer')   && livemap.getLayer('pavedTiles').addControl("images/control/pavedtiles.png", Locale.ui[109]);
			self.hasPrivilege('struct_layer') && livemap.getLayer('buildings').addControl("images/control/buildings.png", Locale.ui[110]);
			self.hasPrivilege('trees_layer')  && livemap.getLayer('trees').addControl("images/control/trees.png", Locale.ui[118]);
			
		} );
		
	};

	this.hasPrivilege = function( key ) {
		return ( this.privileges.indexOf(key) !== -1 );
	};
	
	
	this.updateDayOfYear = function( dayOfYear ) {
		this.dayOfYear = dayOfYear
		this.header.setWeather( this.config.weatherInfo, dayOfYear );
		this.header.setWeatherTooltip( this.config.weatherInfo, dayOfYear, this.getGameDateTime(self.config.daycycle) );
	};
	
	this.updateOnlinePlayers = function() {
		if( ! this.onlinePlayersEnabled ) return false;
		this.ajax( 'get_players', false, function(data) {
			self.players = data;
			// Update header
			if( data.max === false ) self.header.setOnlinePlayers(data.online);
			else self.header.setOnlinePlayers(data.online + " / " + data.max);
			self.hasPrivilege('online_list') && self.header.setOnlinePlayersList(data.list);
			// Update players layer, if initialized and privileged
			self.livemap.getLayer('players') && self.hasPrivilege('player_layer') && self.livemap.updatePlayers(data.list);
			// Update rcon table
			self.rcon !== null && self.rcon.updatePlayerTable(data.list);
		} );
	};
	
	this.updateGameTime = function() {
		// Get current game time n date
		var gameDate  = this.getGameDateTime(this.config.daycycle);
		var dayOfYear = Math.floor((gameDate.getTime() - Date.UTC(gameDate.getUTCFullYear(), 0, 1) ) / 86400000);
		// Update game time and date in header
		this.hasPrivilege('ingame_time') && this.header.setGameTime(gameDate, this.config.daycycle);
		// On day change, update weather and forecast 
		if( this.dayOfYear !== dayOfYear ) {
			this.updateDayOfYear(dayOfYear);
			console.log("Present in-game day: " + dayOfYear);
		}
	};
	
	this.updateRestartTimer = function() {
		var timestamps = this.config.restarts_ts;
		var unixtime = Math.round(Date.now()/1000);
		var addDays  = 0;
		var closest  = -1;
		while( closest < 0 ) {
			timestamps.forEach( function( ts ) {
				var diff = ts + (addDays * 24 * 3600 ) - unixtime;
				if( closest >= 0 ) {
					closest = diff < closest ? diff : closest;
				} else {
					closest = diff > closest ? diff : closest;
				}
			} );
			addDays++;
			if( addDays > 10 ) {
				console.error("Can't process restart timestamp.");
				break;
			}
		}
		this.header.setNextRestart(closest);
	};
	
	this.updateJudgementHour = function() {
		// Skip if no JH defined
		if( this.serverInfo === null || ! this.serverInfo.judgementHour ) return false;
		// Some shortcuts
		var duration    = this.serverInfo.judgementHour.duration * 60;
		var timestamps  = this.serverInfo.judgementHour.timestamps;
		var secondsToJH = Math.round(timestamps[0] - Date.now()/1000);
		var active      = false;
		// Is JH active now?
		if( secondsToJH < 0 && Math.abs(secondsToJH) <= duration ) {
			active = true;
			var timer = {
				hrs: Math.floor((duration + secondsToJH) / 60 / 60 % 24).pad(2),
				min: Math.floor((duration + secondsToJH) / 60 % 60).pad(2),
				sec: Math.floor((duration + secondsToJH) % 60).pad(2)
			}
			var timerString = L.Util.template("{hrs}:{min}:{sec}", timer);
		// JH inactive, but < 24hrs
		} else if( secondsToJH >= 0 && secondsToJH < 24 * 3600 ) {
			var timer = {
				hrs: Math.floor(secondsToJH / 60 / 60 % 24).pad(2),
				min: Math.floor(secondsToJH / 60 % 60).pad(2),
				sec: Math.floor(secondsToJH % 60).pad(2)
			}
			var timerString = L.Util.template("{hrs}:{min}:{sec}", timer);
		// JH inactive and at some future day
		} else {
			var date = new Date(timestamps[0] * 1000);
			var day  = date.getDay();
			var timerString = Locale.daynames[date.getDay()] + ", " + date.getHours().pad(2) + ':' + date.getMinutes().pad(2);
		}
		this.header.setJudgementHour(active, timerString);
	};
	
	// Display a message dialog
	this.showMessage = function( message ) {
		if( ! message ) return true;
		if( message.popup ) {
			switch( message.type ) {
				case "error":			this.showMessagePopup("Error", message.text);			break;
				case "confirmation":	this.showMessagePopup("Confirmation", message.text);	break;
				case "notification":	this.showMessagePopup("Notification", message.text);	break;
			}
		} else {
			this.showMessageNotification(message.type, message.text);
		}
	};
	
	// Display message popup dialog
	this.showMessagePopup = function( title, text ) {
		$("<div>" + text + "</div>").dialog( {
			title: title, buttons: { Close: function() { $(this).dialog("close"); } }
		} );
	};
	
	// Display anmiated temporary notification
	this.showMessageNotification = function( type, text ) {
		/*
			need type switch here for red and blue notifications
		*/
		var notiBox = type !== 'error' ? $("<div class=\"notification noti-success\">" + text + "</div>") : $("<div class=\"notification noti-error\">" + text + "</div>");
		$(document.body).append(notiBox);
		notiBox.fadeIn('slow').delay(this.notificationDuration*1000).fadeOut(2200);
	};
	
	// Perform AJAX GET request
	this.ajax = function( args, spin, callback ) {
		// Convert single-command string to object
		args = typeof args === 'object' ? args : { ajax: args };
		// Spinner animation
		spin && this.livemap.spinner(true);
		// Perform XHR request
		args.livemap_id = this.config.ID;
		$.getJSON("index.php", args, callback)
		// Error handling
		.fail( function(jqxhr, textStatus, error) {
			console.error('Failed to load data from server'); 
			console.log(jqxhr.responseText);
		} )
		// Spinner release
		.always( function() {
			spin && self.livemap.spinner(false); 
		} );
	};
	
	/* Util */
	
	// Get current in-game time (+1000 years)
	this.getGameDateTime = function( dayCycle ) {
		var base  = 1404172800000;							// Unix-Timestamp (ms) of 1st July '14 00:00:00 UTC
		var diff  = Date.now() - base;						// Milliseconds passed since base timestamp
		var add   = diff * (24/dayCycle);					// Multiply time passed by a factor of 24/dayCycle
		var gtime = new Date( base + add + 43200000 );		// Add result to base timestamp plus additional 12 hours
		return gtime;										// Returns JS Date object with exact in-game time and date + 1000 years (UTC)
	}
	
	// Convert pixel position to GeoID
	this.px2geoid = function( x, y ) {
		var TerID, TerX, TerY, BlockX, BlockY;
		BlockX = Math.floor(x/511);
		BlockY = Math.floor(y/511);
		TerID = 442 + BlockX + ( 2 - BlockY ) * 3;
		TerX = x - BlockX * 511;
		TerY = Math.abs(y - (BlockY + 1) * 511);
		return ( (TerID << 18) | (Math.floor(TerY) << 9) | Math.floor(TerX) );
	};
	
	// Convert GeoID to pixel position
	this.geoid2px = function( GeoID ) {
		var TerID = parseInt(GeoID) >> 18;
		var TerX  = parseInt(GeoID) & ((1 << 9) - 1);
		var TerY  = (parseInt(GeoID) >> 9) & ((1 << 9) - 1);
		return this.terpos2px(TerID, TerX, TerY);
	};

	// Convert TerrainPosition to GeoID
	this.terpos2px = function( TerID, TerX, TerY ) {
		var x, y;
		switch( TerID ) {
			case 442:
			case 443:
			case 444:	y = 1532 - TerY;	break;
			case 445:
			case 446:
			case 447:	y = 1021 - TerY;	break;
			case 448:
			case 449:
			case 450:	y = 510 - TerY;		break;
		}
		switch( TerID ) {
			case 443:	
			case 446:	
			case 449:	x = TerX + 511;		break;
			case 444:	
			case 447:	
			case 450:	x = TerX + 1022;	break;
			default:	x = TerX;
		}
		return {x:x,y:y};
	};

}

Number.prototype.pad = function(size) {
	var s = String(this);
	while (s.length < (size || 2)) {s = "0" + s;}
	return s;
}
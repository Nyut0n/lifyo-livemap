var NyuLivemapHeader = {
	
	controller: null,
	
	// Header section shortcuts and tooltips initialization
	init: function( controller ) {
		this.controller = controller;
		this.initBar().initDialogs().initMenu();
		if( controller.config.restarts !== "" ) {
			this.restartsItem.show();
			this.setRestartsTooltip(controller.config.restarts_ts);
		}
		return this;
	},
	
	initBar: function() {
		this.playersItem = $("#header-players").tooltip( { 
			items: "#header-players",
			classes: {"ui-tooltip": "ui-corner-all ui-widget-shadow header-tooltip"},
			hide: false,
			disabled: true,
		} );
		this.gametimeItem = $("#header-gametime").tooltip( {
			items: "#header-gametime",
			classes: {"ui-tooltip": "ui-corner-all ui-widget-shadow header-tooltip"},
			hide: false,
			disabled: true,
		} );
		this.weatherItem = $("#header-weather").tooltip( {
			items: "#header-weather",
			classes: {"ui-tooltip": "ui-corner-all ui-widget-shadow header-tooltip"},
			hide: false,
			disabled: true 
		} );
		this.restartsItem = $("#header-restarts").tooltip( {
			items: "#header-restarts",
			classes: {"ui-tooltip": "ui-corner-all ui-widget-shadow header-tooltip"},
			hide: false,
			disabled: true
		} );
		this.jhItem = $("#header-jh").tooltip( {
			items: "#header-jh",
			classes: {"ui-tooltip": "ui-corner-all ui-widget-shadow header-tooltip"},
			hide: false,
			disabled: true
		} );
		return this;
	},
	
	initDialogs: function() {
		var controller = this.controller;
		// Login Dialog
		this.loginDialog = $("#dialog-login").dialog( {
			autoOpen: false, resizable: false, modal: true,
			height: "auto", width: "auto",
			buttons: [
				{ text: Locale.ui[18], click: function() { $(this).dialog("close"); } },
			]
		} );
		$("#input-rememberme-log").on( 'click', function() {
			var link = document.getElementById('steam-login-log');
			link.href = this.checked ? link.href.replace('rememberme=0', 'rememberme=1') : link.href.replace('rememberme=1', 'rememberme=0');
		} );
		// Server Info Dialog
		this.infoDialog = $("#dialog-info").dialog( {
			autoOpen: false, resizable: false, modal: true,
			height: "auto", width: "auto",
			buttons: [
				{ text: Locale.ui[18], click: function() { $(this).dialog("close"); } },
			]
		} );
		// Teamspeak Dialog
		this.teamspeakDialog = $("#dialog-teamspeak").dialog( {
			autoOpen: false, resizable: false, modal: true,
			height: "auto", width: "auto",
			buttons: [
				{ text: Locale.ui[35], click: function() { window.location.href = "ts3server://" + controller.config.teamspeak; } },
				{ text: Locale.ui[18], click: function() { $(this).dialog("close"); } }
			]
		} );
		// Guildman Dialog
		this.guildmanDialog = $("#dialog-steam").dialog( {
			autoOpen: false, resizable: false, modal: true,
			height: "auto", width: "auto",
			buttons: [
				{ text: Locale.ui[18], click: function() { $(this).dialog("close"); } },
			]
		} );
		$("#input-rememberme-guild").on( 'click', function() {
			var link = document.getElementById('steam-login-guild');
			link.href = this.checked ? link.href.replace('rememberme=0', 'rememberme=1') : link.href.replace('rememberme=1', 'rememberme=0');
		} );
		// Server Rules Dialog
		this.rulesDialog = $("#dialog-rules").dialog( {
			autoOpen: false, resizable: true, modal: true,
			width: "auto", height: Math.round($(window).height() * 0.8),
			classes: { "ui-dialog-content": "bbcode-wysiwyg" },
			buttons: [
				{ text: Locale.ui[18], click: function() { $(this).dialog("close"); } },
			],
			create: function() {
				// Fix for maxWidth bug
				$(this).css("maxWidth", "1000px");
			}
		} );
		var parser = new sceditor.BBCodeParser();
		$("#dialog-rules").html( parser.toHTML(controller.config.rules) );
		return this;
	},
	
	initMenu: function() {
		var controller = this.controller;
		// Create menu and bind icon click
		$("#header-menu").menu().hide();
		this.menuIcon = $("#more-button").on( "click", function() {
			$("#header-menu").show().position( {
				  my: "left top",
				  at: "left bottom",
				  of: this
			} );
			$(document).one( "click", function() {
				  $("#header-menu").hide();
			} );
			return false;
		} );
		// Bind events to menu items
		$("#menu-livemap, #menu-logout, #menu-char, #menu-rcon, #menu-config").on( 'click', function() {
			window.location.href = this.dataset.link;
		} );
		$("#menu-discord, #menu-website").on( 'click', function() {
			window.open(this.dataset.link, '_blank');
		} );
		$("#menu-server").on( 'click', function() {
			this.infoDialog.dialog('open');
		}.bind(this) );
		$("#menu-rules").on( 'click', function() {
			this.rulesDialog.dialog('open');
		}.bind(this) );
		$("#menu-teamspeak").on( 'click', function() {
			this.teamspeakDialog.dialog('open');
		}.bind(this) );
		$("#menu-login").on( 'click', function() {
			this.loginDialog.dialog('open');
		}.bind(this) );
		$("#menu-guildman").on( 'click', function() {
			if( controller.config.hasSteam ) {
				window.location.href = this.dataset.link;
			} else {
				controller.header.guildmanDialog.dialog('open');
			}
		} );
		return this;
	},
	
	setGameTime: function( date, dayCycle ) {
		// Update time and date in header
		var timestring = date.getUTCHours().pad(2) + ':' + date.getUTCMinutes().pad(2);
		var datestring = date.getUTCDate().pad(2) + "-" + (date.getUTCMonth()+1).pad(2) + "-" + (date.getUTCFullYear()-1000);
		document.getElementById('h-gametime').innerHTML = timestring + " <span class=\"hint\">" + datestring + "</span>";
		// Set game time tooltip
		var progression = Math.round( (24/dayCycle) * 100 ) / 100;
		var gameseconds = (23 - date.getUTCHours()) * 3600 + (60 - date.getUTCMinutes()) * 60 + (60 - date.getUTCSeconds());
		var realseconds = Math.round(gameseconds / progression);
		var timerString = L.Util.template( "{hrs}:{min}:{sec}", {
			hrs: Math.floor(realseconds / 60 / 60 % 24).pad(2),
			min: Math.floor(realseconds / 60 % 60).pad(2),
			sec: Math.floor(realseconds % 60).pad(2)
		} );
		var tooltipContent = Locale.ui[2].replace("{num}", dayCycle) + "<br>";
		tooltipContent    += Locale.ui[3] + ": " + progression + "<br>";
		tooltipContent    += Locale.ui[4] + ": " + timerString;
		this.gametimeItem.addClass("has-tooltip").tooltip( "option", { content: tooltipContent, disabled: false } );
		return this;
	},
	
	setOnlinePlayers: function( players ) {
		// If server is offline
		if( players === false ) {
			$("#header-players .header-item-icon").attr('src', 'images/header/plug.png');
			$("#header-players .hint").html(Locale.ui[37]);
			$("#h-now-online").hide();
		// If online, update player number
		} else {
			$("#header-players .header-item-icon").attr('src', 'images/header/players.png');
			$("#header-players .hint").html(Locale.ui[0]);
			$("#h-now-online").html(players).show();
		}
		return this;
	},
	
	setOnlinePlayersList: function( list ) {
		if( list.length > 0 ) {
			// Build html list
			var html = "<ul id=\"online-players-list\">";
			list.forEach( function(player) { html += "<li>" + player.FullName + "</li>"; } );
			html += "</ul>";
			// Update tooltip
			this.playersItem.addClass("has-tooltip").tooltip("option", { content: html, disabled: false });
		} else {
			this.playersItem.removeClass("has-tooltip").tooltip("option", { content: "", disabled: true });
		}
		return this;
	},
	
	setWeather: function( winfo, dayOfYear ) {
		for( var i = 0; i < winfo.length; i++ ) {
			var weather = winfo[i];
			if( weather.day === dayOfYear ) {
				// Update visuals
				var icon = document.getElementById('h-weather-icon');
				icon.src = 'images/weather/' + weather.key + '.png';
				var span_now = document.getElementById('weather-now');
				span_now.innerHTML = weather.weather;
				var span_tomorrow = document.getElementById('weather-tomorrow');
				span_tomorrow.innerHTML = typeof winfo[i+1] == 'object' ? winfo[i+1].weather : '?';
				break;
			}
		}
		return this;
	},
	
	setWeatherTooltip: function( winfo, dayOfYear, time ) {
		if( this.controller.hasPrivilege('weather_fc') ) {
			var content = '<b>' + Locale.ui[20] + ':</b><br>';
			// Find matching record in weatherInfo
			for( var i = (dayOfYear+1); i < (dayOfYear+9); i++ ) {
				var day = i < 365 ? i : i - 365;
				for( j = 0; j < winfo.length; j++ ) {
					if( winfo[j].day === day ) {
						// Generate date label
						time.setUTCDate( time.getUTCDate() + 1 );
						var datestring = time.getUTCDate() + '/' + (time.getUTCMonth()+1);
						// Generate icon and label
						var iconstring = "<img src=\"images/weather/" + winfo[j].key + ".png\">";
						// Create wrapper and append elements
						content += "<div class=\"weather-item\">" + datestring + "<br>" + iconstring + "<br>" + winfo[j].weather + "</div>";
						break;
					}
				}
			}
			this.weatherItem.addClass("has-tooltip").tooltip( "option", { content: content, disabled: false } );
		}
		return this;
	},
	
	setNextRestart: function( secLeft ) {
		var timestrings = {
			hrs: Math.floor(secLeft / 60 / 60 % 24).pad(2),
			min: Math.floor(secLeft / 60 % 60).pad(2),
			sec: Math.floor(secLeft % 60).pad(2)
		}
		// Update restart timer span
		var span = document.getElementById('h-restart-timer');
		span.innerHTML = L.Util.template("{hrs}:{min}:{sec}", timestrings);
		// Manage Warning icon
		var warn = document.getElementById('h-restart-warn');
		if( secLeft < 3600 ) {
			warn.style.display = 'inline';
			warn.style.opacity = 1;
			if( secLeft < 600 ) span.style.color = 'Orange';
			else					 span.style.color = 'Yellow';
			if( secLeft < 300 ) {
				// Cheap way to add blinking effect to warn icon if less than 5min left
				setTimeout( function() { warn.style.opacity = 0.3; }, 500 );
			}
		// Default, if no warn icon displayed
		} else {
			warn.style.display = 'none';
			span.style.color = 'White';
		}
		return this;
	},
	
	setRestartsTooltip: function( restarts ) {
		var content = Locale.ui[7] + ":<ul id=\"restarts-list\">";
		restarts.sort( function(a, b) {
			var aDate = new Date(a*1000);
			var bDate = new Date(b*1000);
			if( aDate.getHours() > bDate.getHours() ) return 1;
			return -1;
		} );
		restarts.forEach( function(ts) {
			var date = new Date(ts*1000);
			content += "<li>" + date.getHours().pad(2) + ':' + date.getMinutes().pad(2) + "</li>";
		} );
		content += "</ul>";
		this.restartsItem.addClass("has-tooltip").tooltip( "option", { content: content, disabled: false } );
		return this;
	},
	
	setJudgementHour: function( active, timestamp ) {
		document.getElementById('h-jh-timer').innerHTML = timestamp;
		$("#h-jh-text").html( active ? Locale.ui[21] : Locale.ui[8] ).toggleClass('header-highlighted', active);
		return this;
	},
	
	setJhTooltip: function( data ) {
		var content = Locale.ui[22] + ":<ul id=\"restarts-list\">";
		var firstTs = data.timestamps[0];
		data.timestamps.forEach( function(ts) {
			if( ts >= firstTs + 7 * 24 * 3600 ) return false;
			var dateBegin = new Date(ts*1000);
			var dateEnd   = new Date(ts*1000 + data.duration*60*1000);
			content += "<li>" + Locale.daynames[dateBegin.getDay()] + ", " + dateBegin.getHours().pad(2) + ':' + dateBegin.getMinutes().pad(2) + " - " + dateEnd.getHours().pad(2) + ':' + dateEnd.getMinutes().pad(2) + "</li>";
		} );
		content += "</ul>" + Locale.ui[23].replace("{num}", data.duration);
		this.jhItem.addClass("has-tooltip").tooltip( "option", { content: content, disabled: false } );
		return this;
	},
	
};
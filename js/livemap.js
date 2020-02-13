function Livemap( controller ) {

	var self = this;
	this.controller = controller;
	
	var ssFactor = 2;		// Supersampling factor for canvas layers
	var dcThreshold = 10;	// Threshold for double columns in guild member lists
	
	this.config  = null;
	this.leaflet = null;
	this.control = null;
	this.layers = {};
	
	this.credits = $("#dialog-credits").dialog( {
		autoOpen: false, modal: true,
		width: "auto", height: "auto"
	} );
	
	// Initialize Leaflet Map
	this.initMap = function( config ) {
		this.config = config;
		// Abort if target div doesn't exist
		if( document.getElementById('livemap') === null ) {
			console.log("Livemap container not found in DOM");
			return false;
		}
		// Create Leaflet
		this.leaflet = L.map( 'livemap', {
			crs: L.CRS.Simple,
			center: [766, 766],
			zoom: 0,
			minZoom: -1,
			maxZoom: 5,
			maxBounds: [[0,0], [2000,1533]],
			maxBoundsViscosity: 0.8,
			renderer: L.svg({padding: 100}),
		} );
		this.leaflet.zoomControl.setPosition('bottomleft');
		this.leaflet.attributionControl.setPrefix("LiF:YO Livemap v" + config.version);
		this.leaflet.attributionControl.addAttribution("<a href=\"javascript:void(0);\" onclick=\"Controller.livemap.credits.dialog('open');\">&starf; about</span>");
		// Load primary map imagery
		this.initLayer('primaryMap').setData(config.mapfile_default).show();
		// Attach design to tooltip and popup panes
		$(this.leaflet.getPane('tooltipPane')).addClass('style-' + config.style_tooltip);
		$(this.leaflet.getPane('popupPane')).addClass('style-' + config.style_tooltip);
		// Create custom pane for labels
		this.leaflet.createPane('labelPane');
		this.leaflet.getPane('labelPane').style.zIndex = 649;
		this.leaflet.getPane('labelPane').style.pointerEvents = 'none';
		// Load map UI
		this.control = L.control.customControl( {position: 'topright'} );
		this.control.addTo(this.leaflet);
		return true;
	};
	
	// Loading animation
	this.spinner = function( bool ) {
		this.leaflet.spin( bool, {
			color: "#ffffff",
			scale: 3,
		} );
	};
	
	this.px2c = function(x, y) {
		return [1533 - parseFloat(y), parseFloat(x)];
	};
	
	this.c2px = function(latlng) {
		return {x: latlng.lng, y: 1533 - latlng.lat};
	};

	/*** Controls ***/
	
	this.createControlButton = function( icon, tooltip ) {
		var div = L.DomUtil.create('div');
		var img = new Image();
		img.src = icon;
		img.setAttribute('title', tooltip);
		div.appendChild(img);
		return div;
	};
	
	this.addControl = function( icon, tooltip, clickCallback ) {
		var button = this.createControlButton(icon, tooltip);
		// Attach click event
		L.DomEvent.on( button, 'click', clickCallback );
		// Append to ui container
		this.control.getContainer().appendChild(button);
		return button;
	};
	
	/*** Layers ***/
	
	this.addLayer = function( name) {
		this.layers[name] = Object.create(LivemapLayer).init(this);
		return this.layers[name];
	};
	
	this.getLayer = function( name ) {
		return ( this.layers.hasOwnProperty(name) ) ? this.layers[name] : false;
	};
	
	this.initLayer = function( name ) {
		
		var layer = this.addLayer(name);
		
		switch(name) {
			
			case 'primaryMap':
				layer.onDraw = function(layerGroup, data) {
					var primaryMap = self.config.pri_map < 2 ? L.imageOverlay(data, [[0,0], [1533,1533]]) : self.createTileLayer();
					primaryMap.addTo(layerGroup);
				};
			break;
			
			case 'secondaryMap':
				layer.onDraw = function(layerGroup, data) {
					var secondaryMap = self.config.alt_map < 2 ? L.imageOverlay(data, [[0,0], [1533,1533]]) : self.createTileLayer();
					secondaryMap.addTo(layerGroup);
				};
				layer.onShow = function() {
					self.getLayer('primaryMap').hide();
				};
				layer.onHide = function() {
					self.getLayer('primaryMap').show();
				};
			break;
			
			case 'pois':
				layer.onDraw = function(layerGroup, data) {
					data.forEach( function(poi) {
						var poiIcon = L.icon( {
							iconUrl: "data:image/svg+xml;base64," + btoa(poi.svg),
							iconSize: [poi.size, poi.size],
							iconAnchor: [poi.size/2, poi.size/2],
						} );
						L.marker(self.px2c(poi.x, poi.y), {icon: poiIcon})
						.bindTooltip(L.Util.template("<b>{name}</b><br>{desc}", poi), {direction:"top"})
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'areas':
				layer.onDraw = function(layerGroup, data) {
					data.forEach( function(area) {
						L.polygon( area.geometry, {
							opacity: 0.5,
							color: "#" + area.color
						} )
						.bindPopup(L.Util.template("<b>{name}</b><br>{desc}", area))
						.on('mouseover', function(e) { this.setStyle({fillOpacity:0.27}); })
						.on('mouseout', function(e) { this.setStyle({fillOpacity:0.2}); })
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'players':
				layer.onDraw = function(layerGroup, data) {
					// Draw Players
					data.forEach( function(player) {
						if( player.hasOwnProperty('old_x') ) { // Markers with existing coordinates are animated
							var line = L.polyline( [self.px2c(player.old_x, player.old_y), self.px2c(player.x, player.y)] );
							// Debug movement path
							//    line.addTo(layerGroup);
							L.Marker.movingMarker(line.getLatLngs(), [2000])
							.bindTooltip(player.FullName, {direction:"bottom"})
							.addTo(layerGroup)
							.start();
						} else { // New ones just pop up as usual markers
							L.marker( self.px2c(player.x, player.y) )
							.bindTooltip(player.FullName, {direction:"bottom"})
							.addTo(layerGroup);
						}
					} );
				};
			break;
			
			case 'grid':
				layer.onDraw = function(layerGroup, data) {
					// Draw canvas
					var size = 1533 * ssFactor;
					var canvas = document.createElement("canvas");
					canvas.width  = size;
					canvas.height = size;
					var ctx = canvas.getContext("2d");					
					var divi = 25;
					var step = size / divi;
					var letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
					ctx.fillStyle = 'White';
					ctx.textAlign = 'center';
					ctx.textBaseline = 'middle';
					ctx.font = '2.5em Serif';
					var y = 1;
					for( var ypos = step/2; ypos < size; ypos += step ) {
						ctx.fillRect( parseInt(step/2), parseInt(ypos), parseInt(size-step), ssFactor );
						if( parseInt(ypos+step/2) < size-5 ) ctx.fillText( y++, parseInt(step/4), parseInt(ypos+step/2) );
					}
					var x = 0;
					for( var xpos = step/2; xpos < size; xpos += step ) {
						ctx.fillRect( parseInt(xpos), parseInt(step/2), ssFactor, parseInt(size-step) );
						if( parseInt(xpos+step/2) < size-5 ) ctx.fillText( letters.charAt(x++), parseInt(xpos+step/2), parseInt(step/4) );
					}
					L.imageOverlay(canvas.toDataURL("image/png"), [[0,0], [1533,1533]]).addTo(layerGroup);
				};
			break;
			
			case 'regions':
				layer.onDraw = function(layerGroup, data) {
					data.forEach( function(terrainblock) {
						var TerID = parseInt(terrainblock.ID),
						posY = 1022 - Math.floor((TerID-442) / 3) * 511 + 2,
						posX = ((TerID-442) % 3) * 511 + 2,
						bounds = [[self.px2c(posX, posY)], [self.px2c(posX + 507, posY + 507)]],
						regionName, regionColor;
						switch( terrainblock.RegionID ) {
							case "12":
								regionName  = Locale.ui[112];
								regionColor = "Yellow";
								break;
							case "13":
								regionName  = Locale.ui[113];
								regionColor = "Cyan";
								break;
							case "14":
								regionName  = Locale.ui[114]
								regionColor = "Magenta";
								break;
							default:
								regionName  = "Unknown";
								regionColor = "Gray";
							
						}
						L.rectangle( bounds, {
							color: regionColor, 
							weight: 0,
							interactive: false,
						} )
						.addTo(layerGroup);
						L.tooltip( {
							permanent: true,
							direction: 'center',
							className: 'map-region-label',
							pane: 'labelPane',
						} )
						.setContent(regionName)
						.setLatLng(self.px2c(posX+253, posY+30))
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'planner':
				layer.config = {
					outpostMode: false,
					mouseCircleColor: "#00ff00",
					mouseCircleRadius: 75,
					mousePosition: [-500, -500],
				};
				layer.onDraw = function(layerGroup, data) {
					var color  = "#ff0000";
					data.guildClaims.forEach( function(claim) {
						var coords = self.px2c(claim.x, claim.y);
						// T4 Guild Claim Circle
						L.circle( coords, {
							weight: 0,
							fillColor: color,
							fillOpacity: layer.config.outpostMode ? 0.4 : 0.32,
							radius: layer.config.outpostMode ? 150 : 75,
							interactive: false,
						} )
						.addTo(layerGroup);
						// T3 Guild Claim Circle
						if( ! layer.config.outpostMode ) {
							L.circle( coords, {
								weight: 0,
								fillColor: color,
								fillOpacity: 0.3,
								radius: 50,
								interactive: false,
							} )
							.addTo(layerGroup);
						}
					} );
					data.outposts.forEach( function(outpost) {
						// Outpost Blocked Radius Circle
						L.circle( self.px2c(outpost.x, outpost.y), {
							weight: 0,
							fillColor: color,
							fillOpacity: 0.4,
							radius: 150,
							interactive: false,
						} )
						.addTo(layerGroup);
					} );
					// Mouse Circle
					layer.mouseCircle = L.circle( layer.config.mousePosition, {
						weight: 0,
						fillColor: this.config.mouseCircleColor,
						fillOpacity: 0.5,
						radius: this.config.mouseCircleRadius,
						interactive: false,
					} )
					.addTo(layer.layerGroup);
					// Mouse Label
					layer.mouseLabel = L.tooltip( {
						permanent: true,
						direction: 'bottom',
						className: 'map-planner-label',
						pane: 'labelPane',
					} )
					.setContent(this.config.outpostMode ? "OUTPOST" : "GUILD CLAIM")
					.setLatLng(layer.config.mousePosition)
					.addTo(layer.layerGroup);
				};
				layer.moveEvent = function(event) {
					// Follow mouse
					this.mouseCircle.setLatLng(event.latlng);
					this.mouseLabel.setLatLng(event.latlng);
					// Check Area
					var blocked = false;
					var mouse = self.c2px(event.latlng);
					var point = L.point(mouse.x, mouse.y);
					var distance = 999999;
					for( var i = 0; i < this.data.guildClaims.length; i++ ) {
						var max_distance = this.config.outpostMode ? 150 : this.data.guildClaims[i].r;
						if( point.distanceTo(L.point(this.data.guildClaims[i].x, this.data.guildClaims[i].y)) <= max_distance ) {
							blocked = true;
							break;
						}
					}
					for( var j = 0; j < this.data.outposts.length; j++ ) {
						if( point.distanceTo(L.point(this.data.outposts[j].x, this.data.outposts[j].y)) <= 150 ) {
							blocked = true;
							break;
						}
					}
					this.mouseCircle.setStyle({fillColor: blocked ? "#ffff00" : "#00ff00"});
				};
				layer.clickEvent = function(event) {
					this.config.mouseCircleRadius  = this.config.mouseCircleRadius === 75 ? 150 : 75;
					this.config.outpostMode = !this.config.outpostMode;
					this.config.mousePosition = event.latlng;
					this.draw();
				};
				layer.onShow = function() {
					L.DomEvent.on( self.leaflet, 'mousemove', this.moveEvent, this );
					L.DomEvent.on( self.leaflet, 'click', this.clickEvent, this );
				};
				layer.onHide = function() {
					L.DomEvent.off( self.leaflet, 'mousemove', this.moveEvent, this );
					L.DomEvent.off( self.leaflet, 'click', this.clickEvent, this );
				};
			break;

			case 'guildClaims':
				layer.onDraw = function(layerGroup, data) {
					// Define visuals
					var lineWidth = parseInt(self.config.width_claim);
					var dashArray = "0";
					if( self.config.style_claim === 'dashed' ) dashArray = "5, " + ( 6 + lineWidth );
					if( self.config.style_claim === 'dotted' ) dashArray = "1, " + ( 2 + lineWidth * 2 );
					
					data.forEach( function(claim) {
						var coords = self.px2c(claim.x, claim.y);
						var color  = '#' + self.config['color_claim_t' + claim.GuildTier];
						// Claim Tooltip & Popup
						var tooltip = L.tooltip().setContent(L.Util.template('<b><img src="images/guild/tier/{GuildTier}.png" class="bw-icon" title="Tier-{GuildTier}"> {name}</b><br>' + Locale.ui[38] + '<br><span class="hint">' + Locale.ui[43] + '</span>', claim));
						// Claim circle
						L.circle( coords, {
							weight: lineWidth,
							color: color,
							fillColor: color,
							fillOpacity: 0.1,
							radius: claim.r,
							dashArray: dashArray,
						} )
						.on('mouseover', function(e) { this.setStyle({fillOpacity:0.25}); })
						.on('mouseout', function(e) { this.setStyle({fillOpacity:0.1}); })
						.on('popupopen', function(e) {
							this.unbindTooltip();
							this.setPopupContent(self.generateClaimPopup(claim));
							this.getPopup().update();
							self.showStandings(claim.GuildID);
						})
						.on('popupclose', function(e) { 
							this.bindTooltip(tooltip);
							self.hideStandings();
						})
						.bindPopup(self.generateClaimPopup(claim), {className:'map-guild-popup', maxWidth:400})
						.bindTooltip(tooltip)
						.addTo(layerGroup);
						// Claim Label
						if( self.config.color_label !== 'ZZZZZZ' ) {
							L.tooltip( {
								permanent: true,
								direction: 'center',
								className: 'map-guild-label',
								pane: 'labelPane',
							} )
							.setContent(claim.name)
							.setLatLng(coords)
							.addTo(layerGroup);
						}
					} );
				};
			break;
			
			case 'standings':
				layer.onDraw = function(layerGroup, data) {
					var StandingColors = ['#999','#FF0000','#FF8800','#FFFF00','#99FF00','#00FF00'];
					// Get active claim object and relevant standings
					var activeClaim = self.getLayer('guildClaims').data.filter( function(c) { return ( data.active === c.GuildID ); } )[0];
					var standings   = data.standings.filter( function(s) { return (s.GuildID1 === data.active); } );
					if( activeClaim ) {
						// Draw line to each guild claim
						self.getLayer('guildClaims').data.forEach( function(otherClaim) {
							var positions = [ self.px2c(activeClaim.x, activeClaim.y), self.px2c(otherClaim.x, otherClaim.y) ];
							// Find StandingTypeID
							var standing_id = 1;
							standings.forEach( function(s) {
								if( s.GuildID2 === otherClaim.GuildID ) standing_id = parseInt(s.StandingTypeID);
							} );
							// Draw line
							L.polyline( positions, {
								weight: 4,
								opacity: 0.6,
								color: StandingColors[standing_id],
							} )
							.bindTooltip(otherClaim.name + ": " + Locale.standings[standing_id])
							.addTo(layerGroup);
						} );
					}
				};
			break;

			case 'adminClaims':
				layer.onDraw = function(layerGroup, data) {
					data.forEach( function(claim) {
						var bounds = [self.px2c(claim.x1, claim.y1), self.px2c(claim.x2, claim.y2)];
						L.rectangle( bounds, {
							weight: 1,
							color: "#ff0000",
							fillColor: "#ff0000",
							fillOpacity: 0.1,
						} )
						.bindTooltip(L.Util.template('<img src="images/flag.png" class="bw-icon"> {Name}<br>' + Locale.ui[39], claim))
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'persoClaims':
				layer.onDraw = function(layerGroup, data) {
					data.forEach( function(claim) {
						var bounds = [self.px2c(claim.x1, claim.y1), self.px2c(claim.x2, claim.y2)];
						L.rectangle( bounds, {
							weight: 1,
							color: "#ffff00",
							fillColor: "#ffff00",
							fillOpacity: 0.1,
						} )
						.bindTooltip(L.Util.template('<img src="images/flag.png" class="bw-icon"> ' + Locale.ui[40], claim))
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'outposts':
				layer.onDraw = function(layerGroup, data) {
					// Create outpost markers
					var outpostIcons = [];
					for( var object_id = 1702; object_id <= 1711; object_id++ ) {
						outpostIcons[object_id] = L.icon( {
							iconUrl: 'images/outposts/' + object_id + '.png',
							iconSize: [16, 16],
							iconAnchor: [8, 8],
							tooltipAnchor: [8, -8],
						} );
					}
					// Draw Outposts
					data.forEach( function(outpost) {
						var position = self.px2c(outpost.x, outpost.y);
						// is a known objectd ID ?
						if( ! Locale.objects.hasOwnProperty(outpost.ObjectTypeID) ) return false;
						// Assign localized building name
						outpost.BuildingName = Locale.objects[outpost.ObjectTypeID];
						// Circle
						L.circle( position, {
							weight: 0,
							fillColor: 'Black',
							fillOpacity: 0.5,
							radius: 10,
						} )
						.addTo(layerGroup);
						// Icon
						L.marker( position, {
							icon: outpostIcons[parseInt(outpost.ObjectTypeID)]
						} )
						.bindTooltip(L.Util.template('<img src="images/flag.png" class="bw-icon"> {BuildingName}<br>' + Locale.ui[41], outpost), {direction:"top"})
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'tradeposts':
				layer.onDraw = function(layerGroup, data) {
					var tp_icon = L.icon( {iconUrl: 'images/tradingpost.png', iconSize: [16, 16], iconAnchor: [8, -3]} );
					// Draw TPs
					data.forEach( function(tradepost) {
						L.marker(self.px2c(tradepost.x, tradepost.y), { icon:tp_icon })
						.bindTooltip(Locale.ui[42], {direction:"top"})
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'animalSpawns':
				layer.onDraw = function(layerGroup, data) {
					var animalData = {
						WolfData: { name: Locale.ui[120], icon: "images/animals/wolf.png" },
						DeerMaleData: { name: Locale.ui[121], icon: "images/animals/deer.png" },
						HindData: { name: Locale.ui[122], icon: "images/animals/hind.png" },
						BoarData: { name: Locale.ui[123], icon: "images/animals/boar.png" },
						SowData: { name: Locale.ui[124], icon: "images/animals/sow.png" },
						GrouseData: { name: Locale.ui[125], icon: "images/animals/grouse.png" },
						HareData: { name: Locale.ui[126], icon: "images/animals/hare.png" },
						BearData: { name: Locale.ui[127], icon: "images/animals/bear.png" },
						WildHorseData: { name: Locale.ui[128], icon: "images/animals/horse.png" },
					};
					// Draw animal icons
					data.forEach( function(spawn) {
						if( ! animalData.hasOwnProperty(spawn.Animal) ) return false;
						var position = self.px2c(spawn.x, spawn.y);
						// Circle
						L.circle( position, {
							weight: 0,
							fillColor: 'Black',
							fillOpacity: 0.5,
							radius: 14,
						} )
						.addTo(layerGroup);
						// Animal icon
						var animal_icon = L.icon( {iconUrl: animalData[spawn.Animal].icon, iconSize: [16, 16], iconAnchor: [8, 8]} );
						L.marker(position, { icon:animal_icon })
						.bindTooltip("Q" + spawn.Quality + " " + animalData[spawn.Animal].name, {direction:"top"})
						.addTo(layerGroup);
					} );
				};
			break;
			
			case 'pavedTiles':
				layer.onDraw = function(layerGroup, data) {
					// Draw canvas
					var canvas = document.createElement("canvas");
					canvas.width  = 1533 * ssFactor;
					canvas.height = 1533 * ssFactor;
					var ctx = canvas.getContext("2d");
					ctx.fillStyle = "Gray";
					for( var i = 0; i < data.stone.length; i++ ) ctx.fillRect( data.stone[i][0] * ssFactor, data.stone[i][1] * ssFactor, 1 * ssFactor, 1 * ssFactor );
					ctx.fillStyle = "LightSlateGray";
					for( var i = 0; i < data.slate.length; i++ ) ctx.fillRect( data.slate[i][0] * ssFactor, data.slate[i][1] * ssFactor, 1 * ssFactor, 1 * ssFactor );
					ctx.fillStyle = "Tan";
					for( var i = 0; i < data.marble.length; i++ ) ctx.fillRect( data.marble[i][0] * ssFactor, data.marble[i][1] * ssFactor, 1 * ssFactor, 1 * ssFactor );
					// Convert to image and attach overlay
					L.imageOverlay(canvas.toDataURL("image/png"), [[0,0], [1533,1533]]).addTo(layerGroup);
				};
			break;
			
			case 'buildings':
				layer.onDraw = function(layerGroup, data) {
					// Draw canvas
					var canvas = document.createElement("canvas");
					canvas.width  = 1533 * ssFactor;
					canvas.height = 1533 * ssFactor;
					var ctx = canvas.getContext("2d");
					ctx.fillStyle = "Cyan";
					for( var i = 0; i < data.length; i++ ) ctx.fillRect( data[i][0] * ssFactor, data[i][1] * ssFactor, 1 * ssFactor, 1 * ssFactor );
					// Convert to image and attach overlay
					L.imageOverlay(canvas.toDataURL("image/png"), [[0,0], [1533,1533]]).addTo(layerGroup);
				};
			break;
			
			case 'trees':
				layer.onDraw = function(layerGroup, data) {
					// Draw canvas
					var canvas = document.createElement("canvas");
					canvas.width  = 1533 * ssFactor;
					canvas.height = 1533 * ssFactor;
					var ctx = canvas.getContext("2d");
					ctx.fillStyle = "Lime";
					for( var i = 0; i < data.length; i++ ) ctx.fillRect( data[i][0] * ssFactor, data[i][1] * ssFactor, 1 * ssFactor, 1 * ssFactor );
					// Convert to image and attach overlay
					L.imageOverlay(canvas.toDataURL("image/png"), [[0,0], [1533,1533]]).addTo(layerGroup);
				};
			break;
			
		}
		
		return layer;
		
	};
	
	this.createTileLayer = function() {
		var tileURL = this.config.isttmap ? 'maps/tileset/' + this.config.ID + '/{z}_{x}_{y}.jpg' : 'maps/tileset/{z}_{x}_{y}.jpg';
		var tileLayer = new L.TileLayer.YoMatrix( tileURL, {
			tileSize: 511,
			minZoom: -1,
			minNativeZoom: 0,
			bounds: [[0,0], [1533, 1533]],
			errorTileUrl: 'images/errortile.jpg'
		} );
		return tileLayer;
	};
	
	this.updatePlayers = function( newData ) {
		var layer = this.getLayer('players');
		if( layer.hasData ) {
			var oldData = layer.data;
			var data = newData.map( function(player) {
				var match = oldData.filter( function(p) { return p.ID === player.ID; } );
				if( match.length > 0 ) {
					player.old_x = match[0].x;
					player.old_y = match[0].y;
				}
				return player;
			} );
			this.getLayer('players').setData(data);
		} else {
			this.getLayer('players').setData(newData);
		}
	};
	
	this.showStandings = function( id ) {
		if( this.controller.hasPrivilege('standings') ) {
			var layer = this.getLayer('standings');
			layer.data.active = id;
			layer.draw();
			layer.show();
		}
	};
	
	this.hideStandings = function() {
		if( this.controller.hasPrivilege('standings') ) {
			var layer = this.getLayer('standings');
			layer.data.active = 0;
			layer.hide();
		}
	};
	
	this.generateClaimMemberlist = function( members ) {
		var ul = L.DomUtil.create("ul");
		var allowSteam = this.controller.hasPrivilege('steam_links');
		var fieldsetClass = members.length > dcThreshold ? "map-guild-members double-col" : "map-guild-members";
		members.forEach( function(member) {
			var isOnline = self.controller.players !== null && self.controller.players.list.filter(function(player){ return player.ID === member.CharID; }).length > 0;
			var rank_key = parseInt(member.gender) === 1 ? 'ranks_m' : 'ranks_f';
			var item = '<li><img class="pre-icon bw-icon" src="images/guild/rank/{GuildRoleId}.png" title="' + Locale[rank_key][member.GuildRoleId] + '">';
			item += allowSteam ? '<a href="https://steamcommunity.com/profiles/{SteamID}" target="_blank">{FullName}</a>' : '{FullName}';
			item += isOnline ? '<img class="post-icon" src="images/online-indicator.png" title="now online">' : '<img class="post-icon" width="8" src="images/blank.png">';
			item += '</li>';
			ul.innerHTML += L.Util.template(item, member);
		} );
		return "<fieldset class=\"" + fieldsetClass + "\"><legend>" + Locale.ui[24] + "</legend>" + ul.outerHTML + "</fieldset>";
	};
	
	this.generateClaimPopup = function( claim ) {
		// Make popup html
		var charterLink = "<br><a class=\"map-guild-link\" href=\"javascript:void(0);\">&raquo; " + Locale.ui[208] + "</a>";
		var html = '<div class="map-guild-title"><img src="images/guild/tier/{GuildTier}.png" class="bw-icon pre-icon" title="' + Locale.ui[202] + ' {GuildTier}">{name}';
		html += (claim.GuildCharter !== "" && claim.GuildCharterPublic) ? charterLink : '';
		html += '</div><div class="map-guild-details">';
		html += '<label>' + Locale.ui[25] + '</label>{founded}';
		html += '<br><label>' + Locale.ui[26] + '</label>{Radius} ' + Locale.ui[27];
		html += this.controller.hasPrivilege('struct_count') ? '<br><label>' + Locale.ui[28] + '</label>{bcount}' : '';
		html += this.controller.hasPrivilege('member_count') ? '<br><label>' + Locale.ui[29] + '</label>{mcount}' : '';
		html += '</div>';
		html +=	this.controller.hasPrivilege('member_names') ? this.generateClaimMemberlist(claim.members) : '';
		// Create element
		var div = $("<div></div>").append(L.Util.template(html, claim));
		// Attach event listener to guild charter link
		div.find(".map-guild-link").on('click', function() { self.openCharter(claim.name, claim.SanitizedCharter); });
		// Return element div
		return div[0];
	};
	
	this.openCharter = function( guildName, charterText ) {
		var bbparser = new sceditor.BBCodeParser();
		$("<div class=\"bbcode-wysiwyg\">" + bbparser.toHTML(charterText) + "</div>").dialog( {
			title: Locale.ui[208] + ": " + guildName,
			width: "auto", minWidth: 300,
			height: "auto", maxHeight: 700,
			buttons: [
				{ text: Locale.ui[18], click: function() { $(this).dialog('close'); } }
			],
			create: function() {
				// Fix for maxWidth bug
				$(this).css("maxWidth", "1000px");
			}
		} );
	};

}

// Layer Object
var LivemapLayer = {
	// Properties
	data: [],
	hasData: false,
	isLoading: false,
	// Callback placeholders
	onDraw: null,
	onLoad: null,
	onShow: null,
	onHide: null,
	// Constructor
	init: function(livemap) {
		this.livemap = livemap;
		this.layerGroup = L.layerGroup();
		return this;
	},
	setData: function( data ) {
		this.data = data;
		this.hasData = true;
		this.isLoading = false;
		this.draw();
		return this;
	},
	setLoader: function( callback ) {
		this.onLoad = callback;
		return this;
	},
	show: function() {
		if( this.hasData ) {
			this.layerGroup.addTo(this.livemap.leaflet);
			this.checkState();
			if( this.onShow !== null ) this.onShow();
		} else if( typeof this.onLoad !== null ) {
			this.isLoading = true;
			this.onLoad();
		} else {
			console.error("This layer has no data nor an onLoad function");
		}
		return this;
	},
	hide: function() { 
		this.layerGroup.remove();
		this.checkState();
		if( this.onHide !== null ) this.onHide();
		return this;
	},
	toggle: function() { 
		this.livemap.leaflet.hasLayer(this.layerGroup) ? this.hide() : this.show();
		return this;
	},
	addControl: function( icon, tooltip, customCallback ) {
		var button = this.control = this.livemap.createControlButton(icon, tooltip);;
		this.livemap.control.getContainer().appendChild(button);
		L.DomEvent.on( button, 'click', this.toggle, this );
		this.checkState();
		return this;
	},
	checkState: function() {
		if( this.hasOwnProperty('control') ) {
			this.livemap.leaflet.hasLayer(this.layerGroup) ? L.DomUtil.addClass(this.control, 'visible-layer') : L.DomUtil.removeClass(this.control, 'visible-layer')
		}
		return this;
	},
	draw: function() { 
		if( this.onDraw === null ) {
			console.error("No onDraw function was set for this layer");
			return false;
		} else {
			this.layerGroup.clearLayers();
			this.onDraw( this.layerGroup, this.data );
			return true;
		}
	},
};

// Leaflet Mini-Plugin for custom ui
L.Control.CustomControl = L.Control.extend({
	onAdd: function(map) {
		var container = this._container = L.DomUtil.create('div', 'leaflet-bar custom-controls');
		L.DomEvent.disableClickPropagation(container);
		return container;
	},
});
L.control.customControl = function(opts) { 
	return new L.Control.CustomControl(opts);
};

// Leaflet default marker size override
L.Icon.Default.prototype.options.iconSize = [20, 30];
L.Icon.Default.prototype.options.iconAnchor = [10, 30];
L.Icon.Default.prototype.options.shadowSize = [30, 30];

// Custom TileLayer cause URL generation is broken for CRS.Simple systems
L.TileLayer.YoMatrix = L.TileLayer.extend( {
    getTileUrl: function(coords) {
        var data = {
			x: coords.x,
			y: 3 * Math.pow(2, this._getZoomForUrl()) + coords.y,
			z: this._getZoomForUrl(),
		};
		return L.Util.template(this._url, L.Util.extend(data, this.options));
    }
} );
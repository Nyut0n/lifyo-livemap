var ConfigMap = {

	config: null,
	element: null,
	leaflet: null,
	
	mode: null,
	cache: {
		positions: [],
		incompleteArea: null,
		mouseLine: null,
	},
	
	init: function( config ) {
		this.config = config;
		this.element = document.getElementById('customdata-map');
		// Abort if target div doesn't exist
		if( this.element === null ) return false;
		// Create Leaflet
		this.element.style.backgroundColor = "#" + config.color_bg;
		this.leaflet = L.map( this.element, {
			crs: L.CRS.Simple,
			center: [766, 766],
			zoom: -1,
			minZoom: -1,
			maxZoom: 4,
			maxBounds: [[0,0], [1533,1533]],
			maxBoundsViscosity: 0.8,
			attributionControl: false,
			renderer: L.svg({padding: 100}),
		} );
		this.leaflet.zoomControl.setPosition('bottomleft');
		// Load primary map image
		L.imageOverlay(config.mapfile_default, [[0,0], [1533,1533]]).addTo(this.leaflet);
		// Return instance
		this.initUI();
		this.initData();
		return this;
	},
	
	initUI: function() {
		// Initialize Buttons
		$("#new-poi-button").button().on( 'click', this.enterPoiMode.bind(this) );
		$("#new-area-button").button().on( 'click', this.enterAreaMode.bind(this) );
		$("#cancel-button").button().on( 'click', this.exitMode.bind(this) ).button('disable');
		// Load JScolor with custom zIndex to fit dialog
		new jscolor('conf-poi-color', {zIndex: 5050});
		new jscolor('conf-area-color', {zIndex: 5050});
		// POI Icon select switch
		$(".conf-poi-icon").on( 'click', function() {
			$("#conf-poi-icon").val( this.dataset.file );
			$(".conf-poi-icon").removeClass('selected');
			$(this).addClass('selected');
		} );
		return this;
	},
	
	initData: function() {
		/* todo: this should work without accessing global vars ... */
		var self = this;
		PoiData.forEach( function(poi) {
			var poiIcon = L.icon( {
				iconUrl: "data:image/svg+xml;base64," + btoa(poi.svg),
				iconSize: [poi.size, poi.size],
				iconAnchor: [poi.size/2, poi.size/2],
			} );
			L.marker(Controller.livemap.px2c(poi.x, poi.y), {icon: poiIcon})
			.bindTooltip(L.Util.template("<b>{name}</b><br>{desc}<br><span class=\"hint\">click to delete</span>", poi), {direction:"top"})
			.addTo(self.leaflet)
			.on('click', function() {
				if( confirm("Remove POI '" + poi.name + "' from the map?") ) {
					window.location.href = "index.php?livemap_id=" + livemap_id + "&action=RemoveCustomData&id=" + poi.ID;
				}
			});
		} );
		AreaData.forEach( function(area) {
			L.polygon( area.geometry, {
				color: "#" + area.color,
				opacity: 0.5,
			} )
			.bindTooltip(L.Util.template("<b>{name}</b><br>{desc}<br><span class=\"hint\">click to delete</span>", area), {direction:"top"})
			.addTo(self.leaflet)
			.on('click', function() {
				if( confirm("Remove Area '" + area.name + "' from the map?") ) {
					window.location.href = "index.php?livemap_id=" + livemap_id + "&action=RemoveCustomData&id=" + area.ID;
				}
			});
		} );
		
	},
	
	enterPoiMode: function() {
		( this.mode !== 'poi' ) ? this.enterMode('poi') : this.exitMode();
	},
	
	enterAreaMode: function() {
		
		( this.mode !== 'area' ) ? this.enterMode('area') : this.exitMode();
	},
	
	enterMode: function( mode ) {
		this.mode = mode;
		// Toggle buttons
		$("#cancel-button").button('enable');
		$("#new-poi-button, #new-area-button").button('disable');
		// Bind events
		L.DomEvent.on( this.leaflet, 'mousemove', this.updateMouse, this );
		L.DomEvent.on( this.leaflet, 'click', this.registerClick, this );
		L.DomEvent.on( this.leaflet, 'contextmenu', this.finishPath, this );
		L.DomEvent.on( this.leaflet, 'keypress', this.finishPath, this );
		L.DomUtil.addClass(this.leaflet._container,'crosshair-cursor-enabled');
	},
	
	exitMode: function() {
		this.mode = null;
		// Toggle buttons
		$("#cancel-button").button('disable');
		$("#new-poi-button, #new-area-button").button('enable');
		// Unbind events
		L.DomEvent.off( this.leaflet, 'mousemove', this.updateMouse, this );
		L.DomEvent.off( this.leaflet, 'click', this.registerClick, this );
		L.DomEvent.off( this.leaflet, 'contextmenu', this.finishPath, this );
		L.DomEvent.off( this.leaflet, 'keypress', this.finishPath, this );
		L.DomUtil.removeClass(this.leaflet._container,'crosshair-cursor-enabled');
		// reset cache
		this.clearCache();
	},
	
	clearCache: function() {
		this.cache.incompleteArea === null || this.cache.incompleteArea.remove();
		this.cache.mouseLine === null || this.cache.mouseLine.remove();
		this.cache = {
			positions: [],
			incompleteArea: null,
			mouseLine: null,
		}
	},
	
	updateMouse: function( event ) {
		if( this.mode === 'area' ) {
			if( this.cache.positions.length > 0 ) {
				if( this.cache.mouseLine === null ) {
					this.cache.mouseLine = L.polyline( [this.cache.positions[this.cache.positions.length-1], event.latlng], {
						color: "#ffffff",
						opacity: 0.6,
					} )
					.addTo(this.leaflet);
				} else {
					this.cache.mouseLine.setLatLngs( [this.cache.positions[this.cache.positions.length-1], event.latlng] );
				}
			}
		}
	},
	
	registerClick: function( event ) {
		var self   = this;
		switch( this.mode ) {
			// Open POI Dialog
			case "poi":
				var pixel  = Controller.livemap.c2px(event.latlng);
				var geo_id = Controller.px2geoid(pixel.x, pixel.y);
				$("#conf-poi-geoid").val(geo_id);
				$("#conf-poi-dialog").dialog( {
					width: "auto", height: "auto",
					modal: true, 
					buttons: {
						Create: function(e) { 
							if( $("#conf-poi-icon").val() == "" ) {
								alert("No icon selected");
								return false;
							}
							$("form", this).submit();
							$(this).dialog('close');
						},
						Cancel: function() {
							self.exitMode();
							$(this).dialog('close');
						}
					}
				} );
			break;
			// Draw new area polygon point
			case "area":
				this.cache.positions.push(event.latlng);
				// Redraw polyline of existing coordinates
				if( this.cache.positions.length > 1 ) {
					this.cache.incompleteArea === null || this.cache.incompleteArea.remove();
					this.cache.incompleteArea = L.polyline( this.cache.positions , {
						color: "#ffffff"
					} )
					.addTo(this.leaflet);
				}
			break;
		}
	},
	
	finishPath: function( event ) {
		var self = this;
		if( this.mode === 'area' ) {
			$("#conf-area-dialog").dialog( {
				width: "auto", height: "auto",
				modal: true,
				buttons: {
					Create: function() { 
						$("form", this).submit();
						$(this).dialog('close');
					},
					Cancel: function() {
						self.exitMode();
						$(this).dialog('close');
					}
				}
			} );
			$("#conf-area-geometry").val( JSON.stringify(this.cache.positions) );
		}
	}
	
};
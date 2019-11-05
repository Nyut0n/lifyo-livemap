var GuildManager = {

	// Simple confirmation dialog for single-click actions
	confirmDialog: function( text, callback ) {
		$("<div>" + text + "<div>").dialog( {
			title: 'Confirm', 
			resizable: true, modal: true,
			height: "auto", width: "auto",
			buttons: {
				"Confirm": callback,
				"Cancel": function() { $(this).dialog("close"); }
			}
		} );
	},
	
	// Processing dialog
	spinnerDialog: function() {
		$("<div><div>").dialog( {
			title: 'Processing', height: 400, width: 400,
			resizable: true, modal: true, closeOnEscape: false,
			create: function() {
				new Spinner({scale:2, color:"#ffffff"}).spin(this);
			}
		} );
	},

	
};
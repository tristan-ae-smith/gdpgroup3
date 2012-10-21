function htmlEscape(str) {
	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;');
}

(function(root) {
	"use strict";

	var y4 = root.y4 = {};


	var scripts = ["js/Scene.js", "js/collections.js", "js/App.js", "js/VideoPlayer.js", "js/Player.js", "js/Media.js", "js/Overlay.js", "js/PersonalChannel.js"],
		styles = ["css/style.css"];

	var htmlVideoBrowsers = ["iPad"];

	if (navigator.userAgent.indexOf("iPad") > -1) {
		y4.browser = "iPad";
	} else {
		y4.browser = "unknown";
	}
	
	// Should HTML5 videos be used?
	y4.useHtmlVideo = htmlVideoBrowsers.indexOf(y4.browser) > -1;

	_.each(scripts, function (script, i) {
		document.write('<script type="text/javascript" src="' + script + '?' + Math.round(Math.random() * 10000000) + '"></script>'); 
	});
	_.each(styles, function (style) {
		document.write('<link type="text/css" rel="stylesheet" href="' + style + '?' + Math.round(Math.random() * 10000000) + '">');
	});

	y4.now = function () {
		return (new Date()).getTime();
	}

	y4.error = function (msg) {
		console.error(msg)
	}


	$(document).ready(function () {
		
		var app = y4.app = new y4.App({
			server: "152.78.144.19:1935"
		});
		$('#container').html("").append(app.render().el);
	});

	$(document).on("touchstart", function(e){ 
	    e.preventDefault(); 
	});

})(this);


(function(y4) {
	"use strict";

	y4.Player = Backbone.View.extend({
		className: "player",
		initialize: function (options) {
			this.videoLayer = new y4.VideoLayerView({ server: options.server });
			this.blackLayer = new y4.BlackLayerView();
			this.stillLayer = new y4.StillLayerView();
			this.overlayLayer = new y4.OverlayLayerView();
		},
		render: function () {
			this.$el.html("").append(
				this.videoLayer.render().el,
				this.blackLayer.render().el,
				this.stillLayer.render().el,
				this.overlayLayer.render().el);

			return this;
		},
		play: function () {
			this.videoLayer.video.play();
		},
		stop: function () {

		},
		setVideoScene: function (scene) {
			var that = this;
			//this.blackLayer.show();
			this.videoLayer.set(scene).unmute();

			/*scene.on("started", function () {
				that.blackLayer.hide();
			}).on("finished", function () {
				that.blackLayer.show();
				that.videoLayer.mute().set(null);
			});
*/
			// that.videoLayer.setDuration???
		},

		setStillScene: function (still) {
			var that = this;
			//this.blackLayer.show();
			this.stillLayer.set(still).show();

			/*this.stillLayer.on("loaded", function () {
				that.blackLayer.hide();
				setTimeout(function () {
					that.blackLayer.show();
					that.stillLayer.hide();
				}, duration);
			});*/

		},
	});

	y4.VideoLayerView = Backbone.View.extend({
		className: "video-layer",
		zIndex: 1,
		initialize: function (options) {
			var VideoPlayer = y4.useHtmlVideo ? y4.HtmlVideoPlayer : y4.FlashVideoPlayer;
			this.video = new VideoPlayerView({ server: options.server });
		},
		mute: function () {
			console.log("TODO");
			return this;
		},
		unmute: function () {
			console.log("TODO");
			return this;
		},
		set: function (scene) {
			console.log(scene.media)
			this.video.setUrl(scene ? scene.media.service : "", scene ? scene.media.url : "");
			return this;
		},
		render: function () {
			this.$el.html("").append(this.video.render().el);
			return this;
		}
	});

	y4.BlackLayerView = Backbone.View.extend({
		className: "black-layer",
		zIndex: 4,
		show: function () {
			this.$el.show();
			return this;
		},
		hide: function () {
			this.$el.hide();
			return this;
		},
		render: function () {
			this.hide();
			return this;
		}
	});

	y4.StillLayerView = y4.BlackLayer.extend({
		className: "still-layer",
		zIndex: 2,
		set: function (scene) {
			this.$el.html("").append(scene.media.render().el);
			return this;
		}
	});

	y4.OverlayLayerView = Backbone.View.extend({
		className: "overlay-layer",
		zIndex: 3,
		set: function (overlay) {
			return this;
		}
	});


}(this.y4));

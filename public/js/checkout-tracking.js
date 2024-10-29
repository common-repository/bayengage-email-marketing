(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	$(document).ready(function () {

		$("input[type='email']").on('blur', function() {
			var beBillingEmailVal = $(this).val();
			console.log(beBillingEmailVal);
			var url = getCookie("be_base_url");

			var data = {
				"shop_url": getCookie("be_cookie_shop_url"),
				"action_webhook": getCookie("be_cookie_action_webhook"),
				"email": beBillingEmailVal,
				"session_id": getCookie("be_cookie_session_id"),
				"user_id": getCookie("be_cookie_user_id"),
				"first_name": getCookie("be_cookie_first_name"),
				"last_name": getCookie("be_cookie_last_name"),
				"role": getCookie("be_cookie_role"),
				"base_url": getCookie("be_base_url"),
				"id": getCookie("be_id")
			};

			var xhrParams = {
				url,
				method: "POST",
				body: JSON.stringify(data),
				async: true,
				callback: function() {}
			}
			remoteCall(xhrParams);
		});

		function getCookie(cname) {
			let name = cname + "=";
			let decodedCookie = decodeURIComponent(document.cookie);
			let ca = decodedCookie.split(";");
			for(let i = 0; i <ca.length; i++) {
				let c = ca[i];
				while (c.charAt(0) == " ") {
				c = c.substring(1);
				}
				if (c.indexOf(name) == 0) {
				return c.substring(name.length, c.length);
				}
			}
			return "";
		}

		function remoteCall(params) {
			var xhr = new XMLHttpRequest();
			xhr.onerror = function() {
				console.error("the request has errors" + xhr.statusText);
			}
			xhr.ontimeout = function() {
				console.error("The request for " + url + " timed out.");
			};
			xhr.onload = function() {
				if (xhr.readyState === 4) {
					if (xhr.status === 200) {
						var responseargs = [xhr.responseText];
						var argstocallback = responseargs.concat(params.data);
						params.callback.apply(this, argstocallback);
					} else {
						console.error(xhr.statusText);
					}
				}
			};
			xhr.open(params.method, params.url, params.sync);
			xhr.setRequestHeader("x-wc-webhook-source", getCookie("be_cookie_shop_url"));
			xhr.setRequestHeader("x-wc-webhook-topic", getCookie("be_cookie_action_webhook"));

			xhr.send(params.body ? params.body : null);
		}
	});	

})( jQuery );

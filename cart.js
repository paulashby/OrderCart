var Cart = (function () {

    'use strict';

    var setup = {
	    success_callbacks : {
	        add: function (e, data) {
	        	//TODO: Provide success feedback - need this for when return is hit after changing quantity
	        	var cart_items = document.getElementsByClassName('cart-items');
	        	if(cart_items && cart_items.length) {
	        		cart_items[0].innerHTML = data.cart;
	        	}
	        },
	        remove: function (e, data) {
	        	// Update the cart
	        	var cart_items = document.getElementsByClassName('cart-items')[0];
	        	cart_items.innerHTML = data.cart;
	        },
	        update: function (e, data) {
	        	// Update the cart
	        	var cart_items = document.getElementsByClassName('cart-items')[0];
	        	cart_items.innerHTML = data.cart;
	        },
	        order: function (e, data) {
	        	//TODO: Provide success feedback?
	        	// Update the cart
	        	var cart_items = document.getElementsByClassName('cart-items')[0];
	        	cart_items.innerHTML = data.message;
	        }
	    }
	};
	var actions = {
	};

	// https://www.sitepoint.com/jquery-document-ready-plain-javascript/
	if 	(document.readyState === "complete" ||
		(document.readyState !== "loading" && !document.documentElement.doScroll)) {
	  	
	  	onDOMloaded();
	} else {
	  document.addEventListener("DOMContentLoaded", onDOMloaded);
	}


	function onDOMloaded () {

		// Use event handlers in actions object

	    document.addEventListener('click', function (e) { 
	    	dataAttrEventHandler(e, actions); 
	    }, false);
	    document.addEventListener('change', function (e) { 
	    	dataAttrEventHandler(e, actions); 
	    }, false);

	    actions.add = function (e) {

	    	var settings = {
				e: e,
				action: 'add',
				token: getToken(e),
				params: {
					sku: e.target.dataset.sku,
	    			qty: document.getElementById(getId(e)).value
	    		},
				action_url: e.target.dataset.actionurl
			};
			doAction(settings);
		};

	    actions.remove = function (e) {

	    	var settings = {
				e: e,
				action: 'remove',
				token: getToken(e),
				params: {
					sku: e.target.dataset.sku
	    		},
				action_url: e.target.dataset.actionurl
			};
			doAction(settings);
	    }	    

	    actions.update = function (e) {

	    	var settings = {
				e: e,
				action: 'update',
				token: getToken(e),
				params: {
					sku: e.target.dataset.sku,
	    			qty: document.getElementById(getId(e)).value
	    		},
				action_url: e.target.dataset.actionurl
			};
			doAction(settings);
	    }

	    actions.order = function (e) {

	    	var token = document.getElementById('order_token');
	    	var settings = {
				e: e,
				action: 'order',
				token: {
					name: token.name,
					value: token.value
				},
				action_url: e.target.dataset.actionurl
			};
			doAction(settings);
		}
	};

	function dataAttrEventHandler (e, actions) {

	    var action = e.target.dataset.action;

	    if(actions[action]) {
	    	actions[action](e);
	    }
	}
	function doAction (settings) {

		var options = {
        	ajaxdata: {
        		action: settings.action,
        		params: settings.params
        	},
        	token: settings.token, 
        	action_url: settings.action_url,
        	role: settings.action, // Set this to run callback
        	event: settings.e 
        };
        if(settings.params){
        	options.ajaxdata.params = settings.params;
        }
        makeRequest(options);
        settings.e.preventDefault();
	}
	function makeRequest (options) {

		var xhttp = new XMLHttpRequest();

		xhttp.onreadystatechange = function() {
		    if (this.readyState == 4 && this.status == 200) {
		    	xhttp.getAllResponseHeaders();
		    	
		    	var response = JSON.parse(this.response);

		    	if(response.error) {
		    		//TODO: Does this need handling?
		    		console.warn('Ajax call returned an error');
		    	} else {
		    		// Route to appropriate callback
		    		setup.success_callbacks[options.role](options.event, response);
		    	}
		    }
		};
		xhttp.open("PUT", options.action_url, true);
		xhttp.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhttp.setRequestHeader('X-' + options.token.name, options.token.value);
		xhttp.setRequestHeader('Content-type', 'application/json');
		xhttp.send(JSON.stringify(options.ajaxdata));
	}
	function getToken (e) {

		var id = getId (e);
		var token = document.getElementById(id + '_token');
		return {
			name: token.name,
			value: token.value
		};
	}
	function getId (e) {
		return e.target.dataset.context + e.target.dataset.sku;
	}
}());
var Cart = (function () {

    'use strict';

    var setup = {
	    success_callbacks : {
	        add: function (e, data) {
	        	//TODO: Provide success feedback - need this for when return is hit after changing quantity
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

		// Do we need to store something for validateOnBlur() which is also called by actions.cancel()? 
		// Use event handlers in actions object

	    document.addEventListener('click', function (e) { 
	    	dataAttrEventHandler(e, actions); 
	    }, false);
	    document.addEventListener('change', function (e) { 
	    	dataAttrEventHandler(e, actions); 
	    }, false);

	    actions.add = function (e) {

	    	var options = {
	        	ajaxdata: {
            		action: 'add',
            		params: {
            			sku: e.target.dataset.sku,
            			qty: document.getElementById(e.target.dataset.sku).value
            		}
            	},
            	role: 'add', // Set this to run callback
            	event: e // Possible needed for callbacks
	        };
	        doAction(options);
	        e.preventDefault();
	    };

	    actions.remove = function (e) {

			var options = {
            	ajaxdata: {
            		action: 'remove',
            		params: {
            			sku: e.target.dataset.sku
            		}
            	},  
            	role: 'remove', // Set this to run callback
            	event: e // Possibly needed for callbacks
	        };
	        doAction(options);
	        e.preventDefault();
	    }	    

	    actions.update = function (e) {
	    	
	    	var options = {
            	ajaxdata: {
            		action: 'update',
            		params: {
            			//TODO: Need to do the back end for this - might be as simple as using changeQuantity with cart context
            			sku: e.target.dataset.sku,
            			qty: document.getElementById(e.target.dataset.sku).value
            		}
            	},  
            	role: 'update', // Set this to run callback
            	event: e // Possibly needed for callbacks
	        };
	        doAction(options);
	        e.preventDefault();
	    }

	    actions.order = function (e) {

			var options = {
            	ajaxdata: {
            		action: 'order'
            	},  
            	role: 'order', // Set this to run callback
            	event: e // Possibly needed for callbacks
	        };
	        doAction(options);
	        e.preventDefault();
	    }
	};

	function dataAttrEventHandler (e, actions) {

	    var action = e.target.dataset.action;

	    if(actions[action]) {
	    	actions[action](e);
	    }
	}
	function doAction (options) {

		var xhttp = new XMLHttpRequest();

		xhttp.onreadystatechange = function() {
		    if (this.readyState == 4 && this.status == 200) {
		    	xhttp.getAllResponseHeaders();
		    	
		    	var response = JSON.parse(this.response);

		    	if(response.error) {
		    		//TODO: Does this need handling?
		    	} else {
		    		// Route to appropriate callback
		    		setup.success_callbacks[options.role](options.event, response);
		    	}
		    }
		};
		xhttp.open("PUT", "", true);
		xhttp.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhttp.setRequestHeader('Content-type', 'application/json');
		xhttp.send(JSON.stringify(options.ajaxdata));
	}

}());
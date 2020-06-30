var Cart = (function () {

    'use strict';

    var setup = {
	    success_callbacks : {
	        submit: function (e, data) {
	        	//TODO: Provide success feedback
	        },
	        qtychange: function (e, data) {
	        	// Update the cart
	        	debugger;
	        	var cart_form = document.getElementsByClassName('cart-items__form');
	        	cart_form.parentNode.replaceChild(data.cart, cart_form);

	        },
	        remove: function (e, data) {
	        	// Update the cart
	        	var cart_form = document.getElementsByClassName('cart-items__form');
	        	cart_form.parentNode.replaceChild(data.cart, cart_form);
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

		// Polyfill closest() for IE
		if (!Element.prototype.matches) {
			Element.prototype.matches = Element.prototype.msMatchesSelector || 
		                              Element.prototype.webkitMatchesSelector;
		}

		if (!Element.prototype.closest) {
		  Element.prototype.closest = function(s) {
		    var el = this;

		    do {
		      if (Element.prototype.matches.call(el, s)) return el;
		      el = el.parentElement || el.parentNode;
		    } while (el !== null && el.nodeType === 1);
		    return null;
		  };
		}

		// Store for validateOnBlur() which is also called by actions.cancel() 
		// Use event handlers in actions object

	    document.addEventListener('click', function (e) {

	    	dataAttrClickHandler(e, actions);

	    }, false);

	    actions.submit = function (e) {

	    	var submitting_form = e.target.closest('form');
	    	var options = {
	        	// Do we need to serialize? The historic ajax problem was caused by $config->appendTemplateFile = 'includes/_main.php'
	        	// We're actually composing a string for this, so don't need to serialize!
	        	// ajaxdata: serialize(submitting_form), // Should contain 'submit' 
	        	ajaxdata: {
            		action: 'submit',
            		// params: serialize(submitting_form)
					/*
            		{
            			sku: submitting_form.getElementsByClassName('form__sku')[0].value,
		    			qty: submitting_form.getElementsByClassName('form__quantity')[0].value,
            			price: submitting_form.getElementsByClassName('form__price')[0].value

            		}
            		*/
            	},
            	role: 'submit', // Set this to run callback
            	event: e // Possible needed for callbacks
	        };
	        doAction(options);
	        e.preventDefault();
	    };

	    actions.qtychange = function (e) {

	    	// This should be excluded with event.preventDefault - we need another eventHandler for onChange
	    	// https://www.w3schools.com/jsref/event_onchange.asp
			var options = {
            	ajaxdata: {
            		action: 'qtychange',
            		params: {
            			sku: e.target.dataset.sku, 
            			qty: e.target.value
            		}
            	},  
            	role: 'qtychange', // Set this to run callback
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

	function dataAttrClickHandler (e, actions) {

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
		    	
		    	// Different callbacks will probably require different arguments
		    	setup.success_callbacks[options.role](options.event, JSON.parse(this.response));
		    }
		};
		xhttp.open("PUT", "", true);
		xhttp.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhttp.setRequestHeader('Content-type', 'application/json');
		xhttp.send(JSON.stringify(options.ajaxdata));
	}

}());
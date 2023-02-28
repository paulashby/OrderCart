//TODO: Refactor with jQuery since we're now using its custom events.

var Cart = (function () {

    'use strict';

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
	    	$('#cart__form__error').removeClass('cart__error--show');
	    	dataAttrEventHandler(e, actions); 
	    }, false);
	    document.addEventListener('change', function (e) { 
	    	dataAttrEventHandler(e, actions); 
	    }, false);

	    actions.add = function (e) {

	    	var event_src = document.getElementById(getId(e));
	    	var params = {
				sku: e.target.dataset.sku,
    			qty: event_src.value
    		};
    		var step = event_src.step;

    		if(parseInt(params.qty, 10) % parseInt(step, 10) !== 0) {
    			// Not permitted as quantity must be increment of step value
    			return;	
    		}

	    	changeQuantity (e, 'add', params);
	    }

	    actions.remove = function (e) {

	    	var params = {
				sku: e.target.dataset.sku
    		};

	    	changeQuantity (e, 'remove', params);
	    }	    

	    actions.update = function (e) {

	    	var params = {
				sku: e.target.dataset.sku,
    			qty: document.getElementById(getId(e)).value
    		};

	    	changeQuantity (e, 'update', params);
	    }

	    actions.order = function (e) {

	    	var token = document.getElementById('order_token');
	    	var settings = {
				e: e,
				action: 'order',
				params: {
					ecopack: document.querySelectorAll('#cartsustainable')[0].checked
				},
				token: {
					name: token.name,
					value: token.value
				},
				action_url: e.target.dataset.actionurl
			};
			doAction(settings);
		}
	};
	function changeQuantity (e, action, params) {
		var settings = {
			e: e,
			action: action,
			token: getToken(e),
			params: params,
			action_url: e.target.dataset.actionurl
		};
		doAction(settings);
	}
	function updateCart (e, action, data) {
		var cart_items = $('.cart-items');

		if(action !== 'add' && cart_items && cart_items.length) {
    		cart_items.html(data.cart);
    	}

    	// Reset classes
    	cart_items.removeClass();

    	if(action === 'order'){
    		cart_items.addClass("cart-items cart-items--confirm");
    	} else if(data.count > 0){
    		cart_items.addClass("cart-items");
    	} else {
    		cart_items.addClass("cart-items cart-items--empty");
    	}

		// Dispatch event for interested parties
		$.event.trigger({
			initiator: e.target,
			type: "updateCart",
			action: action,
			count: data.count
		});
    }
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
		    		var cart_items = $('.cart-items');
		    		$('.cart-forms').append('<p id="cart__form__error">' + response.error + '</p>');
		    		$('#cart__form__error').addClass('cart__error--show');
		    	} else {
		    		// eg (event, 'update', response)
		    		updateCart(options.event, options.role, response);
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
var Cart = (function () {

    'use strict';

    var setup = {
	    success_callbacks : {
	        submit: function (e, data) {
	        	//TODO: Provide success feedback
	        },
	        qtychange: function (e, data) {
	        	//TODO: Provide success feedback
	        	$('.cart-items__form').replaceWith(data.cart);
	        },
	        remove: function (e, data) {
	        	//TODO: Provide success feedback
	        	$('.cart-items__form').replaceWith(data.cart);
	        }
	    }
	};
	var actions = {
	    
	};

	$( document ).ready(function() {
		// Store for validateOnBlur() which is also called by actions.cancel() 
		// Use event handlers in actions object
	    $('.cart-items').on('click', function (e) {
	        dataAttrClickHandler(e, actions);
	    });

	    actions.submit = function (e) {

	    	var submitting_form = $(e.target).closest('form');
	        var options = {
	        	ajaxdata: submitting_form.serialize(), // Should contain 'submit' 
            	role: 'submit', // Set this to run callback
            	event: e // Possible needed for callbacks
	        };
	        doAction(options);

	        // Let jQuery submit the form
	        e.preventDefault();
	    };

	    actions.qtychange = function (e) {

			var new_quantity = $(e.target).val();
			var sku = $(e.target).data('sku');
			var options = {
            	ajaxdata: {
            		qtychange: new_quantity,
            		sku: sku
            	},  
            	role: 'qtychange', // Set this to run callback
            	event: e // Possible needed for callbacks
	        };
	        doAction(options);

	        // Let jQuery submit the form
	        e.preventDefault();
	    };

	    actions.remove = function (e) {

			var sku = $(e.target).data('sku');
			var options = {
            	ajaxdata: {
            		remove: true,
            		sku: sku
            	},  
            	role: 'remove', // Set this to run callback
            	event: e // Possibly needed for callbacks
	        };
	        doAction(options);

	        // Let jQuery submit the form
	        e.preventDefault();
	    }
	});

	function doAction (options) {
		$.ajax({
            type: 'post', 
            data: options.ajaxdata,
            dataType: 'json',
            success: function(data) {
                
                if(data.success === true) {

                    // Different callbacks will probably require different arguments
                    setup.success_callbacks[options.role](options.event, data);

                } else {
                	// TODO: Add an error_report element to populate
                    error_report.html(data.errors.join('<br>')).addClass('form__error--show');
                }
           },
            error: function(jqXHR, textStatus, errorThrown) {
                throw new Error(errorThrown);
            } 
        });
	}
	function dataAttrClickHandler (e, actions) {

	    var action = $(e.target).data('action');
		
	    if(actions[action]) {
	        actions[action](e);
	    }
	}

}());
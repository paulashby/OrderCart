<?php namespace ProcessWire;

$config = array(
	"outputCurrencySign" => array(
		"name"=> "o_csign",
		"type" => "text", 
		"label" => "Currency sign",
		"description" => "This will be prepended to all your prices.", 
		"value" => "£", 
		"required" => true 
	),
	"orderNumber" => array(
		"name"=> "order_message",
		"type" => "text", 
		"label" => "Placed order message",
		"description" => "Message to show when customer successfully places an order", 
		"value" => "Thank you for your order - you will receive a confirmation email shorty.", 
		"required" => true 
	),
	"productImgField" => array(
		"name"=> "f_product_img",
		"type" => "text", 
		"label" => "Name of product image field",
		"description" => "If you want to show product images in your cart, please enter the name of an images field with formatted value set to 'Array of items'", 
		"value" => "", 
		"required" => false 
	),
	"productImgListingSize" => array(
		"name"=> "f_product_img_l_size",
		"type" => "text", 
		"label" => "Size of product image when shown on listing pages",
		"description" => "Please enter a size in pixels", 
		"value" => "", 
		"required" => false 
	),
	"productImgCartSize" => array(
		"name"=> "f_product_img_c_size",
		"type" => "text", 
		"label" => "Size of product image when shown in cart",
		"description" => "Please enter a size in pixels", 
		"value" => "", 
		"required" => false 
	)
);
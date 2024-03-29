<?php namespace ProcessWire;

$config = array(
	"companyID" => array(
		"name"=> "company",
		"type" => "text", 
		"label" => "Your Company Name",
		"description" => "This will be used in confirmation messages.", 
		"value" => "", 
		"required" => true 
	),
	"notificationSender" => array(
		"name"=> "mailfrom",
		"type" => "text", 
		"label" => "Email address to appear in 'from' field",
		"description" => "This will be used in confirmation messages.", 
		"value" => "", 
		"required" => true 
	),
	"outputCurrencySign" => array(
		"name"=> "o_csign",
		"type" => "text", 
		"label" => "Currency sign",
		"description" => "This will be prepended to all your prices.", 
		"value" => "£", 
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
	),
	"shippingInfo" => array(
		"name"=> "f_shipping_info",
		"type" => "text", 
		"label" => "Shipping info to appear at bottom of cart",
		"description" => "If you want to include concise shipping information in your cart, please enter a single short line of text", 
		"value" => "", 
		"required" => false 
	)
);
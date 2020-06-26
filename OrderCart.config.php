<?php namespace ProcessWire;

//TODO: Add field for cart page url
$config = array(
	"outputCurrencySign" => array(
		"name"=> "o_csign",
		"type" => "text", 
		"label" => "Currency sign",
		"description" => "This will be prepended to all your prices.", 
		"value" => "£", 
		"required" => true 
	)
);
# OrderCart

[<img src="https://img.shields.io/badge/License-MIT-yellow.svg">](https://opensource.org/licenses/MIT)

## Table of Contents

[Description](#description)<br />[Installation](#installation)<br />[Usage](#usage)<br />[Contributing](#contributing)<br />[Tests](#tests)<br />[License](#license)<br />[Questions](#questions)<br />

## Description

Order cart module for the [Processwire](https://processwire.com) Content Management System. Allows registered users to add items to a cart for processing by the [ProcessOrderPages](https://github.com/paulashby/ProcessOrderPages) module.

## Installation

Firstly, download and install the latest version of [Processwire](https://processwire.com). Download the following modules ProcessOrderPages folder and place in your /site/modules directory:<br />
- [OrderCart](https://github.com/paulashby/OrderCart)
- [PageMaker](https://github.com/paulashby/PageMaker)
- [ProcessOrderPages](https://github.com/paulashby/ProcessOrderPages)
<br />

On the module settings page, you should configure the following:
- **Company Name** - this will be used in confirmation emails sent to customers
- **Email address** - your emails will be listed as from this address
- **Currency sign** - "£", "$" etc. This will be prepended to your prices
- **Product image field** - the name of the Processwire field used for product images
- **listing image size** - size in pixels of product image when displayed in listings 
- **cart image size** - size in pixels of product image when displayed in cart 
- **Shipping info** - single line of shipping info to be displayed in cart 

## Usage

Load the module in your php file<br />
```$cart = $this->modules->get("OrderCart");```<br />

## Methods<br />
### addToCart<br />
*Add an item to the cart*<br />

```$cart->addToCart("123", 1);```<br />

Parameters:
- **sku** - string: the product code
- **quantity** - integer: the number of units being ordered

Returns:<br />
JSON object with the following properties:
- **success** - boolean
- **cart** - string: updated HTML markup for the cart
- **count** - integer: number of items in cart<br /><br />

### changeQuantity<br />
*Change quantity of cart item*<br />

```$cart->changeQuantity("123", 1);```<br />

Parameters:
- **sku** - string: the product code
- **quantity** - integer: the number of units being ordered<br />

Returns:<br />
JSON object with the following properties:
- **success** - boolean
- **cart** - string: updated HTML markup for the cart
- **count** - integer: number of items in cart<br /><br />

### removeCartItem<br />
*Remove given cart item*<br />

```$cart->removeCartItem("123");```<br />

Parameters:
- **sku** - string: the product code of the item to remove<br />

Returns:<br />
JSON object with the following properties:
- **success** - boolean
- **cart** - string: updated HTML markup for the cart
- **count** - integer: number of items in cart<br /><br />

### getNumCartItems<br />
*Get the number of items in a user's cart*<br />

```$cart->getNumCartItems(123);```<br />

Parameters:
- **user_id** - integer: the Processwire ID of the user<br />

Returns:<br />
Integer<br /><br />

### placeOrder<br />
*Pass the user's cart to the order system for processing.* <br />***NOTE: this method requires the [ProcessOrderPages](https://github.com/paulashby/ProcessOrderPages) module***<br />

```$cart->placeOrder(1);```<br />

Parameters:
- **ecopack** - integer: if you offer environmentally-friend packaging options, you can assign them integer identifiers and accept the user's preference here<br />

Returns:<br />
JSON object with the following properties:
- **success** - boolean
- **cart** - string: HTML markup for the cart with order acknowledgement message
- **count** - integer: 0 (cart is emptied when order is placed)<br /><br />

### getOrderNum<br />
*Get next available order number.*<br />

```$cart->getOrderNum();```<br />

Returns:<br />
Numerical string<br /><br />

### renderPrice<br />
*Gets a formatted price string including currency sign.*<br />

```$cart->renderPrice(150);```<br />

Parameters:
- **price** - integer: value for price in fractional currency unit (eg GBP £1.50 would be passed to the method as 150 with the return value being "£1.50")

Returns:<br />
String - the formatted price<br /><br />

### renderEmptyCart<br />
*Get HTML markup for empty cart*<br />

```$cart->renderEmptyCart("<div class='spinner'></div>");```<br />

Parameters:
- **spinner** - string (optional): HTML markup for a "loading" element to be displayed until cart is populated

Returns:<br />
Array with the following properties:
- **markup** - string: HTML markup for the cart
- **count** - integer: 0 (cart is empty)<br /><br />

### renderCart<br />
Get HTML markup for cart<br />

```$cart->renderCart(true, myImageFunction, true);```<br />

Parameters:
- **omitContainer** - boolean (default false): omit outer div
- **customImageMarkup** - false or function OrderCart can use to output product images
- **eager** - boolean (default false): set to true if lazy loading is not required for cart images

Returns:<br />
String: HTML markup for the cart<br /><br />

### renderProductShot<br />
Get HTML markup for product shot

```$cart->renderProductShot($productPage, 2);```<br />

Parameters:
- **product** - Processwire page: the product page
- **count** - integer: single product shot or double (front/back)

Returns:<br />
String: HTML markup for the product shot<br /><br />

## Contributing

If you would like to make a contribution to the app, simply fork the repository and submit a Pull Request. If I like it, I may include it in the codebase.

## Tests

N/A

## License

Released under the [MIT](https://opensource.org/licenses/MIT) license.

## Questions

Feel free to [email me](mailto:paul@primitive.co?subject=ProcessOrderPages%20query%20from%20GitHub) with any queries. If you'd like to see some of my other projects, my GitHub user name is [paulashby](https://github.com/paulashby).

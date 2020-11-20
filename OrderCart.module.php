<?php namespace ProcessWire;

class OrderCart extends WireData implements Module {
  public static function getModuleInfo() {
    return [
      'title' => 'Order Cart',
      'summary' => 'Provide front end order system for ProcessOrderPages',
      'author' => 'Paul Ashby, primitive.co',
      'version' => 1,
      "requires" => "ProcessOrderPages"
    ];
  }

  public function init() {
    $this->token_name = $this->session->CSRF->getTokenName("oc_token");
    $this->token_value = $this->session->CSRF->getTokenValue("oc_token");
  }
  
  /**
   * Add product to cart (creates a line-item page as child of /processwire/orders/cart-items)
   *
   * @param String $sku The product code
   * @param Integer $quantity 
   * @return String The configured field name
   */
    public function addToCart($sku, $quantity) {
      
      $settings = $this->modules->getConfig("ProcessOrderPages");
      
      $f_customer = $settings["f_customer"];
      $f_sku_ref = $settings["f_sku_ref"];
      $user_id = $this->users->getCurrentUser()->id;
      $parent_selector = $this->getCartPath();
      $child_selector = "$f_customer=$user_id,$f_sku_ref=$sku";

      $exists_in_cart = $this->pages->get($parent_selector)->child($child_selector);

      if($exists_in_cart->id) {

        // Add to exisitng line item if user already has this product in their cart
        $sum = $quantity + $exists_in_cart[$settings["f_quantity"]];
        $exists_in_cart->of(false);
        $exists_in_cart->set($settings["f_quantity"], $sum);
        $exists_in_cart->save();

      } else { 

        // Create a new item
        $item_title = $sku . ": " . $this->users->get($user_id)[$settings["f_display_name"]];
        $item_data = array("title" => $item_title);
        $item_data[$settings["f_customer"]] = $user_id;
        $item_data[$settings["f_sku_ref"]] = $sku;
        $item_data[$settings["f_quantity"]] = $quantity;

        $cart_item = $this->wire("pages")->add($settings["t_line_item"],  $this->getCartPath(), $item_data);
      }

      return json_encode(array("success"=>true, "cart"=>$this->renderCart(true)));
    }
  /**
   * Change quantity of cart item
   *
   * @param String  $sku The item to update
   * @param Integer $quantity The new value
   * @return Json Updated cart markup if successful
   */
    public function changeQuantity($sku, $quantity) {

      $settings = $this->modules->getConfig("ProcessOrderPages");
      $cart_item = $this->getCartItem($sku);

      if($cart_item) { // getCartItem returns false if the item cannot be found

        // Update product
        $cart_item->of(false);
        $cart_item->set($settings["f_quantity"], $quantity);
        $cart_item->save();

        // Return entire form to cart
        return json_encode(array("success"=>true, "cart"=>$this->renderCart(true)));
      }

      return json_encode(array("error"=>"The item could not be found"));
    }
    /**
   * Remove line item from cart
   *
   * @param String  $sku The item to remove
   * @return Json Updated cart markup if successful
   */
    public function removeCartItem($sku) {

      $cart_item = $this->getCartItem($sku);
      
      if($cart_item->id) {
        $cart_item->delete(true);

        return json_encode(array("success"=>true, "cart"=>$this->renderCart(true)));  
      }
      return json_encode(array("error"=>"The item could not be found"));
    }
  /**
   * Get item from cart
   *
   * @param String  $sku The item to get
   * @return Object Line item page or boolean false
   */
    protected function getCartItem($sku) {

      $settings = $this->modules->getConfig("ProcessOrderPages");
      $user_id = $this->users->getCurrentUser()->id;
      $f_customer = $settings["f_customer"];
      $f_sku_ref = $settings["f_sku_ref"];
      $cart_path = $this->getCartPath();
      $parent_selector = $cart_path;
      $child_selector = "{$f_customer}={$user_id}, {$f_sku_ref}={$sku}";
      $cart_item = $this->pages->findOne($parent_selector)->child($child_selector);

      if($cart_item->id) {
        return $cart_item;
      }
      return false;
    }
  /**
   * Get all cart items for the current user
   *
   * @return wireArray The cart items
   */
    protected function getCartItems() {

      $settings = $this->modules->getConfig("ProcessOrderPages");
      $user_id = $this->users->getCurrentUser()->id;
      $t_line_item = $settings["t_line_item"];
      $f_customer = $settings["f_customer"];
      return $this->pages->findOne($this->getCartPath())->children("template={$t_line_item}, {$f_customer}={$user_id}");
    }
  /**
   * Get the path of the cart
   *
   * @return String
   */
    protected function getCartPath() {

      $settings = $this->modules->getConfig("ProcessOrderPages");
      return $settings["order_root"] . "/cart-items/";
    }
  /**
   * Process line items, creating new order in /processwire/orders/pending-orders/
   *
   * @return Json
   */
    public function placeOrder() {

      // Get the parent page for the new order
      $errors = array();

      $orders_parent = $this->getOrdersPage("pending", $this->users->getCurrentUser()->id);
      $page_maker = $this->modules->get("PageMaker");
      
      if($orders_parent->id) {

        $settings = $this->modules->getConfig("ProcessOrderPages");
        $order_number = $this->getOrderNum();
        $spec = array(
          "template" => $settings["t_order"], 
          "parent"=>$orders_parent->path(), 
          "title"=>$order_number,
          "name"=>$order_number
        );

        // Create the order
        $order_page = $page_maker->makePage($spec);
        $cart_items = $this->getCartItems();

        foreach ($cart_items as $item) {
          $item->of(false);
          $item->parent = $order_page;
          $item->save();
        }
        $order_message = $this->order_message;

        return json_encode(array("success"=>true, "message"=>"<h3>$order_message</h3>"));  
      }

      $errors[] = "The orders page could not be found";
      return json_encode(array("errors"=>$errors));
    }
  /**
   * Move order to next step to reflect new status
   *
   * @param String $order_num
   * @param String $order_step
   * @return Boolean
   */
    public function progressOrder($order_num, $order_step) {

      $settings = $this->modules->getConfig("ProcessOrderPages");
      $order_selector = "template=" . $settings["t_order"] . ",name={$order_num}";
      $order_pg = $this->pages->findOne($order_selector);
      
      if($order_pg->id){
        // Get the customer
        $customer = $order_pg->children()->first()[$settings["f_customer"]];
        $next_step = $this->getOrdersPage($order_step, $customer);
        $order_pg->of(false);
        $order_pg->parent = $next_step;
        $order_pg->save();
        return true;
      }
      return false;
    }
  /**
   * Get parent page for order - for current user only if id supplied
   *
   * @param String $order_step
   * @param Integer $user_id
   * @return PageArray or Page
   */
    public function getOrdersPage($order_step, $user_id = null) {

      $settings = $this->modules->getConfig("ProcessOrderPages");
      $parent_path = $settings["order_root"] . "/{$order_step}-orders/";
      $order_parent_name =  "{$user_id}_orders";
      if($user_id) {
        // User provided, so get the orders page just for this customer
        $child_selector = "name=$order_parent_name";
        $user_order_page = $this->pages->get($parent_path)->child($child_selector);
        if($user_order_page->id) {
          return $user_order_page;
        }
        $order_template = $settings["t_user_orders"];
        $spec = array(
          "template" => $order_template, 
          "parent"=>$parent_path, 
          "title"=>$order_parent_name, 
          "name"=> $order_parent_name
        );

        // No orders for this user - make a new page within pending orders
        $page_maker = $this->modules->get("PageMaker");
        return $page_maker->makePage($spec);
      }

      // All orders for given step
      return $this->pages->get($parent_path)->children();
  }
  /**
   * Get order number then increment in db
   *
   * @return  String The unincremented order number
   */
    protected function getOrderNum() {

      $data = $this->modules->getConfig("ProcessOrderPages");
      $order_num = $data["order_num"];
      $this_order_num = $order_num;
      $order_num++;
      $data["order_num"] = $order_num;
      $this->modules->saveConfig("ProcessOrderPages", $data);
      return $this_order_num;
    }
  /**
   * Convert an integer representing GB pence to a GBP string 
   *
   * @param Integer $pence
   * @return String GBP value as a string with decimal point and prepended £
   */
    public function renderPrice($pence) {

      return $this["o_csign"] . number_format($pence/100, 2);
    }
/**
 * Generate HTML markup for quantity number input field
 *
 * @param Array $options [String "context", String "sku"]
 * @return String HTML markup
 */
    protected function renderQuantityField($options) {

      $context = $options["context"];
      $sku = $options["sku"];
      $id = $context . $sku;

      if($context === "listing") {
        $name = "quantity";
        $value = "1";
      } else {
        $name = "quantity[]";
        $value = $options["quantity"];
      }

      return "<input id='$id' class='.form__quantity' type='number' data-context='$context' data-action='qtychange' data-sku='$sku' name='$name' min='1' step='1' value='$value'>";
    }
  /**
   * Generate HTML markup for product listing form
   *
   * @param Page $product The product item
   * @return String HTML markup
   */
    protected function renderItem($product) {

      $settings = $this->modules->get("ProcessOrderPages");
      $action_path = $this->pages->get("template=order-actions")->path;

      $title = $product->title;
      $sku = $product->sku;
      $price = $settings->getPrice($product);

      $qty_field_options = array(
        "context"=>"listing",  
        "sku"=>$sku,
      );

      $token_name = $this->token_name;
      $token_value = $this->token_value;
      $render = "<form action='' method='post'>
      <h2>$title</h2>";
      $render .= $this->renderProductShot($product, true);

      // Include cart buttons if logged in
      if($this->wire("user")->isLoggedin()) {

        $render .= "<label class='.form__label' for='quantity'>Quantity (Packs of 6):</label>";
        $render .= $this->renderQuantityField($qty_field_options);
        $render .= "<input type='hidden' id='sku' name='sku' value='$sku'>
        <input type='hidden' id='listing{$sku}_token' name='$token_name' value='$token_value'>
        <input type='hidden' id='price' name='price' value='$price'>
        <input class='form__button form__button--submit' type='submit' name='submit' value='Add to cart' data-context='listing' data-sku='$sku' data-action='add' data-actionurl='$action_path'>";
      } else {
        $render .= "<p>Login to add to order</p>";
      }

      $render .= "</form>";
      return $render;
    }
  /**
   * Generate HTML markup for current user's cart
   *
   * @param Boolean $omitContainer - true if outer div not required (useful to avoid losing click handler)
   * @param Boolean false or Function $customImageMarkup
   * @return String HTML markup
   */
    public function renderCart($omitContainer = false, $customImageMarkup = false) {

      // Store field and template names in variables for markup
      $settings = $this->modules->get("ProcessOrderPages");
      $action_path = $this->pages->get("template=order-actions")->path;

      $f_sku = $settings["f_sku"];
      $f_sku_ref = $settings["f_sku_ref"];
      $f_quantity = $settings["f_quantity"];
      $open = $omitContainer ? "" : "<div class='cart-items'><script src='" . $this->config->urls->site . "modules/OrderCart/cart.js'></script>";
      $close = $omitContainer ? "" : "</div>";

      $cart_items = $this->getCartItems();

      $render = $open;
      $render .= "<div class='cart-forms'><form class='cart-items__form' action='' method='post'>";

      // cart_items are line_items NOT product pages
      foreach ($cart_items as $item => $data) {

        $sku_ref = $data[$f_sku_ref];
        $sku_uc = strtoupper($sku_ref);
        $product_selector = "template=product, {$f_sku}={$sku_ref}";
        $product = $this->pages->findOne($product_selector);
        $title = $product->title;
        $price = $settings->getPrice($product);
        $r_price = $this->renderPrice($price);
        $quantity = $data[$f_quantity];
        $subtotal = $this->renderPrice($price * $quantity);

        $qty_field_options = array(
          "context"=>"cart", 
          "sku"=>$sku_ref,
          "quantity"=>$quantity
        );

        $token_name = $this->token_name;
        $token_value = $this->token_value;

        $render .= "<fieldset class='form__fieldset'>
        <legend>$title</legend>";

        if($customImageMarkup) {
          $render .= $customImageMarkup($product);
        } else {
          $render .= $this->renderProductShot($product);
        }

        $render .= "<p>SKU: {$sku_uc}</p>
          <label class='form__label' for='quantity'>Quantity (Packs of 6):</label>";
          $render .= $this->renderQuantityField($qty_field_options);
          $render .= "<p class='form__price'>Pack price: $r_price</p>
          <p class='form__price--subtotal'>Subtotal: $subtotal</p>
          <input type='hidden' name='sku[]' value='{$sku_ref}'>
          <input type='hidden' id='cart{$sku_ref}_token' name='$token_name' value='$token_value'>
          <input type='button' class='form__button form__button--remove' value='Remove' data-action='remove'  data-actionurl='$action_path' data-context='cart' data-sku='{$sku_ref}'>
          <input type='button' class='form__button form__button--update' value='Update quantity' data-action='update' data-actionurl='$action_path' data-context='cart' data-sku='{$sku_ref}'>
          </fieldset>";
      }
      $render .= "</form>";

      if(count($cart_items)){

        $render .= "<form class='cart-items__form' action='' method='post'>
          <input type='hidden' id='order_token' name='$token_name' value='$token_value'>
          <input class='form__button form__button--submit' type='submit' name='submit' value='submit' data-action='order' data-actionurl='$action_path'>
        </form>
        </div>";
      } else {
        $render .= "<h3>There are currently no items in the cart</h3>";
      }

      $render .= $close;

      return $render;
    }
  /**
   * Get size of product shot from config
   *
   * @param Boolean $listing - context
   * @return String Size in pixels
   */
    public function getProductShotSize($listing) {

      $size_field = $listing ? $this["f_product_img_l_size"] : $this["f_product_img_c_size"];

      if($size_field) {
        return $size_field;
      } else {
        wire("log")->save("errors", "OrderCart module: size not set for product image");
      }
    }
  /**
   * Generate HTML markup for product shot
   *
   * @param Page $product
   * @return String HTML markup
   */
    public function renderProductShot($product, $listing = false) {

      $product_shot_field = $this["f_product_img"];
      $size = $this->getProductShotSize($listing);
      $product_shot_out = "";

      // Are product shots expected?
      if($product_shot_field){

        // Does product template have the image field?
        if($product[$product_shot_field]) {

          // Is image field populated?
          if(count($product[$product_shot_field])){

            $product_shot_out = "";

            foreach($product[$product_shot_field] as $product_shot) {

              $product_shot_url = $product_shot->size($size, $size)->url;
              $dsc = $product_shot->description;
              $alt_text = $dsc ? $dsc : $product->title;
              $product_shot_out .= "<img src='$product_shot_url' alt='$alt_text'>";

              if( ! $listing) {
                // Only require first image in array for shopping cart
                break;
              }

            }


          } else {
            wire("log")->save("errors", "OrderCart module: product shot unavailable for $title");
          }
        } else {
          wire("log")->save("errors", "OrderCart module: product shot field does not exist");
        }
      }
      return $product_shot_out;
    }
}
<?php namespace ProcessWire;

class OrderCart extends WireData implements Module {
  public static function getModuleInfo() {
    return [
      'title' => 'Order Cart',
      'summary' => 'Provide front end order system for ProcessOrderPages. Requires jQuery >= 3.4.1',
      'author' => 'Paul Ashby, primitive.co',
      'version' => 1.1,
      "singular" => true,
      'autoload' => true,
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
   * @return Json Updated cart markup if successful
   */
    public function addToCart($sku, $quantity) {
      $settings = $this->modules->getConfig("ProcessOrderPages");
      $prfx = $settings["prfx"];
      
      $user_id = $this->users->getCurrentUser()->id;
      $parent_selector = $this->getCartPath();
      $child_selector = "{$prfx}_customer=$user_id, {$prfx}_sku_ref=$sku";

      $exists_in_cart = $this->pages->get($parent_selector)->child($child_selector);

      if($exists_in_cart->id) {

        // Add to exisitng line item if user already has this product in their cart
        $sum = $quantity + $exists_in_cart["{$prfx}_quantity"];
        $exists_in_cart->of(false);
        $exists_in_cart->set("{$prfx}_quantity", $sum);
        $exists_in_cart->save();

      } else { 

        // Create a new item
        $item_title = $sku . ": " . $this->users->get($user_id)["{$prfx}_display_name"];
        $item_data = array("title" => $item_title);
        $item_data["{$prfx}_customer"] = $user_id;
        $item_data["{$prfx}_sku_ref"] = $sku;
        $item_data["{$prfx}_quantity"] = $quantity;

        $cart_item = $this->wire("pages")->add("{$prfx}-line-item",  $this->getCartPath(), $item_data);
      }
      $count = $this->getNumCartItems();

      // Returning cart markup although cart is most likely not displayed when items are added
      return json_encode(array("success"=>true, "cart"=>$this->renderCart(true), "count"=>$count));
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
      $prfx = $settings["prfx"];
      $cart_item = $this->getCartItem($sku);

      if($cart_item) { // getCartItem returns false if the item cannot be found

        // Update product
        $cart_item->of(false);
        $cart_item->set("{$prfx}_quantity", $quantity);
        $cart_item->save();

        $count = $this->getNumCartItems();

        // Return entire form to cart with eager loading images.
        return json_encode(array("success"=>true, "cart"=>$this->renderCart(true, false, true), "count"=>$count));
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
        $count = $this->getNumCartItems();

        return json_encode(array("success"=>true, "cart"=>$this->renderCart(true, false, true), "count"=>$count));  
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
      $prfx = $settings["prfx"];

      $user_id = $this->users->getCurrentUser()->id;
      $cart_path = $this->getCartPath();
      $parent_selector = $cart_path;
      $child_selector = "{$prfx}_customer={$user_id}, {$prfx}_sku_ref={$sku}";
      $cart_item = $this->pages->findOne($parent_selector)->child($child_selector);

      if($cart_item->id) {
        return $cart_item;
      }
      return false;
    }
  /**
   * Get all cart items for the current user
   *
   * @param Boolean/Integer $user_id - integer provided at login as getCurrentUser() returns guest immediately after login
   * @return wireArray The cart items
   */
    protected function getCartItems($user_id = false) {
      if(! $user_id) {
        $user_id = $this->users->getCurrentUser()->id;
      }
      $settings = $this->modules->getConfig("ProcessOrderPages");
      $prfx = $settings["prfx"];
      
      return $this->pages->findOne($this->getCartPath())->children("template={$prfx}-line-item, {$prfx}_customer={$user_id}");
    }

  /**
   * Get number of items in this user's cart
   *
   * @param Boolean/Integer $user_id - integer provided at login as getCurrentUser() returns guest immediately after login
   * @return Integer
   */
    public function getNumCartItems($user_id = false) {

      $settings = $this->modules->getConfig("ProcessOrderPages");
      $prfx = $settings["prfx"];
      $cart_items = $this->getCartItems($user_id);
      $num_items = 0;
      
      foreach ($cart_items as $item) {
        $num_items += $item["{$prfx}_quantity"];
      }
      return $num_items;
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
   * @param Int $ecopack - provide product in sustainable packaging
   * @return Json
   */
    public function placeOrder($ecopack = false) {

      $u = $this->users->getCurrentUser();
      // Get the parent page for the new order
      $orders_parent = $this->getOrdersPage("pending", $u->id);

      if($orders_parent->id) {
        
        $pop = $this->modules->get("ProcessOrderPages");
        $page_maker = $this->modules->get("PageMaker");
        $settings = $this->modules->getConfig("ProcessOrderPages");
        $prfx = $settings["prfx"];

        $f_sku = $settings["f_sku"];
        $order_number = $this->getOrderNum();
        $spec = array(
          "template" => "{$prfx}-order", 
          "parent"=>$orders_parent->path(), 
          "title"=>$order_number,
          "name"=>$order_number
        );

        // Create the order
        $order_page = $page_maker->makePage($spec);
        $cart_items = $this->getCartItems();

        foreach ($cart_items as $item) {
          $sku = $item["{$prfx}_sku_ref"];
          $product_selector = "template=product, $f_sku=$sku";
          $product = $this->pages->findOne($product_selector);
          $quantity_per_unit = $product->price_category->paper->quantity_per_unit;
          $unit_increment = $product->price_category->paper->unit_increment;

          // Cancel the order if quantity is not a multiple of $unit_increment
          if( $unit_increment && $item["{$prfx}_quantity"] % $unit_increment !== 0) {

            // Reinstate any processed items
            $cart = $this->pages->get($this->getCartPath());
            $to_reinstate = $order_page->children();

            foreach ($to_reinstate as $line_item) {
              // Move this order's processed items back to cart as we're cancelling and alerting customer to the problem
              $line_item->of(false);
              $line_item->parent = $cart;
              $line_item->save();
            }
            $this->pages->delete($order_page, true);
            $sku_uc = strtoupper($sku);
            return json_encode(array("error"=>"$sku_uc sells in units of $unit_increment"));
          }
         
         $item->of(false);
          // Store price at time of purchase so unaffected by subsequent price changes
          $price = $pop->getPrice($product) * $quantity_per_unit;
          $item["{$prfx}_purchase_price"] = $price;
          $item["{$prfx}_ecopack"] = (int) $ecopack;
          $item->parent = $order_page;
          $item->save();
        }

        // Send confirmation email
        $to = $u->email;
        $company_name = $this->company;
        $subject = "Your $company_name Order";
        $u_name = $u->name;
        $body = "Hi $u_name,\nThanks for placing an order with $company_name. To save on waste, we use a print-on-demand system, so please allow eight working days for your order to arrive. In the meantime, we'll email you an invoice (new customers pro forma, existing customers usual 30 day terms).";

        $notification_status = $this->sendNotificationEmail($to, $subject, $body);

        $heads_up = "Thank you for your order - you will receive a confirmation email shortly.";

        return json_encode(array("success"=>true, "cart"=>"<h3 class='cart__order-mssg'>$heads_up</h3>", "count"=>0));  
      }

      return json_encode(array("error"=>"The orders page could not be found"));
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
      $prfx = $settings["prfx"];
      $order_selector = "template={$prfx}-order,name={$order_num}";
      $order_pg = $this->pages->findOne($order_selector);
      
      if($order_pg->id){
        // Get the customer
        $customer = $order_pg->children()->first()["{$prfx}_customer"];
        $next_step = $this->getOrdersPage($order_step, $customer);
        $order_pg->of(false);
        $order_pg->parent = $next_step;
        $order_pg->save();

        // if($order_step == "completed") {
        //   // Send confirmation email
        //   $customer_details = $this->users->get($customer);
        //   $to = $customer_details->email;
        //   $company_name = $this->company;
        //   $subject = "Your $company_name Order";
        //   $u_name = $customer_details->name;
        //   $body = "Hi $u_name,\nYour $company_name order (number $order_num) is now in progress.";

        //   $notification_status = $this->sendNotificationEmail($to, $subject, $body);
        // }
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
      $prfx = $settings["prfx"];
      $parent_path = $settings["order_root"] . "/{$order_step}-orders/";
      $order_parent_name =  "{$user_id}_orders";
      if($user_id) {
        // User provided, so get the orders page just for this customer
        $child_selector = "name=$order_parent_name";
        $user_order_page = $this->pages->get($parent_path)->child($child_selector);
        if($user_order_page->id) {
          return $user_order_page;
        }
         $spec = array(
          "template" => "{$prfx}-user-orders", 
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
    public function ___renderPrice($pence) {

      return $this["o_csign"] . number_format($pence/100, 2);
    }
/**
 * Generate HTML markup for quantity number input field
 *
 * @param Array $options [String "context", String "sku"]
 * @return String HTML markup
 */
    protected function ___renderQuantityField($options) {
      $context = $options["context"];
      $sku = $options["sku"];
      $id = $context . $sku;
      $action_path = $options["action_path"];
      $min = array_key_exists("min", $options) ? $options["min"] : 1;
      $step = array_key_exists("step", $options) ? $options["step"] : 1;

      if($context === "listing") {
        $name = "quantity";
        $value = $min;
        $action = "";
      } else {
        $name = "quantity[]";
        $value = $options["quantity"];
        $action = "update";
      }

      return "<input id='$id' class='form__quantity' type='number' data-context='$context' data-action='$action' data-actionurl='$action_path' data-sku='$sku' name='$name' min='$min' step='$step' value='$value'>";
    }
  /**
   * Generate HTML markup for product listing form
   *
   * @param Page $product The product item
   * @param String $context
   * @param Array $info - keys are class name suffixes, values are element content
   * @param Array $qty_settings - String quantity_str name of pack eg wallet, Int min, Int step
   * @return String HTML markup
   */
  protected function renderItem($product, $context = null, $info = array(), $qty_settings = null) {

      $settings = $this->modules->get("ProcessOrderPages");
      $action_path = $this->pages->get("template=order-actions")->path;

      $title = $product->title;
      $sku = $product->sku;
      $product_details = $product->price_category;

      $render = "<form class='item__form' action='' method='post'>";

      $imageMarkupFile = $this["customImageMarkup"];

      if($context == "lightbox") {
        //TODO: This should be treated as a custom markup file in the same way as 'customImageMarkup'
        $templates_path = wire("config")->paths->templates;
        // $count is the number of images we want to load from the image array
        if ($product->product_shot->count > 1) {
          $count = 2;
          $render .= wire("files")->render($templates_path . 'components/buttons/flipButton', ['button_class'=>'flip__button', 'action'=>'flip', 'button_type'=>'flip', 'button_text'=>'']);
        } else {
          $count = 1;
        }
      }
      if($imageMarkupFile) {
        $additional_class = "";
        for ($i=0; $i < $count; $i++) { 
          $render .= $this->files->render($imageMarkupFile, array("product"=>$product, "class"=>"product-shot{$additional_class}", "img_count"=>0, "eager"=>true, "img_index"=>$i, "context"=>$context));
          $additional_class = " product-shot--extra";
        }
      } else {
        $render .= $this->renderProductShot($product, $count);
      }

      $render .= "<div class='form__item-body'>";

      $item_info = "<div class='form__item-info'>
      <p class='products__sku'>$sku</p>
      <h2 class='products__title'>$title</h2>";

      foreach ($info as $class => $value) {
        $item_info .= "<p class='lightbox__$class lightbox__info-entry'>$value</p>";
      }

      $item_info .= "</div><!-- End form__item-info -->";
      // Item info
      $render .= $item_info;

      // Include cart buttons if logged in
      if($this->wire("user")->isLoggedin()) {

        $qty_field_options = array(
          "context"=>"listing",  
          "sku"=>$sku,
          "action_path"=>$action_path
        );

        if($qty_settings) {
          // Add the extra quantity settings to the $qty_field_options array
          $qty_field_options = array_merge($qty_field_options, $qty_settings);
          $quantity_str = "x " . $qty_settings["quantity_str"];
        } else {
          $pack_quantity = $product_details->paper->quantity_per_unit;
          // $quantity_str = $pack_quantity ? "x packs of $pack_quantity" : "Packs"; 
          $quantity_str = "x pack of $pack_quantity"; 
        }

        $qty_field = $this->renderQuantityField($qty_field_options);

        $token_name = $this->token_name;
        $token_value = $this->token_value;

        // Item buttons
        $render .= "<div class='form__item-buttons'>";
        $render .= $qty_field;
        $render .= "<label class='form__quantity-label' for='quantity'>$quantity_str</label>";
        
        $render .= "<input type='hidden' id='listing{$sku}_token' name='$token_name' value='$token_value'>
        <input class='form__button form__button--submit' type='submit' name='submit' value='Add to cart' data-context='listing' data-sku='$sku' data-action='add' data-actionurl='$action_path'>
        </div><!-- End form__item-buttons -->";
      }
      $render .= "</div><!-- End form__item-body -->";

      // Use $context param for class names if provided
      $class_prfx = isset($context) ? $context . "_" : "";

      $render .= "<div class='{$class_prfx}message'><p class='{$class_prfx}message-text'><span class='{$class_prfx}message-content'>Added to cart</span></p></div>";

      $render .= "</form>";
      return $render;
    }
  /**
   * Generate HTML markup for cart container
   *
   * @return String HTML markup
   */
    public function getOuterCartMarkup() {

      $cart_script_url = $this->config->urls->site . "modules/OrderCart/ordercart.js";
      $cart_items = $this->getCartItems();
      $cart_items_class = count($cart_items) ? "cart-items" : "cart-items cart-items--empty";

      return [
        "open" => "<script src='$cart_script_url'></script>
        <div class='$cart_items_class'>",
        "close" => "</div><!-- End cart-items -->"
      ];
    }
  /**
   * Generate HTML markup for empty cart to be populated by AJAX call
   *
   * @return String HTML markup
   * @param String $spinner - placeholder to display while content is loading
   */
    public function renderEmptyCart($spinner = "") {

      $cart_markup = $this->getOuterCartMarkup();
      return $cart_markup["open"] . $spinner . $cart_markup["close"];
    }
  /**
   * Generate HTML markup to populate empty cart
   *
   * @param String $customImageMarkup - name of image markup file
   * @param Boolean $eager - for cases where we don't want lazy loading images
   * @return Array [Int count, String markup]
   */
    public function populateCart($customImageMarkup, $eager = false) {

      $this->setCustomImageMarkup($customImageMarkup);

      $settings = $this->modules->get("ProcessOrderPages");
      $cart_items = $this->getCartItems();
      $item_count = count($cart_items);

      $render = "<div class='cart-forms'>
      <form class='cart-items__form' action='' method='post'>";

      if($item_count){
        $render .= $this->renderCartItems($cart_items, $eager);
        return array("count" => $item_count, "markup" => $render);
      }
      $render .= "</form><!-- End cart-items__form -->
        <h3 class='cart__empty-mssg'>The cart is empty</h3>
        </div><!-- End cart-forms -->";
        return array("count" => 0, "markup" => $render);  
    }    
  /**
   * Generate HTML markup for current user's cart
   *
   * @param Boolean $omitContainer - true if outer div not required (useful to avoid losing click handler)
   * @param Boolean false or Function $customImageMarkup
   * @param Boolean $eager - for cases where we don't lazy loading images
   * @return String HTML markup
   */
  public function renderCart($omitContainer = false, $customImageMarkup = false, $eager = false) {

      if($omitContainer) {
        $container = ["open" => "", "close" => ""];
      } else {
        $container = $this->getOuterCartMarkup();
      }
      $render = $container["open"];
      $render .=  $this->populateCart($customImageMarkup, $eager)["markup"];
      $render .= $container["close"];

      return $render;
    }   
  /**
   * Generate HTML markup for current user's cart items
   *
   * @param Page array $cart_items - line_item pages NOT product pages
   * @param Boolean $eager - for cases where we don't lazy loading images
   * @return String HTML markup
   */
    protected function renderCartItems($cart_items, $eager) {

      $settings = $this->modules->get("ProcessOrderPages");
      $prfx = $settings["prfx"];
      $f_sku = $settings["f_sku"];
      $eager_count = 0;
      $render_data = array(
        "total" => 0,
        "token_name" => $this->token_name,
        "token_value" => $this->token_value,
        "action_path" => $this->pages->get("template=order-actions")->path
      );
      $markup = "";

      // cart_items are line_items NOT product pages
      foreach ($cart_items as $item => $data) {

        $sku_ref = $data["{$prfx}_sku_ref"];
        $product_selector = "template=product, {$f_sku}={$sku_ref}";
        $product = $this->pages->get($product_selector);
        $quantity_per_unit = $product->price_category->paper->quantity_per_unit;

        $quantity = $data["{$prfx}_quantity"];
        $price = $settings->getPrice($product) * $quantity_per_unit;
        $line_item_total = $price * $quantity;

        // Track number of images in case $customImageMarkup has a lazy loading threshold
        $render_data["total"] += $line_item_total;
        $markup .= "<fieldset class='cart__fieldset'>";

        $imageMarkupFile = $this["customImageMarkup"];
        if($imageMarkupFile) {
          $eager_count++;
          $markup .= $this->files->render($imageMarkupFile, array("product"=>$product, "img_count"=>$eager_count, "eager"=>$eager));
        } else {
          $markup .= $this->renderProductShot($product);
        }
        
        $render_data["sku"] = $sku_ref;
        $render_data["quantity"] = $quantity;
        // $render_data["item_str"] = $quantity > 1 ? "Packs" : "Pack";
        $render_data["item_str"] ="pack";
        $render_data["qty_str"] = "6";
        $render_data["product_title"] = $product->title;
        $render_data["lit_formatted"] = $this->renderPrice($line_item_total);
        $render_data["price_formatted"] = $this->renderPrice($price);
        $render_data["context"] = "cart";

        $markup .= $this->renderCartItem($render_data);
      }
      $markup .= $this->renderCartButtons($render_data);

      return $markup;
    }

  /**
   * Generate HTML markup for cart item
   *
   * @param Array $spec [
   *    String token_name
   *    String token_value
   *    String action_path
   *    String sku_ref
   *    String quantity 
   *    String pack_str
   *    String product_title
   *    String lit_formatted
   *    String price_formatted
   *    String context
   * ]
   * @return  String HTML markup
   */
    protected function ___renderCartItem($spec) {

      $markup =  "<div class='cart__info'>
        <p class='products__sku'>" . $spec["sku"] . "</p>
          <h2 class='cart__item-title'>" . $spec["product_title"] . "</h2>";

      $markup .= $this->renderQuantityField($spec);
      $item_qty_str = $spec["item_str"];

      if(array_key_exists("qty_str", $spec)){
        $item_qty_str .= " of " . $spec["qty_str"];
      }
      
      $markup .= "<label class='form__quantity-label' for='quantity'>x $item_qty_str</label>
        <p class='cart__price'>" . $spec["lit_formatted"] . " <span class='cart__price--unit'>" . $spec["price_formatted"] . " per " . $spec["item_str"] . "</span></p>
            <input type='hidden' id='cart" . $spec["sku"] . "_token' name='" . $spec["token_name"] . "' value='" . $spec["token_value"] . "'>
            <input type='button' class='form__link' value='Remove' data-action='remove'  data-actionurl='" . $spec["action_path"] . "' data-context='cart' data-sku='" . $spec["sku"] . "'>
            </div><!-- End cart__info -->
          </fieldset>";

      return $markup;
    }
  /**
   * Generate HTML markup for main cart buttons
   *
   * @param Array $render_data [
   *    Int total
   *    String token_name
   *    String token_value
   *    String action_path
   * ]
   * @return String HTML markup
   */
    protected function renderCartButtons($render_data) {

        $total_formatted = $this->renderPrice($render_data["total"]);
        $shipping_info = $this["f_shipping_info"];
        $shipping_message = strlen($shipping_info) ? "<p class='form__shipping'>$shipping_info</p>" : "";

        return "<p class='cart__price cart__price--total'>Total: $total_formatted</p>
        </form><!-- End cart-items__form -->
        <form class='cart-items__form' action='' method='post'>
          <input type='hidden' id='order_token' name='" . $render_data["token_name"] . "' value='" . $render_data["token_value"] . "'>
          <input class='form__button form__button--submit form__button--cart' type='submit' name='submit' value='order' data-action='order' data-actionurl='" . $render_data["action_path"] . "'>
          <div class='form__eco'>
            <input type='checkbox' id='cartsustainable' class='form__eco-checkbox' name='sustainable' value='sustainable' checked>
              <label for='cartsustainable' class='form__label form__note form__note--eco'>Use sustainable packaging</label>
              $shipping_message
          </div>
          </form>
        </div><!-- End cart-forms -->";
    }
  /**
   * Store name of custom image markup file in module config
   *
   * @param String $customImageMarkup - name of image markup file
   */
    protected function setCustomImageMarkup($customImageMarkup = false){

      if( ! $this["customImageMarkup"] && $customImageMarkup) {
        $data = $this->modules->getConfig($this->className);
        $data["customImageMarkup"] = $customImageMarkup;
        $this->modules->saveConfig($this->className, $data);
      }
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
   * @param Int $count
   * @return String HTML markup
   */
    public function renderProductShot($product, $count = 1) {

      $product_shot_field = $this["f_product_img"];
      $size = $this->getProductShotSize($count != 1); // Everywhere except lightbox
      $product_shot_out = "";

      // Are product shots expected?
      if( ! $product_shot_field){
        return wire("log")->save("errors", "OrderCart module: product shot field name not set in module config");
      }
      // Does product template have the image field?
      if( ! $product[$product_shot_field]) {
        return wire("log")->save("errors", "OrderCart module: product shot field does not exist");
      }
      // Is image field populated?
      $available = count($product[$product_shot_field]);
      if( ! $available){
        return wire("log")->save("errors", "OrderCart module: product shot unavailable for $title");
      }

      $product_shot_out = "";
      $additional_class = "";

      for ($i=0; $i < $count && $i < $available; $i++) { 
        $product_shot = $product[$product_shot_field]->eq($i);
        $product_shot_url = $product_shot->size($size, $size)->url;
        $dsc = $product_shot->description;
        $alt_text = $dsc ? $dsc : $product->title;
        $product_shot_out .= "<img src='$product_shot_url' class='product-shot{$additional_class}' alt='$alt_text'>";
        $additional_class = " product-shot--extra";
      }

      return $product_shot_out;
    }

    /**
     * Get parent page for order - for current user only if id supplied
     *
     * @param String $to
     * @param String $subject
     * @param String $body
     * @return String status message
     */
    protected function sendNotificationEmail($to, $subject, $body) {

      $from = $this->mailfrom;

      if($this->modules->isInstalled("ProcessContactPages")) {

        $pcp = $this->modules->get("ProcessContactPages");
        $message_array = explode("\n", $body);
        $pcp->sendHTMLmail($to, $subject, $message_array);
        return "HTML email sent to $to";

      } 
      $this->mail->send($to, $from, $subject, $body);
      return "Unformatted email sent to $to";
    }
}
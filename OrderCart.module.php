<?php namespace ProcessWire;
class OrderCart extends WireData implements Module {
  public static function getModuleInfo() {
    return [
      'title' => 'Order Cart',
      'summary' => 'Provide front end order system for ProcessOrderPages',
      'author' => 'Paul Ashby, primitive.co',
      'version' => 1
    ];
  }
  public function __construct() {

    parent::__construct();
    $this->settings = $this->modules->get("ProcessOrderPages");
    $this->page_maker = $this->modules->get("PageMaker");

  }
  /**
   * Add product to cart (creates a line-item page as child of /processwire/orders/cart-items)
   *
   * @param string $item The submitted form
   * @return string The configured field name
   */
    public function addToCart($item) {
      
      $sku = $this->sanitizer->text($item->sku);
      $new_quantity = $this->sanitizer->int($item->quantity);
      
      // Is there an existing order for this product?
      $f_customer = $this->settings["f_customer"];
      $f_sku_ref = $this->settings["f_sku_ref"];
      $user_id = $this->users->getCurrentUser()->id;
      $parent_selector = $this->getCartPath();
      $child_selector = "$f_customer=$user_id,$f_sku_ref=$sku";
      $exists_in_cart = $this->pages->get($parent_selector)->child($child_selector);

      if($exists_in_cart->id) {
        
        // Add to existing item
        $sum = $new_quantity + $exists_in_cart[$this->settings["f_quantity"]];
        $exists_in_cart->of(false);
        $exists_in_cart->set($this->settings["f_quantity"], $sum);
        $exists_in_cart->save();

      } else { 

        // Create a new item
        $item_title = $sku . ": " . $this->users->get($user_id)[$this->settings["f_display_name"]];
        $item_data = array("title" => $item_title);
        $item_data[$this->settings["f_customer"]] = $user_id;
        $item_data[$this->settings["f_sku_ref"]] = $sku;
        $item_data[$this->settings["f_quantity"]] = $new_quantity;

        $cart_item = $this->wire("pages")->add($this->settings["t_line-item"],  $this->getCartPath(), $item_data);
      }
      return json_encode(array("success"=>true));
    }
  /**
   * Change quantity of cart item
   *
   * @param string  $sku The item to update
   * @param string  $qty The new value
   * @return Json Updated cart markup if successful
   */
    public function changeQuantity($sku, $qty) {

      $cart_item = $this->getCartItem($sku);
      $qtys = $this->sanitizer->text($qty);

      if($cart_item->id) {
          $cart_item->of(false);
          $cart_item->set($this->settings["f_quantity"], (int)$qtys);
          $cart_item->save();
          return json_encode(array("success"=>true, "cart"=>$this->renderCart(true)));  
      }
      return json_encode(array("error"=>"The item could not be found"));
    }
    /**
   * Remove line item from cart
   *
   * @param string  $sku The item to remove
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
   * @param string  $sku The item to get
   * @return object Line item page or boolean false
   */
    protected function getCartItem($sku) {

      $skus = $this->sanitizer->text($sku);
      $user_id = $this->users->getCurrentUser()->id;
      $f_customer = $this->settings["f_customer"];
      $f_sku_ref = $this->settings["f_sku_ref"];
      $cart_path = $this->getCartPath();
      $parent_selector = "$cart_path, include=all";
      $child_selector = "{$f_customer}={$user_id}, {$f_sku_ref}={$skus}, include=all";
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
      $user_id = $this->users->getCurrentUser()->id;
      $t_line_item = $this->settings["t_line-item"];
      $f_customer = $this->settings["f_customer"];
      return $this->pages->findOne($this->getCartPath() . ", include=all")->children("template={$t_line_item}, {$f_customer}={$user_id}, include=all");
    }
  /**
   * Get the path of the cart
   *
   * @return string
   */
    public function getCartPath() {
      return $this->settings["order_root"] . "/cart-items/";
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
      
      if($orders_parent) {

        $order_number = $this->getOrderNum();
        $spec = array(
          "template" => $this->settings["t_order"], 
          "parent"=>$orders_parent->path(), 
          "title"=>$order_number,
          "name"=>$order_number
        );

        // Create the order
        $order_page = $this->page_maker->makePage($spec);
        $cart_items = $this->getCartItems();

        foreach ($cart_items as $item) {
          $item->of(false);
          $item->parent = $order_page;
          $item->save();
        }
        return json_encode(array("success"=>true));
      }
      $errors[] = "The orders page could not be found";
      return json_encode(array("errors"=>$errors));
    }
  /**
   * Move order to next step to reflect new status
   *
   * @param string $order_num
   * @param string $order_step
   * @return boolean
   */
    public function progressOrder($order_num, $order_step) {
      $order_selector = "template=" . $this->settings["t_order"] . ",name={$order_num}";
      $order_pg = $this->pages->findOne($order_selector);
      
      if($order_pg->id){
        // Get the customer
        $customer = $order_pg->children()->first()[$this->settings["f_customer"]];
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
   * @param string $order_step
   * @param integer $user_id
   * @return PageArray or Page
   */
    public function getOrdersPage($order_step, $user_id = null) {
      
      $parent_path = $this->settings["order_root"] . "/{$order_step}-orders/";
      $parent_selector = "$parent_path,include=all"; 
      $order_parent_name =  "{$user_id}_orders";
      if($user_id) {
        // User provided, so get the orders page just for this customer
        $child_selector = "name=$order_parent_name,include=all";
        $user_order_page = $this->pages->get($parent_selector)->child($child_selector);
        if($user_order_page->id) {
          return $user_order_page;
        }
        $order_template = $this->settings["t_user-orders"];
        $spec = array(
          "template" => $order_template, 
          "parent"=>$parent_path, 
          "title"=>$order_parent_name, 
          "name"=> $order_parent_name
        );

        // No orders for this user - make a new page within pending orders
        return $this->page_maker->makePage($spec);
      }
      // All orders for given step
      return $this->pages->get($parent_path)->children();
    }
  /**
   * Get order number then increment in db
   *
   * @return  string The unincremented order number
   */
    protected function getOrderNum() {

      $data = $this->modules->getConfig("ProcessOrderPages");
      $order_num = $this->sanitizer->text($data["order_num"]);
      $this_order_num = $order_num;
      $order_num++;
      $data["order_num"] = $this->sanitizer->text($order_num);
      $this->modules->saveConfig("ProcessOrderPages", $data);
      return $this_order_num;
    }
  /**
   * Set order number
   *
   * @param string  $val The number to base new orders on
   * @return boolean
   */
    protected function setOrderNum($val) {

      $data = $this->modules->getConfig("ProcessOrderPages");
      $data["order_num"] = $val;
      return $this->modules->saveConfig("ProcessOrderPages", $data);
    }
  /**
   * Increment order number
   *
   * @return string The new order number
   */
    protected function incrementOrderNum() {

      $data = $this->modules->getConfig("ProcessOrderPages");
      $order_num = $this->sanitizer->text($data["order_num"]);
      $order_num++;
      $data["order_num"] = $this->sanitizer->text($order_num);
      $this->modules->saveConfig("ProcessOrderPages", $data);
      return $data["order_num"];
    }
  /**
   * Convert an integer representing GB pence to a GBP string 
   *
   * @param int $pence
   * @return string GBP value as a string with decimal point and prepended £
   */
    public function renderPrice($pence) {

      return $this["o_csign"] . number_format($pence/100, 2);
    }
  /**
   * Generate HTML markup for current user's cart
   *
   * @param boolean $omitContainer - true if outer div not required (useful to avoid losing click handler)
   * @return string HTML markup
   */
    public function renderCart($omitContainer = false) {

      // Store field and template names in variables for markup
      $f_sku = $this->settings["f_sku"];
      $f_sku_ref = $this->settings["f_sku_ref"];
      $f_quantity = $this->settings["f_quantity"];
      $open = $omitContainer ? "" : "<div class='cart-items'>";
      $close = $omitContainer ? "" : "</div>";

      $cart_items = $this->getCartItems();

      $render = $open;
      $render .= "<form class='cart-items__form' action='' method='post'>";
      // cart_items are line_items NOT product pages
      foreach ($cart_items as $item => $data) {
        $sku_ref = $data[$f_sku_ref];
        $sku_uc = strtoupper($sku_ref);
        $product_selector = "template=product, {$f_sku}={$sku_ref}";
        $product = $this->pages->findOne($product_selector);
        $price = $this->renderPrice($product->price);
        $quantity = $data[$f_quantity];
        $subtotal = $this->renderPrice($product->price * $quantity);

        $render .= "<fieldset class='form__fieldset'>
        <legend>" . $product->title . "</legend>";
        
        $render .= "<p>SKU: {$sku_uc}</p>
          <label class='form__label' for='quantity'>Quantity (Packs of 6):</label>
          <input class='form__quantity' type='number' data-action='qtychange' data-sku='{$sku_ref}' name='quantity[]' min='1' step='1' value='{$quantity}'>
          <p class='form__price'>Pack price: $price</p>
          <p class='form__price--subtotal'>Subtotal: $subtotal</p>
          <input type='hidden' name='sku[]' value='{$sku_ref}'>
          <input type='button' class='form__button form__button--remove' value='Remove' data-action='remove' data-sku='{$sku_ref}'>
          </fieldset>";
      }
      $render .= "<input class='form__button form__button--submit' type='submit' name='submit' value='submit'>
        </form>";
      $render .= $close;

      return $render;
    }
  }
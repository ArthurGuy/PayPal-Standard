<?php
/**
 * Impliments PayPal Standard.
 * Generates the form components.
 *
 * @author Arthur Guy <arthur@arthurguy.co.uk>
 * @copyright Copyright 2010, ArthurGuy.co.uk
 * @version 1.1
 * @example
 * Minimum Example
 *  include_once 'PayPal_Standard.php';
 *  $PayPal = new PayPal_Standard();
 *  $PayPal->set_paypal_email('your_paypal@email_address.com');
 *  $PayPal->add_row('', 'Item for Sale', 9.99);
 *  $PayPal->generate_form(true);
 *
 * The PayPal form can be autosubmitted on page load by setting the auto submit option before the form is generated
 *  $PayPal->setup_payment_form('', '', '', '', true);
 * 
 */
class PayPal_Standard {
	private $paypal_email = '';			//Stores the business paypal email address
	private $currency_code = 'GBP';
	private $ipn_url = '';				//The return url for IPN notifications
	private $submit_url = 'https://www.paypal.com/cgi-bin/webscr';
	private $return_url = '';

	private $payment_button_text = 'PayPal Checkout';
	private $payment_button_class = '';
	private $payment_form_name = '';
	private $payment_form_class = '';
	private $payment_form_autosubmit = false;

	private $invoice_number = '';		//A custom unique invoice number
	private $custom_number = '';		//A custom hidden number

	private $basket_items = array();	//Stores an array of basket items, each row is an array within the array
	private $address = array('address_1'=>'', 'address_2'=>'', 'town'=>'', 'county'=>'', 'post_code'=>'', 'country'=>'GB');
	private $customer_details = array('first_name'=>'', 'last_name'=>'', 'email'=>'');
	private $tax = 0;
	private $shipping_details = array('weight_units'=>'kgs', 'weight_cart'=>'', 'shipping'=>'');

	private $discount = array('discount_amount_cart'=>'', 'discount_rate_cart'=>'');



	/** Sets the business paypal email address - this is where payments will be sent */
	function set_paypal_email($email='')
	{
		$this->paypal_email = $email;
		return true;
	}

	/** Sets the IPN notification URL
	 *  Overides the value stored in the PayPal acount
	 */
	function set_ipn_url($url='')
	{
		$this->ipn_url = $url;
		return true;
	}

	/** Adds a row to the basket.
	 *  If this level of detail doesnt need to be passed to PayPal then a single row can be created with a generic name.
	 *  The only required details are the item name and item price.
	 */
	function add_row($item_number='',$item_name='',$amount=0,$quantity=1,$tax=0,$weight=0,$shipping=0,$handling=0,$discount_amount=0,$discount_rate=0)
	{
		if (!empty($item_name) && !empty($amount) && !empty($quantity))
		{
			$this->basket_items[] = array(	'item_number'=>$item_number,
											'item_name'=>$item_name,
											'amount'=>$amount,
											'quantity'=>$quantity,
											'weight'=>$weight,
											'shipping'=>$shipping,
											'handling'=>$handling,
											'discount_amount'=>$discount_amount,
											'discount_rate'=>$discount_rate,
											'tax'=>$tax);
			return true;
		}
		else
		{
			return false;
		}
	}

	/** Sets the customer details incase the customer doesn't have a PayPal acount */
	function set_customer_details($first_name='', $last_name='', $email='')
	{
		$this->customer_details = array(	'first_name'=>$first_name,
											'last_name'=>$last_name,
											'email'=>$email);
		return true;
	}

	/** This prepopulates the customers address incase of new accounts.
	 *  Country code needs to be 2 character ISO codes.
	 *  Force use prevents the customer from overiding it
	 */
	function set_address($address_1='', $address_2='', $town='', $post_code='', $country='GB', $force_use=0)
	{
		$this->address = array(	'address_1'=>$address_1,
								'address_2'=>$address_2,
								'town'=>$town,
								'post_code'=>$post_code,
								'country'=>$country,
								'address_override'=>$force_use);
		return true;
	}

	/** Sets shipping specific details */
	function set_shipping_details($weight_units='', $weight_cart='', $shipping='')
	{
		//If the weight units have been set make sure they are the alowed values if not reset.
		if (!empty($weight_units))
		{
			if (($weight_units != 'lbs') && ($weight_units != 'kgs'))
			{
				$weight_units = 'kgs';
			}
		}
		else
		{
			$weight_units = 'kgs';
		}
		if (!is_numeric($weight_cart))
			$weight_cart = '';
		if (!is_numeric($shipping))
			$shipping = '';
		$this->shipping_details = array('weight_units'=>$weight_units,
										'weight_cart'=>$weight_cart,
										'shipping'=>$shipping);
		return true;
	}

	/** Sets any global order level discounts */
	function set_discount($discount_amount=0, $discount_rate=0)
	{
		if (!is_numeric($discount_amount))
			$discount_amount = '';
		if (!is_numeric($discount_rate))
			$discount_rate = '';
		$this->discount = array('discount_amount_cart'=>$discount_amount,
								'discount_rate_cart'=>$discount_rate);
		return true;
	}

	/** Sets global order level tax */
	function set_tax($tax=0)
	{
		if (!is_numeric($tax))
			$tax = '';
		$this->tax = $tax;
		return true;
	}

	/** Sets the invoice/order number
	 *  This must be unique and will be displayed to the customer
	 */
	function set_invoice_number($number='')
	{
		$this->invoice_number = $number;
		return true;
	}

	/** Sets a hidden ID
	 *  This can be any number and wont be shown to the customer
	 */
	function set_custom_number($number='')
	{
		$this->custom_number = $number;
		return true;
	}

	/** This sets the paypal return or shopping url */
	function set_return_url($return_url='')
	{
		$this->return_url = $return_url;
		return true;
	}

	/** Sets specifics for the visable paypal button and form.
	 *  The form can also automaticly submit with the help of javascript, set autosubmit to true to force this.
	 */
	function setup_payment_form($button_text='', $button_class='', $form_name='', $form_class='', $autosubmit = false)
	{
		if (!empty($button_text))
			$this->payment_button_text = $button_text;
		if (!empty($button_class))
			$this->payment_button_class = $button_class;
		if (!empty($form_name))
			$this->payment_form_name = $form_name;
		if (!empty($form_class))
			$this->payment_form_class = $form_class;

		//If autosubmiting make sure the form has a name
		if ($autosubmit)
		{
			$this->payment_form_autosubmit = true;
			if (empty($form_name))
				$this->payment_form_name = 'paypal_standard_payment_form';
		}
		else
		{
			$this->payment_form_autosubmit = false;
		}
	}

	/** Generates the html form code
	 *  The html is returned as a string unless true is passed as a variable, then it is echoed directly
	 */
	function generate_form($echo = false)
	{
		//Make sure the recipient email address is set
		if (empty($this->paypal_email))
			return false;
		//Make sure there is something in the basket
		if (count($this->basket_items) == 0)
			return false;
		
		$html = '<form method="post" action="'.$this->submit_url.'" name='.$this->payment_form_name.' class="'.$this->payment_form_class.'">';
		$html .= '<input type="hidden" name="cmd" value="_cart">';
		$html .= '<input type="hidden" name="upload" value="1">';
		$html .= '<input type="hidden" name="no_note" value="1">';
		$html .= '<input type="hidden" name="charset" value="utf-8">';

		//Process the order lines
		$i = 1;
		foreach ($this->basket_items as $row)
		{
			if (!empty($row['item_number']))
				$html .= '<input type="hidden" name="item_number_'.$i.'" value="'.$row['item_number'].'">';
			$html .= '<input type="hidden" name="item_name_'.$i.'" value="'.$row['item_name'].'">';
			$html .= '<input type="hidden" name="amount_'.$i.'" value="'.$row['amount'].'">';
			if (!empty($row['quantity']))
				$html .= '<input type="hidden" name="quantity_'.$i.'" value="'.$row['quantity'].'">';
			if (!empty($row['weight']))
				$html .= '<input type="hidden" name="weight_'.$i.'" value="'.$row['weight'].'">';
			if (!empty($row['shipping']))
				$html .= '<input type="hidden" name="shipping_'.$i.'" value="'.$row['shipping'].'">';
			if (!empty($row['handling']))
				$html .= '<input type="hidden" name="handling_'.$i.'" value="'.$row['handling'].'">';
			if (!empty($row['discount_amount']))
				$html .= '<input type="hidden" name="discount_amount_'.$i.'" value="'.$row['discount_amount'].'">';
			if (!empty($row['discount_rate']))
				$html .= '<input type="hidden" name="discount_rate_'.$i.'" value="'.$row['discount_rate'].'">';
			if (!empty($row['tax']))
				$html .= '<input type="hidden" name="tax_'.$i.'" value="'.$row['tax'].'">';
			$i++;
		}

		//Order Details
		$html .= '<input type="hidden" name="business" value="'.$this->paypal_email.'">';
		if (!empty($this->currency_code))
			$html .= '<input type="hidden" name="currency_code" value="'.$this->currency_code.'">';
		if (!empty($this->invoice_number))
			$html .= '<input type="hidden" name="invoice" value="'.$this->invoice_number.'">';
		if (!empty($this->custom_number))
			$html .= '<input type="hidden" name="custom" value="'.$this->custom_number.'">';
		if (!empty($this->return_url))
			$html .= '<input type="hidden" name="shopping_url" value="'.$this->return_url.'">';
		if (!empty($this->ipn_url))
			$html .= '<input type="hidden" name="notify_url" value="'.$this->ipn_url.'">';

		//Tax
		if (!empty($this->tax))
			$html .= '<input type="hidden" name="tax" value="'.$this->tax.'">';

		//Shipping details
		if (!empty($this->shipping_details['shipping']))
			$html .= '<input type="hidden" name="shipping" value="'.$this->shipping_details['shipping'].'">';
		if (!empty($this->shipping_details['weight_units']))
			$html .= '<input type="hidden" name="weight_units" value="'.$this->shipping_details['weight_units'].'">';
		if (!empty($this->shipping_details['weight_cart']))
			$html .= '<input type="hidden" name="weight_cart" value="'.$this->shipping_details['weight_cart'].'">';

		//Discounts
		if (!empty($this->discount['discount_amount_cart']))
			$html .= '<input type="hidden" name="discount_amount_cart" value="'.$this->discount['discount_amount_cart'].'">';
		if (!empty($this->discount['discount_rate_cart']))
			$html .= '<input type="hidden" name="discount_rate_cart" value="'.$this->discount['discount_rate_cart'].'">';

		if (!empty($this->customer_details['first_name']))
			$html .= '<input type="hidden" name="first_name" value="'.$this->customer_details['first_name'].'">';
		if (!empty($this->customer_details['last_name']))
			$html .= '<input type="hidden" name="last_name" value="'.$this->customer_details['last_name'].'">';
		if (!empty($this->customer_details['email']))
			$html .= '<input type="hidden" name="email" value="'.$this->customer_details['email'].'">';

		//Customer Address
		if (!empty($this->address['address_1']))
			$html .= '<input type="hidden" name="address1" value="'.$this->address['address_1'].'">';
		if (!empty($this->address['address_2']))
			$html .= '<input type="hidden" name="address2" value="'.$this->address['address_2'].'">';
		if (!empty($this->address['town']))
			$html .= '<input type="hidden" name="city" value="'.$this->address['town'].'">';
		if (!empty($this->address['post_code']))
			$html .= '<input type="hidden" name="zip" value="'.$this->address['post_code'].'">';
		if (!empty($this->address['country']))
			$html .= '<input type="hidden" name="country" value="'.$this->address['country'].'">';
		if (!empty($this->address['address_override']))
			$html .= '<input type="hidden" name="address_override" value="'.$this->address['address_override'].'">';

		$html .= '<input type="submit" value="'.$this->payment_button_text.'" class="'.$this->payment_button_class.'">';

		$html .= '</form>';

		if ($this->payment_form_autosubmit)
		{
			$html .= '<script type="text/javascript">document.'.$this->payment_form_name.'.submit();</script>';
		}

		if ($echo)
			echo $html;
		else
			return $html;
	}

	
}
?>

<?php
/*
  Copyright (c) 2010. All rights reserved ePay - www.epay.dk.

  This program is free software. You are allowed to use the software but NOT allowed to modify the software. 
  It is also not legal to do any changes to the software and distribute it in your own name / brand. 
*/
class EPAY extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();
	
	public function __construct()
	{
		$this->name = 'epay';
		$this->version = 4.6;
		$this->author = "ePay Payment Solutions / Modded by 1337 ApS";
		$this->module_key = "33db444abbb39b2a7c74d8b7da806a66";
		
		/* Payment modules placed in:
		 *	PrestaShop 1.1 - 1.3 in tab "payment"
		 *  PrestaShop 1.4.x in tab "payments_gateways"
		 */
		if(version_compare(_PS_VERSION_, "1.4.0.0", ">="))
			$this->tab = 'payments_gateways';
		else
			$this->tab = 'Payment';
		
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		
		parent::__construct();
		
		/* The parent construct is required for translations */
		$this->page = basename(__FILE__ , '.php');
		$this->displayName = 'ePay';
		$this->description = $this->l('Accept Dankort, eDankort, VISA, Electron, MasterCard, Maestro, JCB, Diners, AMEX, EWIRE, Nordea and Danske Bank payments by ePay Secure Online Payment System');
	}
	
	public function install()
	{
		if(!parent::install() OR !Configuration::updateValue('EPAY_GOOGLE_PAGEVIEW', '0') OR !Configuration::updateValue('EPAY_WINDOWID', '1') OR !Configuration::updateValue('EPAY_INTEGRATION', '1') OR !Configuration::updateValue('EPAY_ENABLE_INVOICE', '1') OR !$this->registerHook('payment') OR !$this->registerHook('rightColumn') OR !$this->registerHook('adminOrder') OR !$this->registerHook('paymentReturn') OR !$this->registerHook('footer'))
			return false;
		
		if(!$this->_createEpayTable())
			return false;
		
		if(!Configuration::updateValue('EPAY_VERSION', $this->version))
			return false;
		
		return true;
	}
	
	public function uninstall()
	{
		return parent::uninstall();
	}
	
	function _createEpayTable()
	{
		$table_name = _DB_PREFIX_ . 'epay_transactions';
		
		$columns = array
		(
			'id_order' => 'int(10) unsigned NOT NULL',
			'id_cart' => 'int(10) unsigned NOT NULL',
			'epay_transaction_id' => 'int(10) unsigned NOT NULL',
			'card_type' => 'int(4) unsigned NOT NULL DEFAULT 1',
			'cardnopostfix' => 'int(4) unsigned NOT NULL DEFAULT 1',
			'currency' => 'int(4) unsigned NOT NULL DEFAULT 0',
			'amount' => 'int(10) unsigned NOT NULL',
			'amount_captured' => 'int(10) unsigned NOT NULL DEFAULT 0',
			'amount_credited' => 'int(10) unsigned NOT NULL DEFAULT 0',
			'transfee' => 'int(10) unsigned NOT NULL DEFAULT 0',
			'fraud' => 'tinyint(1) NOT NULL DEFAULT 0',
			'captured' => 'tinyint(1) NOT NULL DEFAULT 0',
			'credited' => 'tinyint(1) NOT NULL DEFAULT 0',
			'deleted' => 'tinyint(1) NOT NULL DEFAULT 0',
			'date_add' => 'datetime NOT NULL'
		);
		
		$query = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (';
		
		foreach ($columns as $column_name => $options)
		{
			$query .= '`' . $column_name . '` ' . $options . ', ';
		}
		$query .= ' PRIMARY KEY (`epay_transaction_id`) )';
		
		if(!Db::getInstance()->Execute($query))
			return false;
		
		$i = 0;
		$previous_column = '';
		$query = ' ALTER TABLE `' . $table_name . '` ';
		/* Check the database fields */
		foreach ($columns as $column_name => $options)
		{
			if(!$this->_mysql_column_exists($table_name, $column_name))
			{
				$query .= ($i > 0 ? ', ' : '') . 'ADD `' . $column_name . '` ' . $options . ($previous_column != '' ? ' AFTER `' . $previous_column . '`' : ' FIRST');
				$i++;
			}
			$previous_column = $column_name;
		}
		
		if($i > 0)
			if(!Db::getInstance()->Execute($query))
				return false;
		
		return true;
	}
	
	static function _mysql_column_exists($table_name, $column_name, $link = false)
	{
		echo "SHOW COLUMNS FROM $table_name LIKE '$column_name'";
		$result = Db::getInstance()->executeS("SHOW COLUMNS FROM $table_name LIKE '$column_name'", $link);
		
		return (count($result) > 0);
	}
	
	function recordTransaction($id_order, $id_cart = 0, $transaction_id = 0, $cardid = 0, $cardnopostfix = 0, $currency = 0, $amount = 0, $transfee = 0, $fraud = 0)
	{
		if($id_cart)
			$id_order = Order::getOrderByCartId($id_cart);
		if(!$id_order)
			$id_order = 0;
		
		$captured = (Configuration::get('EPAY_INSTANTCAPTURE') ? 1 : 0);
		
		/* Tilføj transaktionsid til ordren */
		$query = 'INSERT INTO ' . _DB_PREFIX_ . 'epay_transactions
				(id_order, id_cart, epay_transaction_id, card_type, cardnopostfix, currency, amount, transfee, fraud, captured, date_add)
				VALUES 
				(' . $id_order . ', ' . $id_cart . ', ' . $transaction_id . ', ' . $cardid . ', ' . $cardnopostfix . ', ' . $currency . ', ' . $amount . ', ' . $transfee . ', ' . $fraud . ', ' . $captured . ', NOW() )';
		
		if(!Db::getInstance()->Execute($query))
			return false;
		
		return true;
	}
	
	function setCaptured($transaction_id, $amount)
	{
		$query = ' UPDATE ' . _DB_PREFIX_ . 'epay_transactions SET `captured` = 1, `amount` = ' . $amount . ' WHERE `epay_transaction_id` = ' . $transaction_id;
		if(!Db::getInstance()->Execute($query))
			return false;
		return true;
	}
	
	function setCredited($transaction_id, $amount)
	{
		$query = ' UPDATE ' . _DB_PREFIX_ . 'epay_transactions SET `credited` = 1, `amount` = `amount` - ' . $amount . ' WHERE `epay_transaction_id` = ' . $transaction_id;
		if(!Db::getInstance()->Execute($query))
			return false;
		return true;
	}
	
	function deleteTransaction($transaction_id)
	{
		$query = ' UPDATE ' . _DB_PREFIX_ . 'epay_transactions SET `deleted` = 1' . ' WHERE `epay_transaction_id` = ' . $transaction_id;
		if(!Db::getInstance()->Execute($query))
			return false;
		return true;
	}
	
	public function getContent()
	{
		$this->_html = '<h2>ePay</h2>';
		if(isset($_POST["SaveSettings"]))
		{
			if(empty($_POST["merchantnumber"]))
				$this->_postErrors[] = 'Merchantnumber is required. If you don\'t have one please contact ePay on support@epay.dk in order to obtain one!';
			
			if(!sizeof($this->_postErrors))
			{
				$cssurl = $_POST["cssurl"];
				if(empty($cssurl)){
					$cssurl = _PS_BASE_URL_ . '/modules/epay/epay.css';
				}
				
				$iframe_height = $_POST['iframe_height'];
				$iframe_width = $_POST['iframe_width'];
				if(empty($iframe_height))
					$iframe_height = 350;
				if(empty($iframe_width))
					$iframe_width = 350;
				
				Configuration::updateValue('EPAY_MERCHANTNUMBER', $_POST["merchantnumber"]);
				Configuration::updateValue('EPAY_INTEGRATION', $_POST["integration"]);
				Configuration::updateValue('EPAY_CSSURL', $cssurl);
				Configuration::updateValue('EPAY_IFRAME_HEIGHT', $iframe_height);
				Configuration::updateValue('EPAY_IFRAME_WIDTH', $iframe_width);
				Configuration::updateValue('EPAY_WINDOWID', $_POST["windowid"]);
				Configuration::updateValue('EPAY_ENABLE_REMOTE_API', $_POST["remote_api"]);
				Configuration::updateValue('EPAY_INSTANTCAPTURE', $_POST["instantcapture"]);
				Configuration::updateValue('EPAY_GROUP', $_POST["group"]);
				Configuration::updateValue('EPAY_AUTHSMS', $_POST["authsms"]);
				Configuration::updateValue('EPAY_AUTHMAIL', $_POST["authmail"]);
				Configuration::updateValue('EPAY_ADDFEETOSHIPPING', $_POST["addfeetoshipping"]);
				Configuration::updateValue('EPAY_MD5KEY', $_POST["md5key"]);
				Configuration::updateValue('EPAY_REMOTE_API_PASSWORD', $_POST["remote_api_password"]);
				Configuration::updateValue('EPAY_OWNRECEIPT', $_POST["ownreceipt"]);
				Configuration::updateValue('EPAY_GOOGLE_PAGEVIEW', $_POST["googlepageview"]);
				Configuration::updateValue('EPAY_ENABLE_INVOICE', $_POST["enableinvoice"]);
				
				$this->displayConf();
			}
			else
				$this->displayErrors();
		}
		
		$this->displayFormSettings();
		return $this->_html;
	}
	
	public function displayConf()
	{
		$this->_html .= '
		<div class="conf">
			' . $this->l('Settings updated') . '
		</div>';
	}
	
	public function displayErrors()
	{
		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>' . ($nbErrors > 1 ? $this->l('There are') : $this->l('There is')) . ' ' . $nbErrors . ' ' . ($nbErrors > 1 ? $this->l('errors') : $this->l('error')) . '</h3>
			<ol>';
		foreach ($this->_postErrors AS $error)
			$this->_html .= '<li>' . $error . '</li>';
		$this->_html .= '
			</ol>
		</div>';
	}
	
	public function displayFormSettings()
	{
		$merchantnumber = Configuration::get('EPAY_MERCHANTNUMBER');
		$integration = Configuration::get('EPAY_INTEGRATION');
		$cssurl = Configuration::get('EPAY_CSSURL');
		$iframe_height = Configuration::get('EPAY_IFRAME_HEIGHT');
		$iframe_width = Configuration::get('EPAY_IFRAME_WIDTH');
		$windowid = Configuration::get('EPAY_WINDOWID');
		$remote_api = Configuration::get('EPAY_ENABLE_REMOTE_API');
		$remote_api_password = Configuration::get('EPAY_REMOTE_API_PASSWORD');
		$ownreceipt = Configuration::get('EPAY_OWNRECEIPT');
		$instantcapture = Configuration::get('EPAY_INSTANTCAPTURE');
		$addfee = Configuration::get('EPAY_ADDFEE');
		$addfeetoshipping = Configuration::get('EPAY_ADDFEETOSHIPPING');
		$group = Configuration::get('EPAY_GROUP');
		$authsms = Configuration::get('EPAY_AUTHSMS');
		$authmail = Configuration::get('EPAY_AUTHMAIL');
		$md5key = Configuration::get('EPAY_MD5KEY');
		$googlepageview = Configuration::get('EPAY_GOOGLE_PAGEVIEW');
		$enableinvoice = Configuration::get('EPAY_ENABLE_INVOICE');
		
		$this->_html .= '
		<form action="' . $_SERVER["REQUEST_URI"] . '" method="post">
		<fieldset class="width4">
			<legend><img src="../img/admin/contact.gif" />' . $this->l('Settings') . '</legend>
			<table border="0" cellpadding="5" cellspacing="5">
			<tr>
				<td width="250" valign="top">
					<b>' . $this->l('Merchantnumber:') . '</b><br />
					' . $this->l('your merchant number') . '
				</td>
				<td>
		  			<input type="text" size="33" name="merchantnumber" value="' . $merchantnumber . '" />	
		  		</td>
		  	</tr>
		  	<tr>
				<td valign="top">
					<b>' . $this->l('Window state:') . '</b><br />
					' . $this->l('how to integrate ePay') . '
				</td>
				<td>
				  	<div class="">
						<input type="radio" name="integration" value="1" ' . ($integration == "1" ? 'checked="checked"' : '') . ' /> ' . $this->l('Overlay') . '<br />
						<input type="radio" name="integration" value="2" ' . ($integration == "2" ? 'checked="checked"' : '') . ' /> ' . $this->l('iFrame') . '<br />
						<input type="radio" name="integration" value="3" ' . ($integration == "3" ? 'checked="checked"' : '') . ' /> ' . $this->l('Full screen') . '<br />
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('CSS Url:') . '</b><br />
					' . $this->l('Customize the iFrame payment window integration') . '
				</td>
				<td>
				  <div class="">
						<div class=""><input type="text" size="33" name="cssurl" value="' . $cssurl . '" /></div>
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('iFrame Size:') . '</b><br />
					' . $this->l('Set custom iFrame size (Height x Width)') . '
				</td>
				<td>
				  <div class="">
						<div class=""><input type="text" size="5" name="iframe_height" placeholder="Height" value="' . $iframe_height . '" /> x <input type="text" placeholder="Width" size="5" name="iframe_width" value="' . $iframe_width . '" /></div>
					</div>
				</td>
			</tr>
			<tr>
				<td width="250" valign="top">
					<b>' . $this->l('Window ID:') . '</b><br />
					' . $this->l('which window to use') . '
				</td>
				<td>
		  			<input type="text" size="33" name="windowid" value="' . $windowid . '" />	
		  		</td>
		  	</tr>
		  	<tr>
	      		<td>
				<b>' . $this->l('Remote API:') . '</b><br />
				 ' . $this->l('kræver ePay Business') . '
			</td>
			<td>
				  <div class="">
						<input type="radio" name="remote_api" value="1" ' . ($remote_api ? 'checked="checked"' : '') . ' /> ' . $this->l('Yes') . '
						<input type="radio" name="remote_api" value="0" ' . (!$remote_api ? 'checked="checked"' : '') . ' /> ' . $this->l('No') . '
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('Remote API Password:') . '</b>
  				</td>
				<td>
					<input type="text" size="33" name="remote_api_password" value="' . $remote_api_password . '" />
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('MD5 key:') . '</b><br />
					' . $this->l('password also setup in ePay admin') . '
				</td>
				<td>
		  		<div class=""><input type="text" size="33" name="md5key" value="' . $md5key . '" /></div>
		  	</td>
		  </tr>
			<tr>
				<td>
					<b>' . $this->l('Group:') . '</b><br />
					' . $this->l('add transactions to a special group') . '
				</td>
				<td>
				  <div class="">
						<div class=""><input type="text" size="33" name="group" value="' . $group . '" /></div>
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('Auth SMS:') . '</b><br />
					' . $this->l('receive a SMS as the payment is approved') . '
				</td>
				<td>
				  <div class="">
						<div class=""><input type="text" size="33" name="authsms" value="' . $authsms . '" /></div>
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('Auth MAIL:') . '</b><br />
					' . $this->l('receive an email as the payment is approved') . '
				</td>
				<td>
				  <div class="">
						<div class=""><input type="text" size="33" name="authmail" value="' . $authmail . '" /></div>
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('Add transaction fee to shipping:') . '</b>
				</td>
				<td>
				  <div class="">
						<input type="radio" name="addfeetoshipping" value="1" ' . ($addfeetoshipping ? 'checked="checked"' : '') . ' /> ' . $this->l('Yes') . '
						<input type="radio" name="addfeetoshipping" value="0" ' . (!$addfeetoshipping ? 'checked="checked"' : '') . ' /> ' . $this->l('No') . '
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('Instant capture:') . '</b>
				</td>
				<td>
					<div class="">
						<input type="radio" name="instantcapture" value="1" ' . ($instantcapture ? 'checked="checked"' : '') . ' /> ' . $this->l('Yes') . '
						<input type="radio" name="instantcapture" value="0" ' . (!$instantcapture ? 'checked="checked"' : '') . ' /> ' . $this->l('No') . '
					</div>
				</td>
			</tr>
			<tr class="standard">
				<td>
					<b>' . $this->l('Own receipt:') . '</b>
  				</td>
				<td>
					<div class="">
					<input type="radio" name="ownreceipt" value="1" ' . ($ownreceipt ? 'checked="checked"' : '') . ' /> ' . $this->l('Yes') . '
					<input type="radio" name="ownreceipt" value="0" ' . (!$ownreceipt ? 'checked="checked"' : '') . ' /> ' . $this->l('No') . '
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<b>' . $this->l('Enable invoice data:') . '</b>
				</td>
				<td>
					<div class="">
						<input type="radio" name="enableinvoice" value="1" ' . ($enableinvoice ? 'checked="checked"' : '') . ' /> ' . $this->l('Yes') . '
						<input type="radio" name="enableinvoice" value="0" ' . (!$enableinvoice ? 'checked="checked"' : '') . ' /> ' . $this->l('No') . '
					</div>
				</td>
			</tr>
			<tr class="standard">
				<td valign="top">
					<b>' . $this->l('Use Google Pageview Tracking:') . '</b>
					<br />' . $this->l('Notice!') . ' ' . $this->l('You must activate the Google Analytics module for this to work.') . '
  				</td>
				<td>
					<div class="">
					<input type="radio" name="googlepageview" value="1" ' . ($googlepageview ? 'checked="checked"' : '') . ' /> ' . $this->l('Yes') . '
					<input type="radio" name="googlepageview" value="0" ' . (!$googlepageview ? 'checked="checked"' : '') . ' /> ' . $this->l('No') . '
					</div>
				</td>
			</tr>
			</table>

			<br /><center><input type="submit" name="SaveSettings" value="' . $this->l(' Update settings') . '" class="button" /></center>
		</fieldset>
		</form>

		<br />
		<fieldset class="width4">
			<legend><img src="../img/admin/warning.gif" />' . $this->l('Information') . '</legend>
			' . $this->l('You can also setup specific settings from the admin area of ePay:') . ' <a href="https://ssl.ditonlinebetalingssystem.dk/admin/" target="_blank">https://ssl.ditonlinebetalingssystem.dk/admin/</a>.
		</fieldset>';
	}
	
	function get_epay_language($strlan)
	{
		switch($strlan)
		{
			case "dk":
				return 1;
			case "da":
				return 1;
			case "en":
				return 2;
			case "se":
				return 3;
			case "sv":
				return 3;
			case "no":
				return 4;
			case "gl":
				return 5;
			case "is":
				return 6;
			case "de":
				return 7;
		}
		
		return 1;
		// default dk
	}

	private function getInvoiceData($customer, $summary, $forHash = false)
	{
		$invoice["customer"]["email"] = $customer->email;
		$invoice["customer"]["name"] = $summary["invoice"]->firstname . ' ' . $summary["invoice"]->lastname;
		$invoice["customer"]["address"] = $summary["invoice"]->address1;
		$invoice["customer"]["zip"] = intval((string)$summary["invoice"]->postcode);
		$invoice["customer"]["city"] = $summary["invoice"]->city;
		$invoice["customer"]["country"] = $summary["invoice"]->country;
		
		$invoice["shippingaddress"]["name"] = $summary["delivery"]->firstname . ' ' . $summary["delivery"]->lastname;
		$invoice["shippingaddress"]["address"] = $summary["delivery"]->address1;
		$invoice["shippingaddress"]["zip"] = intval((string)$summary["delivery"]->postcode);
		$invoice["shippingaddress"]["city"] = $summary["delivery"]->city;
		$invoice["shippingaddress"]["country"] = $summary["delivery"]->country;
		
		$invoice["lines"] = array();

		foreach ($summary["products"] as $product)
		{	
			$invoice["lines"][] = array
			(
				"id" => ($product["reference"] == "" ? $product["id_product"] : $product["reference"]),
				"description" => addslashes($product["name"] . (isset($product["attributes_small"]) ? (" " . $product["attributes_small"]) : "")),
				"quantity" => intval((string)$product["cart_quantity"]),
				"price" => round((string)$product["price"],2)*100,
				"vat" => (float)round((string)((round($product["price_wt"],2)-round($product["price"],2))/round((string)$product["price"],2))*100, 2)
			);
		}
		
		$invoice["lines"][] = array
			(
				"id" => $this->l('shipping'),
				"description" => $this->l('Shipping'),
				"quantity" => 1,
				"price" => intval((string)round($summary["total_shipping_tax_exc"],2)*100),
				"vat" => ($summary["total_shipping_tax_exc"] > 0 ? ((float)round((string)((round($summary["total_shipping"],2)-round($summary["total_shipping_tax_exc"],2))/round((string)$summary["total_shipping_tax_exc"],2))*100, 2)) : 0)
			);
			
		foreach ($summary["discounts"] as $discount)
		{			
			$invoice["lines"][] = array
			(
				"id" => $discount["id_discount"],
				"description" => $discount["description"],
				"quantity" => 1,
				"price" => -intval(round((string)$discount["value_tax_exc"],2)*100),
				"vat" => (float)round((string)((round($discount["value_real"],2)-round($discount["value_tax_exc"],2))/round((string)$discount["value_tax_exc"],2))*100, 2)
			);
		}	
		
		return $invoice;
	}
	
	private function jsonRemoveUnicodeSequences($struct)
	{
		return preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", json_encode($struct));
	}
	
	public function hookPayment($params, $template = 'epay.tpl')
	{
		global $smarty, $cookie;
		
		$address = new Address(intval($params["cart"]->id_address_invoice));
		$customer = new Customer(intval($params["cart"]->id_customer));
		$merchantnumber = Configuration::get('EPAY_MERCHANTNUMBER');
		$cms = 'prestashop' . $this->version;
		$integration = Configuration::get('EPAY_INTEGRATION');
		$cssurl = ($integration == 2 ? Configuration::get('EPAY_CSSURL') : "");
		$iframe_height = ($integration == 2 ? Configuration::get('EPAY_IFRAME_HEIGHT') : "");
		$iframe_width = ($integration == 2 ? Configuration::get('EPAY_IFRAME_WIDTH') : "");
		$windowid = Configuration::get('EPAY_WINDOWID');
		$instantcapture = Configuration::get('EPAY_INSTANTCAPTURE');
		$group = Configuration::get('EPAY_GROUP');
		$cardholder = Configuration::get('EPAY_CARDHOLDER');
		$authsms = Configuration::get('EPAY_AUTHSMS');
		$authmail = Configuration::get('EPAY_AUTHMAIL');
		$ownreceipt = Configuration::get('EPAY_OWNRECEIPT');
		$enableinvoice = Configuration::get('EPAY_ENABLE_INVOICE');
		$id_currency = intval($params["cart"]->id_currency);
		$currency = new Currency(intval($id_currency));
		$language = $this->get_epay_language(Language::getIsoById($cookie->id_lang));
		$id_cart = intval($params["cart"]->id);
		$message = Message::getMessageByCartId($id_cart);
		$message = urlencode($message["message"]);
		$total = $params["cart"]->getOrderTotal(true)*100;
		$summary = $params["cart"]->getSummaryDetails();
		
		$invoice = $this->getInvoiceData($customer, $summary);

		$server_host = htmlspecialchars($_SERVER["HTTP_HOST"], ENT_COMPAT, 'UTF-8');
		$protocol = 'http://';
		$protocol_ssl = 'https://';
		$protocol_link = (Configuration::get('PS_SSL_ENABLED')) ? $protocol_ssl : $protocol;
		$protocol_content = (isset($useSSL) AND $useSSL AND Configuration::get('PS_SSL_ENABLED')) ? $protocol_ssl : $protocol;
		
		if(Configuration::get('PS_ORDER_PROCESS_TYPE'))
			$declineurl_stdwindow = $protocol_link  . $_SERVER["HTTP_HOST"] . __PS_BASE_URI__ . 'order-opc.php';
		else
			$declineurl_stdwindow = $protocol_link  . $_SERVER["HTTP_HOST"] . __PS_BASE_URI__ . 'order.php';
		
		$accepturl = $protocol_link . $_SERVER["HTTP_HOST"] . __PS_BASE_URI__ . 'modules/epay/validation.php?language=' . $language;
		
		//
		// Calculate md5
		$md5stamp = md5(
						"UTF-8" .
						$cms . 
						$merchantnumber . 
						$integration . 
						$cssurl .
						$windowid . 
						$total . 
						$group . 
						$authsms . 
						$authmail . 
						$ownreceipt . 
						$instantcapture .
						$this->get_iso_code($currency->iso_code) .
						$id_cart .
						$accepturl .
						$declineurl_stdwindow .
						$accepturl .
						$language .
						(Configuration::get('EPAY_GOOGLE_PAGEVIEW') ? Configuration::get('GANALYTICS_ID') : "") .
						$iframe_height . 
						$iframe_width . 
						"1" . //instant callback
						($enableinvoice == 1 ? stripslashes($this->jsonRemoveUnicodeSequences($invoice)) : "") .
						Configuration::get('EPAY_MD5KEY'));
		
		$smarty->assign(array
		(
			'cms_identifier' => $cms,
			'address' => $address,
			'customer' => $customer,
			'windowid' => $windowid,
			'integration' => $integration,
			'cssurl' => $cssurl,
			'iframe_height' => $iframe_height,
			'iframe_width' => $iframe_width,
			'merchantnumber' => $merchantnumber,
			'accepturl' => $accepturl,
			'declineurl_stdwindow' => $declineurl_stdwindow,
			'amount' => $total,
			'currency' => $this->get_iso_code($currency->iso_code),
			'language' => $language,
			'id_cart' => $id_cart,
			'message' => $message,
			'cardholder' => $cardholder,
			'instantcapture' => $instantcapture,
			'group' => $group,
			'authsms' => $authsms,
			'authmail' => $authmail,
			'md5stamp' => $md5stamp,
			'ownreceipt' => $ownreceipt,
			'enableinvoice' => $enableinvoice,
			'invoice' => $this->jsonRemoveUnicodeSequences($invoice),
			'googlepageview' => Configuration::get('EPAY_GOOGLE_PAGEVIEW'),
			'googleid' => Configuration::get('GANALYTICS_ID'),
			)
		);
		
		return $this->display(__FILE__ , $template);
	}
	
	function hookFooter($params)
	{
		$output = '';
		
		if(Configuration::get('EPAY_GOOGLE_PAGEVIEW') == 1 and strlen(Configuration::get('GANALYTICS_ID')) > 0)
		{
			$output .= '
			<script type="text/javascript">
				_gaq.push([\'_setDomainName\', \'none\']);
				_gaq.push([\'_setAllowLinker\', true]);
			</script>';
		}
		
		return $output;
		
	}
	
	public function hookPaymentReturn($params)
	{
		global $smarty;
		if(!$this->active)
			return;
		
		$result = Db::getInstance()->getRow('
			SELECT o.`id_order`, o.`module`, e.`id_cart`, e.`epay_transaction_id`,
				   e.`card_type`, e.`cardnopostfix`, e.`currency`, e.`amount`, e.`transfee`,
				   e.`fraud`, e.`captured`, e.`credited`, e.`deleted`,
				   e.`date_add`
			FROM ' . _DB_PREFIX_ . 'epay_transactions e
			LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON e.`id_cart` = o.`id_cart`
			WHERE o.`id_order` = ' . intval($_GET["id_order"]));
		
		if($result["cardnopostfix"] > 1)
		{
			$smarty->assign(array('postfix' => $result["cardnopostfix"]));
		}
		
		return $this->display(__FILE__ , 'payment_return.tpl');
	}
	
	function hookLeftColumn($params)
	{
		global $smarty;
		
		$merchantnumber = Configuration::get('EPAY_MERCHANTNUMBER');
		$smarty->assign(array('merchantnumber' => $merchantnumber));
		
		return $this->display(__FILE__ , 'blockepaymentlogo.tpl');
	}
	
	function hookRightColumn($params)
	{
		return $this->hookLeftColumn($params);
	}
	
	function hookAdminOrder($params)
	{
		$message = '';
		
		/* Process remote capture/credit/delete */
		if(Configuration::get('EPAY_ENABLE_REMOTE_API'))
		{
			require_once (dirname(__FILE__ ) . '/EpaySoap.php');
			
			$remote_result = $this->_procesRemote($params);
			$message = '<div class="conf">';
			if(@$remote_result->captureResult == "true")
				$message .= $this->l('Payment captured') . '</div>';
			elseif(@$remote_result->creditResult == "true")
				$message .= $this->l('Payment credited') . '</div>';
			elseif(@$remote_result->deleteResult == "true")
				$message .= $this->l('Payment deleted') . '</div>';
			elseif(@$remote_result->move_as_capturedResult == "true")
				$message .= $this->l('Payment closed') . '</div>';
			else
				$message = '';
		}
		
		$result = Db::getInstance()->getRow('
			SELECT o.`id_order`, o.`module`, e.`id_cart`, e.`epay_transaction_id`,
				   e.`card_type`, e.`cardnopostfix`, e.`currency`, e.`amount`, e.`transfee`,
				   e.`fraud`, e.`captured`, e.`credited`, e.`deleted`,
				   e.`date_add`
			FROM ' . _DB_PREFIX_ . 'epay_transactions e
			LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON e.`id_cart` = o.`id_cart`
			WHERE o.`id_order` = ' . intval($params["id_order"]));
		
		if(!isset($result["epay_transaction_id"]) OR $result["module"] != "epay")
			return '';
		
		else
		{
			$html = '<br /><fieldset style="width: 400px;">
						<legend>
						<img src="../modules/' . $this->name . '/logo_small.gif" /> ' . $this->l('ePay info') . '</legend>' . $message . '
						<table>
							<tr>
								<td align="right">
									' . $this->l('ePay control panel :') . '
								</td>
								<td>
									&nbsp;<a href="https://ssl.ditonlinebetalingssystem.dk/admin/login.asp" title="ePay login" target="_blank">.../admin/login.asp</a>
									' . '
								</td>
							</tr>
							<tr>
								<td align="right">
									' . $this->l('ePay transaction number :') . '
								</td>
								<td>
									&nbsp;<b>' . $result["epay_transaction_id"] . '</b>' . '
								</td>
							</tr>
							<tr>
								<td align="right">
									' . $this->l('ePay "order_id" (id_cart) :') . '
								</td>
								<td>
									&nbsp;<b>' . $result["id_cart"] . '</b>' . '
								</td>
							</tr>';
							if($result["cardnopostfix"] > 1)
							{
							$html .= '<tr>
								<td align="right">
									' . $this->l('Postfix :') . '
								</td>
								<td>
									&nbsp;<b>XXXX XXXX XXXX ' . $result["cardnopostfix"] . '</b>' . ($result["fraud"] ? '</td>
							</tr>
							<tr>
								<td align="center" colspan="2">
									<span style="color:red;font-weight:bold;">' . $this->l('Suspicious Payment!') . '</span>' : '') . '
								</td>
							</tr>';
							}
							$html .= '<tr>
								<td align="center" colspan="2">
									<img src="../modules/' . $this->name . '/cards/' . $result["card_type"] . '.png" alt="' . $this->getCardnameById(intval($result["card_type"])) . '" title="' . $this->getCardnameById(intval($result["card_type"])) . '" align="middle">';
			
			if(Configuration::get('EPAY_ENABLE_REMOTE_API'))
			{
				$epaySoap = new EpaySoap();
				$soap_result = $epaySoap->gettransactionInformation(Configuration::get('EPAY_MERCHANTNUMBER'), $result["epay_transaction_id"]);
				
				if(!$soap_result->capturedamount or $soap_result->capturedamount == $soap_result->authamount)
				{
					$epay_amount = number_format($soap_result->authamount / 100, 2, ".", "");
				}
				elseif($soap_result->status == 'PAYMENT_CAPTURED')
				{
					$epay_amount = number_format(($soap_result->capturedamount) / 100, 2, ".", "");
				}
				else
				{
					$epay_amount = number_format(($soap_result->authamount - $soap_result->capturedamount) / 100, 2, ".", "");
				}
				
				if($soap_result->status != 'PAYMENT_DELETED' AND !$soap_result->creditedamount)
				{
					$html .= '<form name="epay_remote" action="' . $_SERVER["REQUEST_URI"] . '" method="post" style="display:inline">' . '<input type="hidden" name="epay_transaktion_id" value="' . $result["epay_transaction_id"] . '" />' . '<input type="hidden" name="epay_order_id" value="' . $result["id_cart"] . '" />' . $this->get_nb_code($result["currency"]) . ' ' . '<input type="text" id="epay_amount" name="epay_amount" value="' . $epay_amount . '" size="' . strlen($epay_amount) . '" />';
					
					
					if(!$soap_result->capturedamount or ($soap_result->splitpayment and $soap_result->status != 'PAYMENT_CAPTURED' and ($soap_result->capturedamount != $soap_result->authamount)))
					{
						$html .= ' <input class="button" name="epay_capture" type="submit" value="' . $this->l('Capture') . '" />' . ' <input class="button" name="epay_delete" type="submit" value="' . $this->l('Delete') . '" 
												 		onclick="return confirm(\'' . $this->l('Really want to delete?') . '\');" />';
						if($soap_result->splitpayment)
						{
							$html .= '<br /><input class="button" name="epay_move_as_captured" type="submit" value="' . $this->l('Close transaction') . '" /> ';
						}
						
					}
					elseif($soap_result->status == 'PAYMENT_CAPTURED' OR $soap_result->acquirer == 'EUROLINE')
					{
						$html .= ' <input class="button" name="epay_credit" type="submit" value="' . $this->l('Credit') . '"
														onclick="return confirm(\'' . $this->l('Do you want to credit:') . ' ' . $this->get_nb_code($result["currency"]) . ' \'+getE(\'epay_amount\').value);" />';
					}
					$html .= '</form>';
				}
				else
				{
					$html .= $this->get_nb_code($result["currency"]) . ' ' . $epay_amount;
					$html .= ($soap_result->status == 'PAYMENT_DELETED' ? ' <span style="color:red;font-weight:bold;">' . $this->l('Deleted') . '</span>' : '');
				}
				
				$html .= '
								<div style="margin-top: 10px;">
								<table class="table" cellspacing="0" cellpadding="0"><tr><th>' . $this->l('Date') . '</th><th>' . $this->l('Event') . '</th></tr>';
				
				$historyArray = $soap_result->history->TransactionHistoryInfo;
				
				if(!array_key_exists(0, $soap_result->history->TransactionHistoryInfo))
				{
					$historyArray = array($soap_result->history->TransactionHistoryInfo);
					// convert to array
				}
				
				for($i = 0; $i < count($historyArray); $i++)
				{
					$html .= "<tr><td>" . str_replace("T", " ", $historyArray[$i]->created) . "</td>";
					$html .= "<td>";
					if(strlen($historyArray[$i]->username) > 0)
					{
						$html .= ($historyArray[$i]->username . ": ");
					}
					$html .= $historyArray[$i]->eventMsg . "</td></tr>";
				}
				
				
				$html .= '</table></div>';
				
			}
			else
				$html .= $this->get_nb_code($result["currency"]) . ' ' . number_format(($result["amount"] + $result["transfee"]) / 100, 2, ",", "");
			
			$html .= '</td></tr></table>
								</fieldset>';
			
			return $html;
		}
	}
	
	function _procesRemote($params)
	{
		if((Tools::isSubmit('epay_capture') OR Tools::isSubmit('epay_move_as_captured') OR Tools::isSubmit('epay_credit') OR Tools::isSubmit('epay_delete')) AND Tools::getIsset('epay_transaktion_id'))
		{
			require_once (dirname(__FILE__ ) . '/EpaySoap.php');
			
			$epay_soap = new EpaySoap();
			
			if(Tools::isSubmit('epay_capture'))
			{
				$result = $epay_soap->capture(Configuration::get('EPAY_MERCHANTNUMBER'), Tools::getValue('epay_transaktion_id'), floatval(Tools::getValue('epay_amount')) * 100);
			}
			elseif(Tools::isSubmit('epay_credit'))
			{
				$result = $epay_soap->credit(Configuration::get('EPAY_MERCHANTNUMBER'), Tools::getValue('epay_transaktion_id'), floatval(Tools::getValue('epay_amount')) * 100);
			}
			elseif(Tools::isSubmit('epay_delete'))
			{
				$result = $epay_soap->delete(Configuration::get('EPAY_MERCHANTNUMBER'), Tools::getValue('epay_transaktion_id'));
			}
			elseif(Tools::isSubmit('epay_move_as_captured'))
			{
				$result = $epay_soap->moveascaptured(Configuration::get('EPAY_MERCHANTNUMBER'), Tools::getValue('epay_transaktion_id'));
			}
			
			if(@$result->captureResult == "true")
			{
				$this->setCaptured(Tools::getValue('epay_transaktion_id'), floatval(Tools::getValue('epay_amount')) * 100);
			}
			elseif(@$result->creditResult == "true")
			{
				$this->setCredited(Tools::getValue('epay_transaktion_id'), floatval(Tools::getValue('epay_amount')) * 100);
			}
			elseif(@$result->deleteResult == "true")
			{
				$this->deleteTransaction(Tools::getValue('epay_transaktion_id'));
			}
			elseif(@$result->move_as_capturedResult == "true")
			{
				
			}
			else
			{
				if(Tools::isSubmit('epay_capture'))
				{
					$pbsresponse = $result->pbsResponse;
				}
				elseif(!Tools::isSubmit('epay_delete') && !Tools::isSubmit('epay_move_as_captured'))
				{
					$pbsresponse = $result->pbsresponse;
				}
				$epay_soap->getEpayError(Configuration::get('EPAY_MERCHANTNUMBER'), $result->epayresponse);
				
				if(!Tools::isSubmit('epay_delete') && !Tools::isSubmit('epay_move_as_captured'))
					$epay_soap->getPbsError(Configuration::get('EPAY_MERCHANTNUMBER'), $pbsresponse);
			}
			
			return $result;
		}
	}
	
	static function getCardnameById($cardid)
	{
		switch($cardid)
		{
			case 1:
				return 'Dankort / VISA/Dankort';
			case 2:
				return 'eDankort';
			case 3:
				return 'VISA / VISA Electron';
			case 4:
				return 'MasterCard';
			case 6:
				return 'JCB';
			case 7:
				return 'Maestro';
			case 8:
				return 'Diners Club';
			case 9:
				return 'American Express';
			case 10:
				return 'ewire';
			case 12:
				return 'Nordea e-betaling';
			case 13:
				return 'Danske Netbetalinger';
			case 14:
				return 'PayPal';
			case 16:
				return 'MobilPenge';
			case 17:
				return 'Klarna';
			case 18:
				return 'Svea';
		}
		return 'unknown';
	}
	
	function get_nb_code($code)
	{
		switch($code)
		{
			case 208:
				return 'DKK';
				break;
			case 978:
				return 'EUR';
				break;
			case 840:
				return 'USD';
				break;
			case 578:
				return 'NOK';
				break;
			case 752:
				return 'SEK';
				break;
			case 826:
				return 'GBP';
				break;
			default:
				return 'DKK';
				break;
		}
	}
	
	function get_iso_code($code)
	{
		switch(strtoupper($code))
		{
			case 'ADP':
				return '020';
				break;
			case 'AED':
				return '784';
				break;
			case 'AFA':
				return '004';
				break;
			case 'ALL':
				return '008';
				break;
			case 'AMD':
				return '051';
				break;
			case 'ANG':
				return '532';
				break;
			case 'AOA':
				return '973';
				break;
			case 'ARS':
				return '032';
				break;
			case 'AUD':
				return '036';
				break;
			case 'AWG':
				return '533';
				break;
			case 'AZM':
				return '031';
				break;
			case 'BAM':
				return '977';
				break;
			case 'BBD':
				return '052';
				break;
			case 'BDT':
				return '050';
				break;
			case 'BGL':
				return '100';
				break;
			case 'BGN':
				return '975';
				break;
			case 'BHD':
				return '048';
				break;
			case 'BIF':
				return '108';
				break;
			case 'BMD':
				return '060';
				break;
			case 'BND':
				return '096';
				break;
			case 'BOB':
				return '068';
				break;
			case 'BOV':
				return '984';
				break;
			case 'BRL':
				return '986';
				break;
			case 'BSD':
				return '044';
				break;
			case 'BTN':
				return '064';
				break;
			case 'BWP':
				return '072';
				break;
			case 'BYR':
				return '974';
				break;
			case 'BZD':
				return '084';
				break;
			case 'CAD':
				return '124';
				break;
			case 'CDF':
				return '976';
				break;
			case 'CHF':
				return '756';
				break;
			case 'CLF':
				return '990';
				break;
			case 'CLP':
				return '152';
				break;
			case 'CNY':
				return '156';
				break;
			case 'COP':
				return '170';
				break;
			case 'CRC':
				return '188';
				break;
			case 'CUP':
				return '192';
				break;
			case 'CVE':
				return '132';
				break;
			case 'CYP':
				return '196';
				break;
			case 'CZK':
				return '203';
				break;
			case 'DJF':
				return '262';
				break;
			case 'DKK':
				return '208';
				break;
			case 'DOP':
				return '214';
				break;
			case 'DZD':
				return '012';
				break;
			case 'ECS':
				return '218';
				break;
			case 'ECV':
				return '983';
				break;
			case 'EEK':
				return '233';
				break;
			case 'EGP':
				return '818';
				break;
			case 'ERN':
				return '232';
				break;
			case 'ETB':
				return '230';
				break;
			case 'EUR':
				return '978';
				break;
			case 'FJD':
				return '242';
				break;
			case 'FKP':
				return '238';
				break;
			case 'GBP':
				return '826';
				break;
			case 'GEL':
				return '981';
				break;
			case 'GHC':
				return '288';
				break;
			case 'GIP':
				return '292';
				break;
			case 'GMD':
				return '270';
				break;
			case 'GNF':
				return '324';
				break;
			case 'GTQ':
				return '320';
				break;
			case 'GWP':
				return '624';
				break;
			case 'GYD':
				return '328';
				break;
			case 'HKD':
				return '344';
				break;
			case 'HNL':
				return '340';
				break;
			case 'HRK':
				return '191';
				break;
			case 'HTG':
				return '332';
				break;
			case 'HUF':
				return '348';
				break;
			case 'IDR':
				return '360';
				break;
			case 'ILS':
				return '376';
				break;
			case 'INR':
				return '356';
				break;
			case 'IQD':
				return '368';
				break;
			case 'IRR':
				return '364';
				break;
			case 'ISK':
				return '352';
				break;
			case 'JMD':
				return '388';
				break;
			case 'JOD':
				return '400';
				break;
			case 'JPY':
				return '392';
				break;
			case 'KES':
				return '404';
				break;
			case 'KGS':
				return '417';
				break;
			case 'KHR':
				return '116';
				break;
			case 'KMF':
				return '174';
				break;
			case 'KPW':
				return '408';
				break;
			case 'KRW':
				return '410';
				break;
			case 'KWD':
				return '414';
				break;
			case 'KYD':
				return '136';
				break;
			case 'KZT':
				return '398';
				break;
			case 'LAK':
				return '418';
				break;
			case 'LBP':
				return '422';
				break;
			case 'LKR':
				return '144';
				break;
			case 'LRD':
				return '430';
				break;
			case 'LSL':
				return '426';
				break;
			case 'LTL':
				return '440';
				break;
			case 'LVL':
				return '428';
				break;
			case 'LYD':
				return '434';
				break;
			case 'MAD':
				return '504';
				break;
			case 'MDL':
				return '498';
				break;
			case 'MGF':
				return '450';
				break;
			case 'MKD':
				return '807';
				break;
			case 'MMK':
				return '104';
				break;
			case 'MNT':
				return '496';
				break;
			case 'MOP':
				return '446';
				break;
			case 'MRO':
				return '478';
				break;
			case 'MTL':
				return '470';
				break;
			case 'MUR':
				return '480';
				break;
			case 'MVR':
				return '462';
				break;
			case 'MWK':
				return '454';
				break;
			case 'MXN':
				return '484';
				break;
			case 'MXV':
				return '979';
				break;
			case 'MYR':
				return '458';
				break;
			case 'MZM':
				return '508';
				break;
			case 'NAD':
				return '516';
				break;
			case 'NGN':
				return '566';
				break;
			case 'NIO':
				return '558';
				break;
			case 'NOK':
				return '578';
				break;
			case 'NPR':
				return '524';
				break;
			case 'NZD':
				return '554';
				break;
			case 'OMR':
				return '512';
				break;
			case 'PAB':
				return '590';
				break;
			case 'PEN':
				return '604';
				break;
			case 'PGK':
				return '598';
				break;
			case 'PHP':
				return '608';
				break;
			case 'PKR':
				return '586';
				break;
			case 'PLN':
				return '985';
				break;
			case 'PYG':
				return '600';
				break;
			case 'QAR':
				return '634';
				break;
			case 'ROL':
				return '642';
				break;
			case 'RUB':
				return '643';
				break;
			case 'RUR':
				return '810';
				break;
			case 'RWF':
				return '646';
				break;
			case 'SAR':
				return '682';
				break;
			case 'SBD':
				return '090';
				break;
			case 'SCR':
				return '690';
				break;
			case 'SDD':
				return '736';
				break;
			case 'SEK':
				return '752';
				break;
			case 'SGD':
				return '702';
				break;
			case 'SHP':
				return '654';
				break;
			case 'SIT':
				return '705';
				break;
			case 'SKK':
				return '703';
				break;
			case 'SLL':
				return '694';
				break;
			case 'SOS':
				return '706';
				break;
			case 'SRG':
				return '740';
				break;
			case 'STD':
				return '678';
				break;
			case 'SVC':
				return '222';
				break;
			case 'SYP':
				return '760';
				break;
			case 'SZL':
				return '748';
				break;
			case 'THB':
				return '764';
				break;
			case 'TJS':
				return '972';
				break;
			case 'TMM':
				return '795';
				break;
			case 'TND':
				return '788';
				break;
			case 'TOP':
				return '776';
				break;
			case 'TPE':
				return '626';
				break;
			case 'TRL':
				return '792';
				break;
			case 'TRY':
				return '949';
				break;
			case 'TTD':
				return '780';
				break;
			case 'TWD':
				return '901';
				break;
			case 'TZS':
				return '834';
				break;
			case 'UAH':
				return '980';
				break;
			case 'UGX':
				return '800';
				break;
			case 'USD':
				return '840';
				break;
			case 'UYU':
				return '858';
				break;
			case 'UZS':
				return '860';
				break;
			case 'VEB':
				return '862';
				break;
			case 'VND':
				return '704';
				break;
			case 'VUV':
				return '548';
				break;
			case 'XAF':
				return '950';
				break;
			case 'XCD':
				return '951';
				break;
			case 'XOF':
				return '952';
				break;
			case 'XPF':
				return '953';
				break;
			case 'YER':
				return '886';
				break;
			case 'YUM':
				return '891';
				break;
			case 'ZAR':
				return '710';
				break;
			case 'ZMK':
				return '894';
				break;
			case 'ZWD':
				return '716';
				break;
		}
		
		return '208';
	}
	
}

?>

<?php
/*
  Copyright (c) 2010. All rights reserved ePay - www.epay.dk.

  This program is free software. You are allowed to use the software but NOT allowed to modify the software. 
  It is also not legal to do any changes to the software and distribute it in your own name / brand. 
*/
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__).'/epay.php');

$server_host = htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8');
$protocol = 'http://';
$protocol_ssl = 'https://';
$protocol_link = (Configuration::get('PS_SSL_ENABLED')) ? $protocol_ssl : $protocol;
$protocol_content = (isset($useSSL) AND $useSSL AND Configuration::get('PS_SSL_ENABLED')) ? $protocol_ssl : $protocol;

$page_redirect = $protocol_link . $server_host . __PS_BASE_URI__;

$amount = number_format($_REQUEST['amount']/100, 2, ".", "");
$id_cart = $_REQUEST['orderid'];

$currency = $_REQUEST['currency'];
$cardid = $_REQUEST['paymenttype'];
$cardnopostfix = (isset($_REQUEST['cardno']) ? substr($_REQUEST["cardno"],  - 4) : 0);
$transfee = (isset($_REQUEST['txnfee']) ? $_REQUEST['txnfee'] : 0);
$fraud = (isset($_REQUEST['fraud']) ? $_REQUEST['fraud'] : 0);
$epay = new EPAY();

//
// Calculate MD5
//
if(strlen(Configuration::get('EPAY_MD5KEY')) > 0){
	$params = $_GET;
	$var = "";
	
	foreach ($params as $key => $value)
		if($key != "hash" && $key != "controller") // Prestashop adds the "controller"-parameter, which we should NOT add to the hash
			$var .= $value;
	
	$genstamp = md5($var . Configuration::get('EPAY_MD5KEY'));
	
	if($genstamp != $_REQUEST["hash"]){
		echo "Error in MD5 data! Please review your passwords in both ePay and your Prestashop admin!";
		exit();
	}
}

$cart = new Cart(intval($id_cart));

if ($cart->OrderExists() == 0){
	$message = "\nPayment via ePay\nePay transaction ID: " . $_GET["txnid"];
	
	if ((version_compare(_PS_VERSION_, "1.4.0.0", ">=") and $epay->validateOrder($_GET["orderid"], _PS_OS_PAYMENT_, $amount, $epay->displayName, $message, array(), NULL, false, $cart->secure_key)) or ($epay->validateOrder($_GET["orderid"], _PS_OS_PAYMENT_, $amount, $epay->displayName, $message)))
	{
		$epay->recordTransaction(null, $id_cart, $_GET["txnid"], $cardid, $cardnopostfix, $currency, $_GET["amount"], $transfee, $fraud);
		
		$order = new Order($epay->currentOrder);
		
		$payment = $order->getOrderPayments();
		$payment[0]->transaction_id = $_GET["txnid"];
		$payment[0]->amount = $amount;
		
		if($transfee > 0)
		{
			$payment[0]->amount = $payment[0]->amount + number_format($transfee / 100, 2, ".", "");

			if(Configuration::get('EPAY_ADDFEETOSHIPPING'))
			{
				$order->total_paid = $order->total_paid + number_format($transfee / 100, 2, ".", "");
				$order->total_paid_tax_incl = $order->total_paid_tax_incl + number_format($transfee / 100, 2, ".", "");
				$order->total_paid_tax_excl = $order->total_paid_tax_excl + number_format($transfee / 100, 2, ".", "");
				$order->total_paid_real = $order->total_paid_real + number_format($transfee / 100, 2, ".", "");
				$order->total_shipping = $order->total_shipping + number_format($transfee / 100, 2, ".", "");
				$order->total_shipping_tax_incl = $order->total_shipping_tax_incl + number_format($transfee / 100, 2, ".", "");
				$order->total_shipping_tax_excl = $order->total_shipping_tax_excl + number_format($transfee / 100, 2, ".", "");
			
				$invoice = $payment[0]->getOrderInvoice($epay->currentOrder);
				$invoice->total_paid_tax_incl = $invoice->total_paid_tax_incl + number_format($transfee / 100, 2, ".", "");
				$invoice->total_paid_tax_excl = $invoice->total_paid_tax_excl + number_format($transfee / 100, 2, ".", "");
				$invoice->total_shipping_tax_incl = $invoice->total_shipping_tax_incl + number_format($transfee / 100, 2, ".", "");
				$invoice->total_shipping_tax_excl = $invoice->total_shipping_tax_excl + number_format($transfee / 100, 2, ".", "");	
				
				$invoice->save();
			}
		}
		$payment[0]->save();
		
		$order->save();
		
		$okpage = $page_redirect . 'order-confirmation.php?id_cart=' . $id_cart . '&id_module=' . $epay->id . '&id_order=' . $epay->currentOrder . '&key=' . $order->secure_key;
		
		header('Location: '. $okpage);
	}
	else
	{
		echo "Prestashop error - unable to process order..";
	}
}
else
{
	$order_id = Order::getOrderByCartId($id_cart);
	$order = new Order($order_id);
	$okpage = $page_redirect . 'order-confirmation.php?id_cart=' . $id_cart . '&id_module=' . $epay->id . '&id_order=' . $order_id . '&key=' . $order->secure_key;

	header('Location: ' . $okpage);
}
	
?>
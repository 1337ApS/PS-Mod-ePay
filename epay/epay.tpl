<div id="epay-payment-window"></div>
<script type="text/javascript" src="https://ssl.ditonlinebetalingssystem.dk/integration/ewindow/paymentwindow.js" charset="UTF-8"></script>
<script type="text/javascript">
	{literal}
	function setupEPayWindow() {
		paymentwindow = new PaymentWindow({
			'encoding': "UTF-8",
			'cms': "{/literal}{$cms_identifier}{literal}",
			'merchantnumber': "{/literal}{$merchantnumber}{literal}",
			'windowstate': "{/literal}{$integration}{literal}",
			{/literal}{if !empty($cssurl)}'cssurl': "{$cssurl}",{/if}{literal}
			'windowid': "{/literal}{$windowid}{literal}",
			'amount': "{/literal}{$amount}{literal}",
			'group': "{/literal}{$group}{literal}",
			'smsreceipt': "{/literal}{$authsms}{literal}",
			'mailreceipt': "{/literal}{$authmail}{literal}",
			'ownreceipt': "{/literal}{$ownreceipt}{literal}",
			'instantcapture': "{/literal}{$instantcapture}{literal}",
			'currency': "{/literal}{$currency}{literal}",
			'orderid': "{/literal}{$id_cart}{literal}",
			'accepturl': "{/literal}{$accepturl}{literal}",
			'cancelurl': "{/literal}{$declineurl_stdwindow}{literal}",
			'callbackurl': "{/literal}{$accepturl}{literal}",
			'language': "{/literal}{$language}{literal}",
			{/literal}
			{if $googlepageview == 1}
				{literal}
				'googletracker': "{/literal}{$googleid}{literal}",
				{/literal}
			{/if}
			{if $integration == 2}
				{literal}
				'iframeheight': "{/literal}{$iframe_height}{literal}",
				'iframewidth': "{/literal}{$iframe_width}{literal}",
				{/literal}
			{/if}
			{literal}
			'instantcallback': "1",
			{/literal}
			{if $enableinvoice == 1}
				{literal}
				'invoice': '{/literal}{$invoice}{literal}',
				{/literal}
			{/if}
			{literal}
			'hash': "{/literal}{$md5stamp}{literal}"
		});
		{/literal}
		{if $integration == 2}
			paymentwindow.append('epay-payment-window');
		{/if}
		{literal}
		paymentwindow.open();
	}
	setupEPayWindow(); 
	{/literal}
</script>
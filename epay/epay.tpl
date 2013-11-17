<script type="text/javascript" src="https://ssl.ditonlinebetalingssystem.dk/integration/ewindow/paymentwindow.js" charset="UTF-8"></script>
<script type="text/javascript">
	{literal}
	function newWindow() {
		paymentwindow = new PaymentWindow({
			'encoding': "UTF-8",
			'cms': "{/literal}{$cms_identifier}{literal}",
			'merchantnumber': "{/literal}{$merchantnumber}{literal}",
			'windowstate': "{/literal}{$integration}{literal}",
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
	}
	{/literal}
</script>
<p class="payment_module">
	<a title="{l s='Pay using ePay' mod='epay'}" href="javascript: newWindow(); paymentwindow.open();" style="text-decoration: none;overflow: hidden;cursor:pointer; min-height: 49px;">
		<span style="height:49px;width:86px;float:left; margin-right: 1em;" id="epay_logos">
			Logo
		</span>
		<span style="width:430px;float:left; margin-left: 2px;">
		{l s='Pay using ePay' mod='epay'}
			<br />	
			<span style="width:100%; float: left;" id="epay_card_logos">Cards</span>
		</span>
	</a>
</p>

<script type="text/javascript" src="https://relay.ditonlinebetalingssystem.dk/integration/paymentlogos/PaymentLogos.aspx?merchantnumber={$merchantnumber}&direction=2&padding=2&rows=1&logo=0&showdivs=0&cardwidth=45&divid=epay_card_logos"></script>
<script type="text/javascript" src="https://relay.ditonlinebetalingssystem.dk/integration/paymentlogos/PaymentLogos.aspx?merchantnumber={$merchantnumber}&direction=2&padding=0&rows=1&logo=1&showdivs=0&showcards=0&enablelink=0&divid=epay_logos"></script>
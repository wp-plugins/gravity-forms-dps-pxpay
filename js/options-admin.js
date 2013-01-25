// Gravity Forms DPS PxPay options admin script

jQuery(function($) {

	/**
	* check whether both the sandbox (test) mode and Stored Payments are selected,
	* show warning message if they are
	*/
	function checkSandbox() {
		var	useTest = ($("input[name='useTest']:checked").val() == "Y");

		if (useTest) {
			$(".gfdpspxpay-opt-admin-test").fadeIn();
		}
		else {
			$(".gfdpspxpay-opt-admin-test").hide();
		}
	}

	$("input[name='useTest']").change(checkSandbox);

	checkSandbox();

});

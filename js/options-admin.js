// Gravity Forms DPS PxPay options admin script

jQuery(function($) {
	"use strict";

	/**
	* check whether both the sandbox (test) mode and Stored Payments are selected,
	* show warning message if they are
	*/
	function checkSandbox() {
		var	useTest = ($("input[name='gfdpspxpay_plugin[useTest]']:checked").val() == "1");

		if (useTest) {
			$(".gfdpspxpay-opt-admin-test").fadeIn();
		}
		else {
			$(".gfdpspxpay-opt-admin-test").hide();
		}
	}

	$("input[name='gfdpspxpay_plugin[useTest]']").change(checkSandbox);

	checkSandbox();

});

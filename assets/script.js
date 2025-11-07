jQuery(document).ready(function ($) {
	$("#umc-scan-btn").on("click", function () {
		$("#umc-results").html("<p>⏳ Tarama yapılıyor...</p>");
		$.post(
			umcAjax.ajax_url,
			{
				action: "umc_scan",
				nonce: umcAjax.nonce,
			},
			function (response) {
				$("#umc-results").html(response);
			}
		);
	});
});

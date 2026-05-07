//window.coupon = "IU28YBJW5";
window.coupon = "";
const coupon2 = window.coupon;
var htmlCode = `
<div id="myCoupon" class="modal">
	<span class="close">&times;</span>
	<div class="modal-content">
		<p>Для новых пользователей мы подготовили специальный купон на скидку!</p>
		<p id="coupon-code">
		</p>
		<p>Этот купон будет ожидать вас на странице оформления заказа.</p>
	</div>
</div>
`;

// if (coupon && !localStorage.getItem('coupon_take_' + coupon2)) {
// 	document.body.innerHTML += htmlCode;
// 	document.getElementById("coupon-code").innerText = coupon2;
// 	var modal = document.getElementById("myCoupon");
// 	var closeBtn = document.getElementsByClassName("close")[0];

// 	closeBtn.onclick = function() {
// 			modal.style.display = "none";
// 			localStorage.setItem('coupon_take_' + coupon2, 'true')
// 	}
// 	window.onclick = function(event) {
// 			if (event.target == modal) {
// 					modal.style.display = "none";
// 					localStorage.setItem('coupon_take_' + coupon2, 'true')
// 			}
// 	}
// }
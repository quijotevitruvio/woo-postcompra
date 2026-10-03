/* Woo Postcompra — tarjeta «Invítanos un café».
 * Sin dependencias. El botón es un enlace normal y funciona sin JS (lleva el
 * monto sugerido); esto solo cambia el monto. El servidor vuelve a validarlo. */
(function () {
	var c = document.querySelector('.pcaf-cafe');
	if (!c) { return; }
	var btn = c.querySelector('.pcaf-cafe__btn'),
		caja = c.querySelector('.pcaf-otro-caja'),
		inp = c.querySelector('.pcaf-otro-input'),
		min = parseInt(c.getAttribute('data-pcaf-min'), 10) || 1,
		max = parseInt(c.getAttribute('data-pcaf-max'), 10) || 0;

	function limitar(v) {
		v = parseInt(v, 10);
		if (!v || v < min) { v = min; }
		if (max && v > max) { v = max; }
		return v;
	}
	function poner(v) {
		var u = btn.getAttribute('href').replace(/([?&]pcaf_monto=)\d+/, '$1' + limitar(v));
		btn.setAttribute('href', u);
	}

	c.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.pcaf-monto') : null;
		if (!b) { return; }
		var todos = c.querySelectorAll('.pcaf-monto');
		for (var i = 0; i < todos.length; i++) { todos[i].classList.remove('is-on'); }
		b.classList.add('is-on');
		if (b.hasAttribute('data-pcaf-otro')) {
			caja.hidden = false;
			inp.focus();
			poner(inp.value);
			return;
		}
		caja.hidden = true;
		poner(b.getAttribute('data-pcaf-monto'));
	});
	if (inp) {
		inp.addEventListener('input', function () { poner(inp.value); });
	}
})();

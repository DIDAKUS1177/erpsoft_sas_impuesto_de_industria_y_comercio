// Switchery
		var elems = Array.prototype.slice.call(document.querySelectorAll('.switch-btn'));
		$('.switch-btn').each(function() {
			new Switchery($(this)[0], $(this).data());
		});

		// Bootstrap Touchspin
		$("input[name='demo_vertical2']").TouchSpin({
			verticalbuttons: true,
			// verticalupclass: 'fa fa-plus',
			// verticaldownclass: 'fa fa-minus'
		});
		
	
		$("input[name='monedas50']").TouchSpin();
		$("input[name='monedas100']").TouchSpin();
		$("input[name='monedas200']").TouchSpin();
		$("input[name='monedas500']").TouchSpin();
		$("input[name='monedas1000']").TouchSpin();
		$("input[name='billetes1000']").TouchSpin();
		$("input[name='billetes2000']").TouchSpin();
		$("input[name='billetes5000']").TouchSpin();
		$("input[name='billetes10000']").TouchSpin();
		$("input[name='billetes20000']").TouchSpin();
		$("input[name='billetes50000']").TouchSpin();
		$("input[name='billetes100000']").TouchSpin();
		$("input[name='detkar_Cantidad']").TouchSpin();
		$("input[name='demo1']").TouchSpin({
			min: 0,
			max: 1000,
			step: 0.1,
			decimals: 2,
			boostat: 5,
			maxboostedstep: 10,
			postfix: '%'
		});
		$("input[name='demo2']").TouchSpin({
			min: -1000000000,
			max: 1000000000,
			stepinterval: 50,
			maxboostedstep: 10000000,
			prefix: '$'
		});
		$("input[name='Cantidad_Producto']").TouchSpin({
			initval: 1
		});
		$("input[name='demo5']").TouchSpin({
			prefix: "pre",
			postfix: "post"
		});
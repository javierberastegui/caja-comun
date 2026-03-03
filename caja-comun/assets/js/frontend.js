(function(){
	const cfg = window.CCF_FRONTEND || {};
	const api = async (path, options = {}) => {
		const headers = Object.assign({'Content-Type':'application/json'}, options.headers || {});
		if (cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
		const res = await fetch((cfg.apiBase || '') + path, Object.assign({}, options, { headers }));
		const data = await res.json().catch(() => ({}));
		if (!res.ok) { throw new Error(data.message || data.error || 'Error'); }
		return data;
	};

	const fmt = (n) => `${Number(n || 0).toFixed(2)} €`;
	const dashboard = document.querySelector('[data-ccf-view="dashboard"]');
	if (dashboard) {
		const month = new Date().toISOString().slice(0,7);
		Promise.all([
			api(`dashboard/month-summary?month_key=${month}`),
			api(`charts/income-vs-common?from=${month}&to=${month}`),
			api(`charts/common-expense-by-category?month=${month}`),
			api(`charts/common-budget-vs-actual?month=${month}`),
			api(`charts/common-budget-trend?from=${month}&to=${month}`)
		]).then(([summary, incomeLine, byCat, budgetActual, trend]) => {
			document.getElementById('ccf-kpi-income').textContent = fmt(summary.income_total);
			document.getElementById('ccf-kpi-separated').textContent = fmt(summary.separated_total);
			document.getElementById('ccf-kpi-common').textContent = fmt(summary.common_budget);
			document.getElementById('ccf-kpi-expense').textContent = fmt(budgetActual.actual_expense);
			new Chart(document.getElementById('ccf-chart-income'), { type:'line', data:{ labels: incomeLine.series.map(r=>r.month_key), datasets:[{label:'Ingresos', data:incomeLine.series.map(r=>r.income_total)},{label:'Separado', data:incomeLine.series.map(r=>r.separated_total)},{label:'Presupuesto común', data:incomeLine.series.map(r=>r.common_budget)}] } });
			new Chart(document.getElementById('ccf-chart-category'), { type:'doughnut', data:{ labels: byCat.map(r=>r.category_name), datasets:[{ data:byCat.map(r=>r.total)}]} });
			new Chart(document.getElementById('ccf-chart-budget-vs-actual'), { type:'bar', data:{ labels:['Presupuesto común','Gasto real'], datasets:[{ data:[budgetActual.common_budget, budgetActual.actual_expense] }] } });
			new Chart(document.getElementById('ccf-chart-trend'), { type:'line', data:{ labels: trend.map(r=>r.month_key), datasets:[{label:'Presupuesto común', data:trend.map(r=>r.common_budget)}] } });
		});
	}

	const fillSelect = (el, rows, labelKey='name', valueKey='id', all=true) => {
		if (!el) return;
		el.innerHTML = all ? '<option value="">Todos</option>' : '';
		rows.forEach((r) => {
			const opt = document.createElement('option'); opt.value = r[valueKey]; opt.textContent = r[labelKey]; el.appendChild(opt);
		});
	};

	const txForm = document.getElementById('ccf-transaction-form');
	if (txForm) {
		Promise.all([api('accounts'), api('categories')]).then(([acc, cat]) => {
			fillSelect(document.getElementById('ccf-source-account'), acc.data, 'name', 'id', false);
			fillSelect(document.getElementById('ccf-destination-account'), acc.data, 'name', 'id', false);
			fillSelect(document.getElementById('ccf-category-select'), cat.data, 'name', 'id', false);
		});
		txForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			const fd = new FormData(txForm);
			const payload = Object.fromEntries(fd.entries());
			const feedback = document.getElementById('ccf-transaction-feedback');
			try {
				const saved = await api('transactions', { method:'POST', body: JSON.stringify(payload) });
				const files = document.getElementById('ccf-attachments').files;
				if (files.length) {
					const data = new FormData();
					Array.from(files).forEach(f => data.append('files[]', f));
					await fetch((cfg.apiBase || '') + `transactions/${saved.id}/attachments`, { method:'POST', headers:{'X-WP-Nonce':cfg.nonce}, body:data });
				}
				feedback.textContent = 'Transacción guardada.';
			} catch (err) { feedback.textContent = err.message; }
		});
	}

	const incomeForm = document.getElementById('ccf-income-form');
	if (incomeForm) {
		const feedback = document.getElementById('ccf-income-feedback');
		incomeForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			const fd = new FormData(incomeForm);
			const month = fd.get('month_key');
			try {
				await api('monthly-incomes', { method:'POST', body: JSON.stringify({ month_key: month, user_id:1, amount: fd.get('income_a'), notes: fd.get('notes_a') }) });
				await api('monthly-incomes', { method:'POST', body: JSON.stringify({ month_key: month, user_id:2, amount: fd.get('income_b'), notes: fd.get('notes_b') }) });
				feedback.textContent = 'Ingresos guardados';
			} catch (err) { feedback.textContent = err.message; }
		});
		document.getElementById('ccf-preview-allocation').addEventListener('click', async () => {
			const month = new FormData(incomeForm).get('month_key');
			const data = await api('monthly-allocations/preview', { method:'POST', body: JSON.stringify({ month_key: month }) });
			feedback.textContent = `Preview: presupuesto común ${fmt(data.common_budget)}`;
		});
		document.getElementById('ccf-run-allocation').addEventListener('click', async () => {
			const month = new FormData(incomeForm).get('month_key');
			const data = await api('monthly-allocations/run', { method:'POST', body: JSON.stringify({ month_key: month }) });
			feedback.textContent = `Asignación ejecutada. Estado: ${data.status || 'ok'}`;
		});
	}

	const filtersForm = document.getElementById('ccf-transactions-filters');
	if (filtersForm) {
		Promise.all([api('accounts'), api('categories')]).then(([acc, cat]) => {
			fillSelect(document.getElementById('ccf-filter-account'), acc.data);
			fillSelect(document.getElementById('ccf-filter-category'), cat.data);
		});
		const loadTx = async () => {
			const params = new URLSearchParams(new FormData(filtersForm));
			const rows = (await api(`transactions?${params.toString()}`)).data || [];
			const tbody = document.querySelector('#ccf-transactions-table tbody');
			tbody.innerHTML = '';
			rows.forEach((tx) => {
				const tr = document.createElement('tr');
				tr.innerHTML = `<td>${tx.transaction_date}</td><td>${tx.type}</td><td>${tx.description || ''}</td><td>${fmt(tx.amount)}</td><td>${tx.status}</td><td><button data-id="${tx.id}" data-reviewed="1">Revisado</button> <button data-id="${tx.id}" data-reviewed="0">Pendiente</button></td>`;
				tbody.appendChild(tr);
			});
		};
		filtersForm.addEventListener('submit', (e) => { e.preventDefault(); loadTx(); });
		document.addEventListener('click', async (e) => {
			const btn = e.target.closest('button[data-id][data-reviewed]');
			if (!btn) return;
			await api(`transactions/${btn.dataset.id}/review`, { method:'POST', body: JSON.stringify({ reviewed: Number(btn.dataset.reviewed), flagged: 0 }) });
			loadTx();
		});
		loadTx();
	}
})();

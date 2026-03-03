(function () {
	const root = document.querySelector('[data-ccf-view="app"]');
	if (!root) return;

	const cfg = window.CCF_FRONTEND || {};
	const charts = {};
	const state = { currentMonth: new Date().toISOString().slice(0, 7), accounts: [], categories: [], rows: [], commonAccountId: null };

	const api = async (path, options = {}) => {
		const isForm = options.body instanceof FormData;
		const headers = Object.assign({}, options.headers || {});
		if (!isForm) headers['Content-Type'] = 'application/json';
		if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
		const response = await fetch((cfg.apiBase || '') + path, Object.assign({}, options, { headers }));
		const data = await response.json().catch(() => ({}));
		if (!response.ok) throw new Error(data.message || data.error || 'No se pudo completar la operación.');
		return data;
	};

	const fmt = (n) => `${Number(n || 0).toFixed(2).replace('.', ',')} €`;
	const safeLabel = (v, fallback) => (v === undefined || v === null || String(v).trim() === '' ? fallback : String(v));
	const setFeedback = (el, msg, isError = false) => {
		if (!el) return;
		el.textContent = msg || '';
		el.classList.toggle('is-error', isError);
	};

	const openMovementModal = () => {
		if (!modal) return;
		if (typeof modal.showModal === 'function') {
			try {
				modal.showModal();
				return;
			} catch (err) {
				// Fallback below for browsers/issues with <dialog>.
			}
		}
		modal.setAttribute('open', 'open');
	};

	const closeMovementModal = () => {
		if (!modal) return;
		if (typeof modal.close === 'function') {
			try {
				modal.close();
				return;
			} catch (err) {
				// Fallback below for browsers/issues with <dialog>.
			}
		}
		modal.removeAttribute('open');
	};

	const monthInput = document.getElementById('ccf-month');
	const tableBody = document.querySelector('#ccf-transactions-table tbody');
	const emptyState = document.getElementById('ccf-empty-state');
	const tableFeedback = document.getElementById('ccf-table-feedback');
	const modal = document.getElementById('ccf-movement-modal');
	const movementForm = document.getElementById('ccf-movement-form');
	const modalFeedback = document.getElementById('ccf-modal-feedback');
	const inlineAttachment = document.getElementById('ccf-inline-attachment');
	const modalTitle = document.getElementById('ccf-modal-title');
	const categorySelect = document.getElementById('ccf-category-select');
	const categoryEmpty = document.getElementById('ccf-category-empty');
	const categoryWrap = document.getElementById('ccf-create-category-wrap');
	const categoryInput = document.getElementById('ccf-create-category-name');
	const categoryCreateFeedback = document.getElementById('ccf-category-create-feedback');
	const commonAccountInfo = document.getElementById('ccf-common-account-info');

	const fillSelect = (el, rows, placeholder, emptyLabel, emptyNode) => {
		el.innerHTML = '';
		const defaultOpt = document.createElement('option');
		defaultOpt.value = '';
		defaultOpt.textContent = rows.length ? placeholder : emptyLabel;
		el.appendChild(defaultOpt);
		rows.forEach((row) => {
			const opt = document.createElement('option');
			opt.value = row.id;
			opt.textContent = row.name;
			el.appendChild(opt);
		});
		if (emptyNode) emptyNode.hidden = rows.length > 0;
	};

	const renderCatalogs = () => {
		fillSelect(categorySelect, state.categories, 'Selecciona categoría', 'No hay categorías creadas todavía', categoryEmpty);
		commonAccountInfo.hidden = !!state.commonAccountId;
	};

	const renderKpis = (summary, budgetActual) => {
		const income = Number(summary.income_total || 0);
		const expense = Number((budgetActual && budgetActual.actual_expense) || 0);
		document.getElementById('ccf-kpi-income').textContent = fmt(income);
		document.getElementById('ccf-kpi-expense').textContent = fmt(expense);
		document.getElementById('ccf-kpi-balance').textContent = fmt(income - expense);
		document.getElementById('ccf-kpi-common').textContent = fmt(summary.common_budget || 0);
	};

	const destroyChart = (id) => { if (charts[id]) charts[id].destroy(); };
	const toggleEmptyForChart = (canvasId, hasData) => {
		const wrap = document.getElementById(canvasId).closest('.ccf-chart-card');
		wrap.querySelector('.ccf-chart-empty').hidden = hasData;
	};

	const renderCharts = (incomeLine, byCat, trend) => {
		const incomeLabels = (incomeLine.series || []).map((r) => safeLabel(r.month_key, state.currentMonth));
		const incomeData = (incomeLine.series || []).map((r) => Number(r.income_total || 0));
		const expenseData = (incomeLine.series || []).map((r) => Number(r.common_budget || 0));
		destroyChart('income');
		const hasIncomeData = incomeData.some((n) => n > 0) || expenseData.some((n) => n > 0);
		toggleEmptyForChart('ccf-chart-income', hasIncomeData);
		if (hasIncomeData) {
			charts.income = new Chart(document.getElementById('ccf-chart-income'), {
				type: 'line',
				data: { labels: incomeLabels, datasets: [{ label: 'Ingresos', data: incomeData, borderColor: '#8ec5ff' }, { label: 'Gasto', data: expenseData, borderColor: '#ff8ea6' }] },
				options: { responsive: true, plugins: { legend: { labels: { color: '#dbeafe' } } }, scales: { x: { ticks: { color: '#bfdbfe' } }, y: { ticks: { color: '#bfdbfe' } } } }
			});
		}

		const catLabels = (byCat || []).map((r) => safeLabel(r.category_name, 'Sin categoría'));
		const catData = (byCat || []).map((r) => Number(r.total || 0));
		destroyChart('category');
		const hasCategoryData = catData.some((n) => n > 0);
		toggleEmptyForChart('ccf-chart-category', hasCategoryData);
		if (hasCategoryData) {
			charts.category = new Chart(document.getElementById('ccf-chart-category'), {
				type: 'doughnut',
				data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: ['#7dd3fc', '#a78bfa', '#f9a8d4', '#34d399', '#fbbf24', '#fb7185'] }] },
				options: { plugins: { legend: { labels: { color: '#dbeafe' } } } }
			});
		}

		const trendLabels = (trend || []).map((r) => safeLabel(r.month_key, state.currentMonth));
		const trendData = (trend || []).map((r) => Number(r.common_budget || 0));
		destroyChart('trend');
		const hasTrendData = trendData.some((n) => n > 0);
		toggleEmptyForChart('ccf-chart-trend', hasTrendData);
		if (hasTrendData) {
			charts.trend = new Chart(document.getElementById('ccf-chart-trend'), {
				type: 'bar',
				data: { labels: trendLabels, datasets: [{ label: 'Presupuesto', data: trendData, backgroundColor: '#60a5fa' }] },
				options: { plugins: { legend: { labels: { color: '#dbeafe' } } }, scales: { x: { ticks: { color: '#bfdbfe' } }, y: { ticks: { color: '#bfdbfe' } } } }
			});
		}
	};

	const fetchTransactions = async () => {
		const data = await api(`transactions?month=${state.currentMonth}&limit=200`);
		const rows = (data.data || []).map((tx) => {
			const account = state.accounts.find((a) => Number(a.id) === Number(tx.source_account_id || tx.destination_account_id));
			const category = state.categories.find((c) => Number(c.id) === Number(tx.category_id));
			return Object.assign({}, tx, {
				account_name: account ? account.name : 'Cuenta común',
				category_name: category ? category.name : ''
			});
		});
		await Promise.all(rows.map(async (row) => {
			const atts = await api(`transactions/${row.id}/attachments`);
			row.attachment_count = (atts.data || []).length;
			row.has_ticket = row.attachment_count > 0;
		}));
		state.rows = rows;
		tableBody.innerHTML = '';
		emptyState.hidden = rows.length > 0;
		rows.forEach((tx) => {
			const tr = document.createElement('tr');
			const hasTicket = tx.has_ticket || Number(tx.attachment_count || 0) > 0;
			tr.innerHTML = `<td>${safeLabel(tx.transaction_date, '-')}</td><td>${safeLabel(tx.type, '-')}</td><td>${safeLabel(tx.description, 'Sin concepto')}</td><td>${safeLabel(tx.category_name, 'Sin categoría')}</td><td>${safeLabel(tx.account_name, 'Cuenta común')}</td><td>${fmt(tx.amount)}</td><td><span class="ccf-ticket ${hasTicket ? 'is-on' : ''}">${hasTicket ? 'Con ticket' : 'Sin ticket'}</span></td><td>${safeLabel(tx.status, 'pendiente')}</td><td><div class="ccf-row-actions"><button type="button" class="ccf-btn ccf-btn-soft" data-action="edit" data-id="${tx.id}">Editar</button><button type="button" class="ccf-btn ccf-btn-soft" data-action="attach" data-id="${tx.id}">Adjuntar</button><button type="button" class="ccf-btn ccf-btn-soft" data-action="ticket" data-id="${tx.id}">Ver</button><button type="button" class="ccf-btn ccf-btn-danger" data-action="delete" data-id="${tx.id}">Borrar</button></div></td>`;
			tableBody.appendChild(tr);
		});
	};

	const refreshAll = async () => {
		setFeedback(tableFeedback, 'Actualizando…');
		try {
			const [summary, incomeLine, byCat, trend, budgetActual] = await Promise.all([
				api(`dashboard/month-summary?month_key=${state.currentMonth}`),
				api(`charts/income-vs-common?from=${state.currentMonth}&to=${state.currentMonth}`),
				api(`charts/common-expense-by-category?month=${state.currentMonth}`),
				api(`charts/common-budget-trend?from=${state.currentMonth}&to=${state.currentMonth}`),
				api(`charts/common-budget-vs-actual?month=${state.currentMonth}`)
			]);
			renderKpis(summary, budgetActual);
			renderCharts(incomeLine, byCat, trend);
			await fetchTransactions();
			setFeedback(tableFeedback, 'Datos al día.');
		} catch (err) {
			setFeedback(tableFeedback, err.message, true);
		}
	};

	const isCategoryRequired = (type) => ['expense', 'income'].includes(type);
	const validateBeforeSave = (payload) => {
		if (!payload.transaction_date) return 'Debes seleccionar una fecha.';
		if (!payload.type) return 'Debes seleccionar un tipo de movimiento.';
		if (!payload.description || payload.description.trim().length === 0) return 'Debes indicar un concepto.';
		if (!payload.amount || Number(payload.amount) <= 0) return 'El importe no es válido.';
		if (!state.commonAccountId) return 'No hay una cuenta común activa. Crea una en administración.';
		if (isCategoryRequired(payload.type) && !payload.category_id) return 'Debes seleccionar una categoría.';
		return '';
	};

	const normalizeBackendError = (message) => {
		const msg = String(message || '').toLowerCase();
		if (msg.includes('categor')) return 'Debes seleccionar una categoría.';
		if (msg.includes('cuenta común')) return 'No hay una cuenta común activa. Crea una en administración.';
		if (msg.includes('common')) return 'Solo se pueden usar cuentas comunes.';
		if (msg.includes('amount') || msg.includes('importe')) return 'El importe no es válido.';
		return message || 'No se pudo guardar el movimiento.';
	};

	const loadCatalogs = async () => {
		const [acc, cat] = await Promise.all([
			api('accounts?status=active&type=common').catch(() => ({ data: [] })),
			api('categories?active=1').catch(() => ({ data: [] }))
		]);
		state.accounts = acc.data || [];
		state.categories = cat.data || [];
		state.commonAccountId = state.accounts.length ? Number(state.accounts[0].id) : null;
		movementForm.source_account_id.value = state.commonAccountId || '';
		renderCatalogs();
	};

	const openModal = (tx) => {
		movementForm.reset();
		setFeedback(modalFeedback, '');
		setFeedback(categoryCreateFeedback, '');
		categoryWrap.hidden = true;
		movementForm.id.value = tx?.id || '';
		movementForm.transaction_date.value = tx?.transaction_date || `${state.currentMonth}-01`;
		movementForm.type.value = tx?.type || 'expense';
		movementForm.description.value = tx?.description || '';
		movementForm.category_id.value = tx?.category_id || '';
		movementForm.source_account_id.value = tx?.source_account_id || state.commonAccountId || '';
		movementForm.amount.value = tx?.amount || '';
		movementForm.status.value = tx?.status || 'posted';
		modalTitle.textContent = tx ? 'Editar movimiento' : 'Nuevo movimiento';
		openMovementModal();
	};

	const isCategoryRequired = (type) => ['expense', 'income'].includes(type);
	const isAccountRequired = (type) => ['expense', 'income', 'transfer', 'adjustment'].includes(type);

	const validateBeforeSave = (payload) => {
		if (!payload.transaction_date) return 'Debes seleccionar una fecha.';
		if (!payload.type) return 'Debes seleccionar un tipo de movimiento.';
		if (!payload.description || payload.description.trim().length === 0) return 'Debes indicar un concepto.';
		if (!payload.amount || Number(payload.amount) <= 0) return 'El importe no es válido.';
		if (isCategoryRequired(payload.type) && !payload.category_id) return 'Debes seleccionar una categoría.';
		if (isAccountRequired(payload.type) && !payload.source_account_id) return 'Debes seleccionar una cuenta.';
		return '';
	};

	const normalizeBackendError = (message) => {
		const msg = String(message || '').toLowerCase();
		if (msg.includes('categor')) return 'Debes seleccionar una categoría.';
		if (msg.includes('cuenta') || msg.includes('account')) return 'Debes seleccionar una cuenta.';
		if (msg.includes('amount') || msg.includes('importe')) return 'El importe no es válido.';
		return message || 'No se pudo guardar el movimiento.';
	};

	const loadCatalogs = async () => {
		const [acc, cat] = await Promise.all([
			api('accounts?status=active').catch(() => ({ data: [] })),
			api('categories?active=1').catch(() => ({ data: [] }))
		]);
		state.accounts = acc.data || [];
		state.categories = cat.data || [];
		renderCatalogs();
	};

	document.getElementById('ccf-new-movement').addEventListener('click', () => openModal());
	document.getElementById('ccf-modal-close').addEventListener('click', () => closeMovementModal());
	document.getElementById('ccf-open-create-category').addEventListener('click', () => { categoryWrap.hidden = false; categoryInput.focus(); });
	document.getElementById('ccf-create-category-cancel').addEventListener('click', () => { categoryWrap.hidden = true; setFeedback(categoryCreateFeedback, ''); });
	document.getElementById('ccf-create-category-submit').addEventListener('click', async () => {
		const name = categoryInput.value.trim();
		if (!name) return setFeedback(categoryCreateFeedback, 'Escribe un nombre para la categoría.', true);
		try {
			const response = await api('categories', { method: 'POST', body: JSON.stringify({ name, active: 1 }) });
			if (!response.id) throw new Error('No se pudo guardar la categoría.');
			await loadCatalogs();
			movementForm.category_id.value = String(response.id);
			categoryInput.value = '';
			categoryWrap.hidden = true;
			setFeedback(categoryCreateFeedback, 'Categoría creada correctamente.');
		} catch (err) {
			setFeedback(categoryCreateFeedback, normalizeBackendError(err.message), true);
		}
	});

	monthInput.addEventListener('change', () => {
		state.currentMonth = monthInput.value;
		refreshAll();
	});

	movementForm.addEventListener('submit', async (e) => {
		e.preventDefault();
		const fd = new FormData(movementForm);
		const txId = fd.get('id');
		const payload = {
			transaction_date: fd.get('transaction_date'),
			type: fd.get('type'),
			description: fd.get('description'),
			category_id: fd.get('category_id') || null,
			source_account_id: state.commonAccountId,
			amount: fd.get('amount'),
			status: fd.get('status')
		};
		const validationError = validateBeforeSave(payload);
		if (validationError) return setFeedback(modalFeedback, validationError, true);
		try {
			const result = txId ? await api(`transactions/${txId}`, { method: 'PUT', body: JSON.stringify(payload) }) : await api('transactions', { method: 'POST', body: JSON.stringify(payload) });
			const realId = txId || result.id;
			if (!realId) throw new Error('No se pudo guardar el movimiento.');
			const files = document.getElementById('ccf-attachments').files;
			if (files.length) {
				const upload = new FormData();
				Array.from(files).forEach((f) => upload.append('files[]', f));
				await api(`transactions/${realId}/attachments`, { method: 'POST', body: upload });
			}
			modal.close();
			await refreshAll();
			setFeedback(tableFeedback, 'Movimiento guardado correctamente.');
		} catch (err) {
			setFeedback(modalFeedback, normalizeBackendError(err.message), true);
		}
	});

	tableBody.addEventListener('click', async (e) => {
		const btn = e.target.closest('button[data-action][data-id]');
		if (!btn) return;
		const id = Number(btn.dataset.id);
		const tx = state.rows.find((row) => Number(row.id) === id);
		if (!tx) return;
		try {
			if (btn.dataset.action === 'edit') return openModal(tx);
			if (btn.dataset.action === 'delete') {
				if (!window.confirm('¿Borrar este movimiento?')) return;
				await api(`transactions/${id}`, { method: 'DELETE' });
			}
			if (btn.dataset.action === 'attach') {
				inlineAttachment.onchange = async () => {
					if (!inlineAttachment.files.length) return;
					const upload = new FormData();
					upload.append('file', inlineAttachment.files[0]);
					await api(`transactions/${id}/attachments`, { method: 'POST', body: upload });
					await refreshAll();
				};
				inlineAttachment.click();
				return;
			}
			if (btn.dataset.action === 'ticket') {
				const atts = await api(`transactions/${id}/attachments`);
				if (!atts.data || !atts.data.length) return setFeedback(tableFeedback, 'Este movimiento aún no tiene ticket.', true);
				window.open(atts.data[0].url, '_blank');
				return;
			}
			await refreshAll();
		} catch (err) {
			setFeedback(tableFeedback, err.message, true);
		}
	});

	(async () => {
		try {
			await loadCatalogs();
			monthInput.value = state.currentMonth;
			await refreshAll();
		} catch (err) {
			setFeedback(tableFeedback, normalizeBackendError(err.message), true);
		}
	})();
})();

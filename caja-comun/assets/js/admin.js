(function () {
	if (!window.Chart || !window.ccfDashboardCharts) return;
	const data = window.ccfDashboardCharts;
	const mk = (id, config) => {
		const el = document.getElementById(id);
		if (el) new Chart(el, config);
	};
	mk('ccf-chart-income', { type: 'bar', data: { labels: data.income_vs_split_vs_common.labels, datasets: [{ label: '€', data: data.income_vs_split_vs_common.data }] } });
	mk('ccf-chart-budget-expense', { type: 'bar', data: { labels: ['Presupuesto común', 'Gasto real'], datasets: [{ data: [data.budget_vs_expense.budget, data.budget_vs_expense.expense] }] } });
	mk('ccf-chart-categories', { type: 'pie', data: { labels: data.common_expense_by_category.map((c) => 'Cat ' + c.category_id), datasets: [{ data: data.common_expense_by_category.map((c) => c.total) }] } });
	mk('ccf-chart-evolution', { type: 'line', data: { labels: data.common_budget_evolution.map((r) => r.month_key), datasets: [{ label: 'Presupuesto común', data: data.common_budget_evolution.map((r) => r.common_budget) }] } });
})();

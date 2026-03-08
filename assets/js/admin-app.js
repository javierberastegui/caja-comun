(function () {
  const root = document.getElementById('eco-pro-admin-app');
  if (!root || typeof window.wp === 'undefined' || !window.wp.apiFetch) {
    return;
  }

  window.wp.apiFetch.use(window.wp.apiFetch.createNonceMiddleware(ecoProData.nonce));

  root.innerHTML = '<p>Cargando transacciones...</p>';

  window.wp.apiFetch({ path: '/economia/v1/transactions' })
    .then(function (items) {
      if (!Array.isArray(items) || items.length === 0) {
        root.innerHTML = '<div class="eco-card"><h2>Economía Pro</h2><p>Sin transacciones aún.</p></div>';
        return;
      }

      const rows = items.map(function (item) {
        return '<tr><td>' + item.id + '</td><td>' + item.type + '</td><td>' + item.amount + '</td><td>' + item.txn_date + '</td><td>' + (item.description || '') + '</td></tr>';
      }).join('');

      root.innerHTML = '<div class="eco-card"><h2>Últimas transacciones</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Tipo</th><th>Importe</th><th>Fecha</th><th>Descripción</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    })
    .catch(function (error) {
      root.innerHTML = '<div class="notice notice-error"><p>Error cargando datos: ' + error.message + '</p></div>';
    });
})();

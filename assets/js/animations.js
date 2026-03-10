(function(){
  function parseEuroNumber(text){
    var cleaned = String(text || '').replace(/[^0-9,.-]/g, '');
    if (!cleaned) return NaN;
    cleaned = cleaned.replace(/\.(?=\d{3}(\D|$))/g, '');
    cleaned = cleaned.replace(',', '.');
    return parseFloat(cleaned);
  }

  function formatEuroNumber(value){
    return Number(value || 0).toLocaleString('es-ES', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }) + ' €';
  }

  function animateNumbers(){
    document.querySelectorAll('.ecopro-card strong, .ecopro-card .amount').forEach(function(el){
      var num = parseEuroNumber(el.textContent);
      if (isNaN(num)) return;
      var current = 0;
      var duration = 700;
      var steps = Math.max(1, Math.round(duration / 16));
      var increment = num / steps;

      function step(){
        current += increment;
        if ((num >= 0 && current >= num) || (num < 0 && current <= num)) {
          el.textContent = formatEuroNumber(num);
          return;
        }
        el.textContent = formatEuroNumber(current);
        requestAnimationFrame(step);
      }

      requestAnimationFrame(step);
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    setTimeout(animateNumbers, 120);
  });
})();

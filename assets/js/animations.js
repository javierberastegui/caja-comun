
(function(){
  function animateNumbers(){
    document.querySelectorAll('.ecopro-card strong, .ecopro-card .amount').forEach(el=>{
      const txt = el.textContent.replace(/[^0-9.,-]/g,'');
      const num = parseFloat(txt.replace(',','.'));
      if(isNaN(num)) return;
      let start=0;
      const dur=700;
      const step = ts=>{
        start += num/ (dur/16);
        if(start>=num){el.textContent = num.toFixed(2).replace('.',',') + ' €';return;}
        el.textContent = start.toFixed(2).replace('.',',') + ' €';
        requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }

  function revealCards(){
    document.querySelectorAll('.ecopro-card').forEach((el,i)=>{
      el.style.opacity=0;
      el.style.transform='translateY(12px)';
      setTimeout(()=>{
        el.style.transition='all .35s ease';
        el.style.opacity=1;
        el.style.transform='translateY(0)';
      }, i*40);
    });
  }

  document.addEventListener('DOMContentLoaded',function(){
    revealCards();
    setTimeout(animateNumbers,120);
  });
})();

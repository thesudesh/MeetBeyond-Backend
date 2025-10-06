// Small dashboard interaction script: tilt on hover + hearts on click
(function(){
  // Soft dashboard tilt with gentle blend and mobile-safe fallback
  function spawnHeartAt(el){
    const rect = el.getBoundingClientRect();
    const x = rect.left + rect.width/2;
    const y = rect.top + rect.height/2;
    const heart = document.createElement('div');
    heart.className = 'heart';
    heart.style.left = x + 'px';
    heart.style.top = y + 'px';
    document.body.appendChild(heart);
    requestAnimationFrame(()=> heart.classList.add('animate'));
    setTimeout(()=> heart.remove(), 1800);
  }

  // throttle helper
  function throttle(fn, wait){
    let last = 0;
    return function(...args){
      const now = Date.now();
      if(now - last > wait){ last = now; fn.apply(this,args); }
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    const isTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
    document.querySelectorAll('.dashboard-link').forEach(el=>{
      el.style.transition = 'transform 0.28s cubic-bezier(.2,.9,.3,1), box-shadow 0.28s';
      if(!isTouch){
        const onMove = throttle((e)=>{
          const rect = el.getBoundingClientRect();
          const dx = e.clientX - (rect.left + rect.width/2);
          const dy = e.clientY - (rect.top + rect.height/2);
          const rx = (-dy / rect.height) * 3; // softer rotation
          const ry = (dx / rect.width) * 3;
          el.style.transform = `perspective(700px) rotateX(${rx}deg) rotateY(${ry}deg)`;
        }, 40);
        el.addEventListener('mousemove', onMove);
        el.addEventListener('mouseleave', ()=>{ el.style.transform = ''; });
      }
      // click/tap spawns a single soft heart
      el.addEventListener('click', (ev)=>{ spawnHeartAt(el); });
    });
  });
})();

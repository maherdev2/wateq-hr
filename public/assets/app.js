document.addEventListener('DOMContentLoaded',()=>{
  const themeToggle=document.getElementById('themeToggle');
  const syncTheme=()=>{
    if(!themeToggle) return;
    const dark=document.documentElement.dataset.theme==='dark';
    themeToggle.setAttribute('aria-pressed',String(dark));
    themeToggle.setAttribute('aria-label',dark?'تفعيل الوضع الفاتح':'تفعيل الوضع الداكن');
    const icon=themeToggle.querySelector('.ph-duotone');
    icon?.classList.toggle('ph-moon-stars',!dark);
    icon?.classList.toggle('ph-sun',dark);
  };
  syncTheme();
  themeToggle?.addEventListener('click',()=>{
    document.documentElement.classList.add('theme-switching');
    const next=document.documentElement.dataset.theme==='dark'?'light':'dark';
    document.documentElement.dataset.theme=next;
    try { localStorage.setItem('wateq-theme',next); } catch (_) {}
    syncTheme();
    requestAnimationFrame(()=>requestAnimationFrame(()=>document.documentElement.classList.remove('theme-switching')));
  });
  const menu=document.getElementById('menuToggle');
  const sidebar=document.getElementById('sidebar');
  if(menu && sidebar){
    menu.setAttribute('aria-controls','sidebar');
    const backdrop=document.createElement('button');
    backdrop.type='button';
    backdrop.className='sidebar-backdrop';
    backdrop.setAttribute('aria-label','إغلاق القائمة');
    backdrop.hidden=true;
    sidebar.after(backdrop);
    const syncMenu=()=>{
      const mobile=window.matchMedia('(max-width:790px)').matches;
      const open=mobile && sidebar.classList.contains('open');
      sidebar.inert=mobile && !open;
      backdrop.hidden=!open;
      menu.setAttribute('aria-expanded',String(open));
    };
    menu.addEventListener('click',()=>{ sidebar.classList.toggle('open'); syncMenu(); });
    backdrop.addEventListener('click',()=>{ sidebar.classList.remove('open'); syncMenu(); menu.focus(); });
    document.addEventListener('keydown',event=>{
      if(event.key==='Escape' && sidebar.classList.contains('open')){
        sidebar.classList.remove('open'); syncMenu(); menu.focus();
      }
    });
    window.addEventListener('resize',syncMenu);
    syncMenu();
  }
  document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{
    if(!window.confirm(form.dataset.confirm)) event.preventDefault();
  }));
  document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
});

try {
  if (localStorage.getItem('wateq-theme') === 'dark') {
    document.documentElement.dataset.theme = 'dark';
  }
} catch (_) {
  // The light theme remains available when browser storage is disabled.
}

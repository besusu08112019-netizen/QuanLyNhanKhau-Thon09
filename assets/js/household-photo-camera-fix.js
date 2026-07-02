(() => {
  function applyHouseholdCameraCapture() {
    document.querySelectorAll('.household-photo-capture-ui input[type="file"][accept="image/*"]').forEach(input => {
      input.setAttribute('capture', 'environment');
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyHouseholdCameraCapture);
  else applyHouseholdCameraCapture();

  const observer = new MutationObserver(applyHouseholdCameraCapture);
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
/**
 * Künstlerleiste (breite Ansicht): Sie klebt direkt unter der Menüleiste und ist genau so hoch wie der sichtbare Rest des Fensters.
 * So lässt sie sich immer bis ganz oben und ganz unten scrollen, egal wie hoch die Menüleiste gerade ist und wie weit die Seite gescrollt ist.
 */
function fit() {
  const side = document.querySelector('.lb-side');
  if (!side) return;
  // Schmale Ansicht: Die Leiste liegt waagerecht über der Seite und scrollt nicht für sich.
  if (!window.matchMedia('(min-width: 901px)').matches) {
    side.style.maxHeight = '';
    return;
  }
  const bar = document.querySelector('.topbar');
  const top = (bar ? bar.getBoundingClientRect().bottom : 0) + 8;
  document.documentElement.style.setProperty('--side-top', `${Math.round(top)}px`);
  // Nie höher als bis zum Ende des Seitenrahmens: sonst schiebt der Rahmen die Leiste am Seitenende unter die Menüleiste.
  const from = Math.max(top, side.getBoundingClientRect().top);
  const parent = side.parentElement;
  const frame = parent ? parent.getBoundingClientRect().bottom - parseFloat(getComputedStyle(parent).paddingBottom) - parseFloat(getComputedStyle(side).marginBottom) : window.innerHeight;
  side.style.maxHeight = `${Math.max(160, Math.floor(Math.min(window.innerHeight, frame) - from - 8))}px`;
}

let queued = false;
const later = () => {
  if (queued) return;
  queued = true;
  requestAnimationFrame(() => { queued = false; fit(); });
};

window.addEventListener('scroll', later, { passive: true });
window.addEventListener('resize', later);
window.visualViewport?.addEventListener('resize', later);
new MutationObserver(later).observe(document.documentElement, { childList: true, subtree: true });

import { onStateChange } from './core.js';
import { genreIcon } from './cards.js';
import { esc } from './ui.js';

/**
 * Wird eine Band zur Genre-Band (oder zum Hybrid), kommt ihr Genre-Abzeichen groß ins Bild.
 * Nur CSS-Transform und Deckkraft, damit es auch auf dem iPad flüssig bleibt.
 */
onStateChange((before, after) => {
  const old = new Map(before.bands.map((b) => [b.id, b.title]));
  for (const b of after.bands) {
    if (b.title && b.title !== old.get(b.id)) show(b);
  }
});

function show(b) {
  document.querySelector('.celebrate')?.remove();
  const el = document.createElement('div');
  el.className = 'celebrate';
  el.innerHTML = `
    <div class="celebrate-icons">${b.genres.map(genreIcon).join('')}</div>
    <div class="celebrate-text">${esc(b.name)} ist jetzt eine ${esc(b.title)}!</div>
    <div class="celebrate-bonus">Sound + ${Math.round((b.genreBonus - 1) * 100)} %</div>`;
  el.addEventListener('click', () => el.remove());
  el.addEventListener('animationend', (e) => {
    if (e.animationName === 'celebrate-out') el.remove();
  });
  document.body.append(el);
}

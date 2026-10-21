import { genreName, genreTag, instrumentIcon, instrumentName } from './cards.js';
import { app, bandById, cat, defineActions, musicianById } from './core.js';
import { closeAllModals, esc, openModal } from './ui.js';

/**
 * Genre-Infofenster: was das Genre bringt, die Voraussetzung für eine Genre-Band, wie es in der Band des Musikers
 * gerade aussieht – und welche eigenen Musiker das Genre mögen (z. B. beim Talent-Scout: Wer würde dazu passen?).
 */
export function openGenreInfo(genre, m = null) {
  const c = cat();
  const band = m?.bandId ? bandById(m.bandId) : null;
  const needs = c.genreLineup[genre].map(([options, need]) => `${need} × ${options.map(instrumentName).join(' oder ')}`).join(' und ');
  const check = band?.genreChecks?.[genre];
  let now = '';
  if (band && band.genres.includes(genre)) {
    now = `<p class="ok">＋ ${esc(band.name)} ist eine ${esc(band.title)}: Sound +${Math.round((band.genreBonus - 1) * 100)} %.</p>`;
  } else if (band && check) {
    const missing = check.requirements.filter((r) => !r.met).map((r) => `${r.need - r.have} × ${r.instruments.map(instrumentName).join(' oder ')}`);
    now = `<p class="muted">${esc(band.name)} ist noch keine ${esc(genreName(genre))}-Band: ${!check.allFans ? `nicht alle mögen ${esc(genreName(genre))} (${check.fans} von ${band.memberIds.length})` : ''}${!check.allFans && missing.length ? ' und ' : ''}${missing.length ? `es fehlt ${esc(missing.join(', '))}` : ''}.</p>`;
  }
  const own = app.state.musicians.filter((x) => x.genres.includes(genre) && x.id !== m?.id);
  const modal = openModal({ html: `
    <div class="eyebrow">Genre</div>
    <h2>${genreTag(genre)}</h2>
    <p>Mögen alle in einer Band dasselbe Genre und passt die Besetzung, wird sie eine Genre-Band: <b>Sound +${Math.round((c.rules.genreBonus - 1) * 100)} %</b>. Das Genre bestimmt auch, welche Musik die Band spielt.</p>
    <p><b>Voraussetzung für eine ${esc(genreName(genre))}-Band:</b> Alle mögen ${esc(genreName(genre))}, und dabei sind ${esc(needs)}.</p>
    ${now ? `<h3>Gerade</h3>${now}` : ''}
    <h3>Deine Musiker, die ${esc(genreName(genre))} mögen</h3>
    ${own.length ? `<ul class="history">${own.map((x) => {
      const b = x.bandId ? bandById(x.bandId) : null;
      return `<li><a href="#/musician/${x.id}" data-genre-go>${x.instruments.map((i) => instrumentIcon(i.type)).join('')} ${esc(x.firstName)} ${esc(x.lastName)}</a><span class="muted">${b ? esc(b.name) : 'ohne Band'}</span></li>`;
    }).join('')}</ul>` : '<p class="muted">Noch keiner.</p>'}
    <div class="dialog-actions"><button class="btn primary" data-close>OK</button></div>` });
  modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
  modal.body.querySelectorAll('[data-genre-go]').forEach((a) => a.addEventListener('click', () => closeAllModals()));
}

defineActions({
  genreInfo({ genre, id }) {
    openGenreInfo(genre, id ? musicianById(id) ?? null : null);
  },
});

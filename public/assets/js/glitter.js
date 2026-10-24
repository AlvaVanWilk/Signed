/**
 * Goldglitzer: einzelne Punkte blitzen an zufälligen Stellen auf, jeder mit eigener Größe, Dauer und Helligkeit.
 * Nichts wiederholt sich im Takt; die Abstände sind zufällig und hängen von der Breite der Leiste ab.
 */
const SELECTOR = '.lv-3 .sk i, .lv-3 .lb-prog i';
const MAX_DOTS = 36;
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

function spark() {
  const bars = [...document.querySelectorAll(SELECTOR)].filter((el) => el.offsetWidth > 4);
  if (bars.length && document.querySelectorAll('.gl-dot').length < MAX_DOTS) {
    // Breitere Leisten bekommen häufiger einen Funken.
    const total = bars.reduce((sum, el) => sum + el.offsetWidth, 0);
    let pick = Math.random() * total;
    const bar = bars.find((el) => (pick -= el.offsetWidth) < 0) ?? bars[0];
    const dot = document.createElement('b');
    const size = 0.9 + Math.random() * Math.random() * 1.8;
    dot.className = 'gl-dot';
    dot.style.cssText = `left:${(Math.random() * 100).toFixed(1)}%;top:${(8 + Math.random() * 84).toFixed(1)}%;width:${size.toFixed(2)}px;height:${size.toFixed(2)}px;--t:${(0.25 + Math.random() * 0.9).toFixed(2)}s;--o:${(0.55 + Math.random() * 0.45).toFixed(2)}`;
    dot.addEventListener('animationend', () => dot.remove());
    bar.append(dot);
  }
  setTimeout(spark, 40 + Math.random() * 190);
}

if (!reduced) setTimeout(spark, 800);

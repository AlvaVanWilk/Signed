/**
 * Platzhalter-Musik aus dem Browser-Synthesizer (Web Audio), bis die Aufnahmen aus Logic Pro da sind.
 * Begleitung: Schlagzeug und Bass im Stil des Genres über die Akkordfolge Am – F – C – G.
 */
let ctx = null;
let master = null;
let noiseBuffer = null;

export function getAudio() {
  if (ctx?.state === 'closed') {
    ctx = null;
    noiseBuffer = null;
  }
  if (!ctx) {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return null;
    ctx = new AudioContext();
    const compressor = ctx.createDynamicsCompressor();
    const volume = ctx.createGain();
    volume.gain.value = 0.8;
    compressor.connect(volume).connect(ctx.destination);
    master = compressor;
  }
  wake();
  return ctx;
}

/**
 * Safari auf iPad/iPhone hält die Tonausgabe an („suspended“ oder „interrupted“, z. B. nach der Bildschirmsperre);
 * dann steht auch die Uhr der Minispiele still. Bei jeder Berührung und beim Zurückkehren wird sie wieder angeworfen –
 * mit einem stillen Ton, weil iOS das nur aus einer Berührung heraus zulässt.
 */
function wake() {
  if (!ctx || ctx.state === 'running' || ctx.state === 'closed') return;
  ctx.resume().catch(() => {});
  try {
    const silent = ctx.createBufferSource();
    silent.buffer = ctx.createBuffer(1, 1, ctx.sampleRate);
    silent.connect(ctx.destination);
    silent.start();
  } catch { /* egal: nur zum Aufwecken */ }
}
for (const type of ['touchend', 'pointerup', 'keydown']) window.addEventListener(type, wake, { capture: true, passive: true });
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') wake();
});

const PROGRESSION = [{ root: 9, minor: true }, { root: 5 }, { root: 0 }, { root: 7 }];
const OCTAVE = { vocals: 60, guitar: 48, bass: 36, keys: 60, synth: 60, turntables: 48, sax: 48, trumpet: 60, violin: 60, flute: 72 };

/** Schritte pro Takt in Achteln: k = Kick, s = Snare, h = Hi-Hat, b = Bass. */
const PATTERNS = {
  pop: { k: [0, 4, 5], s: [2, 6], h: [0, 1, 2, 3, 4, 5, 6, 7], b: [0, 3, 4, 6] },
  rock: { k: [0, 3, 4], s: [2, 6], h: [0, 1, 2, 3, 4, 5, 6, 7], b: [0, 1, 2, 3, 4, 5, 6, 7] },
  metal: { k: [0, 1, 2, 3, 4, 5, 6, 7], s: [2, 6], h: [0, 2, 4, 6], b: [0, 1, 2, 3, 4, 5, 6, 7] },
  punk: { k: [0, 2, 4, 6], s: [1, 3, 5, 7], h: [0, 1, 2, 3, 4, 5, 6, 7], b: [0, 1, 2, 3, 4, 5, 6, 7] },
  jazz: { k: [0], s: [5], h: [0, 2, 3, 4, 6, 7], b: [0, 2, 4, 6], swing: true },
  elektro: { k: [0, 2, 4, 6], s: [2, 6], h: [1, 3, 5, 7], open: [1, 3, 5, 7], b: [1, 3, 5, 7] },
  hiphop: { k: [0, 3, 5], s: [2, 6], h: [0, 1, 2, 3, 4, 5, 6, 7], b: [0, 3, 5], swing: true },
  folk: { k: [0, 4], s: [2, 6], h: [0, 2, 4, 6], b: [0, 4] },
};

export function chordAt(songTime, bpm) {
  const bar = Math.floor(Math.max(0, songTime) / ((60 / bpm) * 4)) % PROGRESSION.length;
  return PROGRESSION[bar];
}

function laneMidi(type, lane, chord) {
  const intervals = chord.minor ? [0, 3, 7, 12] : [0, 4, 7, 12];
  return (OCTAVE[type] ?? 60) + chord.root + intervals[lane];
}

const freq = (midi) => 440 * 2 ** ((midi - 69) / 12);

function noise() {
  if (!noiseBuffer) {
    noiseBuffer = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate);
    const data = noiseBuffer.getChannelData(0);
    for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
  }
  const source = ctx.createBufferSource();
  source.buffer = noiseBuffer;
  return source;
}

function envelope(gain, t, attack, peak, decay) {
  gain.gain.setValueAtTime(0.0001, t);
  gain.gain.exponentialRampToValueAtTime(peak, t + attack);
  gain.gain.exponentialRampToValueAtTime(0.0001, t + attack + decay);
}

function filter(type, frequency, q = 1) {
  const f = ctx.createBiquadFilter();
  f.type = type;
  f.frequency.value = frequency;
  f.Q.value = q;
  return f;
}

// ---------- Schlagzeug ----------

function kick(out, t, vel = 1) {
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();
  osc.frequency.setValueAtTime(140, t);
  osc.frequency.exponentialRampToValueAtTime(42, t + 0.12);
  envelope(gain, t, 0.002, 0.9 * vel, 0.25);
  osc.connect(gain).connect(out);
  osc.start(t);
  osc.stop(t + 0.3);
}

function snare(out, t, vel = 1) {
  const n = noise();
  const gain = ctx.createGain();
  envelope(gain, t, 0.001, 0.45 * vel, 0.16);
  n.connect(filter('highpass', 1200)).connect(gain).connect(out);
  n.start(t);
  n.stop(t + 0.2);
  const osc = ctx.createOscillator();
  const body = ctx.createGain();
  osc.type = 'triangle';
  osc.frequency.setValueAtTime(190, t);
  envelope(body, t, 0.001, 0.3 * vel, 0.08);
  osc.connect(body).connect(out);
  osc.start(t);
  osc.stop(t + 0.12);
}

function hat(out, t, vel = 1, open = false) {
  const n = noise();
  const gain = ctx.createGain();
  envelope(gain, t, 0.001, 0.16 * vel, open ? 0.22 : 0.04);
  n.connect(filter('highpass', 7000)).connect(gain).connect(out);
  n.start(t);
  n.stop(t + (open ? 0.3 : 0.06));
}

function tom(out, t, vel = 1) {
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();
  osc.frequency.setValueAtTime(180, t);
  osc.frequency.exponentialRampToValueAtTime(90, t + 0.2);
  envelope(gain, t, 0.002, 0.6 * vel, 0.3);
  osc.connect(gain).connect(out);
  osc.start(t);
  osc.stop(t + 0.35);
}

// ---------- Instrumente ----------

function tone(type, midi, t, length, vel = 0.8, out = master) {
  const f = freq(midi);
  const gain = ctx.createGain();
  gain.connect(out);
  const sources = [];
  const osc = (wave, frequency, detune = 0) => {
    const o = ctx.createOscillator();
    o.type = wave;
    o.frequency.setValueAtTime(frequency, t);
    o.detune.value = detune;
    sources.push(o);
    return o;
  };
  const vibrato = (o, rate, cents) => {
    const lfo = ctx.createOscillator();
    const depth = ctx.createGain();
    lfo.frequency.value = rate;
    depth.gain.value = cents;
    lfo.connect(depth).connect(o.detune);
    sources.push(lfo);
  };
  const sustain = (attack, peak) => {
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(peak, t + attack);
    gain.gain.setValueAtTime(peak, t + Math.max(attack, length));
    gain.gain.exponentialRampToValueAtTime(0.0001, t + Math.max(attack, length) + 0.15);
  };
  // Gezupft/angeschlagen: klingt von selbst aus; ein gehaltener Ton (länger als das Ausklingen) bleibt leiser stehen bis zum Ende.
  const pluck = (attack, peak, decay) => {
    if (length <= decay) {
      envelope(gain, t, attack, peak, decay);
      return;
    }
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(peak, t + attack);
    gain.gain.exponentialRampToValueAtTime(peak * 0.45, t + attack + 0.3);
    gain.gain.setValueAtTime(peak * 0.45, t + length);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + length + 0.25);
  };

  switch (type) {
    case 'guitar': {
      const lp = ctx.createBiquadFilter();
      lp.type = 'lowpass';
      lp.frequency.setValueAtTime(3200, t);
      lp.frequency.exponentialRampToValueAtTime(length > 0.6 ? 1400 : 700, t + 0.4);
      osc('sawtooth', f).connect(lp).connect(gain);
      pluck(0.003, 0.32 * vel, 0.6);
      break;
    }
    case 'bass': {
      osc('triangle', f).connect(gain);
      osc('sawtooth', f).connect(filter('lowpass', 500)).connect(gain);
      envelope(gain, t, 0.005, 0.5 * vel, Math.max(0.25, length));
      break;
    }
    case 'keys': {
      osc('triangle', f).connect(gain);
      osc('sine', f * 2).connect(gain);
      pluck(0.004, 0.3 * vel, 0.9);
      break;
    }
    case 'synth': {
      const lp = ctx.createBiquadFilter();
      lp.type = 'lowpass';
      lp.frequency.setValueAtTime(4200, t);
      lp.frequency.exponentialRampToValueAtTime(900, t + 0.3);
      osc('sawtooth', f, -8).connect(lp);
      osc('sawtooth', f, 8).connect(lp);
      lp.connect(gain);
      pluck(0.01, 0.22 * vel, 0.45);
      break;
    }
    case 'vocals': {
      const o = osc('sawtooth', f);
      vibrato(o, 5.5, 18);
      o.connect(filter('bandpass', 800, 5)).connect(gain);
      o.connect(filter('bandpass', 1200, 7)).connect(gain);
      sustain(0.05, 1.6 * vel);
      break;
    }
    case 'sax': {
      const o = osc('sawtooth', f);
      vibrato(o, 5, 12);
      o.connect(filter('bandpass', 1100, 1.4)).connect(gain);
      sustain(0.03, 0.6 * vel);
      break;
    }
    case 'trumpet': {
      osc('sawtooth', f).connect(filter('lowpass', 2600)).connect(gain);
      sustain(0.02, 0.28 * vel);
      break;
    }
    case 'violin': {
      const o = osc('sawtooth', f);
      vibrato(o, 6, 15);
      o.connect(filter('lowpass', 3500)).connect(gain);
      sustain(0.06, 0.25 * vel);
      break;
    }
    case 'flute': {
      const o = osc('sine', f);
      vibrato(o, 5, 10);
      o.connect(gain);
      const breath = noise();
      const breathGain = ctx.createGain();
      breathGain.gain.value = 0.05;
      breath.connect(filter('bandpass', f * 2, 2)).connect(breathGain).connect(gain);
      breath.start(t);
      breath.stop(t + length + 0.2);
      sustain(0.04, 0.45 * vel);
      break;
    }
    case 'turntables': {
      // Scratch: die Platte wird vor- und zurückgezogen – Tonhöhe und Rauschen gleiten hoch, runter, wieder hoch.
      const len = Math.max(0.32, Math.min(0.6, length));
      const sweep = (param, base) => {
        param.setValueAtTime(base, t);
        param.exponentialRampToValueAtTime(base * 3.2, t + len * 0.3);
        param.exponentialRampToValueAtTime(base * 0.8, t + len * 0.65);
        param.exponentialRampToValueAtTime(base * 2.4, t + len);
      };
      const n = noise();
      const bp = ctx.createBiquadFilter();
      bp.type = 'bandpass';
      bp.Q.value = 3;
      sweep(bp.frequency, f * 4);
      n.connect(bp).connect(gain);
      n.start(t);
      n.stop(t + len + 0.05);
      const voice = osc('sawtooth', f);
      sweep(voice.frequency, f);
      voice.connect(filter('lowpass', 2400)).connect(gain);
      gain.gain.setValueAtTime(0.0001, t);
      gain.gain.exponentialRampToValueAtTime(0.5 * vel, t + 0.02);
      gain.gain.setValueAtTime(0.5 * vel, t + len * 0.8);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + len + 0.04);
      break;
    }
    default:
      osc('triangle', f).connect(gain);
      pluck(0.005, 0.3 * vel, 0.5);
  }
  for (const s of sources) {
    s.start(t);
    s.stop(t + length + 1.2);
  }
}

/** Ton eines Treffers im Minispiel: Akkordton der Spur, beim Schlagzeug Kick/Snare/Hi-Hat/Tom. */
export function playLane(type, lane, songTime, bpm) {
  if (!ctx) return;
  const t = ctx.currentTime;
  if (type === 'drums') {
    [kick, snare, (o, time) => hat(o, time, 1.4, true), tom][lane](master, t);
    return;
  }
  tone(type, laneMidi(type, lane, chordAt(songTime, bpm)), t, (60 / bpm) * 0.9);
}

/** Ein bestimmter Ton (Etüde): Instrument, MIDI-Ton, Länge in Sekunden. */
export function playNote(type, midi, length = 0.3) {
  if (!ctx) return;
  tone(type, midi, ctx.currentTime, length);
}

/**
 * Begleitung einer Etüde vom Server: [Zeit, Art, MIDI, Länge] ab start (AudioContext-Zeit).
 * Art: k/s/h/t Schlagzeug, bass, pad (Akkord). Leise, damit die eigene Stimme vorn bleibt. Liefert eine Stopp-Funktion.
 */
export function scheduleEvents(events, start, volume = 0.32) {
  if (!ctx) return () => {};
  const bus = ctx.createGain();
  bus.gain.value = volume;
  bus.connect(master);
  for (const [t, kind, midi, length] of events) {
    const at = start + t;
    if (kind === 'k') kick(bus, at, 0.8);
    else if (kind === 's') snare(bus, at, 0.7);
    else if (kind === 'h') hat(bus, at, 0.6);
    else if (kind === 't') tom(bus, at, 0.6);
    else if (kind === 'bass') tone('bass', midi, at, length ?? 0.3, 0.6, bus);
    else if (kind === 'pad') tone('keys', midi, at, length ?? 1, 0.35, bus);
  }
  return () => {
    bus.gain.setTargetAtTime(0, ctx.currentTime, 0.05);
    setTimeout(() => bus.disconnect(), 400);
  };
}

/** Leiser dumpfer Klick bei einem verpassten Ton. */
export function playMiss() {
  if (!ctx) return;
  const t = ctx.currentTime;
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();
  osc.frequency.value = 90;
  envelope(gain, t, 0.002, 0.12, 0.08);
  osc.connect(gain).connect(master);
  osc.start(t);
  osc.stop(t + 0.1);
}

/**
 * Plant die Begleitung ab Zeitpunkt start (AudioContext-Zeit) inklusive Einzählen.
 * Liefert eine Funktion zum Stoppen.
 */
export function scheduleBacking({ genre, bpm, start, duration, drums = true, bass = true, countIn = 4 }) {
  if (!ctx) return () => {};
  const bus = ctx.createGain();
  bus.gain.value = 0.5;
  bus.connect(master);
  const beat = 60 / bpm;
  for (let i = 0; i < countIn; i++) {
    hat(bus, start - (countIn - i) * beat, i === 0 ? 1.6 : 1.1);
  }
  const step = beat / 2;
  const pattern = PATTERNS[genre] || PATTERNS.pop;
  const steps = Math.ceil(duration / step);
  for (let i = 0; i < steps; i++) {
    const s = i % 8;
    let t = start + i * step;
    if (pattern.swing && s % 2 === 1) t += step * 0.33;
    if (drums) {
      if (pattern.k.includes(s)) kick(bus, t, 0.8);
      if (pattern.s.includes(s)) snare(bus, t, 0.7);
      if (pattern.h.includes(s)) hat(bus, t, 0.6, pattern.open?.includes(s));
    }
    if (bass && pattern.b.includes(s)) {
      const chord = chordAt(i * step, bpm);
      tone('bass', 24 + chord.root, t, step * 0.9, 0.5, bus);
    }
  }
  return () => {
    bus.gain.setTargetAtTime(0, ctx.currentTime, 0.05);
    setTimeout(() => bus.disconnect(), 400);
  };
}

// ---------- Nach dem Auftritt ----------

/**
 * Echte Applaus-Aufnahmen (frei verwendbar, mit ffmpeg gekürzt, ausgeblendet und gleich laut gemacht), je nach Größe des Ortes:
 * wenig Publikum (Kneipe, Straße, Offene Bühne), Club (auch Konzertsaal), große Halle (auch Arena). Lautstärke je Aufnahme dazu.
 */
const APPLAUSE = { kneipe: 'klein', club: 'club', saal: 'club', halle: 'halle', arena: 'halle', openair: 'halle', zelt: 'club' };
const APPLAUSE_GAIN = { klein: 0.6, club: 0.8, halle: 0.95 };
const applauseBuffers = {};

/** Aufnahme laden (einmal, dann aus dem Speicher); null, wenn es nicht klappt (dann klatscht der Synthesizer). */
function applauseBuffer(c, name) {
  applauseBuffers[name] ??= fetch(`assets/sfx/applaus-${name}.mp3`)
    .then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error(r.statusText))))
    // Die Rückruf-Form, weil ältere Safaris decodeAudioData noch nicht als Promise kennen.
    .then((data) => new Promise((resolve, reject) => c.decodeAudioData(data, resolve, reject)))
    .catch(() => {
      delete applauseBuffers[name];
      return null;
    });
  return applauseBuffers[name];
}

/**
 * Applaus nach einem Auftritt. strength 0–1: wie begeistert das Publikum ist (lauter und länger, wenig Begeisterung: leiser und kürzer);
 * venue: Ort (Schlüssel aus Balance::VENUES, sonst kleines Publikum). Wer fast niemanden begeistert, hört auch in der Halle nur ein paar Hände.
 */
export function playApplause(strength, delay = 0, venue = null) {
  const c = getAudio();
  if (!c) return;
  const s = Math.max(0, Math.min(1, strength));
  const name = s < 0.15 ? 'klein' : APPLAUSE[venue] ?? 'klein';
  const at = c.currentTime + 0.05 + delay;
  applauseBuffer(c, name).then((buf) => {
    if (!buf) {
      synthApplause(s, Math.max(0, at - c.currentTime));
      return;
    }
    const start = Math.max(c.currentTime + 0.02, at);
    const length = buf.duration * (0.45 + 0.55 * s);
    const volume = APPLAUSE_GAIN[name] * (0.45 + 0.55 * s);
    const src = c.createBufferSource();
    src.buffer = buf;
    const g = c.createGain();
    g.gain.setValueAtTime(volume, start);
    // Kürzer als die Aufnahme: sanft ausblenden statt abschneiden.
    if (length < buf.duration - 0.05) {
      g.gain.setValueAtTime(volume, start + length * 0.6);
      g.gain.linearRampToValueAtTime(0.0001, start + length);
    }
    src.connect(g).connect(master);
    src.start(start);
    src.stop(start + length + 0.05);
    src.onended = () => g.disconnect();
  });
}

/**
 * Applaus aus dem Synthesizer (falls die Aufnahme nicht lädt): vor allem das breite, warme Rauschen vieler Hände, darin einige einzelne
 * Klatscher in eigenem Takt (3–5 pro Sekunde, gedämpft – keine hellen Klicks, die nach Münzen klingen). Bei wenig Begeisterung klatschen
 * nur ein paar Leute, kurz.
 */
function synthApplause(s, delay = 0) {
  const c = getAudio();
  if (!c) return;
  const bus = c.createGain();
  bus.gain.value = 0.5 + 0.4 * s;
  // Alles Helle oben weg: Hände klingen dumpf, nicht klirrend.
  bus.connect(filter('lowpass', 2600, 0.5)).connect(master);
  const length = 1.8 + 2.6 * s;
  const start = c.currentTime + 0.05 + delay;
  const fade = (t) => Math.max(0, 1 - ((t - start) / length) ** 2);

  const wash = noise();
  wash.loop = true;
  const swell = c.createGain();
  const level = 0.05 + 0.3 * s;
  swell.gain.setValueAtTime(0.0001, start);
  swell.gain.exponentialRampToValueAtTime(level, start + 0.3);
  swell.gain.setTargetAtTime(0.0001, start + length * 0.55, length * 0.22);
  wash.connect(filter('highpass', 350, 0.5)).connect(filter('bandpass', 1100, 0.4)).connect(swell).connect(bus);
  wash.start(start, Math.random() * 0.5);
  wash.stop(start + length + 1.5);

  const people = Math.round(2 + 12 * s);
  for (let p = 0; p < people; p++) {
    const body = filter('bandpass', 500 + Math.random() * 700, 0.9);
    const person = c.createGain();
    person.gain.value = (0.4 + Math.random() * 0.4) * (1 - 0.5 * s);
    body.connect(person).connect(bus);
    const rate = 3 + Math.random() * 2;
    const stopAt = start + length * (0.5 + Math.random() * 0.4);
    for (let t = start + Math.random() * 0.4; t < stopAt; t += (1 / rate) * (0.85 + Math.random() * 0.3)) {
      const n = noise();
      const g = c.createGain();
      envelope(g, t, 0.003, 0.6 * fade(t) + 0.0002, 0.09 + Math.random() * 0.05);
      n.connect(g).connect(body);
      n.start(t, Math.random() * 0.9, 0.18);
    }
  }
  setTimeout(() => bus.disconnect(), (length + delay + 2) * 1000);
}

/** Ein Münzklimpern: zwei helle, kurz ausklingende Töne. */
export function playCoin(pitch = 1) {
  const c = getAudio();
  if (!c) return;
  const t = c.currentTime;
  for (const [f, v] of [[2093 * pitch, 0.12], [3136 * pitch, 0.07]]) {
    const osc = c.createOscillator();
    const g = c.createGain();
    osc.type = 'sine';
    osc.frequency.value = f;
    envelope(g, t, 0.002, v, 0.18);
    osc.connect(g).connect(master);
    osc.start(t);
    osc.stop(t + 0.25);
  }
}

// ---------- Verzögerung (Bluetooth-Kopfhörer) ----------

const LATENCY_KEY = 'signed.latency';

/**
 * Wie viel später der Ton beim Ohr ankommt (Sekunden). Eingestellt pro Gerät; sonst, was der Browser meldet.
 * Die Minispiele verschieben Bild und Wertung um diesen Wert, damit man im Takt der Musik tippen kann.
 */
export function latency() {
  const stored = storedLatency();
  if (stored !== null) return stored / 1000;
  const c = getAudio();
  return c ? (c.outputLatency || 0) + (c.baseLatency || 0) : 0;
}

/** Eingestellter Wert in ms oder null (automatisch). */
export function storedLatency() {
  try {
    const value = localStorage.getItem(LATENCY_KEY);
    return value === null ? null : Number(value);
  } catch {
    return null;
  }
}

export function setLatency(ms) {
  try {
    if (ms === null) localStorage.removeItem(LATENCY_KEY);
    else localStorage.setItem(LATENCY_KEY, String(Math.round(ms)));
  } catch {
    // Ohne Speicher gilt der Wert nur bis zum Neuladen nicht – dann eben automatisch.
  }
}

/** Metronom für die Solo-Probe: Vorzähler und ein Klick auf jeden Schlag, betont auf der Eins. */
export function scheduleMetronome({ bpm, start, duration, countIn = 4, meter = 4 }) {
  if (!ctx) return () => {};
  const bus = ctx.createGain();
  bus.gain.value = 0.45;
  bus.connect(master);
  const beat = 60 / bpm;
  for (let i = -countIn; i * beat < duration; i++) {
    // Betont wird die Eins jedes Takts (im 3/4 jeder dritte Schlag).
    hat(bus, start + i * beat, ((i % meter) + meter) % meter === 0 ? 1.6 : 0.9);
  }
  return () => {
    bus.gain.setTargetAtTime(0, ctx.currentTime, 0.05);
    setTimeout(() => bus.disconnect(), 400);
  };
}

// ---------- Tonhöhen-Spiel ----------

/** Natürliche Moll-Tonleiter (passend zu Am – F – C – G), Stufen 0–7. */
const SCALE = [0, 2, 3, 5, 7, 8, 10, 12];
const VOICE_BASE = { vocals: 57, violin: 69 };

/** Stufe (auch Zwischenwerte) → MIDI-Ton. tonality {root, scale} kommt bei Aufnahmen aus deren Noten. */
export function scaleMidi(type, degree, tonality = null) {
  const scale = tonality?.scale ?? SCALE;
  const root = tonality?.root ?? VOICE_BASE[type] ?? 60;
  const d = Math.max(0, Math.min(7, degree));
  const low = Math.floor(d);
  const high = Math.min(7, low + 1);
  return root + scale[low] + (scale[high] - scale[low]) * (d - low);
}

/** Durchgehender Ton, dessen Höhe und Lautstärke man laufend ändern kann (Gesang, Geige). */
export function createVoice(type) {
  if (!ctx) return { setPitch() {}, setLevel() {}, stop() {} };
  const osc = ctx.createOscillator();
  osc.type = 'sawtooth';
  const lfo = ctx.createOscillator();
  const depth = ctx.createGain();
  lfo.frequency.value = type === 'violin' ? 6 : 5.5;
  depth.gain.value = type === 'violin' ? 14 : 18;
  lfo.connect(depth).connect(osc.detune);
  const gain = ctx.createGain();
  gain.gain.value = 0;
  if (type === 'violin') {
    osc.connect(filter('lowpass', 3200)).connect(gain);
  } else {
    osc.connect(filter('bandpass', 800, 5)).connect(gain);
    osc.connect(filter('bandpass', 1200, 7)).connect(gain);
  }
  gain.connect(master);
  osc.start();
  lfo.start();
  const peak = type === 'violin' ? 0.22 : 1.4;
  return {
    setPitch(midi) {
      osc.frequency.setTargetAtTime(freq(midi), ctx.currentTime, 0.02);
    },
    setLevel(level) {
      gain.gain.setTargetAtTime(level * peak, ctx.currentTime, 0.05);
    },
    stop() {
      gain.gain.setTargetAtTime(0, ctx.currentTime, 0.05);
      setTimeout(() => {
        osc.stop();
        lfo.stop();
        gain.disconnect();
      }, 300);
    },
  };
}

// ---------- Mischpult ----------

/** Ein Schlag einer Bandspur mit einstellbarer Lautstärke (0–1). */
export function playTrackBeat(type, beatIndex, songTime, bpm, volume) {
  if (!ctx || volume <= 0.01) return;
  const t = ctx.currentTime;
  if (type === 'drums') {
    const step = beatIndex % 4;
    if (step === 0 || step === 2) kick(master, t, volume);
    else snare(master, t, volume * 0.8);
    hat(master, t, volume * 0.7);
    return;
  }
  const chord = chordAt(songTime, bpm);
  const lane = type === 'bass' ? 0 : [0, 1, 2, 1][beatIndex % 4];
  tone(type, laneMidi(type, lane, chord), t, (60 / bpm) * 0.8, 0.8 * volume);
}

import { app, cat } from './core.js';
import { getAudio } from './audio.js';

/**
 * Die Aufnahmen aus Logic: je Genre und Stufe ein Ordner mit einer MP3 pro Spur.
 * Fehlt eine Spur, nimmt das Spiel die nächstbeste: andere Stufe, dann die andere Variante, dann Pop.
 */

const buffers = new Map();

/** Pfad zur passenden Aufnahme für { type, level, genre, electric } oder null, wenn es (noch) keine gibt. */
export function stemUrl({ type, level, genre, electric }) {
  const music = cat().music || {};
  const [name, variants] = cat().musicStems[type] || [];
  if (!name) return null;
  const names = variants ? [`${name}-${electric ? 'elektrisch' : 'akustisch'}`, `${name}-${electric ? 'akustisch' : 'elektrisch'}`] : [name];
  const levels = [0, 1, 2, 3].sort((a, b) => Math.abs(a - level) - Math.abs(b - level) || b - a);
  for (const g of genre === 'pop' ? ['pop'] : [genre, 'pop']) {
    for (const file of names) {
      for (const l of levels) {
        if (music[`${g}-${l}`]?.includes(file)) return `assets/music/${g}-${l}/${file}.mp3`;
      }
    }
  }
  return null;
}

function load(url) {
  if (!buffers.has(url)) {
    const ctx = getAudio();
    buffers.set(url, fetch(url)
      .then((res) => (res.ok ? res.arrayBuffer() : Promise.reject(new Error(res.statusText))))
      .then((data) => new Promise((resolve, reject) => ctx.decodeAudioData(data, resolve, reject)))
      .catch((e) => {
        buffers.delete(url);
        throw e;
      }));
  }
  return buffers.get(url);
}

/**
 * Lädt die Spuren einer Besetzung. groups: Liste von Listen von Stems oder fertigen Pfaden (eine Gruppe = ein Regler).
 * Liefert null, wenn für keine Gruppe eine Aufnahme existiert – dann bleibt es beim Synthesizer.
 */
export async function loadStems(groups) {
  const urls = groups.map((stems) => stems.map((s) => (typeof s === 'string' ? s : stemUrl(s))).filter(Boolean));
  if (!urls.some((list) => list.length)) return null;
  try {
    return await Promise.all(urls.map((list) => Promise.all(list.map(load))));
  } catch {
    return null;
  }
}

/**
 * Spielt geladene Spuren ab Zeitpunkt when (AudioContext-Zeit), ab Sekunde offset der Aufnahme.
 * Jede Gruppe hängt an einem eigenen Lautstärkeregler. Liefert { gains, stop }.
 */
export function playStems(groups, when, { offset = 0, loop = false, volume = 0.9 } = {}) {
  const ctx = getAudio();
  const bus = ctx.createGain();
  bus.gain.value = volume;
  bus.connect(ctx.destination);
  const sources = [];
  const gains = groups.map((list) => {
    const gain = ctx.createGain();
    gain.connect(bus);
    for (const buffer of list) {
      const source = ctx.createBufferSource();
      source.buffer = buffer;
      source.loop = loop;
      source.connect(gain);
      source.start(Math.max(when, ctx.currentTime), offset % buffer.duration);
      sources.push(source);
    }
    return gain;
  });
  return {
    gains,
    stop() {
      bus.gain.setTargetAtTime(0, ctx.currentTime, 0.05);
      setTimeout(() => {
        for (const s of sources) {
          try { s.stop(); } catch { /* schon zu Ende */ }
        }
        bus.disconnect();
      }, 300);
    },
  };
}

/**
 * Spur eines Musikers aus dem Spielstand (wie Music::stem auf dem Server). Folgt die Band einem Genre, spielen alle
 * darin, sonst jeder eins seiner Lieblingsgenres – fest gewählt über seed, damit es nicht bei jedem Aufruf wechselt.
 */
export function memberStem(m, band = null, seed = 0) {
  const slot = m.bandSlot === 2 && m.instruments[1] ? 1 : 0;
  const type = m.instruments[slot].type;
  const gear = app.state.gear.find((g) => g.musicianId === m.id && cat().gear[g.model].type === type);
  const genres = band?.genres?.length ? band.genres : m.genres;
  return {
    type,
    level: m.level,
    genre: genres[(m.id + seed) % genres.length],
    electric: gear ? !!cat().gear[gear.model].electric : m.id % 2 === 1,
  };
}

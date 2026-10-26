import { api } from '../api.js';
import { app, defineActions, refresh } from '../core.js';
import { esc, money, openModal, toast } from '../ui.js';
import { claimTour, openTour } from './help.js';

export function render() {
  const tab = app.ui.authTab || 'register';
  return `
    <div class="auth">
      <div class="auth-hero">
        <h1 class="logo big">Signed</h1>
        <p class="tagline">Gründe dein Label. Entdecke Musiker. Mach sie berühmt.</p>
        <ul class="auth-points">
          <li>🎴 Öffne Packs voller einmaliger Musiker</li>
          <li>🎸 Stell Bands zusammen und produziere Platten</li>
          <li>🏆 Spiel dich gegen andere Labels in die Charts</li>
        </ul>
      </div>
      <div class="auth-box panel">
        <div class="tabs">
          <button class="tab ${tab === 'register' ? 'active' : ''}" data-act="authTab" data-tab="register">Label gründen</button>
          <button class="tab ${tab === 'login' ? 'active' : ''}" data-act="authTab" data-tab="login">Anmelden</button>
        </div>
        ${tab === 'register' ? `
          <form data-form="register" class="stack">
            <label class="field"><span>Name deines Labels</span><input name="labelName" maxlength="40" required placeholder="z. B. Neonherz Records"></label>
            <label class="field"><span>Benutzername</span><input name="username" maxlength="20" required autocomplete="username" placeholder="3–20 Zeichen, ohne Leerzeichen"></label>
            <label class="field"><span>Passwort (mind. 8 Zeichen)</span><input name="password" type="password" minlength="8" required autocomplete="new-password"></label>
            <button class="btn primary big">Label gründen</button>
          </form>` : `
          <form data-form="login" class="stack">
            <label class="field"><span>Benutzername</span><input name="username" required autocomplete="username"></label>
            <label class="field"><span>Passwort</span><input name="password" type="password" required autocomplete="current-password"></label>
            <button class="btn primary big">Anmelden</button>
          </form>`}
      </div>
    </div>`;
}

function openWelcome() {
  const modal = openModal({
    html: `
      <div class="eyebrow">Willkommen bei Signed</div>
      <h2>${esc(app.label.name)} ist gegründet!</h2>
      <p>Startkapital: <b>${money(app.state.label.money)}</b>. Im Posteingang liegen schon die ersten Kassetten von Musikern, die zu dir wollen, und die Bewerbung eines Scouts, der für dich Talente sucht.</p>
      <div class="dialog-actions"><button class="btn primary" data-start>Zum Posteingang</button></div>`,
  });
  modal.body.querySelector('[data-start]').addEventListener('click', () => {
    modal.close();
    location.hash = '#/einsendungen';
  });
}

defineActions({
  authTab({ tab }) {
    app.ui.authTab = tab;
    document.getElementById('app').innerHTML = render();
  },
  async register(values) {
    try {
      const res = await api('register', values);
      app.label = res.label;
      // Neues Label: erst die kurze Tour, dann der Talent-Scout.
      const tour = claimTour();
      await refresh();
      if (tour) openTour(openWelcome);
      else openWelcome();
    } catch (e) {
      toast(e.message, 'error');
    }
  },
  async login(values) {
    try {
      const res = await api('login', values);
      app.label = res.label;
      await refresh();
    } catch (e) {
      toast(e.message, 'error');
    }
  },
});

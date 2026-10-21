const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ESCAPES[c]);

const nf0 = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 });
const nf1 = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

export const fmt0 = (n) => nf0.format(n);
export const fmt1 = (n) => nf1.format(n);
const nf2fix = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const fmt2 = (n) => nf2fix.format(n);
const nf2 = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
/** Kleine Gewinne (unter 1) mit zwei Nachkommastellen, damit nicht „+0,0“ dasteht. */
export const fmtGain = (n) => (Math.abs(n) < 1 ? nf2 : nf1).format(n);
export const money = (n) => `${nf0.format(n)} ₲`;
export const percent = (x) => `${nf0.format(Math.round(x * 100))} %`;

export function duration(seconds) {
  const s = Math.max(0, Math.ceil(seconds));
  if (s >= 86400) return `${Math.floor(s / 86400)} T ${Math.floor((s % 86400) / 3600)} Std.`;
  if (s >= 3600) return `${Math.floor(s / 3600)} Std. ${Math.floor((s % 3600) / 60)} Min.`;
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

export function timeAgo(ts, now) {
  const diff = Math.max(0, now - ts);
  if (diff < 60) return 'gerade eben';
  if (diff < 3600) return `vor ${Math.floor(diff / 60)} Min.`;
  if (diff < 86400) return `vor ${Math.floor(diff / 3600)} Std.`;
  const days = Math.floor(diff / 86400);
  return days === 1 ? 'gestern' : `vor ${days} Tagen`;
}

export function bar(value, max = 100, cls = '') {
  const width = Math.max(0, Math.min(100, (value / max) * 100));
  return `<span class="bar ${cls}"><span class="bar-fill" style="width:${width}%"></span></span>`;
}

export function toast(message, kind = 'info') {
  const root = document.getElementById('toast-root');
  const el = document.createElement('div');
  el.className = `toast toast-${kind}`;
  el.textContent = message;
  root.append(el);
  setTimeout(() => el.classList.add('out'), 3800);
  setTimeout(() => el.remove(), 4300);
}

// ---------- Modals ----------

const openModals = [];

/**
 * render: Funktion, die den Inhalt liefert; wird bei jedem neuen Spielstand neu aufgerufen.
 * html: fester Inhalt (für Dialoge mit Eingaben, die nicht neu gezeichnet werden sollen).
 */
export function openModal({ render = null, html = '', wide = false, className = '', dismissable = true, onClose = null } = {}) {
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop';
  backdrop.innerHTML = `
    <div class="modal ${wide ? 'wide' : ''} ${className}" role="dialog" aria-modal="true">
      ${dismissable ? '<button class="modal-close" aria-label="Schließen">✕</button>' : ''}
      <div class="modal-body"></div>
    </div>`;
  const body = backdrop.querySelector('.modal-body');
  const modal = {
    el: backdrop,
    body,
    render,
    dismissable,
    closed: false,
    refresh() {
      if (this.render && !this.closed) {
        const html = this.render();
        if (html === null) {
          this.close();
        } else {
          body.innerHTML = html;
        }
      }
    },
    close() {
      if (this.closed) return;
      this.closed = true;
      backdrop.remove();
      openModals.splice(openModals.indexOf(this), 1);
      document.body.classList.toggle('has-modal', openModals.length > 0);
      onClose?.();
    },
  };
  backdrop.addEventListener('mousedown', (e) => {
    if (e.target === backdrop && modal.dismissable) modal.close();
  });
  backdrop.querySelector('.modal-close')?.addEventListener('click', () => modal.close());
  if (render) {
    modal.refresh();
  } else {
    body.innerHTML = html;
  }
  openModals.push(modal);
  document.body.classList.add('has-modal');
  document.getElementById('modal-root').append(backdrop);
  return modal;
}

export function refreshModals() {
  for (const modal of [...openModals]) modal.refresh();
}

export function topModal() {
  return openModals[openModals.length - 1] ?? null;
}

export function closeAllModals() {
  for (const modal of [...openModals]) modal.close();
}

document.addEventListener('keydown', (e) => {
  const modal = topModal();
  if (e.key === 'Escape' && modal?.dismissable) {
    modal.close();
  }
});

export function confirmDialog(text, { ok = 'OK', danger = false } = {}) {
  return new Promise((resolve) => {
    let answer = false;
    const modal = openModal({
      html: `<p class="dialog-text">${esc(text)}</p>
        <div class="dialog-actions">
          <button class="btn ghost" data-answer="no">Abbrechen</button>
          <button class="btn ${danger ? 'danger' : 'primary'}" data-answer="yes">${esc(ok)}</button>
        </div>`,
      onClose: () => resolve(answer),
    });
    modal.body.addEventListener('click', (e) => {
      const button = e.target.closest('[data-answer]');
      if (!button) return;
      answer = button.dataset.answer === 'yes';
      modal.close();
    });
  });
}

export function promptDialog(title, value = '', { ok = 'Speichern', maxLength = 40 } = {}) {
  return new Promise((resolve) => {
    let answer = null;
    const modal = openModal({
      html: `<form class="dialog-form">
          <label class="field"><span>${esc(title)}</span><input name="value" maxlength="${maxLength}" value="${esc(value)}" required></label>
          <div class="dialog-actions">
            <button type="button" class="btn ghost" data-cancel>Abbrechen</button>
            <button class="btn primary">${esc(ok)}</button>
          </div>
        </form>`,
      onClose: () => resolve(answer),
    });
    const form = modal.body.querySelector('form');
    const input = form.querySelector('input');
    input.focus();
    input.select();
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      answer = input.value.trim();
      modal.close();
    });
    form.querySelector('[data-cancel]').addEventListener('click', () => modal.close());
  });
}

export class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

/** GET ohne body, POST mit body (auch {}). */
/** Länger wartet keine Anfrage: Hängt die Verbindung (WLAN weg, Server langsam), soll das Spiel nicht einfrieren. */
const TIMEOUT_MS = 15000;

export async function api(action, body, { retry = body === undefined } = {}) {
  const options = { credentials: 'same-origin' };
  if (body !== undefined) {
    options.method = 'POST';
    options.headers = { 'Content-Type': 'application/json', 'X-Signed': '1' };
    options.body = JSON.stringify(body);
  }
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  let response;
  let data;
  try {
    response = await fetch(`api.php?a=${encodeURIComponent(action)}`, { ...options, signal: controller.signal });
    data = await response.json();
  } catch {
    clearTimeout(timer);
    // Nur Abfragen dürfen einfach wiederholt werden; Aktionen könnten sonst doppelt passieren.
    if (retry) return api(action, body, { retry: false });
    throw response
      ? new ApiError('Der Server hat keine gültige Antwort geschickt.', response.status)
      : new ApiError('Keine Verbindung zum Server. Prüf das WLAN und versuch es noch einmal.', 0);
  }
  clearTimeout(timer);
  if (!data.ok) {
    throw new ApiError(data.error || 'Unbekannter Fehler.', response.status);
  }
  return data;
}

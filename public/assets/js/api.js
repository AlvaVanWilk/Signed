export class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

/** GET ohne body, POST mit body (auch {}). */
export async function api(action, body) {
  const options = { credentials: 'same-origin' };
  if (body !== undefined) {
    options.method = 'POST';
    options.headers = { 'Content-Type': 'application/json', 'X-Signed': '1' };
    options.body = JSON.stringify(body);
  }
  let response;
  try {
    response = await fetch(`api.php?a=${encodeURIComponent(action)}`, options);
  } catch {
    throw new ApiError('Keine Verbindung zum Server.', 0);
  }
  let data;
  try {
    data = await response.json();
  } catch {
    throw new ApiError('Der Server hat keine gültige Antwort geschickt.', response.status);
  }
  if (!data.ok) {
    throw new ApiError(data.error || 'Unbekannter Fehler.', response.status);
  }
  return data;
}
